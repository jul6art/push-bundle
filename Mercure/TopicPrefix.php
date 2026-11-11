<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

use Symfony\Component\Mercure\Update;

/**
 * The application's own corner of a shared Mercure hub: `/cereezer/organizations/7/feed` rather
 * than `/organizations/7/feed` (`push.mercure.topic_prefix: '/cereezer'`).
 *
 * ⚠️ **Several applications on one hub publish the same topic names.** Without a prefix, a subscriber
 * of one application granted `/organizations/7/feed` receives what organisation 7 of EVERY
 * application publishes — the ids are per database, not per hub. The prefix keeps each
 * application's subscribers on its own topics.
 *
 * Applied in ONE place per side, by the bundle: {@see EntityChangePublisher} prefixes what it
 * publishes, {@see SubscriberCookieFactory} what it grants. An application's resolvers and
 * controllers keep writing topics without the prefix; what it publishes by hand goes through
 * {@see self::update()}, and the topics it hands back to the browser through {@see subscription()}.
 *
 * Idempotent: a topic that already carries the prefix is left as it is.
 *
 * > ⚠️ **The hub's key stays shared.** The prefix closes the leak between subscribers, not the case
 * > of a compromised application publishing on another one's topics.
 */
final readonly class TopicPrefix
{
    private string $prefix;

    public function __construct(string $prefix = '')
    {
        $this->prefix = rtrim($prefix, '/');
    }

    /** The prefix, without a trailing slash; empty when the application set none. */
    public function prefix(): string
    {
        return $this->prefix;
    }

    public function apply(string $topic): string
    {
        if ('' === $this->prefix || '*' === $topic || $topic === $this->prefix || str_starts_with($topic, $this->prefix.'/')) {
            return $topic;
        }

        return $this->prefix.(str_starts_with($topic, '/') ? '' : '/').$topic;
    }

    /**
     * @param iterable<string> $topics
     *
     * @return list<string>
     */
    public function applyAll(iterable $topics): array
    {
        $prefixed = [];
        foreach ($topics as $topic) {
            $prefixed[] = $this->apply($topic);
        }

        return $prefixed;
    }

    /**
     * What a subscriber is granted, and what its `EventSource` listens to: the topics and the URL
     * patterns, prefixed.
     *
     * ⚠️ `*` — every topic of the hub — becomes the pattern `<prefix>/*`: every topic of THIS
     * application. Granted as such, an administrator of one application would read all the others.
     *
     * @param list<string> $topics
     * @param list<string> $patterns
     *
     * @return array{topics: list<string>, patterns: list<string>}
     */
    public function subscription(array $topics, array $patterns = []): array
    {
        if ('' === $this->prefix) {
            return ['topics' => array_values($topics), 'patterns' => array_values($patterns)];
        }

        $everything = \in_array('*', $topics, true) || \in_array('*', $patterns, true);
        $without = static fn (array $list): array => array_values(array_filter($list, static fn (string $item): bool => '*' !== $item));

        $patterns = $this->applyAll($without($patterns));
        if ($everything) {
            $patterns[] = $this->prefix.'/*';
        }

        return ['topics' => $this->applyAll($without($topics)), 'patterns' => array_values(array_unique($patterns))];
    }

    /**
     * An update on the application's own topics: `new Update(…)` with the prefix applied.
     *
     * @param string|list<string> $topics
     */
    public function update(string|array $topics, string $data = '', bool $private = false, ?string $id = null, ?string $type = null, ?int $retry = null): Update
    {
        return new Update(\is_string($topics) ? $this->apply($topics) : $this->applyAll($topics), $data, $private, $id, $type, $retry);
    }
}
