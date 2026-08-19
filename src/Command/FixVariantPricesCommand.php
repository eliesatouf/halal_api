<?php
// src/Command/FixVariantPricesCommand.php
namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Entity\Dish;
use App\Entity\PriceVariant;


#[AsCommand(name: 'app:fix-variant-prices')]
class FixVariantPricesCommand extends Command
{
    protected static $defaultName = 'app:fix-variant-prices';

    public function __construct(private EntityManagerInterface $entityManager)
    {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Fixing Variant Prices');
        
        $variants = $this->entityManager->getRepository(PriceVariant::class)
            ->createQueryBuilder('pv')
            ->join('pv.dish', 'd')
            ->where('pv.price = 0')
            ->andWhere('d.active = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
        
        $io->section(sprintf('Found %d variants with price = 0', count($variants)));
        
        $updated = 0;
        foreach ($variants as $variant) {
            $dish = $variant->getDish();
            $size = $variant->getSize();
            
            // Determine new price based on dish name and size
            $newPrice = $this->calculatePrice($dish->getName(), $size);
            
            $variant->setPrice($newPrice);
            $updated++;
            
            $io->writeln(sprintf(
                'Updated: %s - %s: %s → %s',
                $dish->getName(),
                $size,
                '0',
                $newPrice
            ));
        }
        
        $this->entityManager->flush();
        
        $io->success(sprintf('Updated %d variant prices', $updated));
        return Command::SUCCESS;
    }
    
    private function calculatePrice(string $dishName, string $size): float
    {
        // Base prices by dish type
        $basePrices = [
            'Malaya' => 120,
            'Dark Malaya' => 130,
            'Tadhana' => 140,
            'Paubaya' => 140,
            'Iced Tea' => 80,
            'Latte' => 90,
            'Spanish Latte' => 100,
            'Caramel Macchiato' => 100,
            'Americano' => 65,
            'Matcha' => 110,
        ];
        
        // Size multipliers
        $sizeMultipliers = [
            'Small' => 1.0,
            'Medium' => 1.2,
            'Large' => 1.4,
            'Regular' => 1.0,
            'Grande' => 1.3,
            'Venti' => 1.5,
        ];
        
        // Find base price
        $basePrice = 100; // default
        foreach ($basePrices as $key => $price) {
            if (stripos($dishName, $key) !== false) {
                $basePrice = $price;
                break;
            }
        }
        
        // Apply size multiplier
        $multiplier = $sizeMultipliers[$size] ?? 1.0;
        
        return round($basePrice * $multiplier, 2);
    }
}