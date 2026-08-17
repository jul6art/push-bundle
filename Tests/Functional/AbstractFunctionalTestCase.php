<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Functional;

use Jul6Art\PushBundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\ErrorHandler\ErrorHandler;

abstract class AbstractFunctionalTestCase extends TestCase
{
    private ?TestKernel $kernel = null;

    #[\Override]
    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;

        self::restoreSymfonyExceptionHandler();

        parent::tearDown();
    }

    /**
     * Boots a kernel and returns its container.
     *
     * The build directory is keyed on the configuration so that two scenarios never
     * share a compiled container, while identical scenarios still reuse the cache.
     *
     * @param array<string, mixed> $pushConfig
     */
    final protected function boot(string $environment = 'test', array $pushConfig = [], bool $withCore = true): ContainerInterface
    {
        $uniqueId = substr(md5(serialize([$pushConfig, $withCore])), 0, 12);

        $this->kernel = new TestKernel($environment, $pushConfig, $withCore, $uniqueId);
        $this->kernel->boot();

        return $this->kernel->getContainer();
    }

    /**
     * FrameworkBundle::boot() calls ErrorHandler::register(), which leaves one
     * exception handler on the stack. Booting is our own side effect, so we pop it
     * back off instead of letting PHPUnit report leaked global state.
     */
    private static function restoreSymfonyExceptionHandler(): void
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        if (\is_array($handler) && $handler[0] instanceof ErrorHandler) {
            restore_exception_handler();
        }
    }
}
