<?php

namespace Jul6Art\PushBundle\Message\Interfaces;

/**
 * Interface AsyncEventInterface
 */
interface AsyncEventInterface
{
    /**
     * @return int|null
     */
    public function getCreatedById(): ?int;
}
