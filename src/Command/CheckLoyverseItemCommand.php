<?php
// src/Command/CheckLoyverseItemCommand.php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Service\SettingsService;
use App\Entity\LoyverseToken;
use Doctrine\ORM\EntityManagerInterface;

#[AsCommand(
    name: 'app:check-loyverse-item',
    description: 'Check Loyverse item details',
)]
class CheckLoyverseItemCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $client,
        private SettingsService $settingsService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get access token
        $storeId = $this->settingsService->get('LOYVERSE_STORE_ID');
        $token = $this->entityManager->getRepository(LoyverseToken::class)
            ->findOneBy(['storedId' => $storeId]);

        if (!$token) {
            $io->error('No Loyverse token found');
            return Command::FAILURE;
        }

        $accessToken = $token->getAccessToken();
        $itemId = '1daa29ee-71ed-4d5c-9478-5e8c1786ce2f';

        $io->info("Checking item: $itemId");

        try {
            $response = $this->client->request('GET', "https://api.loyverse.com/v1.0/items/{$itemId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $accessToken,
                    'Accept' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray();

            $io->success("HTTP Status: $statusCode");
            $io->section('Item Details:');
            $io->writeln('Name: ' . ($data['item_name'] ?? 'N/A'));
            $io->writeln('ID: ' . ($data['id'] ?? 'N/A'));

            $io->section('Variants:');
            if (isset($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $variant) {
                    $io->writeln(sprintf(
                        '  - %s: %s (Price: %s)',
                        $variant['variant_id'] ?? 'N/A',
                        $variant['option1_value'] ?? 'N/A',
                        $variant['default_price'] ?? 'N/A'
                    ));
                }
            }

            $io->section('Full Response:');
            $io->writeln(json_encode($data, JSON_PRETTY_PRINT));

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
