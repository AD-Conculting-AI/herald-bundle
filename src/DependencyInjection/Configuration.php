<?php

declare(strict_types=1);

namespace Herald\Bundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('herald');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('api_url')
                    ->defaultValue('')
                ->end()
                ->scalarNode('api_key')
                    ->defaultValue('')
                ->end()
                ->booleanNode('verify_peer')
                    ->defaultTrue()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
