<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Attribute;

use Jul6Art\PushBundle\Attribute\Asyncable;
use Jul6Art\PushBundle\Tests\Fixtures\AsyncableEntity;
use Jul6Art\PushBundle\Tests\Fixtures\AsyncableEntityEvent;
use Jul6Art\PushBundle\Tests\Fixtures\PartiallyAsyncableEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Asyncable::class)]
final class AsyncableTest extends TestCase
{
    /**
     * It used to be a Doctrine annotation implementing Doctrine\ORM\Mapping\Annotation,
     * an interface removed in ORM 3.
     */
    public function testItIsANativeAttribute(): void
    {
        $attributes = new \ReflectionClass(Asyncable::class)->getAttributes(\Attribute::class);

        self::assertCount(1, $attributes);
        self::assertSame(\Attribute::TARGET_CLASS, $attributes[0]->newInstance()->flags);
    }

    public function testTheEventClassIsMandatory(): void
    {
        self::assertTrue(false === new \ReflectionMethod(Asyncable::class, '__construct')->getParameters()[0]->isDefaultValueAvailable());
    }

    public function testItDefaultsToTrackingEveryEvent(): void
    {
        self::assertSame([], new Asyncable(AsyncableEntityEvent::class)->getEvents());
    }

    public function testItKeepsTheEventClass(): void
    {
        self::assertSame(AsyncableEntityEvent::class, new Asyncable(AsyncableEntityEvent::class)->getEventClass());
    }

    public function testItKeepsTheConfiguredEvents(): void
    {
        $attribute = new Asyncable(eventClass: AsyncableEntityEvent::class, events: ['postLoad']);

        self::assertSame(['postLoad'], $attribute->getEvents());
    }

    public function testItIsReadableFromAnAnnotatedEntity(): void
    {
        $attributes = new \ReflectionClass(AsyncableEntity::class)->getAttributes(Asyncable::class);

        self::assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        self::assertSame(AsyncableEntityEvent::class, $attribute->getEventClass());
        self::assertSame([], $attribute->getEvents());
    }

    public function testTheEventListIsReadBack(): void
    {
        $attributes = new \ReflectionClass(PartiallyAsyncableEntity::class)->getAttributes(Asyncable::class);

        self::assertSame(['postPersist', 'postUpdate'], $attributes[0]->newInstance()->getEvents());
    }
}
