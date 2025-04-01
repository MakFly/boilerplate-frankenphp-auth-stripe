<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Payment\SubscriptionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @command app:subscriptions:clean-pending --hours=12
 * @command app:subscriptions:clean-pending --dry-run
 * 
 * @description Nettoie les abonnements restés en pending trop longtemps
 * 
 * @option hours Nombre d'heures après lequel un abonnement pending est considéré comme abandonné
 * @option dry-run Exécuter en mode simulation (sans modifications)
 */
#[AsCommand(
    name: 'app:subscriptions:clean-pending',
    description: 'Nettoie les abonnements restés en pending trop longtemps'
)]
class CleanPendingSubscriptionsCommand extends Command
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'hours',
                null,
                InputOption::VALUE_OPTIONAL,
                'Nombre d\'heures après lequel un abonnement pending est considéré comme abandonné',
                24
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Exécuter en mode simulation (sans modifications)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $hours = (int) $input->getOption('hours');
        $dryRun = $input->getOption('dry-run');

        $io->title('Nettoyage des abonnements en pending');
        
        if ($dryRun) {
            $io->note('Mode simulation activé (aucune modification ne sera effectuée)');
        }
        
        $io->note(sprintf('Recherche des abonnements en pending créés il y a plus de %d heures', $hours));

        try {
            if ($dryRun) {
                $cutoffDate = new \DateTimeImmutable('now - ' . $hours . ' hours');
                $pendingSubscriptions = $this->subscriptionService->findPendingSubscriptionsBeforeDate($cutoffDate);
                $count = count($pendingSubscriptions);
                
                if ($count === 0) {
                    $io->success('Aucun abonnement en pending à nettoyer');
                } else {
                    $io->success(sprintf('%d abonnement(s) en pending seraient nettoyés', $count));
                }
            } else {
                $count = $this->subscriptionService->cleanPendingSubscriptions($hours);
                
                if ($count === 0) {
                    $io->success('Aucun abonnement en pending n\'a été nettoyé');
                } else {
                    $io->success(sprintf('%d abonnement(s) en pending ont été nettoyés', $count));
                }
            }
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Erreur lors du nettoyage des abonnements en pending: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
} 