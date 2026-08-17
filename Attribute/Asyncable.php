<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Attribute;

/**
 * Marks an entity as asyncable and names the event class to dispatch for it.
 *
 * This used to be a Doctrine annotation implementing
 * Doctrine\ORM\Mapping\Annotation, an interface removed in Doctrine ORM 3.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class Asyncable
{
    /**
     * @param class-string $eventClass event dispatched for the annotated entity
     * @param list<string> $events     Doctrine events to track; an empty list tracks all
     *                                 of them
     */
    public function __construct(
        private string $eventClass,
        private array $events = [],
    ) {
    }

    /**
     * @return class-string
     */
    public function getEventClass(): string
    {
        return $this->eventClass;
    }

    /**
     * @return list<string>
     */
    public function getEvents(): array
    {
        return $this->events;
    }
}
