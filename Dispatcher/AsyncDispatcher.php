<?php

namespace Jul6Art\PushBundle\Dispatcher;

use Jul6Art\PushBundle\Dispatcher\Interfaces\DispatcherInterface;
use Jul6Art\PushBundle\Factory\AsyncFactory;
use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;
use Jul6Art\PushBundle\Service\Traits\MessageBusAwareTrait;

/**
 * Class AsyncDispatcher
 */
class AsyncDispatcher implements DispatcherInterface
{
    use MessageBusAwareTrait;

    /**
     * @param EntityAsyncEventInterface $event
     */
    public function dispatch(EntityAsyncEventInterface $event): void
    {
        $this->bus->dispatch($event);
    }
}
