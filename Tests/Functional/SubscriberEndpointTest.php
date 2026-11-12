<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Functional;

use Jul6Art\PushBundle\Controller\MercureTokenController;
use Jul6Art\PushBundle\Mercure\GlobalFeedSubscriptionProvider;
use Jul6Art\PushBundle\Mercure\SubscriptionProviderInterface;
use Jul6Art\PushBundle\Tests\Fixtures\Mercure\AccountSubscriptionProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * The subscriber endpoint as an application wires it (ADR-0001): the real authorisation checker, the
 * real token storage, the hub's own token factory. Turning an AccessDeniedException into a 401 or a
 * 403 is the firewall's job, and each application's HTTP tests cover it. Without a hub — or without
 * the security component — the endpoint does not exist: MercureHubPassTest.
 */
#[CoversClass(MercureTokenController::class)]
final class SubscriberEndpointTest extends AbstractFunctionalTestCase
{
    public function testWithoutConfigurationEveryAccountListensToTheGlobalFeed(): void
    {
        $container = $this->boot();
        $this->signIn($container, $this->account('ada', 'ROLE_USER'));

        $response = $this->endpoint($container)();

        self::assertSame(['subscribed' => ['/global/feed'], 'patterns' => []], $this->body($response));
        self::assertCount(1, $response->headers->getCookies(), 'The token is in the cookie.');
        self::assertInstanceOf(GlobalFeedSubscriptionProvider::class, $container->get(SubscriptionProviderInterface::class));
    }

    public function testAnAnonymousRequestIsDenied(): void
    {
        $container = $this->boot();

        $this->expectException(AccessDeniedException::class);
        $this->endpoint($container)();
    }

    /**
     * `IS_AUTHENTICATED_FULLY` by default, as the copies had it: a session recognised by its
     * remember-me cookie gets no token unless the application says so.
     */
    public function testARememberedSessionIsDeniedByDefault(): void
    {
        $container = $this->boot();
        $this->signIn($container, new RememberMeToken(new InMemoryUser('ada', null, ['ROLE_USER']), 'main'));

        $this->expectException(AccessDeniedException::class);
        $this->endpoint($container)();
    }

    public function testARememberedSessionIsGrantedWhenTheApplicationAcceptsIt(): void
    {
        $container = $this->boot(pushConfig: ['mercure' => ['subscriber' => ['access' => 'IS_AUTHENTICATED']]]);
        $this->signIn($container, new RememberMeToken(new InMemoryUser('ada', null, ['ROLE_USER']), 'main'));

        self::assertSame(['/global/feed'], $this->body($this->endpoint($container)())['subscribed']);
    }

    public function testTheAccessAttributeIsTheApplicationsChoice(): void
    {
        $container = $this->boot(pushConfig: ['mercure' => ['subscriber' => ['access' => 'ROLE_ADMIN']]]);

        $this->signIn($container, $this->account('admin', 'ROLE_ADMIN'));
        self::assertSame(['/global/feed'], $this->body($this->endpoint($container)())['subscribed']);

        $this->signIn($container, $this->account('ada', 'ROLE_USER'));
        $this->expectException(AccessDeniedException::class);
        $this->endpoint($container)();
    }

    public function testTheApplicationsProviderDecidesAndTheHubPrefixApplies(): void
    {
        $container = $this->boot(pushConfig: ['mercure' => [
            'topic_prefix' => '/shop',
            'subscriber' => ['provider' => AccountSubscriptionProvider::class],
        ]]);
        $this->signIn($container, $this->account('ada', 'ROLE_USER'));

        self::assertSame(
            ['subscribed' => ['/shop/accounts/ada/feed'], 'patterns' => ['/shop/rooms/:id']],
            $this->body($this->endpoint($container)()),
        );
    }

    public function testAProvidersRefusalIsAnAccessDenial(): void
    {
        $container = $this->boot(pushConfig: ['mercure' => ['subscriber' => ['provider' => AccountSubscriptionProvider::class]]]);
        $this->signIn($container, $this->account('banned', 'ROLE_USER'));

        $this->expectException(AccessDeniedException::class);
        $this->endpoint($container)();
    }

    private function endpoint(ContainerInterface $container): MercureTokenController
    {
        $controller = $container->get(MercureTokenController::class);
        self::assertInstanceOf(MercureTokenController::class, $controller);

        return $controller;
    }

    private function account(string $name, string $role): TokenInterface
    {
        return new UsernamePasswordToken(new InMemoryUser($name, null, [$role]), 'main', [$role]);
    }

    private function signIn(ContainerInterface $container, TokenInterface $token): void
    {
        $storage = $container->get('security.token_storage');
        self::assertInstanceOf(TokenStorageInterface::class, $storage);
        $storage->setToken($token);
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
}
