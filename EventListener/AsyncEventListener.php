<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\EventListener;

use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Jul6Art\CoreBundle\EventListener\AbstractEventListener;
use Jul6Art\PushBundle\Attribute\Traits\AsyncAttributeReaderAwareTrait;
use Jul6Art\PushBundle\Dispatcher\Traits\AsyncDispatcherAwareTrait;
use Jul6Art\PushBundle\EventListener\Interfaces\AsyncEventListenerInterface;
use Jul6Art\PushBundle\Factory\EntityAsyncEventFactory;

/**
 * Class AsyncEventListener.
 */
class AsyncEventListener extends AbstractEventListener implements AsyncEventListenerInterface
{
    use AsyncAttributeReaderAwareTrait;
    use AsyncDispatcherAwareTrait;

    #[\Override]
    public function postLoad(PostLoadEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->asyncAttributeReader->hasPostLoadEvent($entity)) {
            return;
        }

        $currentUserId = $this->getCurrentUserIdOrNull();

        // A viewed event without a viewer carries no information.
        if (null === $currentUserId) {
            return;
        }

        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityViewedMessage($entity, $currentUserId));
    }

    #[\Override]
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->asyncAttributeReader->hasPostPersistEvent($entity)) {
            return;
        }

        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityCreatedMessage($entity, $this->getCurrentUserIdOrNull()));
    }

    #[\Override]
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->asyncAttributeReader->hasPostUpdateEvent($entity)) {
            return;
        }

        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityEditedMessage($entity, $this->getCurrentUserIdOrNull()));
    }

    #[\Override]
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$this->asyncAttributeReader->hasPreRemoveEvent($entity)) {
            return;
        }

        $this->asyncDispatcher->dispatch(EntityAsyncEventFactory::createEntityDeletedMessage($entity, $this->getCurrentUserIdOrNull()));
    }
}
