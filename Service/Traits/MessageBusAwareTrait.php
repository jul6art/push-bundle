<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Service\Traits;

use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait MessageBusAwareTrait.
 */
trait MessageBusAwareTrait
{
    protected MessageBusInterface $bus;

    #[Required]
    public function setBus(MessageBusInterface $bus): void
    {
        $this->bus = $bus;
    }
}
