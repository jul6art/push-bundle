<?php

namespace Jul6Art\PushBundle\Factory;

use Jul6Art\PushBundle\Factory\Interfaces\AsyncFactoryInterface;
use Jul6Art\PushBundle\Message\Constants\EntityAsyncEventType;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Class AsyncFactory
 */
final class AsyncFactory implements AsyncFactoryInterface
{
    /**
     * @inheritDoc
     */
    public static function create(...$args): Object
    {
        if (\count($args) !== 4) {
            Throw new \InvalidArgumentException('You need 4 arguments to create an EntityAsyncEvent: string $type, string $entityClass, int $entityId and ?int $currentUserId');
        }

        return new EntityAsyncEvent($args[0], $args[1], $args[2], $args[3]);
    }

    /**
     * @param string $entityClass
     * @param int $entityId
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityCreatedMessage(string $entityClass, int $entityId, int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED, $class, $id, $currentUserId);
    }


    /**
     * @param string $entityClass
     * @param int $entityId
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityDeletedMessage(string $entityClass, int $entityId, int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED, $class, $id, $currentUserId);
    }


    /**
     * @param string $entityClass
     * @param int $entityId
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityEditedMessage(string $entityClass, int $entityId, int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED, $class, $id, $currentUserId);
    }


    /**
     * @param string $entityClass
     * @param int $entityId
     * @param int $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityViewedMessage(string $entityClass, int $entityId, int $currentUserId): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_VIEWED, $class, $id, $currentUserId);
    }
}
