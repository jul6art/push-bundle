<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Jul6Art\PushBundle\Attribute\BroadcastableEntity;

/**
 * Renomme son type dans la charge utile : le client parle de « Gadget » là où la classe
 * s'appelle `Widget`. C'est le seul usage de l'argument `type`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'widget')]
#[BroadcastableEntity(type: 'Gadget')]
class Widget
{
    use IdTrait;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $label = 'gadget'
    ) {
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }
}
