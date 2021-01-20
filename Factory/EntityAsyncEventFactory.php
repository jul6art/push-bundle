<?php

namespace Jul6Art\PushBundle\Factory;

use Jul6Art\PushBundle\Factory\Interfaces\EntityAsyncEventFactoryInterface;
use Jul6Art\PushBundle\Message\Constants\EntityAsyncEventType;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Class AsyncFactory
 */
final class EntityAsyncEventFactory implements EntityAsyncEventFactoryInterface
{
    /**
     * @inheritDoc
     */
    public static function create(...$args): Object
    {
        if (\count($args) !== 3) {
            Throw new \InvalidArgumentException('You need 3 arguments to create an EntityAsyncEvent: string $type, Object $entity and ?int $currentUserId');
        }

        $entityClass = get_class($args[1]);
        $entityId = $args[1]->getId();

        return new EntityAsyncEvent($args[0], $entityClass, $entityId, $args[2]);
    }

    /**
     * @param Object $entity
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityCreatedMessage(Object $entity, int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED, $entity, $currentUserId);
    }

    /**
     * @param Object $entity
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityDeletedMessage(Object $entity, int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED, $entity, $currentUserId);
    }

    /**
     * @param Object $entity
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityEditedMessage(Object $entity, int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED, $entity, $currentUserId);
    }

    /**
     * @param Object $entity
     * @param int $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityViewedMessage(Object $entity, int $currentUserId): EntityAsyncEventInterface
    {
        return self::create(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_VIEWED, $entity, $currentUserId);
    }
}
