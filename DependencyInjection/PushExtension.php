<?php

namespace Jul6Art\PushBundle\DependencyInjection;

use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Mercure\Update;

/**
 * Class PushExtension.
 */
class PushExtension extends Extension implements PrependExtensionInterface
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');

        $this->addAnnotatedClassesToCompile([
            'Jul6Art\\PushBundle\\',
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @throws \Exception
     */
    public function prepend(ContainerBuilder $container)
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!isset($bundles['CoreBundle'])) {
            throw new \RuntimeException(sprintf('"%s" bundle is required', CoreBundle::class));
        }

        $configs = $container->resolveEnvPlaceholders($container->getExtensionConfig($this->getAlias()), true);

        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach ($config as $key => $parameter) {
            $container->setParameter(sprintf('%s.%s', $this->getAlias(), $key), $parameter);
        }

        $routing = $config['routing'] ?? [];

        if ($config['async']) {
            $routing[Update::class] = 'async_priority_high';
            $routing[EntityAsyncEvent::class] = 'async_priority_high';
        } else {
            $routing[EntityAsyncEvent::class] = 'sync';
        }

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'transports' => [
                    'sync' => 'sync://',
                    'async_priority_high' =>[
                        'dsn' => $config['transport_method'],
                        'options' => [
                            'queue_name' => 'high',
                        ],
                    ],
                    'async_priority_low' => [
                        'dsn' => $config['transport_method'],
                        'options' => [
                            'queue_name' => 'low',
                        ],
                    ],
                ],
                'routing' => $routing,
            ],
        ]);
    }
}
