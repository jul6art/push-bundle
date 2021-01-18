<?php

namespace Jul6Art\PushBundle\MessageHandler;

use App\Entity\Category;
use App\Entity\Member;
use Jul6Art\PushBundle\Message\Constants\EntityAsyncEventType;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use App\Handler\Traits\NotificationHandlerAwareTrait;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\Messenger\Handler\MessageHandlerInterface;

/**
 * Class EntityAsyncEventHandler
 */
class EntityAsyncEventHandler implements MessageHandlerInterface
{
    use NotificationHandlerAwareTrait;

    /**
     * @param EntityAsyncEvent $message
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function __invoke(EntityAsyncEvent $message)
    {
        $class = $message->getClass();

        if (\in_array($class, [Category::class, Member::class])) {
            switch ($message->getType()) {
                case EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED:
                    $this->notificationHandler->notifyEntityCreated(
                        $class,
                        $message->getId(),
                        $message->getCreatedById()
                    );
                    break;
                case EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED:
                    $this->notificationHandler->notifyEntityDeleted(
                        $class,
                        $message->getId(),
                        $message->getCreatedById()
                    );
                    break;
                case EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED:
                    break;
                case EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_VIEWED:
                default:
                    $this->notificationHandler->notifyEntityViewed(
                        $class,
                        $message->getId(),
                        $message->getCreatedById()
                    );
            }
        }
    }
}
