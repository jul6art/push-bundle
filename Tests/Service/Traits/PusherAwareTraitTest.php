<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Service\Traits;

use Jul6Art\PushBundle\Service\Pusher;
use Jul6Art\PushBundle\Service\Traits\PusherAwareTrait;
use Jul6Art\PushBundle\Tests\Fixtures\PusherAwareService;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Service\Attribute\Required;

#[CoversTrait(PusherAwareTrait::class)]
final class PusherAwareTraitTest extends TestCase
{
    /**
     * The "@required" annotation is gone, so the attribute is the only thing making
     * the container call this setter.
     */
    public function testTheSetterIsMarkedRequired(): void
    {
        $attributes = new \ReflectionMethod(PusherAwareService::class, 'setPusher')->getAttributes(Required::class);

        self::assertCount(1, $attributes);
    }

    public function testThePropertyIsTyped(): void
    {
        $type = new \ReflectionProperty(PusherAwareService::class, 'pusher')->getType();

        self::assertNotNull($type);
        self::assertSame(Pusher::class, (string) $type);
    }

    public function testTheSetterStoresThePusher(): void
    {
        $service = new PusherAwareService();
        $pusher = new Pusher(async: true, enabled: true);

        $service->setPusher($pusher);

        self::assertSame($pusher, $service->pusher());
    }

    public function testThePusherIsOnlyAvailableOnceTheSetterRan(): void
    {
        $service = new PusherAwareService();

        $this->expectException(\Error::class);
        $this->expectExceptionMessageIsOrContains('must not be accessed before initialization');

        $pusher = $service->pusher();

        self::fail(\sprintf('Reading the pusher before injection should fail, got "%s".', $pusher::class));
    }
}
