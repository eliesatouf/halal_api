<?php
// src/Entity/Settings.php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Serializer\Filter\PropertyFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use Symfony\Contracts\Cache\ItemInterface;

#[ApiResource(
    operations: [
        new Get(
            normalizationContext: ['groups' => ['settings:read']],
            cacheHeaders: [
                'max_age' => 3600,
                'shared_max_age' => 3600,
                'public' => true
            ]
        ),
        new GetCollection(
            cacheHeaders: [
                'max_age' => 3600,
                'shared_max_age' => 3600,
                'public' => true
            ]
        ),
        new Post(security: 'is_granted("ROLE_ADMIN")'),
        new Patch(
            denormalizationContext: ['groups' => ['settings:write']],
            security: 'is_granted("ROLE_ADMIN")'
        ),
    ],
    normalizationContext: ['groups' => ['settings:read']],
    cacheHeaders: [
        'max_age' => 3600,
        'shared_max_age' => 3600,
        'public' => true
    ]
)]

#[ApiFilter(SearchFilter::class, properties: [
    'settingKey' => 'exact'
])]

#[ORM\Entity]
#[ORM\Table(name: 'app_settings')]
#[ORM\HasLifecycleCallbacks]
class Settings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['settings:read'])]
    private ?int $id = null;

    #[ORM\Column(name: 'setting_key', length: 100, unique: true)]
    #[Groups(['settings:read','settings:write'])]
    private ?string $settingKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['settings:read','settings:write'])]
    private ?string $value = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['settings:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters and Setters
    public function getId(): ?int { return $this->id; }
    
    public function getSettingKey(): ?string { return $this->settingKey; }
    public function setSettingKey(string $settingKey): static
    {
        $this->settingKey = $settingKey;
        return $this;
    }
    
    public function getValue(): ?string { return $this->value; }
    public function setValue(?string $value): static
    {
        $this->value = $value;
        return $this;
    }
    
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}