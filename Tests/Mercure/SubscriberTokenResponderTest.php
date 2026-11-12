<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Mercure;

use Jul6Art\PushBundle\Mercure\GlobalFeedSubscriptionProvider;
use Jul6Art\PushBundle\Mercure\GlobalFeedTopicResolver;
use Jul6Art\PushBundle\Mercure\SubscriberCookieFactory;
use Jul6Art\PushBundle\Mercure\SubscriberTokenResponder;
use Jul6Art\PushBundle\Mercure\Subscription;
use Jul6Art\PushBundle\Mercure\TopicPrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mercure\Jwt\DefaultClaimsTokenFactory;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The answer every subscriber endpoint gives (ADR-0001): the token in the cookie, never in the body;
 * the body listing exactly what the token grants, prefixed as the hub carries it.
 */
#[CoversClass(SubscriberTokenResponder::class)]
#[CoversClass(Subscription::class)]
#[CoversClass(GlobalFeedSubscriptionProvider::class)]
final class SubscriberTokenResponderTest extends TestCase
{
    private const string AUDIENCE = 'https://app.test/.well-known/mercure';

    public function testTheBodyListsTheTopicsAndThePatternsPrefixedAsTheHubCarriesThem(): void
    {
        $response = $this->responder(prefix: '/shop')->respond(new Subscription(['/accounts/7/feed'], ['/rooms/:id']));

        self::assertSame(['subscribed' => ['/shop/accounts/7/feed'], 'patterns' => ['/shop/rooms/:id']], $this->body($response));
    }

    /**
     * `patterns` is always there: the front end reads one shape, whatever the application grants.
     */
    public function testPatternsIsAnEmptyListWhenThereIsNone(): void
    {
        $response = $this->responder()->respond(new Subscription(['/global/feed']));

        self::assertSame(['subscribed' => ['/global/feed'], 'patterns' => []], $this->body($response));
    }

    public function testTheTokenGrantsExactlyWhatTheBodyLists(): void
    {
        $response = $this->responder(prefix: '/shop')->respond(new Subscription(['/accounts/7/feed'], ['/rooms/:id']));

        self::assertSame(
            [['match' => '/shop/accounts/7/feed'], ['match' => '/shop/rooms/:id', 'match_type' => 'urlpattern']],
            $this->grant($this->cookie($response)->getValue() ?? ''),
        );
    }

    /**
     * A 1.0 hub refuses the token anywhere JavaScript could read it: it never reaches the body.
     */
    public function testTheTokenTravelsInTheCookieOnly(): void
    {
        $response = $this->responder()->respond(new Subscription(['/global/feed']));
        $token = $this->cookie($response)->getValue() ?? '';

        self::assertNotSame('', $token);
        self::assertStringNotContainsString($token, (string) $response->getContent());
        self::assertArrayNotHasKey('token', $this->body($response));
    }

    public function testTheCookieIsScopedToTheHubAndHiddenFromJavaScript(): void
    {
        $cookie = $this->cookie($this->responder()->respond(new Subscription(['/global/feed'])));

        self::assertSame('__Secure-mercure_access_token', $cookie->getName(), 'The name the hub reads.');
        self::assertSame('/.well-known/mercure', $cookie->getPath());
        self::assertTrue($cookie->isSecure());
        self::assertTrue($cookie->isHttpOnly());
        self::assertSame(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
    }

    /**
     * An endpoint of another shape adds its keys (superp's organisation area hands back the hub URL);
     * they never replace the list the token grants.
     */
    public function testExtraKeysAreAddedAndNeverReplaceTheGrantedList(): void
    {
        $response = $this->responder()->respond(
            new Subscription(['/global/feed']),
            ['hubUrl' => '/.well-known/mercure', 'subscribed' => ['*'], 'patterns' => ['/*']],
        );

        self::assertSame(['subscribed' => ['/global/feed'], 'patterns' => [], 'hubUrl' => '/.well-known/mercure'], $this->body($response));
    }

    public function testTheDefaultProviderGivesEveryAccountTheGlobalFeed(): void
    {
        $subscription = new GlobalFeedSubscriptionProvider()->subscriptionFor(new InMemoryUser('ada', null));

        self::assertSame([GlobalFeedTopicResolver::TOPIC], $subscription->topics);
        self::assertSame([], $subscription->patterns);
    }

    public function testASubscriptionKeepsListsWhateverTheKeysItWasGiven(): void
    {
        $filtered = [3 => '/a', 9 => '/b'];
        $subscription = new Subscription($filtered, $filtered);

        self::assertSame(['/a', '/b'], $subscription->topics);
        self::assertSame(['/a', '/b'], $subscription->patterns);
    }

    private function responder(string $prefix = ''): SubscriberTokenResponder
    {
        $tokens = new DefaultClaimsTokenFactory(
            new LcobucciFactory('a-secret-that-is-long-enough-for-hmac-sha256'),
            ['iss' => 'https://app.test', 'aud' => self::AUDIENCE, 'sub' => 'app', 'client_id' => 'app'],
        );

        return new SubscriberTokenResponder(new SubscriberCookieFactory(
            new MockHub(self::AUDIENCE, new StaticTokenProvider('t'), static fn (Update $update): string => 'id', $tokens),
            '/.well-known/mercure',
            3600,
            true,
            new TopicPrefix($prefix),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function body(JsonResponse $response): array
    {
        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $body;
    }

    private function cookie(JsonResponse $response): Cookie
    {
        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        return $cookies[0];
    }

    /**
     * @return list<array{match: string, match_type?: string}>
     */
    private function grant(string $token): array
    {
        $json = base64_decode(strtr(explode('.', $token)[1] ?? '', '-_', '+/'), true);
        self::assertIsString($json);

        /** @var array{authorization_details: list<array{topics: list<array{match: string, match_type?: string}>}>} $payload */
        $payload = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);

        return $payload['authorization_details'][0]['topics'];
    }
}
