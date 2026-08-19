<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use App\Service\OrderEmailService;

#[AsCommand(
    name: 'mail_test',
    description: 'Add a short description for your command',
)]

class MailTestCommand extends Command
{
    private $orderEmailService;
    public function __construct(OrderEmailService $orderEmailService)
    {
        parent::__construct();
        $this->orderEmailService = $orderEmailService;
    }

    protected function configure(): void
    {
        // $this
        //     ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
        //     ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        // ;
        $this->setDescription('Test the order email service');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Testing email service...');
        
        // You might need to create a mock order for testing
        $output->writeln('Email service is ready. Create an order via API to test.');

        return Command::SUCCESS;
    }
}
