<?php
namespace App\State;

use App\Entity\User;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class UserMeProvider implements ProviderInterface
{
    public function __construct(
        private Security $security
    ) {}

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $user = $this->security->getUser();
        
        if (!$user) {
            throw new UnauthorizedHttpException('Bearer', 'User not authenticated');
        }
        
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid user type');
        }
        
        return $user;
    }
}