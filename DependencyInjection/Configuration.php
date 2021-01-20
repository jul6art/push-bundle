<?php

namespace Jul6Art\PushBundle\DependencyInjection;

use Jul6Art\PushBundle\DependencyInjection\Constants\TransportType;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Class Configuration.
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $builder = new TreeBuilder('push');

        $node = $builder->getRootNode();

        $node
            ->children()
                ->scalarNode('async')->defaultTrue()->end()
                ->scalarNode('enabled')->defaultTrue()->end()
                ->scalarNode('transport_type')->defaultValue(TransportType::TRANSPORT_TYPE_DATABASE)->end()
                ->scalarNode('transport_method')->defaultValue('doctrine://default')->end()
                ->arrayNode('routing')->useAttributeAsKey('name')
                    ->scalarPrototype()->end()
                ->end()
            ->end();

        return $builder;
    }
}
