<?php

declare(strict_types=1);

namespace Jul6Art\PushBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Jul6Art\CoreBundle\Entity\Traits\IdTrait;
use Jul6Art\PushBundle\Attribute\BroadcastableEntity;

/**
 * Le cas ordinaire : diffusé, avec une liste blanche de champs et une suppression douce — de
 * quoi vérifier que `deletedAt` passant de null à une date se lit « deleted » et non « updated ».
 */
#[ORM\Entity]
#[ORM\Table(name: 'article')]
#[BroadcastableEntity(changedFields: ['title', 'publishedAt'])]
class Article
{
    use IdTrait;

    /**
     * Déclaré à la main plutôt que par `SoftDeletableTrait` : la détection du publisher porte sur
     * le **nom** du champ, pas sur un trait, et ce bundle n'a pas à relever son plancher
     * `core-bundle` pour une commodité de test.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    /** Champ hors liste blanche : il ne doit jamais apparaître dans changedFields. */
    #[ORM\Column]
    private int $viewCount = 0;

    public function __construct(
        #[ORM\Column(length: 120)]
        private string $title = 'Un titre'
    ) {
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function publish(): static
    {
        $this->publishedAt = new \DateTimeImmutable();

        return $this;
    }

    public function view(): static
    {
        ++$this->viewCount;

        return $this;
    }

    public function softDelete(): static
    {
        $this->deletedAt = new \DateTimeImmutable();

        return $this;
    }
}
