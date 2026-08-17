<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Factory;

use Jul6Art\PushBundle\Factory\Interfaces\EntityAsyncEventFactoryInterface;
use Jul6Art\PushBundle\Message\Constants\EntityAsyncEventType;
use Jul6Art\PushBundle\Message\EntityAsyncEvent;
use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Class EntityAsyncEventFactory.
 */
final class EntityAsyncEventFactory implements EntityAsyncEventFactoryInterface
{
    /**
     * @throws \InvalidArgumentException if the arguments do not match the contract
     */
    #[\Override]
    public static function create(mixed ...$args): EntityAsyncEventInterface
    {
        if (3 !== \count($args)) {
            throw new \InvalidArgumentException('You need 3 arguments to create an EntityAsyncEvent: string $type, object $entity and ?int $currentUserId');
        }

        [$type, $entity, $currentUserId] = array_values($args);

        if (!\is_string($type)) {
            throw new \InvalidArgumentException(\sprintf('The first argument must be a string, "%s" given.', get_debug_type($type)));
        }

        if (!\is_object($entity)) {
            throw new \InvalidArgumentException(\sprintf('The second argument must be an object, "%s" given.', get_debug_type($entity)));
        }

        if (null !== $currentUserId && !\is_int($currentUserId)) {
            throw new \InvalidArgumentException(\sprintf('The third argument must be an int or null, "%s" given.', get_debug_type($currentUserId)));
        }

        return self::build($type, $entity, $currentUserId);
    }

    #[\Override]
    public static function createEntityCreatedMessage(object $entity, ?int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::build(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_CREATED, $entity, $currentUserId);
    }

    #[\Override]
    public static function createEntityDeletedMessage(object $entity, ?int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::build(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_DELETED, $entity, $currentUserId);
    }

    #[\Override]
    public static function createEntityEditedMessage(object $entity, ?int $currentUserId = null): EntityAsyncEventInterface
    {
        return self::build(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_EDITED, $entity, $currentUserId);
    }

    #[\Override]
    public static function createEntityViewedMessage(object $entity, int $currentUserId): EntityAsyncEventInterface
    {
        return self::build(EntityAsyncEventType::ENTITY_ASYNC_EVENT_TYPE_VIEWED, $entity, $currentUserId);
    }

    private static function build(string $type, object $entity, ?int $currentUserId): EntityAsyncEventInterface
    {
        return new EntityAsyncEvent($type, $entity::class, self::resolveEntityId($entity), $currentUserId);
    }

    /**
     * The message only carries the identifier, so the entity has to expose one.
     *
     * @throws \InvalidArgumentException if the entity has no usable identifier
     */
    private static function resolveEntityId(object $entity): int
    {
        if (!method_exists($entity, 'getId')) {
            throw new \InvalidArgumentException(\sprintf('Entity "%s" must expose a getId() method to be dispatched asynchronously.', $entity::class));
        }

        $id = $entity->getId();

        if (!is_numeric($id)) {
            throw new \InvalidArgumentException(\sprintf('Entity "%s" has no usable identifier yet.', $entity::class));
        }

        return (int) $id;
    }
}
