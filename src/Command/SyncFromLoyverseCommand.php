<?php
// src/Command/SyncFromLoyverseCommand.php
namespace App\Command;

use App\Service\LoyverseSyncService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:sync-from-loyverse')]
class SyncFromLoyverseCommand extends Command
{
    protected static $defaultName = 'app:sync-from-loyverse';
    
    public function __construct(private LoyverseSyncService $syncService)
    {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this->setDescription('Sync all data from Loyverse to local database');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Syncing from Loyverse');

        //1. Sync Categories
        // $io->section('Syncing Categories');
        // $categoryCount = $this->syncService->syncCategoriesFromLoyverse();
        // $io->text("Categories: {$categoryCount} synced");
        
        // // 2. Sync Items
        $io->section('Syncing Items');
        $itemCount = $this->syncService->syncItemsFromLoyverse();
        $io->text("Items: {$itemCount} synced");
        
        // // 3. Sync Customers
        // $io->section('Syncing Customers');
        // $customerCount = $this->syncService->syncCustomersFromLoyverse();
        // $io->text("Customers: {$customerCount} synced");

        // // 4. Sync Payment Types
        // $io->section('Syncing Payment Types');
        // $PaymentTypeCount = $this->syncService->syncPaymentTypesFromLoyverse();
        // $io->text("Paymen Types: {$PaymentTypeCount} synced");

        // 5. Sync Modifiers
        // $io->section('Syncing Modifiers');
        // $ModifiersCount = $this->syncService->syncModifiersFromLoyverse();
        // $io->text("Modifiers : {$ModifiersCount} synced");
        
        $io->success('Sync completed!');
        
        return Command::SUCCESS;
    }
}