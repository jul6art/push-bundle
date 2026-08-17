<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Dispatcher\Traits;

use Jul6Art\PushBundle\Dispatcher\Interfaces\AsyncDispatcherInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait AsyncDispatcherAwareTrait.
 */
trait AsyncDispatcherAwareTrait
{
    protected AsyncDispatcherInterface $asyncDispatcher;

    #[Required]
    public function setAsyncDispatcher(AsyncDispatcherInterface $asyncDispatcher): void
    {
        $this->asyncDispatcher = $asyncDispatcher;
    }
}
