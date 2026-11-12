<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * The answer to "let this browser subscribe": the token in a cookie, the list in the body.
 *
 * - **The token travels in the cookie only** ({@see SubscriberCookieFactory::createCookie()}): a 1.0
 *   hub refuses it anywhere JavaScript could read it.
 * - **The body is `{ subscribed, patterns }`**, prefixed as the token grants them: the front end
 *   subscribes to exactly what the token allows (`match=` for each topic, `match_urlpattern=` for each
 *   pattern). `patterns` is always there, empty when there is none. Deriving the list twice — once for
 *   the token, once for the `EventSource` — is how the two drift.
 *
 * {@see \Jul6Art\PushBundle\Controller\MercureTokenController} calls it; an endpoint of another shape
 * — a second area with its own access rule, a response that carries more — calls it too, with its own
 * {@see Subscription} and the keys it adds in `$extra` (which never replace `subscribed` or `patterns`).
 */
final readonly class SubscriberTokenResponder
{
    public function __construct(
        private SubscriberCookieFactory $cookieFactory,
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function respond(Subscription $subscription, array $extra = []): JsonResponse
    {
        $token = $this->cookieFactory->createToken($subscription->topics, $subscription->patterns);
        $granted = $this->cookieFactory->subscription($subscription->topics, $subscription->patterns);

        $response = new JsonResponse(['subscribed' => $granted['topics'], 'patterns' => $granted['patterns']] + $extra);
        $response->headers->setCookie($this->cookieFactory->createCookie($token));

        return $response;
    }
}
