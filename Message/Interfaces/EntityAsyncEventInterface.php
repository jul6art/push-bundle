<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Message\Interfaces;

/**
 * Interface EntityAsyncEventInterface.
 */
interface EntityAsyncEventInterface extends AsyncEventInterface
{
    /**
     * @return class-string
     */
    public function getEntityClass(): string;

    public function getEntityId(): int;

    public function getType(): string;
}
