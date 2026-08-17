<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Message;

use Jul6Art\PushBundle\Message\Interfaces\EntityAsyncEventInterface;

/**
 * Class EntityAsyncEvent.
 */
class EntityAsyncEvent extends AbstractAsyncEvent implements EntityAsyncEventInterface
{
    /**
     * @param class-string $entityClass
     */
    public function __construct(
        private readonly string $type,
        private readonly string $entityClass,
        private readonly int $entityId,
        ?int $createdById = null,
    ) {
        parent::__construct($createdById);
    }

    /**
     * @return class-string
     */
    #[\Override]
    public function getEntityClass(): string
    {
        return $this->entityClass;
    }

    #[\Override]
    public function getEntityId(): int
    {
        return $this->entityId;
    }

    #[\Override]
    public function getType(): string
    {
        return $this->type;
    }
}
