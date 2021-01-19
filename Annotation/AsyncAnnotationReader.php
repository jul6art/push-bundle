<?php
namespace Jul6Art\PushBundle\Annotation;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Annotation;

/**
 * Class AsyncAnnotationReader
 */
class AsyncAnnotationReader
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
     * @return Annotation|null
     * @throws \ReflectionException
     */
    public function getAsyncAnnotation(Object $entity): ?Annotation
    {
        return $this->reader->getClassAnnotation(new \ReflectionClass(get_class($entity)), Asyncable::class);
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
