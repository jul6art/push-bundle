<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Attribute\Traits;

use Jul6Art\PushBundle\Attribute\Interfaces\AsyncAttributeReaderInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Trait AsyncAttributeReaderAwareTrait.
 */
trait AsyncAttributeReaderAwareTrait
{
    protected AsyncAttributeReaderInterface $asyncAttributeReader;

    #[Required]
    public function setAsyncAttributeReader(AsyncAttributeReaderInterface $asyncAttributeReader): void
    {
        $this->asyncAttributeReader = $asyncAttributeReader;
    }
}
