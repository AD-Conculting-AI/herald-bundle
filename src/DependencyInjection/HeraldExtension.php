<?php

declare(strict_types=1);

namespace Herald\Bundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class HeraldExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('herald.api_url', $config['api_url']);
        $container->setParameter('herald.api_key', $config['api_key']);
        $container->setParameter('herald.verify_peer', $config['verify_peer']);
        $container->setParameter('herald.webhook_secret', $config['webhook_secret']);

        $loader = new YamlFileLoader($container, new FileLocator(\dirname(__DIR__, 2) . '/Resources/config'));
        $loader->load('services.yaml');
    }
}
