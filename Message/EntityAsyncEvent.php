<?php

namespace Jul6Art\PushBundle\Message;

use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Class EntityAsyncEvent
 */
class EntityAsyncEvent extends AbstractAsyncEvent implements EntityAsyncEventInterface
{
    /**
     * @var string
     */
    private $class;

    /**
     * @var int
     */
    private $id;

    /**
     * @var string
     */
    private $type;

    /**
     * EntityAsyncEvent constructor.
     * @param string $type
     * @param string $class
     * @param int $id
     * @param int|null $createdById
     */
    public function __construct(string $type, string $class, int $id, int $createdById = null)
    {
        parent::__construct($createdById);

        $this->class = $class;
        $this->id = $id;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getClass(): string
    {
        return $this->class;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
}
