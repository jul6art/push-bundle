<?php

namespace Jul6Art\PushBundle\Service\Traits;

use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Trait MessageBusAwareTrait.
 */
trait MessageBusAwareTrait
{
    /**
     * @var MessageBusInterface
     */
    protected $bus;

    /**
     * @required
     *
     * @param MessageBusInterface $bus
     */
    public function setBus(MessageBusInterface $bus): void
    {
        $this->bus = $bus;
    }
}
