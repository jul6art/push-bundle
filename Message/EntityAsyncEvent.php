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
    private $entityClass;

    /**
     * @var int
     */
    private $entityId;

    /**
     * @var string
     */
    private $type;

    /**
     * EntityAsyncEvent constructor.
     * @param string $type
     * @param string $entityClass
     * @param int $entityId
     * @param int|null $createdById
     */
    public function __construct(string $type, string $entityClass, int $entityId, int $createdById = null)
    {
        parent::__construct($createdById);

        $this->entityClass = $entityClass;
        $this->entityId = $entityId;
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    /**
     * @return int
     */
    public function getEntityId(): int
    {
        return $this->entityId;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }
}
