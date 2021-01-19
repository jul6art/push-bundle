<?php

namespace Jul6Art\PushBundle\Dispatcher\Traits;

use Jul6Art\PushBundle\Dispatcher\AsyncDispatcher;

/**
 * Trait AsyncDispatcherAwareTrait
 */
trait AsyncDispatcherAwareTrait
{
    /**
     * @var AsyncDispatcher
     */
    protected $asyncDispatcher;

    /**
     * @required
     *
     * @param AsyncDispatcher $asyncDispatcher
     */
    public function setAsyncDispatcher(AsyncDispatcher $asyncDispatcher): void
    {
        $this->asyncDispatcher = $asyncDispatcher;
    }
}
