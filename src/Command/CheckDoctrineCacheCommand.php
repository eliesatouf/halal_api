<?php
// src/Command/CheckDoctrineCacheCommand.php

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:check-doctrine-cache',
    description: 'Check Doctrine cache configuration',
)]
class CheckDoctrineCacheCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $configuration = $this->entityManager->getConfiguration();
        
        $io->title('Doctrine Cache Configuration');
        
        // Check metadata cache
        $metadataCache = $configuration->getMetadataCache();
        $io->writeln('Metadata Cache: ' . get_class($metadataCache));
        
        // Check query cache
        $queryCache = $configuration->getQueryCache();
        $io->writeln('Query Cache: ' . get_class($queryCache));
        
        // Check result cache
        $resultCache = $configuration->getResultCache();
        $io->writeln('Result Cache: ' . get_class($resultCache));
        
        // Check if APCu is available
        $io->section('APCu Status');
        if (function_exists('apcu_enabled') && apcu_enabled()) {
            $io->success('APCu is enabled');
            
            $info = apcu_cache_info(true);
            $stats = apcu_sma_info();
            
            $io->writeln('Total Memory: ' . round($stats['num_seg'] * $stats['seg_size'] / 1024 / 1024, 2) . ' MB');
            $io->writeln('Available Memory: ' . round($stats['avail_mem'] / 1024 / 1024, 2) . ' MB');
            $io->writeln('Cached entries: ' . $info['num_entries']);
            $io->writeln('Cache hits: ' . $info['num_hits']);
            $io->writeln('Cache misses: ' . $info['num_misses']);
            $io->writeln('Hit rate: ' . round(($info['num_hits'] / max(1, ($info['num_hits'] + $info['num_misses']))) * 100, 2) . '%');
        } else {
            $io->error('APCu is not enabled');
            
            // Check if extension is loaded
            if (extension_loaded('apcu')) {
                $io->note('APCu extension is loaded but not enabled for CLI');
                $io->note('Check apc.enable_cli in your CLI php.ini');
            } else {
                $io->note('APCu extension is not installed');
            }
        }
        
        // Check Symfony cache pools
        $io->section('Cache Pools Status');
        $io->writeln('Check your cache pools are configured:');
        $io->writeln('  - settings.cache');
        $io->writeln('  - doctrine.metadata_cache_pool');
        $io->writeln('  - doctrine.result_cache_pool');
        $io->writeln('  - doctrine.system_cache_pool');
        
        return Command::SUCCESS;
    }
}