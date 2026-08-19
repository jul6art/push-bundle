<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\DependencyInjection\Compiler;

use Jul6Art\PushBundle\Mercure\BufferingHub;
use Jul6Art\PushBundle\Mercure\EntityChangePublisher;
use Jul6Art\PushBundle\Mercure\FeedTopicResolverInterface;
use Jul6Art\PushBundle\Mercure\GlobalFeedTopicResolver;
use Jul6Art\PushBundle\Mercure\MercureDrainListener;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Drops the whole real-time stack when the application has no Mercure hub.
 *
 * `symfony/mercure-bundle` only creates `mercure.hub.default` once `mercure.hubs` is configured,
 * and an extension cannot see that — extensions run before the other bundles have had their say.
 * So the extension declares the services and this pass removes them if the hub never appeared.
 *
 * Removing rather than failing is the right trade here: an application can legitimately install
 * this bundle for its Messenger side (`Asyncable`, `AsyncDispatcher`) and want nothing to do with
 * real-time. Refusing to boot would make the async features hostage to a hub.
 *
 * > ⚠️ The consequence is the trap the README spells out: **no hub, no publishing, silently**.
 * > An entity carrying `#[BroadcastableEntity]` in an application without `mercure.hubs` emits
 * > nothing and reports nothing.
 */
final class MercureHubPass implements CompilerPassInterface
{
    #[\Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->has('mercure.hub.default')) {
            return;
        }

        foreach ([EntityChangePublisher::class, BufferingHub::class, MercureDrainListener::class, GlobalFeedTopicResolver::class] as $service) {
            $container->removeDefinition($service);
        }

        $container->removeAlias(FeedTopicResolverInterface::class);
    }
}
