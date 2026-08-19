<?php

namespace App\Entity;

use App\Repository\GalleryRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Delete;

#[ApiResource(
    operations: [
        new Get( ),
        new GetCollection(),
        new Post(security: 'is_granted("ROLE_ADMIN")'),
        new Put(security: 'is_granted("ROLE_ADMIN")'),
        new Delete(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(
            denormalizationContext: ['groups' => ['admin:write']],
            normalizationContext: ['groups' => ['admin:read']],
            securityPostDenormalize: "is_granted('ROLE_ADMIN')",
            validationContext: ['groups' => ['admin:write']],
            security: 'is_granted("ROLE_ADMIN")'
        )
    ]
)]

#[ORM\Entity(repositoryClass: GalleryRepository::class)]
class Gallery
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['admin:read','admin:write'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $name = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $imageSource = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $imageX = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $imageY = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $imageRotate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $noteX = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $noteY = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $noteRotate = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $noteTextColor = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?string $noteBgColor = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['admin:read','admin:write'])]
    private ?bool $active = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getImageSource(): ?string
    {
        return $this->imageSource;
    }

    public function setImageSource(?string $imageSource): static
    {
        $this->imageSource = $imageSource;

        return $this;
    }

    public function getImageX(): ?string
    {
        return $this->imageX;
    }

    public function setImageX(?string $imageX): static
    {
        $this->imageX = $imageX;

        return $this;
    }

    public function getImageY(): ?string
    {
        return $this->imageY;
    }

    public function setImageY(?string $imageY): static
    {
        $this->imageY = $imageY;

        return $this;
    }

    public function getImageRotate(): ?string
    {
        return $this->imageRotate;
    }

    public function setImageRotate(?string $imageRotate): static
    {
        $this->imageRotate = $imageRotate;

        return $this;
    }

    public function getNoteX(): ?string
    {
        return $this->noteX;
    }

    public function setNoteX(?string $noteX): static
    {
        $this->noteX = $noteX;

        return $this;
    }

    public function getNoteY(): ?string
    {
        return $this->noteY;
    }

    public function setNoteY(?string $noteY): static
    {
        $this->noteY = $noteY;

        return $this;
    }

    public function getNoteRotate(): ?string
    {
        return $this->noteRotate;
    }

    public function setNoteRotate(?string $noteRotate): static
    {
        $this->noteRotate = $noteRotate;

        return $this;
    }

    public function getNoteTextColor(): ?string
    {
        return $this->noteTextColor;
    }

    public function setNoteTextColor(?string $noteTextColor): static
    {
        $this->noteTextColor = $noteTextColor;

        return $this;
    }

    public function getNoteBgColor(): ?string
    {
        return $this->noteBgColor;
    }

    public function setNoteBgColor(?string $noteBgColor): static
    {
        $this->noteBgColor = $noteBgColor;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(?bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
