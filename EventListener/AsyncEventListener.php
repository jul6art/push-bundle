<?php

namespace Jul6Art\PushBundle\EventListener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use Jul6Art\CoreBundle\EventListener\AbstractEventListener;
use Jul6Art\PushBundle\Annotation\Traits\AsyncAnnotationReaderAwareTrait;
use Jul6Art\PushBundle\Dispatcher\Traits\AsyncDispatcherAwareTrait;
use Jul6Art\PushBundle\EventListener\Interfaces\AsyncEventListenerInterface;
use Jul6Art\PushBundle\Factory\EntityAsyncEventFactory;
use Jul6Art\PushBundle\Service\Traits\MessageBusAwareTrait;

/**
 * Class AbstractAsyncEventListener
 */
class AsyncEventListener extends AbstractEventListener implements AsyncEventListenerInterface
{
    use AsyncAnnotationReaderAwareTrait;
    use AsyncDispatcherAwareTrait;

    /**
     * @param LifecycleEventArgs $args
     */
    public function postLoad(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->asyncAnnotationReader->hasPostLoadEvent($entity)) {
            return;
        }

        $currentUserId = $this->getCurrentUserIdOrNull();

        if (null !== $currentUserId) {
            $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityViewedMessage($entity, $currentUserId));
        }
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postPersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->asyncAnnotationReader->hasPostPersistEvent($entity)) {
            return;
        }

        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityCreatedMessage($entity, $this->getCurrentUserIdOrNull()));
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->asyncAnnotationReader->hasPostUpdateEvent($entity)) {
            return;
        }

        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityEditedMessage($entity, $this->getCurrentUserIdOrNull()));
    }

    /**
     * @param LifecycleEventArgs $args
     */
    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->asyncAnnotationReader->hasPreRemoveEvent($entity)) {
            return;
        }

        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityDeletedMessage($entity, $this->getCurrentUserIdOrNull()));
    }
}
