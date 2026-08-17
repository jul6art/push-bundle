<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Service\Traits;

use Jul6Art\PushBundle\Service\Pusher;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait PusherAwareTrait.
 */
trait PusherAwareTrait
{
    protected Pusher $pusher;

    #[Required]
    public function setPusher(Pusher $pusher): void
    {
        $this->pusher = $pusher;
    }
}
