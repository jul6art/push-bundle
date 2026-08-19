<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Mercure;

use Jul6Art\PushBundle\Mercure\SubscriberCookieFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;

/**
 * Le jeton d'abonnement et son cookie. Ce que ces tests protègent n'est pas la signature — c'est
 * la portée : un cookie mal cadré est soit inopérant, soit lisible par du JavaScript.
 */
#[CoversClass(SubscriberCookieFactory::class)]
final class SubscriberCookieFactoryTest extends TestCase
{
    public function testTheTokenCarriesTheTopicsAsASubscribeClaim(): void
    {
        $token = $this->factory()->createToken(['/organizations/7/feed', '/global/feed']);

        self::assertSame(
            ['publish' => [], 'subscribe' => ['/organizations/7/feed', '/global/feed']],
            $this->claim($token),
        );
    }

    /**
     * Un abonné ne publie pas. La revendication `publish` est vide, et pas absente : un hub qui
     * la lirait comme un joker accorderait à chaque lecteur le droit d'écrire.
     */
    public function testASubscriberIsNeverGrantedPublishRights(): void
    {
        self::assertSame([], $this->claim($this->factory()->createToken(['/feed']))['publish']);
    }

    public function testTheCookieIsScopedToTheHubAndHiddenFromJavaScript(): void
    {
        $cookie = $this->factory()->createCookie('a-token');

        self::assertSame('mercureAuthorization', $cookie->getName());
        self::assertSame('/.well-known/mercure', $cookie->getPath(), 'Le cookie ne doit pas être envoyé à toute l\'application.');
        self::assertTrue($cookie->isHttpOnly(), 'Le jeton est pour la connexion du navigateur, pas pour du JS.');
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    public function testTheCookieAndTheTokenExpireTogether(): void
    {
        $factory = $this->factory(lifetime: 60);

        $expiresAt = $factory->createCookie('a-token')->getExpiresTime();

        // Une marge de quelques secondes : les deux horloges sont prises à un instant différent.
        self::assertLessThanOrEqual(2, abs($expiresAt - (time() + 60)));
    }

    /**
     * `cookie_secure: false` existe pour un poste de développement en http. Le défaut est `true`,
     * et le contraire doit être un choix écrit.
     */
    public function testTheSecureFlagCanBeLoweredDeliberately(): void
    {
        self::assertFalse($this->factory(secure: false)->createCookie('a-token')->isSecure());
    }

    private function factory(int $lifetime = 3600, bool $secure = true): SubscriberCookieFactory
    {
        return new SubscriberCookieFactory(
            new LcobucciFactory('a-secret-that-is-long-enough-for-hmac-sha256'),
            'mercureAuthorization',
            '/.well-known/mercure',
            $lifetime,
            $secure,
        );
    }

    /**
     * @return array{subscribe: list<string>, publish: list<string>}
     */
    private function claim(string $token): array
    {
        [, $payload] = explode('.', $token);

        $json = base64_decode(strtr($payload, '-_', '+/'), true);
        self::assertIsString($json);

        /** @var array{mercure: array{subscribe: list<string>, publish: list<string>}} $decoded */
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded['mercure'];
    }
}
