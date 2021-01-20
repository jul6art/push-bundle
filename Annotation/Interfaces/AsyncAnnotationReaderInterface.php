<?php

namespace Jul6Art\PushBundle\Annotation\Interfaces;

use Jul6Art\PushBundle\Annotation\Asyncable;

/**
 * Interface AsyncAnnotationReaderInterface
 */
interface AsyncAnnotationReaderInterface
{
    /**
     * @param Object $entity
     * @return Asyncable|null
     * @throws \ReflectionException
     */
    public function getAsyncAnnotation(Object $entity): ?Asyncable;

    /**
     * @param Object $entity
     * @param string $event
     * @return bool
     * @throws \ReflectionException
     */
    public function hasEvent(Object $entity, string $event): bool;

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function hasPostLoadEvent(Object $entity): bool;

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function hasPostPersistEvent(Object $entity): bool;

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function hasPostUpdateEvent(Object $entity): bool;

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function hasPreRemoveEvent(Object $entity): bool;

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function isAsyncable(Object $entity): bool;
}
