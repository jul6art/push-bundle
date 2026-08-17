<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Dispatcher;

use Jul6Art\PushBundle\Dispatcher\Interfaces\AsyncDispatcherInterface;
use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;
use Jul6Art\PushBundle\Service\Traits\MessageBusAwareTrait;

/**
 * Class AsyncDispatcher.
 *
 * It used to declare the empty DispatcherInterface while AsyncDispatcherInterface,
 * the one actually describing dispatch(), went unused.
 */
class AsyncDispatcher implements AsyncDispatcherInterface
{
    use MessageBusAwareTrait;

    #[\Override]
    public function dispatch(EntityAsyncEventInterface $event): void
    {
        $this->bus->dispatch($event);
    }
}
