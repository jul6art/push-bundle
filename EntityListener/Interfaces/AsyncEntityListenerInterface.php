<?php

namespace Jul6Art\PushBundle\EntityListener\Interfaces;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Jul6Art\CoreBundle\EntityListener\Interfaces\EntityListenerInterface;

/**
 * Interface AsyncEntityListenerInterface
 */
interface AsyncEntityListenerInterface extends EntityListenerInterface
{
    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function postLoad(Object $entity, LifecycleEventArgs $event): void;

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function postPersist(Object $entity, LifecycleEventArgs $event): void;

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function postUpdate(Object $entity, LifecycleEventArgs $event): void;

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function preRemove(Object $entity, LifecycleEventArgs $event): void;
}
