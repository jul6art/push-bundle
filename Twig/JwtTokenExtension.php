<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Twig;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Exposes `jwt_token()` to Twig — returns a JWT for the currently
 * authenticated user, or an empty string when unauthenticated.
 *
 * ```twig
 * {# once, in the layout #}
 * <script>window.jwtToken = {{ jwt_token()|json_encode|raw }};</script>
 * ```
 *
 * What it replaces: every controller generating a token and passing it to its template, so that
 * an autocomplete widget can authenticate its `/api/*` calls. Duplicated in each action, and
 * easy to forget in one — at which point that page's widgets silently stop working.
 *
 * The token is memoised per request, so ten calls in one response sign one payload.
 *
 * > ⚠️ **Registered only when `lexik/jwt-authentication-bundle` is installed.** Without it the
 * > function does not exist and a template calling it fails at render time — deliberately loud,
 * > because a silent empty string would look like "not logged in".
 *
 * > ⚠️ **This is an API token, not the Mercure one.** For the real-time feed see
 * > {@see \Jul6Art\PushBundle\Mercure\SubscriberCookieFactory}, which mints a different token
 * > with a `mercure.subscribe` claim.
 */
final class JwtTokenExtension extends AbstractExtension
{
    private ?string $cached = null;

    public function __construct(
        private readonly Security $security,
        private readonly JWTTokenManagerInterface $jwtManager,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('jwt_token', $this->jwtToken(...)),
        ];
    }

    public function jwtToken(): string
    {
        if (null !== $this->cached) {
            return $this->cached;
        }

        $user = $this->security->getUser();

        if (!$user instanceof UserInterface) {
            return $this->cached = '';
        }

        return $this->cached = $this->jwtManager->create($user);
    }
}
