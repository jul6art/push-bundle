<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures\Mercure;

use Jul6Art\PushBundle\Mercure\Subscription;
use Jul6Art\PushBundle\Mercure\SubscriptionProviderInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An application's decision, for the tests: the account's own channel and a family of rooms; an
 * account named "banned" may listen to nothing.
 */
final readonly class AccountSubscriptionProvider implements SubscriptionProviderInterface
{
    #[\Override]
    public function subscriptionFor(UserInterface $user): Subscription
    {
        if ('banned' === $user->getUserIdentifier()) {
            throw new AccessDeniedException('Banned accounts listen to nothing.');
        }

        return new Subscription(['/accounts/'.$user->getUserIdentifier().'/feed'], ['/rooms/:id']);
    }
}
