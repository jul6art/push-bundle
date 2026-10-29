<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Mercure;

use Jul6Art\PushBundle\Mercure\SubscriberCookieFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Mercure\Jwt\DefaultClaimsTokenFactory;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

/**
 * Le jeton d'abonnement et son cookie. Ce que ces tests protègent n'est pas la signature — c'est
 * la portée : un jeton que le hub 1.0 refuse (claims manquants, mauvaise audience), un cookie mal
 * cadré ou mal nommé, et le temps réel s'arrête sans rien dire.
 */
#[CoversClass(SubscriberCookieFactory::class)]
final class SubscriberCookieFactoryTest extends TestCase
{
    private const string AUDIENCE = 'https://app.test/.well-known/mercure';

    public function testTheTokenGrantsSubscribeOnExactlyTheTopics(): void
    {
        $token = $this->factory()->createToken(['/organizations/7/feed', '/global/feed']);

        self::assertSame([[
            'type' => 'https://mercure.rocks/authorization-detail',
            'actions' => ['subscribe'],
            'topics' => [['match' => '/organizations/7/feed'], ['match' => '/global/feed']],
        ]], $this->payload($token)['authorization_details']);
    }

    /**
     * Un abonné ne publie pas : aucune entrée du jeton ne porte l'action `publish`.
     */
    public function testASubscriberIsNeverGrantedPublishRights(): void
    {
        foreach ($this->payload($this->factory()->createToken(['/feed']))['authorization_details'] as $detail) {
            self::assertNotContains('publish', $detail['actions']);
        }
    }

    /**
     * Un hub 1.0 n'accepte qu'un jeton d'accès RFC 9068 : `typ: at+jwt`, l'émetteur qu'il connaît,
     * son audience, une expiration. Le bundle les tient de la fabrique du hub, jamais d'une copie.
     */
    public function testTheTokenCarriesTheClaimsOfTheHubsFactory(): void
    {
        $token = $this->factory()->createToken(['/feed']);
        $payload = $this->payload($token);

        self::assertSame('at+jwt', $this->header($token)['typ']);
        self::assertSame('https://app.test', $payload['iss']);
        self::assertSame(self::AUDIENCE, $payload['aud']);
        self::assertSame('app', $payload['sub']);
        self::assertSame('app', $payload['client_id']);
    }

    /**
     * Rien à écouter, rien d'accordé : une entrée sans sujet ferait rejeter tout le jeton par le hub.
     */
    public function testNoTopicGrantsNothing(): void
    {
        self::assertArrayNotHasKey('authorization_details', $this->payload($this->factory()->createToken([])));
    }

    public function testAHubWithoutAFactoryIsAConfigurationError(): void
    {
        $factory = new SubscriberCookieFactory(new MockHub(self::AUDIENCE, new StaticTokenProvider('t'), static fn (Update $update): string => 'id'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageIsOrContains('mercure.hubs.<name>.jwt');

        $factory->createToken(['/feed']);
    }

    public function testTheCookieIsScopedToTheHubAndHiddenFromJavaScript(): void
    {
        $cookie = $this->factory()->createCookie('a-token');

        self::assertSame('__Secure-mercure_access_token', $cookie->getName(), 'Le nom que le hub lit, pas un nom à part.');
        self::assertSame('/.well-known/mercure', $cookie->getPath(), 'Le cookie ne doit pas être envoyé à toute l\'application.');
        self::assertTrue($cookie->isHttpOnly(), 'Le jeton est pour la connexion du navigateur, pas pour du JS.');
        self::assertTrue($cookie->isSecure());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    public function testTheCookieAndTheTokenExpireTogether(): void
    {
        $factory = $this->factory(lifetime: 60);

        $cookieExpiry = $factory->createCookie('a-token')->getExpiresTime();
        $tokenExpiry = $this->payload($factory->createToken(['/feed']))['exp'];

        // Une marge de quelques secondes : les horloges sont prises à des instants différents.
        self::assertLessThanOrEqual(2, abs($cookieExpiry - (time() + 60)));
        self::assertLessThanOrEqual(2, abs($tokenExpiry - $cookieExpiry));
    }

    /**
     * `cookie_secure: false` existe pour un poste de développement en http — avec un hub dont le
     * `cookie_name` n'a pas le préfixe `__Secure-`. Le défaut est `true`, et le contraire doit être
     * un choix écrit.
     */
    public function testTheSecureFlagCanBeLoweredDeliberately(): void
    {
        self::assertFalse($this->factory(secure: false, cookieName: 'mercure_access_token')->createCookie('a-token')->isSecure());
    }

    private function factory(int $lifetime = 3600, bool $secure = true, ?string $cookieName = null): SubscriberCookieFactory
    {
        $tokens = new DefaultClaimsTokenFactory(
            new LcobucciFactory('a-secret-that-is-long-enough-for-hmac-sha256'),
            ['iss' => 'https://app.test', 'aud' => self::AUDIENCE, 'sub' => 'app', 'client_id' => 'app'],
        );

        return new SubscriberCookieFactory(
            new MockHub(self::AUDIENCE, new StaticTokenProvider('t'), static fn (Update $update): string => 'id', $tokens, null, $cookieName),
            '/.well-known/mercure',
            $lifetime,
            $secure,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function header(string $token): array
    {
        return $this->decode(explode('.', $token)[0]);
    }

    /**
     * @return array{iss: string, aud: string, sub: string, client_id: string, exp: int, authorization_details: list<array{type: string, actions: list<string>, topics: list<array{match: string}>}>}
     */
    private function payload(string $token): array
    {
        /** @var array{iss: string, aud: string, sub: string, client_id: string, exp: int, authorization_details: list<array{type: string, actions: list<string>, topics: list<array{match: string}>}>} $payload */
        $payload = $this->decode(explode('.', $token)[1]);

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $segment): array
    {
        $json = base64_decode(strtr($segment, '-_', '+/'), true);
        self::assertIsString($json);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
