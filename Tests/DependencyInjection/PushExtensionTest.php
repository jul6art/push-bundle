<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\DependencyInjection;

use Jul6Art\CoreBundle\CoreBundle;
use Jul6Art\PushBundle\Attribute\AsyncAttributeReader;
use Jul6Art\PushBundle\DependencyInjection\PushExtension;
use Jul6Art\PushBundle\Dispatcher\AsyncDispatcher;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Jul6Art\PushBundle\MessageHandler\EntityAsyncEventHandler;
use Jul6Art\PushBundle\Service\Pusher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[CoversClass(PushExtension::class)]
final class PushExtensionTest extends TestCase
{
    public function testLoadRegistersEveryService(): void
    {
        $container = $this->load();

        foreach ([AsyncAttributeReader::class, AsyncDispatcher::class, EntityAsyncEventHandler::class, Pusher::class] as $id) {
            self::assertTrue($container->hasDefinition($id), $id);
        }
    }

    /**
     * The reader was injected with "@annotations.reader", a service Symfony 7 no longer
     * registers; attributes need no reader at all.
     */
    public function testTheReaderNoLongerNeedsAnAnnotationReader(): void
    {
        self::assertSame([], $this->load()->getDefinition(AsyncAttributeReader::class)->getMethodCalls());
    }

    /**
     * Mercure deprecated PublisherInterface and its __invoke() contract in 0.5.
     */
    public function testThePusherIsWiredOnTheMercureHub(): void
    {
        $calls = [];
        foreach ($this->load()->getDefinition(Pusher::class)->getMethodCalls() as $call) {
            self::assertIsArray($call);
            self::assertIsString($call[0]);
            self::assertIsArray($call[1]);

            self::assertInstanceOf(Reference::class, $call[1][0]);

            $calls[$call[0]] = (string) $call[1][0];
        }

        self::assertSame(HubInterface::class, $calls['setHub']);
        self::assertArrayNotHasKey('setPublisher', $calls);
    }

    public function testEveryRegisteredClassExists(): void
    {
        foreach ($this->load()->getDefinitions() as $id => $definition) {
            if ('service_container' === $id) {
                continue;
            }

            $class = $definition->getClass() ?? $id;
            self::assertTrue(class_exists($class) || interface_exists($class), \sprintf('Service "%s" points at missing class "%s".', $id, $class));
        }
    }

    public function testPrependExposesTheConfigurationAsParameters(): void
    {
        $container = $this->prepend([]);

        self::assertTrue($container->getParameter('push.async'));
        self::assertTrue($container->getParameter('push.enabled'));
        self::assertSame('database', $container->getParameter('push.transport_type'));
        self::assertSame('doctrine://default', $container->getParameter('push.transport_method'));
    }

    public function testPrependRequiresCoreBundle(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains(CoreBundle::class);

        $this->prepend([], withCore: false);
    }

    public function testAsyncRoutesBothTheUpdateAndTheEntityEvent(): void
    {
        $routing = $this->routing($this->prepend([]));

        self::assertSame('async_priority_high', $routing[Update::class]);
        self::assertSame('async_priority_high', $routing[EntityAsyncEvent::class]);
    }

    public function testSyncOnlyRoutesTheEntityEvent(): void
    {
        $routing = $this->routing($this->prepend(['async' => false]));

        self::assertSame('sync', $routing[EntityAsyncEvent::class]);
        self::assertArrayNotHasKey(Update::class, $routing);
    }

    public function testExtraRoutingIsPreserved(): void
    {
        $routing = $this->routing($this->prepend(['routing' => ['App\Message\Custom' => 'async_priority_low']]));

        self::assertSame('async_priority_low', $routing['App\Message\Custom']);
        self::assertSame('async_priority_high', $routing[EntityAsyncEvent::class]);
    }

    public function testTheTransportsUseTheConfiguredDsn(): void
    {
        $transports = $this->messenger($this->prepend(['transport_method' => 'amqp://guest:guest@localhost']))['transports'] ?? null;

        self::assertSame([
            'sync' => 'sync://',
            'async_priority_high' => [
                'dsn' => 'amqp://guest:guest@localhost',
                'options' => ['queue_name' => 'high'],
            ],
            'async_priority_low' => [
                'dsn' => 'amqp://guest:guest@localhost',
                'options' => ['queue_name' => 'low'],
            ],
        ], $transports);
    }

    private function containerBuilder(bool $withCore = true): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.bundles' => $withCore ? ['CoreBundle' => CoreBundle::class] : [],
            'kernel.environment' => 'test',
        ]));
    }

    private function load(): ContainerBuilder
    {
        $container = $this->containerBuilder();
        new PushExtension()->load([], $container);

        return $container;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function prepend(array $config, bool $withCore = true): ContainerBuilder
    {
        $container = $this->containerBuilder($withCore);
        $extension = new PushExtension();
        $container->registerExtension($extension);
        $container->loadFromExtension('push', $config);

        $extension->prepend($container);

        return $container;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function messenger(ContainerBuilder $container): array
    {
        foreach ($container->getExtensionConfig('framework') as $frameworkConfig) {
            if (isset($frameworkConfig['messenger']) && \is_array($frameworkConfig['messenger'])) {
                return $frameworkConfig['messenger'];
            }
        }

        self::fail('No messenger configuration was prepended.');
    }

    /**
     * @return array<string, string>
     */
    private function routing(ContainerBuilder $container): array
    {
        $routing = $this->messenger($container)['routing'] ?? [];
        self::assertIsArray($routing);

        $flattened = [];
        foreach ($routing as $message => $transport) {
            self::assertIsString($message);
            self::assertIsString($transport);

            $flattened[$message] = $transport;
        }

        return $flattened;
    }
}
