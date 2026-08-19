<?php 
// src/State/BundleProcessor.php
namespace App\State;        

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Order;
use App\Service\OfferStrategy\OfferStrategyInterface;
use Psr\Log\LoggerInterface;
use App\Repository\OfferTypeRepository;
use Symfony\Bundle\SecurityBundle\Security;
use ApiPlatform\Metadata\Post;
use App\Entity\Bundle;
use App\Entity\BundleItem;
use Doctrine\ORM\EntityManagerInterface;

class BundleProcessor implements ProcessorInterface
{

    public function __construct(
        private ProcessorInterface $persistProcessor,
        private LoggerInterface $logger,
        private Security $security,
        private EntityManagerInterface $entityManager
    ) 
    {
       
    }
    
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
       
        if ($data instanceof Bundle) {
            $this->logger->error(" data instanceof Bundle");
            $this->processBundleItems($data);
        }
    }

    private function processBundleItems(Bundle $bundle): void
    {
        $this->logger->error("processBundleItems");
        $items = [];
        foreach ($bundle->getItems() as $item) {
            $this->logger->error("fuck this");
            $items[] = [
                'id' => $item->getId(),
                'dish' => '/api/dishes/' . $item->getDish()->getId(), 
                'quantity' => $item->getQuantity()
            ];
            $this->logger->error("fuck this id" . $id);
        }

    }
}