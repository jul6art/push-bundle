<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Controller;

use Jul6Art\PushBundle\Mercure\SubscriberTokenResponder;
use Jul6Art\PushBundle\Mercure\SubscriptionProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * The endpoint the browser calls before opening its `EventSource`: it sets the subscriber cookie and
 * returns the authoritative list (`<meta name="mercure-token-url">`, which admin-bundle renders from
 * `admin.mercure.token_route` and the datatable bus reads).
 *
 * **The bundle declares no route**: the application does, under the name and path its front end
 * already uses —
 *
 * ```yaml
 * # config/routes/push.yaml
 * admin_mercure_token:
 *     path: /admin/mercure-token
 *     controller: Jul6Art\PushBundle\Controller\MercureTokenController
 *     methods: [GET]
 * ```
 *
 * Who may call it is `push.mercure.subscriber.access` (`IS_AUTHENTICATED_FULLY` by default, any
 * attribute `isGranted()` accepts); what they hear is the configured
 * {@see SubscriptionProviderInterface}, the application's decision.
 */
final readonly class MercureTokenController
{
    public function __construct(
        private AuthorizationCheckerInterface $authorizationChecker,
        private TokenStorageInterface $tokenStorage,
        private SubscriptionProviderInterface $subscriptionProvider,
        private SubscriberTokenResponder $responder,
        private string $access = 'IS_AUTHENTICATED_FULLY',
    ) {
    }

    public function __invoke(): JsonResponse
    {
        if (!$this->authorizationChecker->isGranted($this->access)) {
            $denied = new AccessDeniedException('This account may not subscribe to the Mercure hub.');
            $denied->setAttributes([$this->access]);

            throw $denied;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof UserInterface) {
            throw new AccessDeniedException('A Mercure subscription belongs to a signed-in account.');
        }

        return $this->responder->respond($this->subscriptionProvider->subscriptionFor($user));
    }
}
