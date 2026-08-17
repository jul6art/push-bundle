<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Message;

use Jul6Art\PushBundle\Message\Interfaces\AsyncEventInterface;

/**
 * Class AbstractAsyncEvent.
 */
abstract class AbstractAsyncEvent implements AsyncEventInterface
{
    public function __construct(
        protected readonly ?int $createdById = null,
    ) {
    }

    #[\Override]
    public function getCreatedById(): ?int
    {
        return $this->createdById;
    }
}
