<?php

namespace Jul6Art\PushBundle\Message;

use Jul6Art\PushBundle\Message\Interfaces\AsyncEventInterface;

/**
 * Class AbstractAsyncEvent
 */
abstract class AbstractAsyncEvent implements AsyncEventInterface
{
    /**
     * @var int|null
     */
    protected $createdById;

    /**
     * AbstractAsyncEvent constructor.
     * @param int|null $createdById
     */
    public function __construct(int $createdById = null)
    {
        $this->createdById = $createdById;
    }

    /**
     * @return int|null
     */
    public function getCreatedById(): ?int
    {
        return $this->createdById;
    }
}
