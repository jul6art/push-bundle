<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Functional;

use Jul6Art\PushBundle\Mercure\BufferingHub;
use Jul6Art\PushBundle\Mercure\EntityChangePublisher;
use Jul6Art\PushBundle\Mercure\FeedTopicResolverInterface;
use Jul6Art\PushBundle\Mercure\GlobalFeedTopicResolver;
use Jul6Art\PushBundle\Mercure\MercureDrainListener;
use Jul6Art\PushBundle\Mercure\SubscriberCookieFactory;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Mercure\HubInterface;

/**
 * Le câblage du temps réel, et surtout son absence.
 *
 * Une application peut légitimement installer ce bundle pour son côté Messenger (`Asyncable`,
 * `AsyncDispatcher`) sans vouloir de hub. Dans ce cas tout le temps réel disparaît — et c'est le
 * piège que le README énonce : `#[BroadcastableEntity]` ne diffuse alors **rien, en silence**.
 */
#[CoversNothing]
final class MercureWiringTest extends AbstractFunctionalTestCase
{
    public function testWithAHubTheWholeStackIsRegistered(): void
    {
        $container = $this->boot();

        self::assertInstanceOf(EntityChangePublisher::class, $container->get(EntityChangePublisher::class));
        self::assertInstanceOf(BufferingHub::class, $container->get(BufferingHub::class));
        self::assertInstanceOf(GlobalFeedTopicResolver::class, $container->get(FeedTopicResolverInterface::class));
    }

    /**
     * Le tampon est un **décorateur** : `HubInterface` doit résoudre vers lui, sinon les
     * publications passeraient à côté et une panne de hub redeviendrait un 500.
     */
    public function testTheBufferingHubDecoratesTheRealOne(): void
    {
        // La question qu'il faut poser : à travers quoi le publisher publie-t-il ? Si ce n'est
        // pas le tampon, une panne de hub redevient un 500 sur une écriture réussie.
        self::assertInstanceOf(BufferingHub::class, $this->hubBehind($this->boot()));
    }

    public function testBufferingCanBeTurnedOffWithoutLosingThePublisher(): void
    {
        $container = $this->boot('test', ['mercure' => ['buffering' => false]]);

        self::assertInstanceOf(EntityChangePublisher::class, $container->get(EntityChangePublisher::class));
        self::assertNotInstanceOf(BufferingHub::class, $this->hubBehind($container));

        $this->expectException(ServiceNotFoundException::class);
        $container->get(MercureDrainListener::class);
    }

    /**
     * La fabrique de cookie signe avec la fabrique du hub et nomme le cookie comme le hub le lit :
     * un second secret ou un second nom, ici, finirait par diverger de ceux du hub, et un jeton
     * rejeté se lit « le temps réel ne marche pas », pas « la configuration est fausse ». Sans
     * hub, `MercureHubPass` la retire (MercureHubPassTest).
     */
    public function testTheSubscriberFactorySignsWithTheHub(): void
    {
        $container = $this->boot();

        $factory = $container->get(SubscriberCookieFactory::class);
        self::assertInstanceOf(SubscriberCookieFactory::class, $factory);

        [, $payload] = explode('.', $factory->createToken(['/global/feed']));
        $claims = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($claims);
        self::assertSame('https://example.com', $claims['iss'] ?? null);
        self::assertSame('https://example.com/.well-known/mercure', $claims['aud'] ?? null);

        self::assertSame($this->hubBehind($container)->getCookieName(), $factory->createCookie('t')->getName());
    }

    /**
     * Le hub que le publisher a réellement reçu. Passer par les identifiants du conteneur est
     * illusoire ici : la décoration réécrit `mercure.hub.default`, et ce qui compte n'est pas un
     * identifiant mais ce qui est injecté.
     */
    private function hubBehind(ContainerInterface $container): HubInterface
    {
        $publisher = $container->get(EntityChangePublisher::class);
        self::assertInstanceOf(EntityChangePublisher::class, $publisher);

        $hub = new \ReflectionProperty(EntityChangePublisher::class, 'hub')->getValue($publisher);
        self::assertInstanceOf(HubInterface::class, $hub);

        return $hub;
    }

    /**
     * Un résolveur applicatif remplace le défaut : c'est la seule façon d'obtenir un cloisonnement
     * par locataire, et le bundle ne peut pas le deviner.
     */
    public function testAnApplicationResolverReplacesTheDefault(): void
    {
        $container = $this->boot('test', ['mercure' => ['topic_resolver' => GlobalFeedTopicResolver::class]]);

        self::assertInstanceOf(GlobalFeedTopicResolver::class, $container->get(FeedTopicResolverInterface::class));
    }
}
