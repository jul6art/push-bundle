<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Service;

use Jul6Art\PushBundle\Service\Pusher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Pusher used to publish through Mercure's PublisherInterface::__invoke(), deprecated
 * since symfony/mercure 0.5 in favour of HubInterface::publish().
 */
#[CoversClass(Pusher::class)]
final class PusherTest extends TestCase
{
    public function testItPublishesOnTheHubWhenSynchronous(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                self::assertSame(['/topic'], $update->getTopics());
                self::assertSame('{"a":1}', $update->getData());

                return true;
            }))
            ->willReturn('id');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->pusher(async: false, enabled: true, hub: $hub, bus: $bus)->push('/topic', ['a' => 1]);
    }

    public function testItDispatchesOnTheBusWhenAsynchronous(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(Update::class))
            ->willReturn(new Envelope(new \stdClass()));

        $this->pusher(async: true, enabled: true, hub: $hub, bus: $bus)->push('/topic', ['a' => 1]);
    }

    public function testItDoesNothingWhenDisabled(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $this->pusher(async: false, enabled: false, hub: $hub, bus: $bus)->push('/topic', ['a' => 1]);
    }

    public function testItAcceptsAnEmptyPayload(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                self::assertSame('[]', $update->getData());

                return true;
            }))
            ->willReturn('id');

        $this->pusher(async: false, enabled: true, hub: $hub)->push('/topic');
    }

    /**
     * The payload may be any iterable, including a generator.
     */
    public function testItEncodesATraversablePayload(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())
            ->method('publish')
            ->with(self::callback(static function (Update $update): bool {
                self::assertSame('{"a":1,"b":2}', $update->getData());

                return true;
            }))
            ->willReturn('id');

        $generator = (static function (): \Generator {
            yield 'a' => 1;
            yield 'b' => 2;
        })();

        $this->pusher(async: false, enabled: true, hub: $hub)->push('/topic', $generator);
    }

    /**
     * json_encode() used to be able to return false, silently producing an Update with
     * an empty body.
     */
    public function testItFailsLoudlyOnAnUnencodablePayload(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::never())->method('publish');

        $this->expectException(\JsonException::class);

        $this->pusher(async: false, enabled: true, hub: $hub)->push('/topic', ['bad' => \NAN]);
    }

    public function testTheSettersAreMarkedRequired(): void
    {
        foreach (['setHub', 'setBus'] as $method) {
            self::assertCount(1, new \ReflectionMethod(Pusher::class, $method)->getAttributes(Required::class), $method);
        }
    }

    private function pusher(bool $async, bool $enabled, HubInterface $hub, ?MessageBusInterface $bus = null): Pusher
    {
        $pusher = new Pusher($async, $enabled);
        $pusher->setHub($hub);
        $pusher->setBus($bus ?? self::createStub(MessageBusInterface::class));

        return $pusher;
    }
}
