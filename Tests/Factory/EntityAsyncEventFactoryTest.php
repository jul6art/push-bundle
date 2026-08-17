<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Factory;

use Jul6Art\PushBundle\Factory\EntityAsyncEventFactory;
use Jul6Art\PushBundle\Message\Constants\EntityAsyncEventType;
use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;
use Jul6Art\PushBundle\Tests\Fixtures\AsyncableEntity;
use Jul6Art\PushBundle\Tests\Fixtures\IdlessEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EntityAsyncEventFactory::class)]
final class EntityAsyncEventFactoryTest extends TestCase
{
    /**
     * @param \Closure(object, int): EntityAsyncEventInterface $factory
     */
    #[DataProvider('namedFactories')]
    public function testTheNamedFactoriesCarryTheRightType(\Closure $factory, string $expectedType): void
    {
        $event = $factory(new AsyncableEntity(42), 7);

        self::assertSame($expectedType, $event->getType());
        self::assertSame(AsyncableEntity::class, $event->getEntityClass());
        self::assertSame(42, $event->getEntityId());
        self::assertSame(7, $event->getCreatedById());
    }

    /**
     * @return iterable<string, array{\Closure(object, int): EntityAsyncEventInterface, string}>
     */
    public static function namedFactories(): iterable
    {
        yield 'created' => [EntityAsyncEventFactory::createEntityCreatedMessage(...), EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED];
        yield 'deleted' => [EntityAsyncEventFactory::createEntityDeletedMessage(...), EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED];
        yield 'edited' => [EntityAsyncEventFactory::createEntityEditedMessage(...), EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED];
        yield 'viewed' => [EntityAsyncEventFactory::createEntityViewedMessage(...), EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_VIEWED];
    }

    public function testTheCurrentUserIsOptional(): void
    {
        self::assertNull(EntityAsyncEventFactory::createEntityCreatedMessage(new AsyncableEntity(1))->getCreatedById());
    }

    public function testCreateAcceptsTheThreeArgumentContract(): void
    {
        $event = EntityAsyncEventFactory::create(
            EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED,
            new AsyncableEntity(5),
            9,
        );

        self::assertSame(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED, $event->getType());
        self::assertSame(5, $event->getEntityId());
        self::assertSame(9, $event->getCreatedById());
    }

    public function testCreateRejectsTheWrongArgumentCount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('You need 3 arguments');

        EntityAsyncEventFactory::create('type', new AsyncableEntity(1));
    }

    public function testCreateRejectsANonStringType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('first argument must be a string');

        EntityAsyncEventFactory::create(42, new AsyncableEntity(1), null);
    }

    public function testCreateRejectsANonObjectEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('second argument must be an object');

        EntityAsyncEventFactory::create('type', 'not-an-entity', null);
    }

    public function testCreateRejectsANonIntUserId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('third argument must be an int or null');

        EntityAsyncEventFactory::create('type', new AsyncableEntity(1), 'nope');
    }

    /**
     * The old factory called getId() blindly on the entity.
     */
    public function testItRejectsAnEntityWithoutGetId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('must expose a getId() method');

        EntityAsyncEventFactory::createEntityCreatedMessage(new IdlessEntity());
    }

    public function testItRejectsAnUnpersistedEntity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('has no usable identifier');

        EntityAsyncEventFactory::createEntityCreatedMessage(new AsyncableEntity(null));
    }
}
