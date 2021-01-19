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
    private $event;

    /**
     * Asyncable constructor.
     * @param string $event
     */
    public function __construct(array $params)
    {
        if (!array_key_exists('event', $params)) {
            throw new \InvalidArgumentException('"event" parameter missing in @Asyncable annotation');
        }

        $this->event = $params['event'];
    }

    /**
     * @return string
     */
    public function getEvent(): string
    {
        return $this->event;
    }
}
