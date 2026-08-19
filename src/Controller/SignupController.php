<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;
use App\Entity\User;
use App\Entity\Customer;
use App\Entity\EmailVerification;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\LoyverseSyncService;
use Psr\Log\LoggerInterface;
use DateTime;


final class SignupController extends AbstractController
{
    #[Route('/signup', name: 'app_signup')]
    public function index(
        #[CurrentUser] ?User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        LoyverseSyncService $loyverseSyncService, // Add this
        LoggerInterface $logger // Add this
    ): JsonResponse
    {
        $givenName = $request->getPayload()->get('givenName');
        $email = $request->getPayload()->get('email');
        $email = strtolower($email);
        $password = $request->getPayload()->get('password');
        $address = $request->getPayload()->get('address');
        $telephone = $request->getPayload()->get('telephone');

        $retUser = $entityManager->getRepository(User::class)->findBy(['email'=>$email]);

        $emailCheck = 0;
        $mobile1Check = 0;

        $retUser = $entityManager->getRepository(User::class)->findBy(['email'=>$email]);
        $telephoneCheck = $entityManager->getRepository(Customer::class)->findBy(['telephone'=>$telephone]);

        if($retUser){$emailCheck = 1; }
        if($telephoneCheck){$mobile1Check = 1; }

        $userExists = $emailCheck || $mobile1Check;

        if (!$userExists) {
            $client = new Customer();
            $client->setEmail($email);
            $client->setName($givenName);  
            $client->setAddress($address);
            $client->setTelephone($telephone);
            $client->setActive(true);
            $client->setCreatedOn( new \DateTime());

            $entityManager->persist($client);
            $entityManager->flush();  

            $createdCustomer = $entityManager->getRepository(Customer::class)->findBy(['email'=>$email]);
            $customer = $createdCustomer[0]; // Get the customer object

            $user = new User();
            $hashedPassword = $passwordHasher->hashPassword($user, $password);
            $user->setEmail($email);
            $user->setGivenName($givenName);
            $user->setPassword($hashedPassword);
            $user->setTelephone($telephone);
            $user->setActive(true);
            $user->setCustomer($customer);
            $user->setCreatedOn( new \DateTime());

            $entityManager->persist($user);
            $entityManager->flush();

            // SYNC CUSTOMER TO LOYVERSE
            try {
                $logger->info('Syncing new customer to Loyverse', [
                    'customer_id' => $customer->getId(),
                    'email' => $customer->getEmail()
                ]);
                
                $syncResult = $loyverseSyncService->syncCustomerToLoyverse($customer);
                
                if ($syncResult) {
                    $logger->info('Customer synced to Loyverse successfully');
                } else {
                    $logger->warning('Failed to sync customer to Loyverse, but registration succeeded');
                    // Don't fail registration if Loyverse sync fails
                }
            } catch (\Exception $e) {
                $logger->error('Exception syncing customer to Loyverse', [
                    'error' => $e->getMessage()
                ]);
                // Continue - don't fail registration
            }

            $message = 'created successfully';  
        } else {
            $msg = 'The user '.$email . ' is already registered';
            $message = ['status'=>1,'message'=>$msg];
        }

        return $this->json([
            'data' => $message
        ]);
    }
}