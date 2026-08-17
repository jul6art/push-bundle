<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures;

use Jul6Art\PushBundle\Attribute\Asyncable;

/**
 * Asyncable but without an identifier, which the message cannot carry.
 */
#[Asyncable(eventClass: AsyncableEntityEvent::class)]
class IdlessEntity
{
}
