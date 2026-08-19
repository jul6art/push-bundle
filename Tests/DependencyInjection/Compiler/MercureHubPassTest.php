<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\DependencyInjection\Compiler;

use Jul6Art\PushBundle\DependencyInjection\Compiler\MercureHubPass;
use Jul6Art\PushBundle\Mercure\BufferingHub;
use Jul6Art\PushBundle\Mercure\EntityChangePublisher;
use Jul6Art\PushBundle\Mercure\FeedTopicResolverInterface;
use Jul6Art\PushBundle\Mercure\GlobalFeedTopicResolver;
use Jul6Art\PushBundle\Mercure\MercureDrainListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * Le cas « application sans hub », testé au niveau du pass et non du kernel.
 *
 * Pourquoi pas un test fonctionnel : `Service\Pusher` — antérieur à ce lot — dépend en dur de
 * `HubInterface`, donc le bundle **entier** ne démarre pas sans hub aujourd'hui. Ce pass reste
 * néanmoins nécessaire et correct : il traite le jour où cette dépendance deviendra optionnelle,
 * et il documente que le temps réel est une brique séparable du côté Messenger.
 */
#[CoversClass(MercureHubPass::class)]
final class MercureHubPassTest extends TestCase
{
    public function testWithAHubEverythingSurvives(): void
    {
        $container = $this->containerWithRealTime();
        $container->register('mercure.hub.default', \stdClass::class);

        new MercureHubPass()->process($container);

        self::assertTrue($container->hasDefinition(EntityChangePublisher::class));
        self::assertTrue($container->hasDefinition(BufferingHub::class));
        self::assertTrue($container->hasAlias(FeedTopicResolverInterface::class));
    }

    /**
     * Retirer plutôt que refuser de démarrer : une application peut installer ce bundle pour son
     * côté Messenger et ne rien vouloir du temps réel. Prendre les fonctions asynchrones en otage
     * d'un hub serait le mauvais compromis.
     */
    public function testWithoutAHubTheRealTimeStackIsRemoved(): void
    {
        $container = $this->containerWithRealTime();

        new MercureHubPass()->process($container);

        foreach ([EntityChangePublisher::class, BufferingHub::class, MercureDrainListener::class, GlobalFeedTopicResolver::class] as $service) {
            self::assertFalse($container->hasDefinition($service), \sprintf('%s doit disparaître.', $service));
        }

        self::assertFalse($container->hasAlias(FeedTopicResolverInterface::class));
    }

    public function testThePassIsIdempotentOnAnEmptyContainer(): void
    {
        $container = new ContainerBuilder();

        new MercureHubPass()->process($container);

        self::assertSame(['service_container'], array_keys($container->getDefinitions()));
    }

    private function containerWithRealTime(): ContainerBuilder
    {
        $container = new ContainerBuilder();

        foreach ([EntityChangePublisher::class, BufferingHub::class, MercureDrainListener::class, GlobalFeedTopicResolver::class] as $service) {
            $container->setDefinition($service, new Definition(\stdClass::class));
        }

        $container->setAlias(FeedTopicResolverInterface::class, GlobalFeedTopicResolver::class);

        return $container;
    }
}
