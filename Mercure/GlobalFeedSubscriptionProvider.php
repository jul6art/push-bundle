<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The default: every account listens to the one channel {@see GlobalFeedTopicResolver} publishes on.
 *
 * Right for a single tenant, and only for one. The day the application gains tenants, the list
 * becomes an authorisation decision — write a {@see SubscriptionProviderInterface} and name it in
 * `push.mercure.subscriber.provider`, alongside a `topic_resolver` that partitions what is published.
 */
final readonly class GlobalFeedSubscriptionProvider implements SubscriptionProviderInterface
{
    #[\Override]
    public function subscriptionFor(UserInterface $user): Subscription
    {
        return new Subscription([GlobalFeedTopicResolver::TOPIC]);
    }
}
