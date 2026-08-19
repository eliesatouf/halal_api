<?php

namespace App\Entity;

use App\Repository\DiningSalesReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new Get(security: 'is_granted("ROLE_ADMIN")'),
        new GetCollection(security: 'is_granted("ROLE_ADMIN")')
    ],
    normalizationContext: ['groups' => ['sales_report:read']]
)]

#[ORM\HasLifecycleCallbacks]
#[ORM\Entity(repositoryClass: DiningSalesReportRepository::class)]
class DiningSalesReport
{   
    #[Groups(['sales_report:read'])]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups(['sales_report:read'])]
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $reportType = null;

    #[Groups(['sales_report:read'])]
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $startDate = null;

    #[Groups(['sales_report:read'])]
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $endDate = null;

    #[Groups(['sales_report:read'])]
    #[ORM\Column(nullable: true)]
    private ?array $summaryData = null;

    #[Groups(['sales_report:read'])]
    #[ORM\Column(nullable: true)]
    private ?array $detailedData = null;

    #[Groups(['sales_report:read'])]
    #[ORM\Column(nullable: true)]
    private ?\DateTime $generatedAt = null;

    #[ORM\PrePersist]
    public function setGeneratedAtValue(): void
    {
        $this->generatedAt = new \DateTime();
    }
    

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReportType(): ?string
    {
        return $this->reportType;
    }

    public function setReportType(?string $reportType): static
    {
        $this->reportType = $reportType;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getSummaryData(): ?array
    {
        return $this->summaryData;
    }

    public function setSummaryData(?array $summaryData): static
    {
        $this->summaryData = $summaryData;

        return $this;
    }

    public function getDetailedData(): ?array
    {
        return $this->detailedData;
    }

    public function setDetailedData(?array $detailedData): static
    {
        $this->detailedData = $detailedData;

        return $this;
    }

    public function getGeneratedAt(): ?\DateTime
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?\DateTime $generatedAt): static
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }
}
