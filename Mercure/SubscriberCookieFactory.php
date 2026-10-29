<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Mercure;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\Grant;

/**
 * Mints the subscriber JWT and the cookie the browser needs to open an `EventSource`.
 *
 * A Mercure subscriber authenticates with an access token granting `subscribe` on its topics, and
 * the browser cannot attach an `Authorization` header to an `EventSource` — hence a cookie,
 * scoped to the hub path. Mercure 1.0 offers no other way: the `authorization` query parameter is
 * gone (RFC 9700 forbids a token in a URL). Both halves are fiddly and neither is
 * application-specific, which is why they live here:
 *
 * ```php
 * #[Route('/organization/mercure-token', methods: ['GET'])]
 * #[IsGranted('ROLE_USER')]
 * public function __invoke(SubscriberCookieFactory $factory): Response
 * {
 *     $topics = $this->topicsFor($this->getUser());          // ← the application's policy
 *     $token = $factory->createToken($topics);
 *
 *     $response = new JsonResponse(['subscribed' => $topics, 'token' => $token]);
 *     $response->headers->setCookie($factory->createCookie($token));
 *
 *     return $response;
 * }
 * ```
 *
 * **Which topics a user may subscribe to stays in the application** — it is an authorisation
 * decision, and the token's grant is what enforces it on the hub. The bundle signs what it is
 * given, with the hub's own factory: the `iss`, `aud`, `sub` and `client_id` claims a 1.0 hub
 * requires come from `mercure.hubs.<name>.jwt.claims`, and the cookie bears the name the hub
 * reads (`cookie_name`, `__Secure-mercure_access_token` by default).
 *
 * > ⚠️ **The hub must be served under the application's own host.** The cookie is host-only and
 * > `SameSite=Strict`: a browser never sends it to a hub on another site, and 1.0 leaves no other
 * > way in — relay `/.well-known/mercure` under the application's domain.
 *
 * > ⚠️ **Return the topic list to the client as well.** The claim and the topics the
 * > `EventSource` actually subscribes to must agree; deriving them twice — once here, once in a
 * > template — is how they drift. Hand the same array back in the response body and let the
 * > front end use it.
 *
 * > ⚠️ **`*` is a wildcard on both sides.** A claim of `['*']` lets an account subscribe to
 * > every topic, present and future. Convenient for an administrator, and a tenant leak for
 * > anyone else.
 */
final readonly class SubscriberCookieFactory
{
    /**
     * @param int $lifetime seconds the token and the cookie stay valid. They expire together on
     *                      purpose: a cookie outliving its token yields an `EventSource` that
     *                      reconnects and is rejected, which reads as "real-time is broken"
     *                      rather than "log in again"
     */
    public function __construct(
        private HubInterface $hub,
        private string $cookiePath = '/.well-known/mercure',
        private int $lifetime = 3600,
        private bool $cookieSecure = true,
    ) {
    }

    /**
     * @param list<string> $topics   topics the subscriber is allowed to listen to, matched exactly
     *                               (`*` matches every topic)
     * @param list<string> $patterns URL Patterns it is allowed to listen to (`/organizations/:id/feed`):
     *                               Mercure 1.0's replacement for URI templates, which a 1.x hub would
     *                               compare letter by letter. The subscriber listens with
     *                               `match_urlpattern=`
     */
    public function createToken(array $topics, array $patterns = []): string
    {
        $factory = $this->hub->getFactory()
            ?? throw new \LogicException('The Mercure hub signs no token: configure "mercure.hubs.<name>.jwt" (its secret, and the "iss", "aud", "sub" and "client_id" claims).');

        $matchers = array_filter(['exact' => $topics, 'urlpattern' => $patterns], static fn (array $list): bool => [] !== $list);

        return $factory->create(
            [] === $matchers ? [] : [new Grant([Grant::ACTION_SUBSCRIBE], $matchers)],
            ['exp' => new \DateTimeImmutable(\sprintf('+%d seconds', $this->lifetime))],
        );
    }

    /**
     * Scoped to the hub path and `httpOnly`: the token is for the browser's connection to the
     * hub, never for JavaScript to read. `SameSite=Strict` because a cross-site request has no
     * business opening someone else's feed.
     */
    public function createCookie(string $token): Cookie
    {
        return Cookie::create(
            $this->hub->getCookieName(),
            $token,
            new \DateTimeImmutable(\sprintf('+%d seconds', $this->lifetime)),
            $this->cookiePath,
            null,
            $this->cookieSecure,
            true,
            false,
            Cookie::SAMESITE_STRICT,
        );
    }
}
