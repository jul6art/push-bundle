<?php

namespace Jul6Art\PushBundle\Dispatcher\Interfaces;

use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Interface AsyncDispatcherInterface
 */
interface AsyncDispatcherInterface extends DispatcherInterface
{
    /**
     * @param EntityAsyncEventInterface $event
     */
    public function dispatch(EntityAsyncEventInterface $event): void;
}
