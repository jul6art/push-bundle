<?php

namespace Jul6Art\PushBundle\DependencyInjection;

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
            ->scalarNode('async')->defaultFalse()->end()
            ->scalarNode('enabled')->defaultTrue()->end()
            ->end();

        return $builder;
    }
}
