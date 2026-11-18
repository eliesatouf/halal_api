<?php
// src/Entity/Note.php

namespace App\Entity;

use App\Repository\NoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NoteRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(columns: ['entity_type', 'entity_id'], name: 'idx_note_entity')]
class Note
{
    public const ENTITY_INQUIRY    = 'inquiry';
    public const ENTITY_EXHIBITOR  = 'exhibitor';
    public const ENTITY_PARTNER    = 'partner';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['note:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    #[Groups(['note:read'])]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::ENTITY_INQUIRY, self::ENTITY_EXHIBITOR, self::ENTITY_PARTNER])]
    private ?string $entityType = null;

    #[ORM\Column]
    #[Groups(['note:read'])]
    #[Assert\NotNull]
    #[Assert\Positive]
    private ?int $entityId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['note:read'])]
    private ?User $author = null;

    #[ORM\Column(type: 'text')]
    #[Groups(['note:read'])]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 5000)]
    private ?string $body = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['note:read'])]
    private ?\DateTime $createdOn = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['note:read'])]
    private ?\DateTime $updatedOn = null;

    #[ORM\PrePersist]
    public function setCreatedOnValue(): void
    {
        if ($this->createdOn === null) {
            $this->createdOn = new \DateTime();
        }
        $this->updatedOn = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedOn = new \DateTime();
    }

    public function getId(): ?int { return $this->id; }

    public function getEntityType(): ?string { return $this->entityType; }
    public function setEntityType(string $entityType): static { $this->entityType = $entityType; return $this; }

    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(int $entityId): static { $this->entityId = $entityId; return $this; }

    public function getAuthor(): ?User { return $this->author; }
    public function setAuthor(User $author): static { $this->author = $author; return $this; }

    public function getBody(): ?string { return $this->body; }
    public function setBody(string $body): static { $this->body = $body; return $this; }

    public function getCreatedOn(): ?\DateTime { return $this->createdOn; }
    public function setCreatedOn(?\DateTime $createdOn): static { $this->createdOn = $createdOn; return $this; }

    public function getUpdatedOn(): ?\DateTime { return $this->updatedOn; }
    public function setUpdatedOn(?\DateTime $updatedOn): static { $this->updatedOn = $updatedOn; return $this; }
}