<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Category;
use App\Entity\Settings;
use App\Entity\Landingpage;
use App\Entity\FeaturedItem;
use App\Entity\Offer;
use App\Entity\Modifier;
use App\Entity\ModifierOption;
use App\Repository\SettingsRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[AsCommand(
    name: 'app:cache:warmup:full',
    description: 'Warms up Doctrine cache AND your custom API endpoints',
)]
class FullCacheWarmupCommand extends Command
{
    private string $apiBaseUrl;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private SettingsRepository $settingsRepository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // ===== GET API BASE URL FROM SETTINGS =====
        $this->apiBaseUrl = $this->settingsRepository->getValue('api_base_url');
        
        if (!$this->apiBaseUrl) {
            $io->error('❌ api_base_url not found in settings! Please add it first.');
            $io->note('Run: php bin/console app:settings:set api_base_url https://tidybubbleslaundryMate.com');
            return Command::FAILURE;
        }
        
        // Remove trailing slash if present
        $this->apiBaseUrl = rtrim($this->apiBaseUrl, '/');
        
        $io->title('🔥 Full Cache Warmup - Doctrine + API Endpoints');
        $io->text(sprintf('🌐 Using API Base URL: %s', $this->apiBaseUrl));

        $count = 0;

        // ========== DOCTRINE SECOND-LEVEL CACHE ==========
        $io->section('📦 Warming Doctrine Second-Level Cache');

        // Categories
        $io->text('Loading categories...');
        $categories = $this->entityManager
            ->getRepository(Category::class)
            ->findAll();
        $count += count($categories);
        $io->text(sprintf('✓ %d categories', count($categories)));

        // Settings (cache all settings)
        $io->text('Loading all settings...');
        $settings = $this->settingsRepository->getAllValues();
        $count += count($settings);
        $io->text(sprintf('✓ %d settings', count($settings)));

        // Landingpage
        $io->text('Loading landing pages...');
        $landingpages = $this->entityManager
            ->getRepository(Landingpage::class)
            ->findAll();
        $count += count($landingpages);
        $io->text(sprintf('✓ %d landing pages', count($landingpages)));

        // Featured Items
        $io->text('Loading featured items...');
        $featured = $this->entityManager
            ->getRepository(FeaturedItem::class)
            ->findAll();
        $count += count($featured);
        $io->text(sprintf('✓ %d featured items', count($featured)));

        // Offers (with homepage filter)
        $io->text('Loading homepage offers...');
        $offers = $this->entityManager
            ->getRepository(Offer::class)
            ->findBy(['active' => true, 'assignedToHomePage' => true]);
        $count += count($offers);
        $io->text(sprintf('✓ %d offers', count($offers)));

        // Modifiers
        $io->text('Loading modifiers...');
        $modifiers = $this->entityManager
            ->getRepository(Modifier::class)
            ->findAll();
        $count += count($modifiers);
        $io->text(sprintf('✓ %d modifiers', count($modifiers)));

        // Modifier Options
        $io->text('Loading modifier options...');
        $modifierOptions = $this->entityManager
            ->getRepository(ModifierOption::class)
            ->findAll();
        $count += count($modifierOptions);
        $io->text(sprintf('✓ %d modifier options', count($modifierOptions)));

        // Active Dishes
        $io->text('Loading active dishes with category...');
        $dishes = $this->entityManager
            ->createQuery('
                SELECT d, c
                FROM App\Entity\Dish d
                LEFT JOIN d.category c
                WHERE d.active = true
            ')
            ->setCacheable(true)
            ->getResult();
        $count += count($dishes);
        $io->text(sprintf('✓ %d active dishes with category', count($dishes)));

        // ========== API ENDPOINT WARMUP ==========
        $io->section('🌐 Warming API Endpoints');

        $endpoints = [
            '/api/homepage-data',
            '/api/homepage-deferred',
            '/api/featured_items',
            '/api/categories',
            '/api/dishes?active=true',
            '/api/offers?active=true&assignedToHomePage=true',
            '/api/modifiers',
            '/api/variants',
            '/api/modifier_options',
            '/api/settings?settingKey=working_hours',
            '/api/settings?settingKey=closing_date',
            '/api/settings?settingKey=closing_message',
            '/api/settings?settingKey=re_opening_message',
            '/api/settings?settingKey=first_order_discount.enabled',
            '/api/settings?settingKey=first_order_discount.percentage'
        ];

        $successCount = 0;
        $failCount = 0;

        foreach ($endpoints as $endpoint) {
            try {
                $fullUrl = $this->apiBaseUrl . $endpoint;
                $io->text(sprintf('Calling %s...', $endpoint));
                
                $response = $this->httpClient->request('GET', $fullUrl, [
                    'headers' => [
                        'User-Agent' => 'CacheWarmup/1.0',
                        'Accept' => 'application/json',
                        'X-Warmup-Request' => 'true'
                    ],
                    'timeout' => 30, // Increased timeout
                    'verify_peer' => false, // TEMPORARY: Disable SSL verification for debugging
                    'verify_host' => false,  // TEMPORARY: Disable SSL verification for debugging
                ]);
                
                $statusCode = $response->getStatusCode();
                $content = $response->getContent(false); // false = don't throw on error
                $contentLength = strlen($content);
                
                if ($statusCode === 200) {
                    $io->text(sprintf('✓ %s → 200 OK (%d bytes)', $endpoint, $contentLength));
                    $successCount++;
                } else {
                    $io->warning(sprintf('%s returned HTTP %d', $endpoint, $statusCode));
                    $io->text(sprintf('Response: %s', substr($content, 0, 500)));
                    $failCount++;
                }
            } catch (TransportExceptionInterface $e) {
                $io->error(sprintf('Transport error for %s: %s', $endpoint, $e->getMessage()));
                $failCount++;
            } catch (\Exception $e) {
                $io->error(sprintf('Failed to warm %s: %s', $endpoint, $e->getMessage()));
                $failCount++;
            }
        }

        $io->success([
            '✅ FULL CACHE WARMUP COMPLETE!',
            sprintf('📊 Doctrine: %d entities cached', $count),
            sprintf('🌐 API: %d/%d endpoints warmed successfully', $successCount, count($endpoints)),
            '🚀 First user will have INSTANT response!'
        ]);

        if ($failCount > 0) {
            $io->warning(sprintf('%d endpoints failed. Check the logs above.', $failCount));
            return Command::SUCCESS; // Still return success for Doctrine part
        }

        return Command::SUCCESS;
    }
}