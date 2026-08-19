<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Mercure;

use Jul6Art\PushBundle\Mercure\FeedTopicResolverInterface;
use Jul6Art\PushBundle\Mercure\GlobalFeedTopicResolver;
use Jul6Art\PushBundle\Tests\Fixtures\Entity\Article;
use Jul6Art\PushBundle\Tests\Fixtures\Entity\Widget;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GlobalFeedTopicResolver::class)]
final class FeedTopicResolverTest extends TestCase
{
    public function testTheDefaultResolverPutsEverythingOnOneTopic(): void
    {
        $resolver = new GlobalFeedTopicResolver();

        self::assertSame('/global/feed', $resolver->resolveTopic(new Article()));
        self::assertSame('/global/feed', $resolver->resolveTopic(new Widget()));
    }

    /**
     * Le contrat est volontairement minuscule : une entité, un topic. C'est tout ce que le
     * publisher a besoin de savoir, et tout ce qu'une application a besoin d'écrire.
     */
    public function testAnApplicationCanRoutePerTenant(): void
    {
        $resolver = new class implements FeedTopicResolverInterface {
            public function resolveTopic(object $entity): string
            {
                return $entity instanceof Article ? '/organizations/7/feed' : '/admin/feed';
            }
        };

        self::assertSame('/organizations/7/feed', $resolver->resolveTopic(new Article()));
        self::assertSame('/admin/feed', $resolver->resolveTopic(new Widget()));
    }
}
