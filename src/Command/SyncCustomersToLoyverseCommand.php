<?php
// src/Command/SyncCustomersToLoyverseCommand.php
namespace App\Command;

use App\Entity\Customer;
use App\Service\LoyverseSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:sync-customers-to-loyverse')]
class SyncCustomersToLoyverseCommand extends Command
{
    protected static $defaultName = 'app:sync-customers-to-loyverse';
    
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoyverseSyncService $syncService
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $customers = $this->entityManager->getRepository(Customer::class)->findAll();
        
        foreach ($customers as $customer) {
            $output->write("Syncing {$customer->getName()}... ");
            $result = $this->syncService->syncCustomerToLoyverse($customer);
            $output->writeln($result ? '✓' : '✗');
        }
        
        return Command::SUCCESS;
    }
}