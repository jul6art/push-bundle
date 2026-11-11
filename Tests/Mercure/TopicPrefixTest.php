<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Mercure;

use Jul6Art\PushBundle\Mercure\TopicPrefix;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Le coin d'une application sur un hub partagé. Ce que ces tests protègent : deux applications
 * publient `/organizations/7/feed`, et l'abonné de l'une ne doit rien recevoir de l'autre.
 */
#[CoversClass(TopicPrefix::class)]
final class TopicPrefixTest extends TestCase
{
    public function testATopicIsPrefixedOnce(): void
    {
        $prefix = new TopicPrefix('/shop');

        self::assertSame('/shop/organizations/7/feed', $prefix->apply('/organizations/7/feed'));
        self::assertSame('/shop/organizations/7/feed', $prefix->apply('/shop/organizations/7/feed'), 'Idempotent: a topic already prefixed stays as it is.');
        self::assertSame('/shop/feed', $prefix->apply('feed'));
        self::assertSame(['/shop/a', '/shop/b'], $prefix->applyAll(['/a', '/b']));
    }

    public function testAPrefixThatIsOnlyTheStartOfASegmentIsNotTheApplications(): void
    {
        self::assertSame('/shop/shop-mobile/feed', new TopicPrefix('/shop')->apply('/shop-mobile/feed'));
    }

    /**
     * Mercure recommande des IRI : `https://example.org/organizations/7/feed` garde son schéma et son hôte, et
     * prend le préfixe au début de son chemin — jamais `/shop/https://…`, qu'aucun abonné n'écouterait.
     */
    public function testAnAbsoluteIriTakesThePrefixAtTheStartOfItsPath(): void
    {
        $prefix = new TopicPrefix('/shop');

        self::assertSame('https://example.org/shop/organizations/7/feed', $prefix->apply('https://example.org/organizations/7/feed'));
        self::assertSame('https://example.org/shop/organizations/7/feed', $prefix->apply('https://example.org/shop/organizations/7/feed'), 'Idempotent on an IRI too.');
        self::assertSame('https://example.org/shop', $prefix->apply('https://example.org'));
        self::assertSame('urn://example/shop/a', $prefix->apply('urn://example/a'));
        self::assertSame(['topics' => ['https://example.org/shop/servers'], 'patterns' => ['https://example.org/shop/organizations/:slug/alerts']], $prefix->subscription(['https://example.org/servers'], ['https://example.org/organizations/:slug/alerts']));
    }

    public function testWithoutAPrefixNothingChanges(): void
    {
        $prefix = new TopicPrefix();

        self::assertSame('', $prefix->prefix());
        self::assertSame('/organizations/7/feed', $prefix->apply('/organizations/7/feed'));
        self::assertSame(['topics' => ['*', '/a'], 'patterns' => ['/b/:id']], $prefix->subscription(['*', '/a'], ['/b/:id']));
    }

    public function testATrailingSlashIsNotPartOfThePrefix(): void
    {
        self::assertSame('/blog', new TopicPrefix('/blog/')->prefix());
    }

    public function testASubscriptionIsPrefixedOnBothItsTopicsAndItsPatterns(): void
    {
        self::assertSame(
            ['topics' => ['/shop/platform/feed'], 'patterns' => ['/shop/organizations/:id/feed']],
            new TopicPrefix('/shop')->subscription(['/platform/feed'], ['/organizations/:id/feed']),
        );
    }

    /**
     * `*` accorde tout le hub : sur un hub partagé, l'administrateur d'une application lirait toutes
     * les autres. Il devient `<préfixe>/*`, tout ce qui est À ELLE.
     */
    public function testTheWildcardBecomesEveryTopicOfTheApplication(): void
    {
        self::assertSame(['topics' => [], 'patterns' => ['/blog/*']], new TopicPrefix('/blog')->subscription(['*']));
        self::assertSame(['topics' => ['/blog/a'], 'patterns' => ['/blog/*']], new TopicPrefix('/blog')->subscription(['/a', '*'], ['*']));
    }

    public function testAnUpdateIsPublishedOnThePrefixedTopics(): void
    {
        $update = new TopicPrefix('/arcade')->update(['/players/3', '/players/4'], '{}', true);

        self::assertSame(['/arcade/players/3', '/arcade/players/4'], $update->getTopics());
        self::assertSame('{}', $update->getData());
        self::assertTrue($update->isPrivate());
        self::assertSame(['/arcade/players/3'], new TopicPrefix('/arcade')->update('/players/3')->getTopics());
    }
}
