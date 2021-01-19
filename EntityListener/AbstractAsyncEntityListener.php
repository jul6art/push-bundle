<?php

namespace Jul6Art\PushBundle\EntityListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Jul6Art\CoreBundle\EntityListener\AbstractEntityListener;
use Jul6Art\PushBundle\Dispatcher\Traits\AsyncDispatcherAwareTrait;
use Jul6Art\PushBundle\EntityListener\Interfaces\AsyncEntityListenerInterface;
use Jul6Art\PushBundle\Factory\EntityAsyncEventFactory;
use Jul6Art\PushBundle\Service\Traits\MessageBusAwareTrait;

/**
 * Class AbstractAsyncEntityListener
 */
abstract class AbstractAsyncEntityListener extends AbstractEntityListener implements AsyncEntityListenerInterface
{
    use AsyncDispatcherAwareTrait;

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function postLoad(Object $entity, LifecycleEventArgs $event): void
    {
        $currentUserId = $this->getCurrentUserIdOrNull();

        if (null !== $currentUserId) {
            $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityViewedMessage($entity, $currentUserId));
        }
    }

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function postPersist(Object $entity, LifecycleEventArgs $event): void
    {
        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityCreatedMessage($entity, $this->getCurrentUserIdOrNull()));
    }

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function postUpdate(Object $entity, LifecycleEventArgs $event): void
    {
        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityEditedMessage($entity, $this->getCurrentUserIdOrNull()));
    }

    /**
     * @param Object $entity
     * @param LifecycleEventArgs $event
     */
    public function preRemove(Object $entity, LifecycleEventArgs $event): void
    {
        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityDeletedMessage($entity, $this->getCurrentUserIdOrNull()));
    }
}
