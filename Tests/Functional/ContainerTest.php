<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Functional;

use Jul6Art\PushBundle\Attribute\AsyncAttributeReader;
use Jul6Art\PushBundle\Dispatcher\AsyncDispatcher;
use Jul6Art\PushBundle\EventListener\AsyncEventListener;
use Jul6Art\PushBundle\MessageHandler\EntityAsyncEventHandler;
use Jul6Art\PushBundle\Service\Pusher;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Boots a real kernel with CoreBundle, MercureBundle and PushBundle together.
 */
#[CoversNothing]
final class ContainerTest extends AbstractFunctionalTestCase
{
    /**
     * The container used to reference "annotations.reader", gone in Symfony 7, and the
     * deprecated Mercure PublisherInterface alias.
     */
    public function testEveryBundleServiceIsInstantiable(): void
    {
        $container = $this->boot();

        self::assertInstanceOf(AsyncAttributeReader::class, $container->get(AsyncAttributeReader::class));
        self::assertInstanceOf(AsyncDispatcher::class, $container->get(AsyncDispatcher::class));
        self::assertInstanceOf(AsyncEventListener::class, $container->get(AsyncEventListener::class));
        self::assertInstanceOf(EntityAsyncEventHandler::class, $container->get(EntityAsyncEventHandler::class));
        self::assertInstanceOf(Pusher::class, $container->get(Pusher::class));
    }

    /**
     * AsyncEventListener inherits its token storage from CoreBundle's abstract parent
     * definition, so a broken parent would surface right here.
     */
    public function testTheEventListenerInheritsTheCoreBundleParent(): void
    {
        $listener = $this->boot()->get(AsyncEventListener::class);

        self::assertInstanceOf(AsyncEventListener::class, $listener);
        self::assertNull($listener->getCurrentUserOrNull());
    }

    public function testTheConfigurationIsExposedAsContainerParameters(): void
    {
        $container = $this->boot();

        self::assertTrue($container->getParameter('push.async'));
        self::assertTrue($container->getParameter('push.enabled'));
        self::assertSame('database', $container->getParameter('push.transport_type'));
        self::assertSame('doctrine://default', $container->getParameter('push.transport_method'));
    }

    public function testTheAsyncFlagReachesTheContainer(): void
    {
        self::assertFalse($this->boot('test', ['async' => false])->getParameter('push.async'));
    }

    public function testTheTransportsArePrepended(): void
    {
        $container = $this->boot();

        foreach (['sync', 'async_priority_high', 'async_priority_low'] as $transport) {
            self::assertTrue($container->has('messenger.transport.'.$transport), $transport);
        }
    }

    public function testCoreBundleParametersAreStillExposed(): void
    {
        self::assertFalse($this->boot()->getParameter('core.email_debug'));
    }

    public function testBootingWithoutCoreBundleIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('bundle is required');

        $this->boot('test', [], withCore: false);
    }
}
