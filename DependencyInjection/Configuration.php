<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\DependencyInjection;

use Jul6Art\PushBundle\DependencyInjection\Constants\TransportType;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration.
 */
class Configuration implements ConfigurationInterface
{
    #[\Override]
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('push');

        $treeBuilder->getRootNode()
            ->children()
                ->booleanNode('async')
                    ->info('Route the updates through Messenger instead of publishing them inline.')
                    ->defaultTrue()
                ->end()
                ->booleanNode('enabled')
                    ->info('Turn the pusher off without removing the bundle.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('transport_type')
                    ->info('Kind of Messenger transport backing the async queues.')
                    ->defaultValue(TransportType::TRANSPORT_TYPE_DATABASE)
                ->end()
                ->scalarNode('transport_method')
                    ->info('DSN of the async_priority_high and async_priority_low transports.')
                    ->defaultValue('doctrine://default')
                ->end()
                ->arrayNode('mercure')
                    ->info('Real-time feed. Every service below is registered only when the application configures a hub (mercure.hubs); without one, nothing here exists and nothing is published.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('buffering')
                            ->info('Decorate the hub so publish() is deferred to kernel.terminate and a hub failure can never surface to the user. Turning this off makes a slow hub a slow response, and a broken hub a 500 on a write that succeeded.')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('topic_resolver')
                            ->info('Service implementing Mercure\\FeedTopicResolverInterface. Defaults to GlobalFeedTopicResolver, which puts everything on /global/feed — correct for a single tenant, a leak for more than one.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('jwt_secret')
                            ->info('Secret signing the subscriber JWT, i.e. the same secret the hub validates with. Required to register SubscriberCookieFactory.')
                            ->defaultNull()
                        ->end()
                        ->integerNode('token_lifetime')
                            ->info('Seconds the subscriber token and its cookie stay valid. They expire together on purpose.')
                            ->defaultValue(3600)
                        ->end()
                        ->scalarNode('cookie_name')
                            ->defaultValue('mercureAuthorization')
                        ->end()
                        ->scalarNode('cookie_path')
                            ->info('Path the cookie is scoped to — the hub endpoint, not the application.')
                            ->defaultValue('/.well-known/mercure')
                        ->end()
                        ->booleanNode('cookie_secure')
                            ->info('Send the cookie over HTTPS only. Set it to false only for a local http setup, and know that you are doing it.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('routing')
                    ->info('Extra Messenger routing, merged with the routing this bundle adds.')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
