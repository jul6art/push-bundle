<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures;

use Jul6Art\PushBundle\Attribute\Asyncable;

/**
 * Restricts the tracked Doctrine events to two of them.
 */
#[Asyncable(eventClass: AsyncableEntityEvent::class, events: ['postPersist', 'postUpdate'])]
class PartiallyAsyncableEntity
{
    public function getId(): int
    {
        return 7;
    }
}
