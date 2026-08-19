<?php
// src/Command/FixLoyverseVariantIdsCommand.php
namespace App\Command;

use App\Entity\Dish;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:fix-loyverse-variant-ids')]
class FixLoyverseVariantIdsCommand extends Command
{
    protected static $defaultName = 'app:fix-loyverse-variant-ids';
    
    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // For now, manually set the variant ID for known dishes
        $dishes = $this->entityManager->getRepository(Dish::class)
            ->findBy(['loyverseItemId' => '9a8ca0c5-4298-457f-878c-5ff28fe44e3e']);
        
        foreach ($dishes as $dish) {
            $dish->setLoyverseVariantId('77201397-d96c-4f48-9199-d9caacf76d50');
            $output->writeln("Updated {$dish->getName()} with variant ID");
        }
        
        //$this->entityManager->flush();
        $output->writeln('held!');
        
        return Command::SUCCESS;
    }
}