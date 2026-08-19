<?php
namespace App\Command;

use App\Service\LoyverseSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\Dish;

#[AsCommand(
    name: 'sync-dishes-to-loyverse',
    description: '',
)]

class SyncDishesToLoyverseCommand extends Command
{
    protected static $defaultName = 'app:sync-dishes-to-loyverse';
    
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoyverseSyncService $syncService
    ) {
        parent::__construct();
    }
    
    protected function configure(): void
    {
        $this->setDescription('Sync all pending dishes to Loyverse');
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Get all dishes that need syncing
        $dishes = $this->entityManager->getRepository(Dish::class)
            ->createQueryBuilder('d')
            //->where('d.syncStatus != :synced OR d.syncStatus IS NULL')
            ->Where('d.active = :active')
            //->setParameter('synced', 'synced')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
        
        $io->section('Syncing ' . count($dishes) . ' dishes to Loyverse');
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($dishes as $dish) {
            $io->write('Syncing: ' . $dish->getName() . '... ');
            
            if ($this->syncService->syncDishToLoyverse($dish)) {
                $io->writeln('<info>✓</info>');
                $successCount++;
            } else {
                $io->writeln('<error>✗</error>');
                $failCount++;
            }
        }
        
        $io->success(sprintf('Completed: %d succeeded, %d failed', $successCount, $failCount));
        
        return Command::SUCCESS;
    }
}