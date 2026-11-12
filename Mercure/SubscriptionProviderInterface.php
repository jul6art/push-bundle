<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Which topics the signed-in account may listen to: **the application's authorisation decision**.
 *
 * A subscriber receives everything that goes through a channel it may listen to, and no Doctrine
 * filter applies to a channel: the list returned here is the only partition there is. Derive it from
 * the account — its organisation, its permissions —, never from a request parameter, or the client
 * picks what it hears.
 *
 * Configured with `push.mercure.subscriber.provider`; {@see GlobalFeedSubscriptionProvider} by
 * default, which is right for a single tenant only.
 */
interface SubscriptionProviderInterface
{
    /**
     * @throws AccessDeniedException when this account may not listen to anything; the endpoint answers 403
     */
    public function subscriptionFor(UserInterface $user): Subscription;
}
