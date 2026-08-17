<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures;

/**
 * Carries no attribute, so it must never be dispatched.
 */
class PlainEntity
{
    public function getId(): int
    {
        return 3;
    }
}
