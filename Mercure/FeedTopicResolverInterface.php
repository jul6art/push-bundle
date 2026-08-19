<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

/**
 * Decides which Mercure topic an entity's changes are published on.
 *
 * This is the seam that keeps {@see EntityChangePublisher} free of any application entity. The
 * publisher knows *that* a change happened; only the application knows *who should hear about
 * it* — and in a multi-tenant application that is the whole security boundary of the real-time
 * feed, since a subscriber receives everything on a topic it is allowed to listen to.
 *
 * ```php
 * final class OrganizationFeedTopicResolver implements FeedTopicResolverInterface
 * {
 *     public function resolveTopic(object $entity): string
 *     {
 *         if ($entity instanceof Organization) {
 *             return '/organizations/'.$entity->getId().'/feed';
 *         }
 *
 *         if (method_exists($entity, 'getOrganization')) {
 *             $organization = $entity->getOrganization();
 *
 *             return $organization instanceof Organization
 *                 ? '/organizations/'.$organization->getId().'/feed'
 *                 : '/admin/feed';
 *         }
 *
 *         return '/global/feed';
 *     }
 * }
 * ```
 *
 * > ⚠️ **A tenant-scoped entity whose tenant is null must not fall back to a shared topic.**
 * > The example sends it to `/admin/feed` rather than `/global/feed`, because every tenant user
 * > is subscribed to the latter: a super-admin row landing there would both leak its existence
 * > and trigger a reload in every tenant. Getting this wrong is silent — the feed simply works,
 * > for too many people.
 *
 * The returned topic is also what the publisher uses to group a burst of changes, so two
 * entities on the same topic collapse into one `bulk` event and two entities on different
 * topics never do.
 *
 * The bundle ships {@see GlobalFeedTopicResolver} as the default, which puts everything on
 * `/global/feed` — correct for a single-tenant application, and wrong the moment there is more
 * than one tenant.
 */
interface FeedTopicResolverInterface
{
    /**
     * @return string a Mercure topic, e.g. `/organizations/42/feed`
     */
    public function resolveTopic(object $entity): string;
}
