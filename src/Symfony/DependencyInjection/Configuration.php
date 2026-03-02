<?php

declare(strict_types=1);

namespace HiveCpq\Client\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('hive_cpq');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('base_url')
                    ->defaultValue('https://connect.hivecpq.com/api/v1')
                ->end()
                ->integerNode('timeout')
                    ->defaultValue(30)
                ->end()
                ->integerNode('max_retries')
                    ->defaultValue(3)
                ->end()
                ->scalarNode('user_agent')
                    ->defaultValue('HiveCpq.Client.PHP/1.0.0')
                ->end()
                ->arrayNode('auth')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->values(['client_credentials', 'oauth2', 'bearer_token'])
                            ->isRequired()
                        ->end()
                        ->scalarNode('auth_domain')
                            ->defaultValue('authenticate.hivecpq.com')
                        ->end()
                        ->scalarNode('client_id')->defaultNull()->end()
                        ->scalarNode('client_secret')->defaultNull()->end()
                        ->scalarNode('audience')
                            ->defaultValue('https://ebusinesscloud.eu.auth0.com/api/v2/')
                        ->end()
                        ->scalarNode('username')->defaultNull()->end()
                        ->scalarNode('password')->defaultNull()->end()
                        ->scalarNode('bearer_token')->defaultNull()->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
