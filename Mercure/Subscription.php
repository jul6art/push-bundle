<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

/**
 * What an account may listen to on the hub: topics matched exactly, and URL Patterns for a family of
 * channels (`/organizations/:id/feed` — Mercure 1.0 dropped URI templates).
 *
 * Written as the application writes them, **without** the hub prefix: {@see SubscriberTokenResponder}
 * applies it once, to the token and to the list handed back alike ({@see TopicPrefix}).
 */
final readonly class Subscription
{
    /** @var list<string> */
    public array $topics;

    /** @var list<string> */
    public array $patterns;

    /**
     * Any array of strings is taken — a list built with `array_filter()` keeps its keys — and kept
     * as a list: the token and the JSON body both expect one.
     *
     * @param array<array-key, string> $topics
     * @param array<array-key, string> $patterns
     */
    public function __construct(array $topics, array $patterns = [])
    {
        $this->topics = array_values($topics);
        $this->patterns = array_values($patterns);
    }
}
