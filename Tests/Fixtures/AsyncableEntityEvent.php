<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures;

use Jul6Art\CoreBundle\Event\AbstractEvent;

/**
 * The event class the Asyncable attribute points at: it takes the entity as its only
 * constructor argument and overrides the CoreBundle event names.
 */
class AsyncableEntityEvent extends AbstractEvent
{
    public const string CREATED = 'event.asyncable_entity.created';
    public const string DELETED = 'event.asyncable_entity.deleted';
    public const string EDITED = 'event.asyncable_entity.edited';
    public const string VIEWED = 'event.asyncable_entity.viewed';

    public function __construct(private readonly object $entity)
    {
        parent::__construct();
    }

    public function getEntity(): object
    {
        return $this->entity;
    }
}
