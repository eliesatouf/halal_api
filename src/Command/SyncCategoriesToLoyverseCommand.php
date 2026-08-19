<?php
// src/Command/SyncCategoriesToLoyverseCommand.php
namespace App\Command;

use App\Entity\Category;
use App\Service\LoyverseSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(    
    name: 'app:sync-categories-to-loyverse',
    description: 'Sync categories to Loyverse'
)]
class SyncCategoriesToLoyverseCommand extends Command
{
    
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoyverseSyncService $syncService
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Syncing Categories to Loyverse');
        
        // First, fetch existing categories from Loyverse
        $io->section('Fetching existing Loyverse categories...');
        $existingCategories = [];
        
        try {
            $existingCategories = $this->syncService->fetchLoyverseCategories();
            $io->success(sprintf('Found %d existing categories in Loyverse', count($existingCategories)));
        } catch (\Exception $e) {
            $io->error('Failed to fetch Loyverse categories: ' . $e->getMessage());
            return Command::FAILURE;
        }
        
        // Get all active categories from your database
        $categories = $this->entityManager->getRepository(Category::class)
            ->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
        
        $io->section(sprintf('Syncing %d categories...', count($categories)));
        
        $successCount = 0;
        $failCount = 0;
        
        foreach ($categories as $category) {
            $io->write(sprintf('Syncing "%s"... ', $category->getName()));
            
            try {
                $loyverseId = $this->syncService->syncCategoryToLoyverse($category, $existingCategories);
                
                if ($loyverseId) {
                    $category->setLoyverseCategoryId($loyverseId);
                    $io->writeln('<info>✓</info>');
                    $successCount++;
                } else {
                    $io->writeln('<comment>⚠ Already exists</comment>');
                }
            } catch (\Exception $e) {
                $io->writeln('<error>✗ ' . $e->getMessage() . '</error>');
                $failCount++;
            }
        }
        
        $this->entityManager->flush();
        
        $io->success(sprintf(
            'Completed: %d succeeded, %d failed, %d total',
            $successCount,
            $failCount,
            count($categories)
        ));
        
        return Command::SUCCESS;
    }
}