<?php

namespace Jul6Art\PushBundle\EventListener;

use Jul6Art\CoreBundle\EventListener\AbstractEntityEventListener;
use Jul6Art\PushBundle\EventListener\Interfaces\AsyncEntityEventListenerInterface;
use Jul6Art\PushBundle\Service\Traits\MessageBusAwareTrait;

/**
 * Class AbstractAsyncEntityEventListener
 */
abstract class AbstractAsyncEntityEventListener extends AbstractEntityEventListener implements AsyncEntityEventListenerInterface
{
    use MessageBusAwareTrait;
}
