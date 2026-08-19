<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Attribute;

/**
 * Marks a Doctrine entity as broadcastable via the tenant feed.
 *
 * ```php
 * #[ORM\Entity]
 * #[BroadcastableEntity]
 * class Page { … }
 *
 * #[BroadcastableEntity(type: 'CmsPage', changedFields: ['title', 'publishedAt'])]
 * class BlogPost { … }
 * ```
 *
 * `EventListener\EntityChangePublisher` reads it on every flush and emits one small update per
 * change, on the topic your `Mercure\FeedTopicResolverInterface` decides.
 *
 * > ⚠️ **The payload never carries business data** — a type, an IRI, an id, an action, a
 * > timestamp, the actor's id. Subscribers refetch through the API, which is where the access
 * > rules live. Putting a field value in the payload would broadcast it to every subscriber of
 * > the topic, past any voter.
 *
 * > ⚠️ **The attribute does nothing without a configured hub.** No `mercure.hubs` in the
 * > application, no publisher — silently. See the bundle's README.
 *
 * @see \Jul6Art\PushBundle\Mercure\EntityChangePublisher
 * @see \Jul6Art\PushBundle\Mercure\FeedTopicResolverInterface
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class BroadcastableEntity
{
    /**
     * @param string|null  $type          Override for the "type" field in the payload.
     *                                    Defaults to the class short name (e.g. "Page").
     * @param list<string> $changedFields Whitelist of field names to expose in the
     *                                    "changedFields" payload key for UPDATE events.
     *                                    Fields outside this list are never emitted —
     *                                    useful for client-side filtering without
     *                                    leaking sensitive mutations (passwords, etc.).
     *                                    Empty list (default) = no changedFields key.
     */
    public function __construct(
        public ?string $type = null,
        public array $changedFields = [],
    ) {
    }
}
