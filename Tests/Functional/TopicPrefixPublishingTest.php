<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Jul6Art\PushBundle\Mercure\BufferingHub;
use Jul6Art\PushBundle\Mercure\SubscriberCookieFactory;
use Jul6Art\PushBundle\Mercure\TopicPrefix;
use Jul6Art\PushBundle\Tests\Fixtures\Entity\Article;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Mercure\Update;

/**
 * `push.mercure.topic_prefix`, de bout en bout : ce que le publisher émet, ce que le jeton accorde,
 * et ce que l'application publie elle-même portent le même préfixe — un seul oubli, et l'abonné
 * n'entend plus rien de ce côté-là.
 */
#[CoversNothing]
final class TopicPrefixPublishingTest extends AbstractFunctionalTestCase
{
    public function testTheBundlePublishesAndGrantsInTheApplicationsCorner(): void
    {
        $container = $this->boot(pushConfig: ['mercure' => ['topic_prefix' => '/cereezer']]);

        $entityManager = $container->get('doctrine.orm.default_entity_manager');
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $entityManager->persist(new Article('Bonjour'));
        $entityManager->flush();

        $hub = $container->get(BufferingHub::class);
        self::assertInstanceOf(BufferingHub::class, $hub);
        $buffer = new \ReflectionProperty(BufferingHub::class, 'buffer')->getValue($hub);
        self::assertIsArray($buffer);
        self::assertCount(1, $buffer);
        $update = $buffer[0];
        self::assertInstanceOf(Update::class, $update);
        self::assertSame(['/cereezer/global/feed'], $update->getTopics());
        /** @var array{topic: string} $payload */
        $payload = json_decode($update->getData(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('/cereezer/global/feed', $payload['topic'], 'The JS bus filters on the topic the payload echoes.');

        $factory = $container->get(SubscriberCookieFactory::class);
        self::assertInstanceOf(SubscriberCookieFactory::class, $factory);
        self::assertSame(['topics' => ['/cereezer/global/feed'], 'patterns' => []], $factory->subscription(['/global/feed']));

        $prefix = $container->get(TopicPrefix::class);
        self::assertInstanceOf(TopicPrefix::class, $prefix);
        self::assertSame(['/cereezer/users/3/notifications'], $prefix->update('/users/3/notifications', '{}', true)->getTopics());
    }

    public function testWithoutAPrefixTheTopicsAreTheResolvers(): void
    {
        $prefix = $this->boot()->get(TopicPrefix::class);

        self::assertInstanceOf(TopicPrefix::class, $prefix);
        self::assertSame('/global/feed', $prefix->apply('/global/feed'));
    }
}
