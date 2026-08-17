<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\EventListener\Interfaces;

use Doctrine\ORM\Event\PostLoadEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Jul6Art\CoreBundle\EventListener\Interfaces\EventListenerInterface;

/**
 * Interface AsyncEventListenerInterface.
 *
 * Doctrine ORM 3 removed the catch-all LifecycleEventArgs in favour of one class
 * per lifecycle event.
 */
interface AsyncEventListenerInterface extends EventListenerInterface
{
    public function postLoad(PostLoadEventArgs $args): void;

    public function postPersist(PostPersistEventArgs $args): void;

    public function postUpdate(PostUpdateEventArgs $args): void;

    public function preRemove(PreRemoveEventArgs $args): void;
}
