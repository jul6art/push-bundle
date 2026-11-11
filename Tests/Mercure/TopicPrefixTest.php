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
        $prefix = new TopicPrefix('/cereezer');

        self::assertSame('/cereezer/organizations/7/feed', $prefix->apply('/organizations/7/feed'));
        self::assertSame('/cereezer/organizations/7/feed', $prefix->apply('/cereezer/organizations/7/feed'), 'Idempotent: a topic already prefixed stays as it is.');
        self::assertSame('/cereezer/feed', $prefix->apply('feed'));
        self::assertSame(['/cereezer/a', '/cereezer/b'], $prefix->applyAll(['/a', '/b']));
    }

    public function testAPrefixThatIsOnlyTheStartOfASegmentIsNotTheApplications(): void
    {
        self::assertSame('/cereezer/cereezer-mobile/feed', new TopicPrefix('/cereezer')->apply('/cereezer-mobile/feed'));
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
        self::assertSame('/superp', new TopicPrefix('/superp/')->prefix());
    }

    public function testASubscriptionIsPrefixedOnBothItsTopicsAndItsPatterns(): void
    {
        self::assertSame(
            ['topics' => ['/cereezer/platform/feed'], 'patterns' => ['/cereezer/organizations/:id/feed']],
            new TopicPrefix('/cereezer')->subscription(['/platform/feed'], ['/organizations/:id/feed']),
        );
    }

    /**
     * `*` accorde tout le hub : sur un hub partagé, l'administrateur d'une application lirait toutes
     * les autres. Il devient `<préfixe>/*`, tout ce qui est À ELLE.
     */
    public function testTheWildcardBecomesEveryTopicOfTheApplication(): void
    {
        self::assertSame(['topics' => [], 'patterns' => ['/superp/*']], new TopicPrefix('/superp')->subscription(['*']));
        self::assertSame(['topics' => ['/superp/a'], 'patterns' => ['/superp/*']], new TopicPrefix('/superp')->subscription(['/a', '*'], ['*']));
    }

    public function testAnUpdateIsPublishedOnThePrefixedTopics(): void
    {
        $update = new TopicPrefix('/devingame')->update(['/players/3', '/players/4'], '{}', true);

        self::assertSame(['/devingame/players/3', '/devingame/players/4'], $update->getTopics());
        self::assertSame('{}', $update->getData());
        self::assertTrue($update->isPrivate());
        self::assertSame(['/devingame/players/3'], new TopicPrefix('/devingame')->update('/players/3')->getTopics());
    }
}
