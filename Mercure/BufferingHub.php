<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

/**
 * Decorator that turns {@see HubInterface::publish()} into a deferred, crash-safe
 * no-op at request time.
 *
 * ## Why
 *
 * `EntityChangePublisher` calls `$hub->publish()` inside `postFlush`. The
 * reference Mercure implementation opens an HTTP connection to the hub —
 * when the hub is **down** or **slow**:
 *
 *   - Exceptions propagate out of `$em->flush()` and kill the whole HTTP
 *     request (500 to the user even though the DB write succeeded).
 *   - Slow hub = slow response, because the publish is blocking.
 *
 * ## What this class does
 *
 * 1. `publish()` is **buffered** in-memory, never hits the wire during the
 *    request. Returns a synthetic event id so callers that check the
 *    return value stay happy.
 * 2. {@see drain()} is called by {@see MercureDrainListener} on
 *    `kernel.terminate` (after the response was sent to the client) and
 *    on `console.terminate` (for CLI commands). Each buffered update is
 *    wrapped in try/catch; a hub failure is logged and swallowed —
 *    **never surfaces to the user**.
 *
 * ## Semantics trade-off
 *
 * - Updates emitted during a request are no longer guaranteed to reach the
 *   hub before the response is flushed — there's a ~ms-level gap during
 *   which the client is told "success" but subscribers haven't yet
 *   received the event. Acceptable: the UI reconnects to Mercure with
 *   `lastEventID`, so a brief delay is replayed, and the feed is
 *   eventually-consistent by design.
 * - If PHP-FPM dies between response and `kernel.terminate`, the buffered
 *   updates are lost. No data loss in DB — just a missed real-time
 *   notification; subscribers will see the new state on their next
 *   datatable reload / page navigation.
 */
final class BufferingHub implements HubInterface
{
    /** @var list<Update> */
    private array $buffer = [];

    public function __construct(
        // The decoration is declared by the bundle's compiler pass, not by an
        // `#[AsDecorator]` attribute: `mercure.hub.default` only exists once the
        // application configures a hub, and a bundle cannot decorate a service that may
        // never be there. Not `readonly` because PHP 8.4
        // forbids reflection-based overrides on readonly properties —
        // swapping the inner with a RecordingHub in integration tests is
        // the only way we can prove the DI chain is wired correctly.
        // `private` is enough to preserve the production invariant
        // (never rebound after construction by application code).
        private HubInterface $inner,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(Update $update): string
    {
        $this->buffer[] = $update;

        // The Mercure Update API requires publish() to return an event id
        // (opaque string used by subscribers with Last-Event-ID). We mint a
        // deterministic placeholder; the real id from the hub is discarded
        // when drain() runs. Code that stores the return value for later
        // correlation would break — none in this codebase does.
        return 'buffered-'.spl_object_hash($update);
    }

    /**
     * Sends every buffered update to the underlying hub. Exceptions thrown
     * by the real hub (network error, 5xx, timeout) are caught and logged
     * individually so one bad update doesn't drop the rest.
     *
     * @return array{sent: int, failed: int}
     */
    public function drain(): array
    {
        if ([] === $this->buffer) {
            return ['sent' => 0, 'failed' => 0];
        }

        $pending = $this->buffer;
        $this->buffer = [];

        $sent = 0;
        $failed = 0;
        foreach ($pending as $update) {
            try {
                $this->inner->publish($update);
                ++$sent;
            } catch (\Throwable $e) {
                ++$failed;
                $this->logger->warning('mercure.publish_failed', [
                    'topics' => $update->getTopics(),
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    public function getUrl(): string
    {
        return $this->inner->getUrl();
    }

    public function getPublicUrl(): string
    {
        return $this->inner->getPublicUrl();
    }

    public function getProvider(): TokenProviderInterface
    {
        return $this->inner->getProvider();
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return $this->inner->getFactory();
    }
}
