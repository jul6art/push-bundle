<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\MessageHandler;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Jul6Art\PushBundle\Attribute\AsyncAttributeReader;
use Jul6Art\PushBundle\Message\Constants\EntityAsyncEventType;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Jul6Art\PushBundle\MessageHandler\EntityAsyncEventHandler;
use Jul6Art\PushBundle\Tests\Fixtures\AsyncableEntity;
use Jul6Art\PushBundle\Tests\Fixtures\AsyncableEntityEvent;
use Jul6Art\PushBundle\Tests\Fixtures\PartiallyAsyncableEntity;
use Jul6Art\PushBundle\Tests\Fixtures\PlainEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(EntityAsyncEventHandler::class)]
final class EntityAsyncEventHandlerTest extends TestCase
{
    /**
     * MessageHandlerInterface was deprecated in Symfony 6.2 and removed in 7.0.
     */
    public function testItIsDeclaredThroughTheAttribute(): void
    {
        $reflection = new \ReflectionClass(EntityAsyncEventHandler::class);

        self::assertCount(1, $reflection->getAttributes(AsMessageHandler::class));
        self::assertSame([], $reflection->getInterfaceNames());
    }

    #[DataProvider('typesAndEventNames')]
    public function testItDispatchesTheMatchingEventName(string $type, string $expectedName): void
    {
        $entity = new AsyncableEntity(1);
        $dispatched = [];

        $handler = $this->handler($entity, $dispatched);
        $handler(new EntityAsyncEvent($type, AsyncableEntity::class, 1, 4));

        self::assertCount(1, $dispatched);
        self::assertSame($expectedName, $dispatched[0]['name']);

        $event = $dispatched[0]['event'];
        self::assertInstanceOf(AsyncableEntityEvent::class, $event);
        self::assertSame($entity, $event->getEntity());
        self::assertSame(4, $event->getData()->get('createdById'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function typesAndEventNames(): iterable
    {
        yield 'created' => [EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED, AsyncableEntityEvent::CREATED];
        yield 'deleted' => [EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED, AsyncableEntityEvent::DELETED];
        yield 'edited' => [EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED, AsyncableEntityEvent::EDITED];
        yield 'viewed' => [EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_VIEWED, AsyncableEntityEvent::VIEWED];
        yield 'unknown falls back to viewed' => ['something.else', AsyncableEntityEvent::VIEWED];
    }

    public function testItDoesNothingWhenTheEntityIsGone(): void
    {
        $dispatched = [];

        $handler = $this->handler(null, $dispatched);
        $handler(new EntityAsyncEvent(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED, AsyncableEntity::class, 1));

        self::assertSame([], $dispatched);
    }

    public function testItDoesNothingForANonAsyncableEntity(): void
    {
        $dispatched = [];

        $handler = $this->handler(new PlainEntity(), $dispatched);
        $handler(new EntityAsyncEvent(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED, PlainEntity::class, 3));

        self::assertSame([], $dispatched);
    }

    /**
     * The entity restricts its events to postPersist and postUpdate, so a delete must
     * not be dispatched.
     */
    public function testItHonoursTheEventList(): void
    {
        $dispatched = [];

        $handler = $this->handler(new PartiallyAsyncableEntity(), $dispatched);
        $handler(new EntityAsyncEvent(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED, PartiallyAsyncableEntity::class, 7));

        self::assertSame([], $dispatched);

        $handler(new EntityAsyncEvent(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED, PartiallyAsyncableEntity::class, 7));

        self::assertCount(1, $dispatched);
    }

    /**
     * @param list<array{event: Event, name: string}> $dispatched
     */
    private function handler(?object $entity, array &$dispatched): EntityAsyncEventHandler
    {
        $repository = self::createStub(EntityRepository::class);
        $repository->method('find')->willReturn($entity);

        $entityManager = self::createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        $eventDispatcher = self::createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')
            ->willReturnCallback(static function (object $event, ?string $name = null) use (&$dispatched): object {
                self::assertInstanceOf(Event::class, $event);
                self::assertIsString($name);

                $dispatched[] = ['event' => $event, 'name' => $name];

                return $event;
            });

        $handler = new EntityAsyncEventHandler();
        $handler->setAsyncAttributeReader(new AsyncAttributeReader());
        $handler->setEntityManager($entityManager);
        $handler->setEventDispatcher($eventDispatcher);

        return $handler;
    }
}
