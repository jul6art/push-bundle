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
    public static function create(...$param): Object
    {
        $args = func_get_args();
        $countArgs = func_num_args();

        if ($countArgs !== 4) {
            Throw new \RuntimeException('You need 4 arguments to create an EntityAsyncEvent: string $type, string $class, int $id and ?int $currentUserId');
        }

        return new EntityAsyncEvent($args[0], $args[1], $args[2], $args[3]);
    }

    /**
     * @param string $class
     * @param int $id
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityCreatedMessage(string $class, int $id, int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED, $class, $id, $currentUserId);
    }


    /**
     * @param string $class
     * @param int $id
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityDeletedMessage(string $class, int $id, int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED, $class, $id, $currentUserId);
    }


    /**
     * @param string $class
     * @param int $id
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityEditedMessage(string $class, int $id, int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED, $class, $id, $currentUserId);
    }


    /**
     * @param string $class
     * @param int $id
     * @param int $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityViewedMessage(string $class, int $id, int $currentUserId): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_VIEWED, $class, $id, $currentUserId);
    }
}
