<?php
// src/Controller/CustomerProfileController.php
namespace App\Controller;

    use App\Dto\CustomerProfile;
    use App\Entity\Customer;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\Routing\Annotation\Route;
    use Symfony\Component\Serializer\SerializerInterface;
    use Symfony\Bundle\SecurityBundle\Security;
    use Psr\Log\LoggerInterface;

    #[Route('/api')]
    class CustomerProfileController extends AbstractController
    {
        public function __construct(
            private Security $security,
            private EntityManagerInterface $em,
            private SerializerInterface $serializer,
            private LoggerInterface $logger

        ) 
        {
            $this->logger = $logger;
        }

        #[Route('/customer_profile', name: 'customer_profile', methods: ['GET'])]
        public function getProfile(): JsonResponse
        {
            $user = $this->security->getUser();
            $customer = $user->getCustomer();

            if (!$customer) {
                throw $this->createNotFoundException('Customer not found');
            }

            $profile = new CustomerProfile();
            $profile->id = $customer->getId();
            $profile->name = $customer->getName();
            $profile->email = $customer->getEmail();
            $profile->address = $customer->getAddress();
            $profile->telephone = $customer->getTelephone();
            

            // Serialize with the profile group
            $json = $this->serializer->serialize($profile, 'json', [
                'groups' => ['customer:profile']
            ]);

            return new JsonResponse($json, 200, [], true);
        }

        
    }