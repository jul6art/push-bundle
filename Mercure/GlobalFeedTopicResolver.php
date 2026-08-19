<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

/**
 * Default resolver: everything on one topic.
 *
 * Right for a single-tenant application, and wrong as soon as there is a second tenant — every
 * subscriber of `/global/feed` hears about every change. A multi-tenant application implements
 * {@see FeedTopicResolverInterface} itself; the bundle cannot guess where its tenant boundary
 * lies.
 */
final class GlobalFeedTopicResolver implements FeedTopicResolverInterface
{
    public const string TOPIC = '/global/feed';

    public function resolveTopic(object $entity): string
    {
        return self::TOPIC;
    }
}
