<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;

/**
 * Aucune attribute : rien de cette entité ne doit franchir le hub. La plupart des entités d'une
 * application ressemblent à celle-ci, donc « ne rien faire » est le comportement qui doit être
 * certain.
 */
#[ORM\Entity]
#[ORM\Table(name: 'secret')]
class Secret
{
    use IdTrait;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $value = 'confidentiel'
    ) {
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }
}
