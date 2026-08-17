<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Message\Interfaces;

/**
 * Interface AsyncEventInterface.
 */
interface AsyncEventInterface
{
    public function getCreatedById(): ?int;
}
