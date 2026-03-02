<?php

declare(strict_types=1);

namespace HiveCpq\Client\Symfony\DependencyInjection;

use HiveCpq\Client\HiveClient;
use HiveCpq\Client\Symfony\HiveClientFactory;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

class HiveCpqExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $definition = new Definition(HiveClient::class);
        $definition->setFactory([HiveClientFactory::class, 'create']);
        $definition->setArgument(0, $config);
        $definition->setArgument(1, new Reference('logger', ContainerBuilder::NULL_ON_INVALID_REFERENCE));
        $definition->setPublic(true);

        $container->setDefinition(HiveClient::class, $definition);
        $container->setAlias('hive_cpq.client', HiveClient::class)->setPublic(true);
    }
}
