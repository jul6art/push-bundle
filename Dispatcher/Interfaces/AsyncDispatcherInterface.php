<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Dispatcher\Interfaces;

use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Interface AsyncDispatcherInterface.
 */
interface AsyncDispatcherInterface extends DispatcherInterface
{
    public function dispatch(EntityAsyncEventInterface $event): void;
}
