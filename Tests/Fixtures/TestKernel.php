<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\PushBundle\Attribute\AsyncAttributeReader;
use Jul6Art\PushBundle\Dispatcher\AsyncDispatcher;
use Jul6Art\PushBundle\EventListener\AsyncEventListener;
use Jul6Art\PushBundle\MessageHandler\EntityAsyncEventHandler;
use Jul6Art\PushBundle\PushBundle;
use Jul6Art\PushBundle\Service\Pusher;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * Minimal application kernel used by the functional tests.
 */
final class TestKernel extends Kernel
{
    /**
     * @param array<string, mixed> $pushConfig configuration for the "push" extension
     * @param bool                 $withCore   PushExtension requires CoreBundle, so the
     *                                         tests need to be able to leave it out
     */
    public function __construct(
        string $environment,
        private readonly array $pushConfig = [],
        private readonly bool $withCore = true,
        private readonly string $uniqueId = 'default',
        private readonly bool $withHub = true,
    ) {
        // Debug mode installs Symfony's error handler and never removes it, which
        // PHPUnit rightly reports as leaking global state.
        parent::__construct($environment, false);
    }

    /**
     * @return iterable<BundleInterface>
     */
    #[\Override]
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        if ($this->withHub) {
            yield new MercureBundle();
        }

        if ($this->withCore) {
            yield new CoreBundle();
        }

        yield new PushBundle();
    }

    #[\Override]
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load($this->configure(...));
    }

    #[\Override]
    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    #[\Override]
    public function getCacheDir(): string
    {
        return $this->buildDir().'/cache';
    }

    #[\Override]
    public function getLogDir(): string
    {
        return $this->buildDir().'/log';
    }

    #[\Override]
    protected function build(ContainerBuilder $container): void
    {
        // The bundle's services are private; the tests need to reach them to assert on
        // what Resources/config/services.yaml actually produced.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            #[\Override]
            public function process(ContainerBuilder $container): void
            {
                $exposed = [
                    AsyncAttributeReader::class,
                    AsyncDispatcher::class,
                    AsyncEventListener::class,
                    EntityAsyncEventHandler::class,
                    Pusher::class,
                ];

                foreach ($exposed as $id) {
                    if ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    } elseif ($container->hasAlias($id)) {
                        $container->getAlias($id)->setPublic(true);
                    }
                }

                // The three transports the bundle prepends are private and would be
                // pruned, hiding whether the framework configuration took effect. Only
                // these are exposed: publishing the whole messenger.transport.* set
                // drags in serializers this kernel does not configure.
                foreach (['sync', 'async_priority_high', 'async_priority_low'] as $transport) {
                    $id = 'messenger.transport.'.$transport;

                    if ($container->hasDefinition($id)) {
                        $container->getDefinition($id)->setPublic(true);
                    }
                }
            }
        }, PassConfig::TYPE_BEFORE_REMOVING, 100);
    }

    private function buildDir(): string
    {
        return \sprintf('%s/jul6art-push-bundle-tests/%s/%s', sys_get_temp_dir(), $this->uniqueId, $this->environment);
    }

    private function configure(ContainerBuilder $container): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'push-bundle-tests',
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'translator' => ['default_path' => '%kernel.project_dir%/translations'],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $container->loadFromExtension('security', [
            'providers' => ['in_memory' => ['memory' => null]],
            'firewalls' => ['main' => ['security' => false]],
        ]);

        $container->loadFromExtension('doctrine', [
            'dbal' => ['driver' => 'pdo_sqlite', 'memory' => true],
            'orm' => [
                'controller_resolver' => ['auto_mapping' => false],
                'mappings' => [
                    'PushBundleTests' => [
                        'type' => 'attribute',
                        'dir' => __DIR__.'/Entity',
                        'prefix' => 'Jul6Art\\PushBundle\\Tests\\Fixtures\\Entity',
                        'is_bundle' => false,
                    ],
                ],
            ],
        ]);

        if ($this->withHub) {
            $this->configureMercure($container);
        }

        $container->loadFromExtension('push', $this->pushConfig);
    }

    /**
     * Une application peut installer ce bundle pour son côté Messenger et n'avoir aucun hub :
     * c'est le cas que `MercureHubPass` doit traiter en retirant tout le temps réel.
     */
    private function configureMercure(ContainerBuilder $container): void
    {
        $container->loadFromExtension('mercure', [
            'hubs' => [
                'default' => [
                    'url' => 'https://example.com/.well-known/mercure',
                    'jwt' => ['value' => 'jwt-token'],
                ],
            ],
        ]);
    }
}
