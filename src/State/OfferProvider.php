<?php
// src/State/OfferProvider.php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Offer;
use App\Repository\OfferRepository;
use Symfony\Bundle\SecurityBundle\Security;

class OfferProvider implements ProviderInterface
{
    public function __construct(
        private OfferRepository $repository,
        private Security $security
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // For single item operations (GET /api/offers/{id})
        if ($operation instanceof \ApiPlatform\Metadata\Get) {
            $offer = $this->repository->find($uriVariables['id']);
            return $this->shouldReturnOffer($offer) ? $offer : null;
           // return ['wtf1'];
        }

        // For collection operations (GET /api/offers)
        if ($operation instanceof \ApiPlatform\Metadata\GetCollection) {
            return $this->security->isGranted('ROLE_ADMIN') 
                ? $this->repository->findAll() 
                : $this->repository->findBy(['active' => true,'assignedToHomePage' => true]);
        }

        return null;
    }

    private function shouldReturnOffer(?Offer $offer): bool
    {
        return $offer && ($this->security->isGranted('ROLE_ADMIN') || $offer->isActive());
        //return ['wtf3'];
    }
}