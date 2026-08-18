<?php

namespace App\Trait;

use Doctrine\ORM\Mapping as ORM;

/**
 * Ajoute les dates de création et de modification aux entités Doctrine.
 */
trait TimestampableTrait
{
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Initialise les dates lors de la première persistance Doctrine.
     *
     * @return void
     */
    #[ORM\PrePersist]
    public function initializeTimestamps(): void
    {
        $now = new \DateTimeImmutable();

        // Ne les initialise que si elles n'ont pas déjà été définies
        $this->createdAt ??= $now;
        $this->updatedAt ??= $now;
    }

    /**
     * Met à jour la date de modification avant chaque mise à jour Doctrine.
     *
     * @return void
     */
    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
