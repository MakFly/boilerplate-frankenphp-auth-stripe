<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Webhook\WebhookProcessor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:webhook:retry',
    description: 'Retries failed webhook processing'
)]
class WebhookRetryCommand extends Command
{
    public function __construct(
        private readonly WebhookProcessor $webhookProcessor
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Maximum number of webhooks to retry',
                10
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');

        $io->title('Retry failed webhooks');
        $io->note(sprintf('Attempting to retry up to %d failed webhooks', $limit));

        try {
            $count = $this->webhookProcessor->retryFailedWebhooks($limit);
            
            if ($count === 0) {
                $io->success('No failed webhooks to retry or all retries failed');
            } else {
                $io->success(sprintf('Successfully retried %d webhook(s)', $count));
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Error during webhook retry: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 