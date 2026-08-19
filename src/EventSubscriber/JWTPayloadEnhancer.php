<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;

class JWTPayloadEnhancer
{
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        
        if (!$user instanceof User) {
            return;
        }
        
        $payload = $event->getData();
        
        // Add custom claims to the JWT token
        $payload['givenName'] = $user->getGivenName();
        $payload['familyName'] = $user->getFamilyName();
        $payload['userId'] = $user->getId()->toRfc4122();
        $payload['email'] = $user->getEmail();
        $payload['phone'] = $user->getTelephone();
        
        // Add customer information if available
        $customer = $user->getCustomer();
        if ($customer) {
            $payload['customerId'] = $customer->getId();
            $payload['customerName'] = $customer->getName();
            $payload['customerEmail'] = $customer->getEmail();
        }
        
        $event->setData($payload);
    }
}