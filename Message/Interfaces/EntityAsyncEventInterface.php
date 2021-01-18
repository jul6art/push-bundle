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
    public function getClass(): string;

    /**
     * @return int
     */
    public function getId(): int;

    /**
     * @return string
     */
    public function getType(): string;
}
