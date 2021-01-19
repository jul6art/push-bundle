<?php

namespace Jul6Art\PushBundle\EntityListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Jul6Art\CoreBundle\EntityListener\AbstractEntityListener;
use Jul6Art\PushBundle\EntityListener\Interfaces\AsyncEntityListenerInterface;
use Jul6Art\PushBundle\Factory\AsyncFactory;
use Jul6Art\PushBundle\Service\Traits\MessageBusAwareTrait;

/**
 * Class AbstractAsyncEntityListener
 */
abstract class AbstractAsyncEntityListener extends AbstractEntityListener implements AsyncEntityListenerInterface
{
    use MessageBusAwareTrait;

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function postLoad(Object $entity, LifecycleEventArgs $event): void
    {
        $currentUserId = $this->getCurrentUserIdOrNull();

        if (null !== $currentUserId) {
            $this->bus->dispatch(AsyncFactory::createEntityViewedMessage(
                get_class($entity),
                $entity->getId(),
                $currentUserId
            ));
        }
    }

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function postPersist(Object $entity, LifecycleEventArgs $event): void
    {
        $this->bus->dispatch(AsyncFactory::createEntityCreatedMessage(
            get_class($entity),
            $entity->getId(),
            $this->getCurrentUserIdOrNull()
        ));
    }

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function postUpdate(Object $entity, LifecycleEventArgs $event): void
    {
        $this->bus->dispatch(AsyncFactory::createEntityEditedMessage(
            get_class($entity),
            $entity->getId(),
            $this->getCurrentUserIdOrNull()
        ));
    }

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function preRemove(Object $entity, LifecycleEventArgs $event): void
    {
        $this->bus->dispatch(AsyncFactory::createEntityDeletedMessage(
            get_class($entity),
            $entity->getId(),
            $this->getCurrentUserIdOrNull()
        ));
    }
}
