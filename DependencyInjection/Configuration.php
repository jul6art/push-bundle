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
                ->arrayNode('routing')
                    ->info('Extra Messenger routing, merged with the routing this bundle adds.')
                    ->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
