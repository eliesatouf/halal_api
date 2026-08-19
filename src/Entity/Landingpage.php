<?php
namespace App\Entity;

use App\Repository\LandingpageRepository;
use Doctrine\ORM\Mapping as ORM;
use ApiPlatform\Metadata\ApiProperty;
use Symfony\Component\Validator\Constraints as Assert;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Patch;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use App\DataProvider\LandingpageDataProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    normalizationContext: ['groups' => ['landingpage:read', 'homepage:read']],
    security: "is_granted('PUBLIC_ACCESS')"
)]

#[Get(provider: LandingpageDataProvider::class)] // Only GET uses your cache provider
#[GetCollection(provider: LandingpageDataProvider::class)] // Collection also uses cache
#[Put(securityPostDenormalize: "is_granted('ROLE_ADMIN')")] 
#[Post(security: "is_granted('ROLE_ADMIN')")]
#[Patch(security: "is_granted('ROLE_ADMIN')")]

#[ORM\Entity(repositoryClass: LandingpageRepository::class)]
class Landingpage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $heroHeader = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $heroText = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $heroVideo = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $heroImageFallback = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $heroButtontext = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $featuredBlocksheader = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $FeaturedBlockstext = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $FeaturedBlocksitem1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $FeaturedBlocksitem2 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $FeaturedBlocksitem3 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $Offerheader = null;

    #[ORM\Column(length: 1000, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $Offertext = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $Offeritem1 = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['landingpage:read', 'homepage:read'])]
    private ?string $Offeritem2 = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getheroHeader(): ?string
    {
        return $this->heroHeader;
    }

    public function setheroHeader(?string $heroHeader): static
    {
        $this->heroHeader = $heroHeader;

        return $this;
    }

    public function getheroText(): ?string
    {
        return $this->heroText;
    }

    public function setheroText(?string $heroText): static
    {
        $this->heroText = $heroText;

        return $this;
    }

    public function getHeroVideo(): ?string
    {
        return $this->heroVideo;
    }

    public function setHeroVideo(?string $heroVideo): static
    {
        $this->heroVideo = $heroVideo;

        return $this;
    }

    public function getHeroImageFallback(): ?string
    {
        return $this->heroImageFallback;
    }

    public function setHeroImageFallback(?string $heroImageFallback): static
    {
        $this->heroImageFallback = $heroImageFallback;

        return $this;
    }

    public function getHeroButtontext(): ?string
    {
        return $this->heroButtontext;
    }

    public function setHeroButtontext(?string $heroButtontext): static
    {
        $this->heroButtontext = $heroButtontext;

        return $this;
    }

    public function getFeaturedBlocksheader(): ?string
    {
        return $this->featuredBlocksheader;
    }

    public function setFeaturedBlocksheader(?string $featuredBlocksheader): static
    {
        $this->featuredBlocksheader = $featuredBlocksheader;

        return $this;
    }

    public function getFeaturedBlockstext(): ?string
    {
        return $this->FeaturedBlockstext;
    }

    public function setFeaturedBlockstext(?string $FeaturedBlockstext): static
    {
        $this->FeaturedBlockstext = $FeaturedBlockstext;

        return $this;
    }

    public function getFeaturedBlocksitem1(): ?string
    {
        return $this->FeaturedBlocksitem1;
    }

    public function setFeaturedBlocksitem1(?string $FeaturedBlocksitem1): static
    {
        $this->FeaturedBlocksitem1 = $FeaturedBlocksitem1;

        return $this;
    }

    public function getFeaturedBlocksitem2(): ?string
    {
        return $this->FeaturedBlocksitem2;
    }

    public function setFeaturedBlocksitem2(?string $FeaturedBlocksitem2): static
    {
        $this->FeaturedBlocksitem2 = $FeaturedBlocksitem2;

        return $this;
    }

    public function getFeaturedBlocksitem3(): ?string
    {
        return $this->FeaturedBlocksitem3;
    }

    public function setFeaturedBlocksitem3(?string $FeaturedBlocksitem3): static
    {
        $this->FeaturedBlocksitem3 = $FeaturedBlocksitem3;

        return $this;
    }

    public function getOfferheader(): ?string
    {
        return $this->Offerheader;
    }

    public function setOfferheader(?string $Offerheader): static
    {
        $this->Offerheader = $Offerheader;

        return $this;
    }

    public function getOffertext(): ?string
    {
        return $this->Offertext;
    }

    public function setOffertext(?string $Offertext): static
    {
        $this->Offertext = $Offertext;

        return $this;
    }

    public function getOfferitem1(): ?string
    {
        return $this->Offeritem1;
    }

    public function setOfferitem1(?string $Offeritem1): static
    {
        $this->Offeritem1 = $Offeritem1;

        return $this;
    }

    public function getOfferitem2(): ?string
    {
        return $this->Offeritem2;
    }

    public function setOfferitem2(?string $Offeritem2): static
    {
        $this->Offeritem2 = $Offeritem2;

        return $this;
    }
}
