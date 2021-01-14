<?php

namespace Jul6Art\PushBundle\Service\Traits;

use Jul6Art\PushBundle\Service\Pusher;

/**
 * Trait PusherAwareTrait
 */
trait PusherAwareTrait
{
    /**
     * @var Pusher
     */
    protected $pusher;

    /**
     * @required
     */
    public function setPusher(Pusher $pusher): void
    {
        $this->pusher = $pusher;
    }
}
