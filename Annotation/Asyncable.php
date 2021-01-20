<?php

namespace Jul6Art\PushBundle\Annotation;

use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\Annotation\Target;
use Doctrine\ORM\Mapping\Annotation as AnnotationInterface;

/**
 * @Annotation
 *
 * @Target("CLASS")
 *
 * Class Asyncable
 */
class Asyncable implements AnnotationInterface
{
    /**
     * @var string
     */
    private $eventClass;

    /**
     * @var string[]
     */
    private $events = [];

    /**
     * Asyncable constructor.
     * @param string $event
     */
    public function __construct(array $params)
    {
        if (!array_key_exists('eventClass', $params)) {
            throw new \InvalidArgumentException('"eventClass" parameter missing in @Asyncable annotation');
        }

        $this->eventClass = $params['eventClass'];

        if (array_key_exists('events', $params) and \is_iterable($params['events'])) {
            $this->events = $params['events'];
        }
    }

    /**
     * @return string
     */
    public function getEventClass(): string
    {
        return $this->eventClass;
    }

    /**
     * @return string[]
     */
    public function getEvents(): iterable
    {
        return $this->events;
    }
}
