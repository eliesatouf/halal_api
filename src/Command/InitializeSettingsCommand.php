<?php

namespace App\Command;

use App\Entity\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:initialize-settings',
    description: 'Populates the Settings table with values from environment variables',
)]
class InitializeSettingsCommand extends Command
{
    private const SETTINGS_TO_MIGRATE = [
        'APP_NAME',
        'BRAND_NAME',
        'BRAND_LOCATION',
        'CONTACT_NUMBER',
        'CONTACT_EMAIL',
        'INFO_EMAIL',
        'LOGO_PATH',
        'FRONTEND_URL',
        'MERCURE_URL',
        'LOYVERSE_CLIENT_ID',
        'LOYVERSE_CLIENT_SECRET',
        'LOYVERSE_REDIRECT_URI',
        'LOYVERSE_STORE_ID',
        'FIRST_ORDER_ENABLED',
        'FIRST_ORDER_DISCOUNT_PERCENTAGE',
        'PASSWORD_RESET',
        'MAILER_FROM_NAME',
        'SHARED_FOLDER',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $repository = $this->entityManager->getRepository(Settings::class);

        $io->title('Initializing Settings from .env');

        foreach (self::SETTINGS_TO_MIGRATE as $key) {
            $value = $_ENV[$key] ?? null;

            if ($value === null) {
                $io->warning(sprintf('Variable %s not found in .env, skipping.', $key));
                continue;
            }

            $setting = $repository->findOneBy(['settingKey' => $key]);

            if (!$setting) {
                $setting = new Settings();
                $setting->setSettingKey($key);
                $this->entityManager->persist($setting);
                $io->note(sprintf('Creating new setting: %s', $key));
            } else {
                $io->note(sprintf('Updating existing setting: %s', $key));
            }

            $setting->setValue((string) $value);
        }

        $this->entityManager->flush();

        $io->success('Settings initialized successfully!');

        return Command::SUCCESS;
    }
}
