<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Attribute;

use Jul6Art\PushBundle\Attribute\Interfaces\AsyncAttributeReaderInterface;

/**
 * Reads the Asyncable attribute straight off the entity class.
 *
 * It used to wrap Doctrine's AnnotationReader, injected through the
 * "annotations.reader" service that Symfony 7 no longer registers. Attributes need
 * no reader at all, only reflection.
 */
class AsyncAttributeReader implements AsyncAttributeReaderInterface
{
    private const string POST_LOAD = 'postLoad';
    private const string POST_PERSIST = 'postPersist';
    private const string POST_UPDATE = 'postUpdate';
    private const string PRE_REMOVE = 'preRemove';

    #[\Override]
    public function getAsyncAttribute(object $entity): ?Asyncable
    {
        $attributes = new \ReflectionClass($entity)->getAttributes(Asyncable::class);

        return [] === $attributes ? null : $attributes[0]->newInstance();
    }

    #[\Override]
    public function hasEvent(object $entity, string $event): bool
    {
        $attribute = $this->getAsyncAttribute($entity);

        if (!$attribute instanceof Asyncable) {
            return false;
        }

        $events = $attribute->getEvents();

        return [] === $events || \in_array($event, $events, true);
    }

    #[\Override]
    public function hasPostLoadEvent(object $entity): bool
    {
        return $this->hasEvent($entity, self::POST_LOAD);
    }

    #[\Override]
    public function hasPostPersistEvent(object $entity): bool
    {
        return $this->hasEvent($entity, self::POST_PERSIST);
    }

    #[\Override]
    public function hasPostUpdateEvent(object $entity): bool
    {
        return $this->hasEvent($entity, self::POST_UPDATE);
    }

    #[\Override]
    public function hasPreRemoveEvent(object $entity): bool
    {
        return $this->hasEvent($entity, self::PRE_REMOVE);
    }

    #[\Override]
    public function isAsyncable(object $entity): bool
    {
        return $this->getAsyncAttribute($entity) instanceof Asyncable;
    }
}
