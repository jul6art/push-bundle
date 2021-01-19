<?php


namespace Jul6Art\PushBundle\Factory\Interfaces;

use Jul6Art\CoreBundle\Factory\Interfaces\FactoryInterface;
use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Interface EntityAsyncEventFactoryInterface
 */
interface EntityAsyncEventFactoryInterface extends FactoryInterface
{
    /**
     * @param mixed ...$args
     * @return Object
     */
    public static function create(...$args): Object;

    /**
     * @param Object $entity
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityCreatedMessage(Object $entity, int $currentUserId = null): EntityAsyncEventInterface;


    /**
     * @param Object $entity
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityDeletedMessage(Object $entity, int $currentUserId = null): EntityAsyncEventInterface;


    /**
     * @param Object $entity
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityEditedMessage(Object $entity, int $currentUserId = null): EntityAsyncEventInterface;


    /**
     * @param Object $entity
     * @param int $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityViewedMessage(Object $entity, int $currentUserId): EntityAsyncEventInterface;
}
