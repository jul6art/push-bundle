<?php

namespace Jul6Art\PushBundle\EventListener\Interfaces;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Jul6Art\CoreBundle\EventListener\Interfaces\EventListenerInterface;

/**
 * Interface AsyncEntityListenerInterface
 */
interface AsyncEventListenerInterface extends EventListenerInterface
{
    /**
     * @param LifecycleEventArgs $args
     */
    public function postLoad(LifecycleEventArgs $args): void;

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args): void;

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args): void;

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args): void;
}
