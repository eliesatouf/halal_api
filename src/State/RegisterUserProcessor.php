<?php
// api/src/State/RegisterUserProcessor.php
namespace App\State;

use App\Entity\User;
use App\Entity\Customer;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Psr\Log\LoggerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Service\LoyverseSyncService;

final class RegisterUserProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: 'api_platform.doctrine.orm.state.persist_processor')]
        private ProcessorInterface $persistProcessor,
        private LoggerInterface $logger,
        private UserPasswordHasherInterface $passwordHasher,
        private LoyverseSyncService $loyverseSyncService // Add this
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // 1. Create and configure User
        $user = new User();
        $user->setEmail($data->getEmail())
             ->setPassword($data->getPassword())
             ->setGivenname($data->getGivenname())
             ->setActive($data->isActive())
             ->setCreatedOn(new \DateTime())
             ->setLocalAccount($data->getLocalAccount())
             ->setRoles($data->getRoles());
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $user->getPassword());
        $user->setPassword($hashedPassword);

        // 2. Create and link Customer
        $customer = new Customer();  
        $customer->setUser($user)
             ->setName($data->getGivenname())
             ->setTelephone($data->getTelephone())
             ->setActive(true)
             ->setStatus('new')
             ->setCreatedOn(new \DateTime())
             ->setEmail($data->getEmail());

        $user->setCustomer($customer);

        $localAccount = $data->getLocalAccount();

        // 3. Persist (both will be saved due to cascade)
        $result = $this->persistProcessor->process($user, $operation, $uriVariables, $context);

        if ($result === null) {
            throw new \RuntimeException('Persistence failed');
        }

        // 4. Sync customer to Loyverse (AFTER persistence)
        // if($localAccount=== false)
        // {
        //     try {
        //         $this->logger->info('Syncing new customer to Loyverse', [
        //             'customer_id' => $customer->getId(),
        //             'email' => $customer->getEmail()
        //         ]);
                
        //         $syncResult = $this->loyverseSyncService->syncCustomerToLoyverse($customer);
                
        //         if ($syncResult) {
        //             $this->logger->info('Customer synced to Loyverse successfully');
        //         } else {
        //             $this->logger->warning('Failed to sync customer to Loyverse, but registration succeeded');
        //             // Don't fail registration if Loyverse sync fails
        //         }
        //     } catch (\Exception $e) {
        //         $this->logger->error('Exception syncing customer to Loyverse', [
        //             'error' => $e->getMessage()
        //         ]);
        //         // Continue - don't fail registration
        //     }
        // }

        return $result;
    }
}