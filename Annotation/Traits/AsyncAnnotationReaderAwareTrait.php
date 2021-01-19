<?php

namespace Jul6Art\PushBundle\Annotation\Traits;

use Jul6Art\PushBundle\Annotation\AsyncAnnotationReader;

/**
 * Class AsyncAnnotationReaderAwareTrait
 */
trait AsyncAnnotationReaderAwareTrait
{
    /**
     * @var AsyncAnnotationReader
     */
    protected $asyncAnnotationReader;

    /**
     * @required
     *
     * @param AsyncAnnotationReader $asyncAnnotationReader
     */
    public function setAsyncAnnotationReader(AsyncAnnotationReader $asyncAnnotationReader): void
    {
        $this->asyncAnnotationReader = $asyncAnnotationReader;
    }
}
