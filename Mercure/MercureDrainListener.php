<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

use Psr\Log\LoggerInterface;

/**
 * Drains {@see BufferingHub} at the end of every HTTP request and CLI
 * command, after the response (or command output) has been sent.
 *
 * `kernel.terminate` fires *after* the response body has reached the
 * client, so any hub latency or failure during drain is invisible to the
 * user. `console.terminate` plays the same role for CLI flushes
 * (fixtures, migrations, cron).
 *
 * The two events and their priority are declared by the bundle, not by an attribute: the
 * listener only exists when a hub is configured, and a `#[AsEventListener]` on a vendor class is
 * only honoured if the application autoconfigures `vendor/` — which it should not.
 *
 * ## Why we inject `BufferingHub` concretely (not `HubInterface`)
 *
 * Symfony aliases `HubInterface` to `mercure.hub.default.traceable`, a
 * profiler wrapper that sits *above* our decorator. Autowiring the
 * interface would hand us the wrapper — `instanceof BufferingHub` would
 * be false, and drain() would silently short-circuit. Injecting the
 * concrete class guarantees we're talking to the buffer owner.
 */
final class MercureDrainListener
{
    public function __construct(
        private readonly BufferingHub $hub,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onKernelTerminate(): void
    {
        $this->drain();
    }

    public function onConsoleTerminate(): void
    {
        $this->drain();
    }

    private function drain(): void
    {
        try {
            $result = $this->hub->drain();
            if ($result['failed'] > 0) {
                $this->logger->warning('mercure.drain_partial', $result);
            }
        } catch (\Throwable $e) {
            // Defensive: drain() already catches per-update errors, but we
            // still wrap to guarantee that no exception escapes a terminate
            // event listener — they shouldn't ever abort cleanup.
            $this->logger->error('mercure.drain_unexpected', [
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);
        }
    }
}
