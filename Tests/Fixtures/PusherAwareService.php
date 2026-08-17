<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures;

use Jul6Art\PushBundle\Service\Pusher;
use Jul6Art\PushBundle\Service\Traits\PusherAwareTrait;

/**
 * Uses PusherAwareTrait, which the bundle ships for consumers, and exposes what was
 * injected so the trait is covered without reaching into protected state.
 */
class PusherAwareService
{
    use PusherAwareTrait;

    public function pusher(): Pusher
    {
        return $this->pusher;
    }
}
