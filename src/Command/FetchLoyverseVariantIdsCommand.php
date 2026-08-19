<?php
// src/Command/FetchLoyverseVariantIdsCommand.php
namespace App\Command;

use App\Entity\Dish;
use App\Service\LoyverseSyncService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:fix-variant-prices')]
class FetchLoyverseVariantIdsCommand extends Command
{
    protected static $defaultName = 'app:fetch-loyverse-variant-ids';
    
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoyverseSyncService $syncService
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dishes = $this->entityManager->getRepository(Dish::class)
            ->findBy(['loyverseItemId' => 'NOT NULL']);
        
        foreach ($dishes as $dish) {
            try {
                $output->write("Fetching variant for {$dish->getName()}... ");
                
                // Make API call to get item details
                $httpClient = $this->syncService->getClient(); // You need to add getClient() method
                $response = $httpClient->request('GET', "items/{$dish->getLoyverseItemId()}");
                
                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    
                    if (isset($data['variants'][0]['variant_id'])) {
                        $dish->setLoyverseVariantId($data['variants'][0]['variant_id']);
                        $output->writeln("OK - {$data['variants'][0]['variant_id']}");
                    } else {
                        $output->writeln("NO VARIANTS");
                    }
                } else {
                    $output->writeln("FAILED - {$response->getStatusCode()}");
                }
                
            } catch (\Exception $e) {
                $output->writeln("ERROR - {$e->getMessage()}");
            }
        }
        
        $this->entityManager->flush();
        $output->writeln("\nDone!");
        
        return Command::SUCCESS;
    }
}