<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Factory\Interfaces;

use Jul6Art\CoreBundle\Factory\Interfaces\FactoryInterface;
use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Interface EntityAsyncEventFactoryInterface.
 */
interface EntityAsyncEventFactoryInterface extends FactoryInterface
{
    /**
     * Expects exactly three arguments: string $type, object $entity and
     * ?int $currentUserId.
     */
    public static function create(mixed ...$args): EntityAsyncEventInterface;

    public static function createEntityCreatedMessage(object $entity, ?int $currentUserId = null): EntityAsyncEventInterface;

    public static function createEntityDeletedMessage(object $entity, ?int $currentUserId = null): EntityAsyncEventInterface;

    public static function createEntityEditedMessage(object $entity, ?int $currentUserId = null): EntityAsyncEventInterface;

    public static function createEntityViewedMessage(object $entity, int $currentUserId): EntityAsyncEventInterface;
}
