<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Attribute\Interfaces;

use Jul6Art\PushBundle\Attribute\Asyncable;

/**
 * Interface AsyncAttributeReaderInterface.
 */
interface AsyncAttributeReaderInterface
{
    public function getAsyncAttribute(object $entity): ?Asyncable;

    public function hasEvent(object $entity, string $event): bool;

    public function hasPostLoadEvent(object $entity): bool;

    public function hasPostPersistEvent(object $entity): bool;

    public function hasPostUpdateEvent(object $entity): bool;

    public function hasPreRemoveEvent(object $entity): bool;

    public function isAsyncable(object $entity): bool;
}
