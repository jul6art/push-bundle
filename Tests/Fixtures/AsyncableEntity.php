<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures;

use Jul6Art\PushBundle\Attribute\Asyncable;

/**
 * Tracks every Doctrine event, since no event list is given.
 */
#[Asyncable(eventClass: AsyncableEntityEvent::class)]
class AsyncableEntity
{
    public function __construct(private readonly ?int $id = 1)
    {
    }

    public function getId(): ?int
    {
        return $this->id;
    }
}
