<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests;

use Jul6Art\PushBundle\DependencyInjection\PushExtension;
use Jul6Art\PushBundle\PushBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PushBundle::class)]
final class PushBundleTest extends TestCase
{
    public function testItResolvesThePushExtensionByConvention(): void
    {
        $extension = new PushBundle()->getContainerExtension();

        self::assertInstanceOf(PushExtension::class, $extension);
        self::assertSame('push', $extension->getAlias());
    }

    public function testItsPathPointsAtTheBundleRoot(): void
    {
        $bundle = new PushBundle();

        self::assertSame('PushBundle', $bundle->getName());
        self::assertFileExists($bundle->getPath().'/Resources/config/services.yaml');
    }
}
