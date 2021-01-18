<?php


namespace Jul6Art\PushBundle\Factory\Interfaces;

use Jul6Art\CoreBundle\Factory\Interfaces\FactoryInterface;
use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Interface AsyncFactoryInterface
 */
interface AsyncFactoryInterface extends FactoryInterface
{
    /**
     * @param mixed ...$args
     * @return Object
     */
    public static function create(...$args): Object;

    /**
     * @param string $class
     * @param int $id
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityCreatedMessage(string $class, int $id, int $currentUserId = null): EntityAsyncEventInterface;


    /**
     * @param string $class
     * @param int $id
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityDeletedMessage(string $class, int $id, int $currentUserId = null): EntityAsyncEventInterface;


    /**
     * @param string $class
     * @param int $id
     * @param int|null $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityEditedMessage(string $class, int $id, int $currentUserId = null): EntityAsyncEventInterface;


    /**
     * @param string $class
     * @param int $id
     * @param int $currentUserId
     * @return EntityAsyncEventInterface
     */
    public static function createEntityViewedMessage(string $class, int $id, int $currentUserId): EntityAsyncEventInterface;
}
