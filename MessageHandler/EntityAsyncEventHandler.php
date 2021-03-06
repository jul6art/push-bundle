<?php

namespace Jul6Art\PushBundle\MessageHandler;

use Jul6Art\CoreBundle\Service\Traits\EntityManagerAwareTrait;
use Jul6Art\CoreBundle\Service\Traits\EventDispatcherAwareTrait;
use Jul6Art\PushBundle\Annotation\Traits\AsyncAnnotationReaderAwareTrait;
use Jul6Art\PushBundle\Message\Constants\EntityAsyncEventType;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Class EntityAsyncEventHandler
 */
class EntityAsyncEventHandler implements MessageHandlerInterface
{
    use AsyncAnnotationReaderAwareTrait;
    use EntityManagerAwareTrait;
    use EventDispatcherAwareTrait;

    /**
     * @param EntityAsyncEvent $message
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function __invoke(EntityAsyncEvent $message)
    {
        $entity = $this->entityManager->getRepository($message->getEntityClass())->find($message->getEntityId());

        if (null === $entity) {
            return;
        }

        if (!$this->asyncAnnotationReader->isAsyncable($entity)) {
            return;
        }

        $eventClass = $this->asyncAnnotationReader->getAsyncAnnotation($entity)->getEventClass();

        $event = new $eventClass($entity);
        $event->getData()->set('createdById', $message->getCreatedById());

        switch ($message->getType()) {
            case EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED:
                if ($this->asyncAnnotationReader->hasPostPersistEvent($entity)) {
                    $this->eventDispatcher->dispatch($event, $eventClass::CREATED);
                }
                break;
            case EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED:
                if ($this->asyncAnnotationReader->hasPreRemoveEvent($entity)) {
                    $this->eventDispatcher->dispatch($event, $eventClass::DELETED);
                }
                break;
            case EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED:
                if ($this->asyncAnnotationReader->hasPostUpdateEvent($entity)) {
                    $this->eventDispatcher->dispatch($event, $eventClass::EDITED);
                }
                break;
            case EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_VIEWED:
            default:
                if ($this->asyncAnnotationReader->hasPostLoadEvent($entity)) {
                    $this->eventDispatcher->dispatch($event, $eventClass::VIEWED);
                }
        }
    }
}
