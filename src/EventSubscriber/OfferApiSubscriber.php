<?php

namespace App\EventSubscriber;

use ApiPlatform\Symfony\EventListener\EventPriorities;
use App\Entity\Offer;
use App\Repository\OfferTypeRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ViewEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class OfferApiSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private OfferTypeRepository $offerTypeRepository
    ) {}

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::VIEW => ['addTypeNameToResponse', EventPriorities::POST_SERIALIZE],
        ];
    }

    public function addTypeNameToResponse(ViewEvent $event)
    {
        $offer = $event->getControllerResult();
        $request = $event->getRequest();

        // Only handle Offer entities and GET requests
        if (!$offer instanceof Offer || !in_array($request->getMethod(), [Request::METHOD_GET, Request::METHOD_GET_COLLECTION])) {
            return;
        }

        // Get the response content
        $response = $event->getResponse();
        if (!$response) {
            return;
        }

        $content = json_decode($response->getContent(), true);
        
        // Add typeName to the response
        if (isset($content['type'])) {
            $content['typeName'] = $this->getOfferTypeName($content['type']);
        }

        // For collections
        if (isset($content['hydra:member']) && is_array($content['hydra:member'])) {
            foreach ($content['hydra:member'] as &$item) {
                if (isset($item['type'])) {
                    $item['typeName'] = $this->getOfferTypeName($item['type']);
                }
            }
        }

        $response->setContent(json_encode($content));
    }

    private function getOfferTypeName(?string $typeIri): ?string
    {
        if (!$typeIri) {
            return null;
        }

        $parts = explode('/', $typeIri);
        $typeId = end($parts);
        
        if (!is_numeric($typeId)) {
            return null;
        }

        $offerType = $this->offerTypeRepository->find($typeId);
        return $offerType ? $offerType->getName() : null;
    }
}