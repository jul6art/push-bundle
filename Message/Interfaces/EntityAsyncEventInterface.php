<?php

namespace Jul6Art\PushBundle\Message\Interfaces;

/**
 * Interface EntityAsyncEventInterface
 */
interface EntityAsyncEventInterface extends AsyncEventInterface
{
    /**
     * @return string
     */
    public function getEntityClass(): string;

    /**
     * @return int
     */
    public function getEntityId(): int;

    /**
     * @return string
     */
    public function getType(): string;
}
