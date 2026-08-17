<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Attribute;

use Jul6Art\PushBundle\Attribute\Asyncable;
use Jul6Art\PushBundle\Attribute\AsyncAttributeReader;
use Jul6Art\PushBundle\Tests\Fixtures\AsyncableEntity;
use Jul6Art\PushBundle\Tests\Fixtures\AsyncableEntityEvent;
use Jul6Art\PushBundle\Tests\Fixtures\PartiallyAsyncableEntity;
use Jul6Art\PushBundle\Tests\Fixtures\PlainEntity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The reader used to wrap Doctrine's AnnotationReader, injected through the
 * "annotations.reader" service Symfony 7 no longer registers.
 */
#[CoversClass(AsyncAttributeReader::class)]
final class AsyncAttributeReaderTest extends TestCase
{
    private AsyncAttributeReader $reader;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new AsyncAttributeReader();
    }

    public function testItNeedsNoDependencyAtAll(): void
    {
        self::assertNull(new \ReflectionClass(AsyncAttributeReader::class)->getConstructor());
        self::assertFalse(method_exists(AsyncAttributeReader::class, 'setReader'));
    }

    public function testItReadsTheAttributeOfAnAsyncableEntity(): void
    {
        $attribute = $this->reader->getAsyncAttribute(new AsyncableEntity());

        self::assertInstanceOf(Asyncable::class, $attribute);
        self::assertSame(AsyncableEntityEvent::class, $attribute->getEventClass());
    }

    public function testAPlainEntityHasNoAttribute(): void
    {
        self::assertNull($this->reader->getAsyncAttribute(new PlainEntity()));
    }

    public function testIsAsyncable(): void
    {
        self::assertTrue($this->reader->isAsyncable(new AsyncableEntity()));
        self::assertFalse($this->reader->isAsyncable(new PlainEntity()));
    }

    /**
     * An empty event list means every event is tracked.
     */
    #[DataProvider('everyEvent')]
    public function testAnEntityWithoutAnEventListTracksEverything(string $method): void
    {
        self::assertTrue($this->reader->{$method}(new AsyncableEntity()));
    }

    #[DataProvider('everyEvent')]
    public function testAPlainEntityTracksNothing(string $method): void
    {
        self::assertFalse($this->reader->{$method}(new PlainEntity()));
    }

    public function testAnEntityWithAnEventListOnlyTracksThose(): void
    {
        $entity = new PartiallyAsyncableEntity();

        self::assertTrue($this->reader->hasPostPersistEvent($entity));
        self::assertTrue($this->reader->hasPostUpdateEvent($entity));
        self::assertFalse($this->reader->hasPostLoadEvent($entity));
        self::assertFalse($this->reader->hasPreRemoveEvent($entity));
    }

    public function testHasEventComparesStrictly(): void
    {
        $entity = new PartiallyAsyncableEntity();

        self::assertTrue($this->reader->hasEvent($entity, 'postPersist'));
        self::assertFalse($this->reader->hasEvent($entity, 'unknownEvent'));
        self::assertFalse($this->reader->hasEvent($entity, '0'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function everyEvent(): iterable
    {
        yield 'postLoad' => ['hasPostLoadEvent'];
        yield 'postPersist' => ['hasPostPersistEvent'];
        yield 'postUpdate' => ['hasPostUpdateEvent'];
        yield 'preRemove' => ['hasPreRemoveEvent'];
    }
}
