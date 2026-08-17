<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\DependencyInjection;

use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Mercure\Update;

/**
 * Class PushExtension.
 *
 * @phpstan-type PushConfig array{
 *     async: bool,
 *     enabled: bool,
 *     transport_type: string,
 *     transport_method: string,
 *     routing: array<string, string>,
 * }
 */
class PushExtension extends Extension implements PrependExtensionInterface
{
    #[\Override]
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );

        $loader->load('services.yaml');
    }

    /**
     * @throws \RuntimeException if CoreBundle is not registered
     */
    #[\Override]
    public function prepend(ContainerBuilder $container): void
    {
        $bundles = $container->getParameter('kernel.bundles');

        if (!\is_array($bundles) || !isset($bundles['CoreBundle'])) {
            throw new \RuntimeException(\sprintf('"%s" bundle is required', CoreBundle::class));
        }

        $config = $this->resolveConfig($container);

        foreach ($config as $key => $parameter) {
            $container->setParameter(\sprintf('%s.%s', $this->getAlias(), $key), $parameter);
        }

        $container->prependExtensionConfig('framework', [
            'messenger' => [
                'transports' => [
                    'sync' => 'sync://',
                    'async_priority_high' => [
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
                'routing' => $this->buildRouting($config),
            ],
        ]);
    }

    /**
     * @param PushConfig $config
     *
     * @return array<string, string>
     */
    private function buildRouting(array $config): array
    {
        $routing = $config['routing'];

        if ($config['async']) {
            $routing[Update::class] = 'async_priority_high';
            $routing[EntityAsyncEvent::class] = 'async_priority_high';

            return $routing;
        }

        $routing[EntityAsyncEvent::class] = 'sync';

        return $routing;
    }

    /**
     * Normalises the processed configuration into a shape the rest of the class can
     * rely on: Symfony's config layer only guarantees an untyped array.
     *
     * @return PushConfig
     */
    private function resolveConfig(ContainerBuilder $container): array
    {
        $configs = $container->resolveEnvPlaceholders($container->getExtensionConfig($this->getAlias()), true);

        $config = $this->processConfiguration(new Configuration(), \is_array($configs) ? $configs : []);

        return [
            'async' => false !== ($config['async'] ?? true),
            'enabled' => false !== ($config['enabled'] ?? true),
            'transport_type' => self::asString($config['transport_type'] ?? null),
            'transport_method' => self::asString($config['transport_method'] ?? null),
            'routing' => self::asRouting($config['routing'] ?? null),
        ];
    }

    private static function asString(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }

    /**
     * @return array<string, string>
     */
    private static function asRouting(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $routing = [];

        foreach ($value as $message => $transport) {
            if (\is_string($message) && \is_string($transport)) {
                $routing[$message] = $transport;
            }
        }

        return $routing;
    }
}
