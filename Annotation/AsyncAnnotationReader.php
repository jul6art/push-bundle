<?php

namespace Jul6Art\PushBundle\Annotation;

use Doctrine\Common\Annotations\AnnotationReader;
use Jul6Art\PushBundle\Annotation\Interfaces\AsyncAnnotationReaderInterface;

/**
 * Class AsyncAnnotationReader
 */
class AsyncAnnotationReader implements AsyncAnnotationReaderInterface
{
    /**
     * @var AnnotationReader
     */
    private $reader;

    /**
     * @required
     * @param AnnotationReader $reader
     */
    public function setReader(AnnotationReader $reader): void
    {
        $this->reader = $reader;
    }

    /**
     * @param Object $entity
     * @return Asyncable|null
     * @throws \ReflectionException
     */
    public function getAsyncAnnotation(Object $entity): ?Asyncable
    {
        return $this->reader->getClassAnnotation(new \ReflectionClass(get_class($entity)), Asyncable::class);
    }

    /**
     * @param Object $entity
     * @param string $event
     * @return bool
     * @throws \ReflectionException
     */
    public function hasEvent(Object $entity, string $event): bool
    {
        if (!$this->isAsyncable($entity)) {
            return false;
        }

        $annotation = $this->getAsyncAnnotation($entity);

        $events = $annotation->getEvents();

        return !\count($events) or in_array($event, $events);
    }

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function hasPostLoadEvent(Object $entity): bool
    {
        return $this->hasEvent($entity, 'postLoad');
    }

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function hasPostPersistEvent(Object $entity): bool
    {
        return $this->hasEvent($entity, 'postPersist');
    }

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function hasPostUpdateEvent(Object $entity): bool
    {
        return $this->hasEvent($entity, 'postUpdate');
    }

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function hasPreRemoveEvent(Object $entity): bool
    {
        return $this->hasEvent($entity, 'preRemove');
    }

    /**
     * @param Object $entity
     * @return bool
     * @throws \ReflectionException
     */
    public function isAsyncable(Object $entity): bool
    {
        return $this->getAsyncAnnotation($entity) instanceof Asyncable;
    }
}
