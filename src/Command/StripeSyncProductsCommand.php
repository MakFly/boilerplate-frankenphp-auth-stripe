<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\StripeProductsRepository;
use App\Service\Stripe\StripeProductService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * # Synchronisation automatique (vérifie l'existence dans Stripe)
 ** php bin/console app:stripe:sync-products

 ** # Force la synchronisation vers Stripe
 ** php bin/console app:stripe:sync-products -d to-stripe -f

 ** # Importe tous les produits depuis Stripe
 ** php bin/console app:stripe:sync-products -d from-stripe

 ** # Importe un produit spécifique depuis Stripe
 ** php bin/console app:stripe:sync-products -d from-stripe -p prod_xyz123

 ** # Pour forcer la synchronisation et récupérer les produits Stripe s'ils existent
 ** php bin/console app:stripe:sync-products -d to-stripe -f
 **
 ** # Pour une synchronisation normale vers Stripe (sans écraser avec les données Stripe)
 ** php bin/console app:stripe:sync-products -d to-stripe
 */
#[AsCommand(
    name: 'app:stripe:sync-products',
    description: 'Synchronise les produits avec Stripe (import/export)',
)]
class StripeSyncProductsCommand extends Command
{
    public function __construct(
        private readonly StripeProductService $stripeProductService,
        private readonly StripeProductsRepository $productsRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'direction',
                'd',
                InputOption::VALUE_REQUIRED,
                'Direction de la synchronisation (to-stripe, from-stripe, ou auto)',
                'auto'
            )
            ->addOption(
                'product-id',
                'p',
                InputOption::VALUE_OPTIONAL,
                'ID du produit Stripe à synchroniser (uniquement pour from-stripe)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Force la synchronisation sans vérification'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $direction = $input->getOption('direction');
        $force = $input->getOption('force');

        if ($direction === 'auto') {
            return $this->autoSync($io, $force);
        }

        if ($direction === 'to-stripe') {
            return $this->syncToStripe($io, $force);
        }

        if ($direction === 'from-stripe') {
            return $this->syncFromStripe($io, $input->getOption('product-id'));
        }

        $io->error('Direction invalide. Utilisez "auto", "to-stripe" ou "from-stripe".');
        return Command::FAILURE;
    }

    private function autoSync(SymfonyStyle $io, bool $force = false): int
    {
        $localProducts = $this->productsRepository->findAll();

        if (empty($localProducts)) {
            $io->note('Aucun produit local trouvé. Tentative de synchronisation depuis Stripe...');
            return $this->syncAllFromStripe($io);
        }

        foreach ($localProducts as $product) {
            try {
                // Vérifier si le produit existe déjà dans Stripe
                if (!$force && $product->getStripeProductId()) {
                    // Synchroniser depuis Stripe si le produit existe
                    $this->stripeProductService->syncFromStripe($product->getStripeProductId());
                    $io->success(sprintf('Produit "%s" mis à jour depuis Stripe', $product->getName()));
                } else {
                    // Créer ou mettre à jour dans Stripe si le produit n'existe pas
                    $this->stripeProductService->syncToStripe($product);
                    $io->success(sprintf('Produit "%s" synchronisé vers Stripe', $product->getName()));
                }
            } catch (\Exception $e) {
                $io->error(sprintf('Erreur lors de la synchronisation du produit "%s": %s', $product->getName(), $e->getMessage()));
                if (!$force) {
                    return Command::FAILURE;
                }
            }
        }

        return Command::SUCCESS;
    }

    private function syncToStripe(SymfonyStyle $io, bool $force = false): int
    {
        try {
            // Vérifier si des produits existent sur Stripe
            if ($force && $this->stripeProductService->checkStripeProducts()) {
                $io->note('Des produits existent sur Stripe. Synchronisation depuis Stripe avec nettoyage de la base de données...');
                $this->stripeProductService->syncAllFromStripeWithCleanup();
                $io->success('Tous les produits ont été synchronisés depuis Stripe avec succès');
                return Command::SUCCESS;
            }

            // Si pas de produits sur Stripe ou pas de force, on continue avec la synchronisation normale
            $products = $this->productsRepository->findAll();

            if (empty($products)) {
                $io->warning('Aucun produit trouvé dans la base de données.');
                return Command::SUCCESS;
            }

            foreach ($products as $product) {
                try {
                    $this->stripeProductService->syncToStripe($product, $force);
                    $io->success(sprintf('Produit "%s" synchronisé avec Stripe', $product->getName()));
                } catch (\Exception $e) {
                    $io->error(sprintf('Erreur lors de la synchronisation du produit "%s": %s', $product->getName(), $e->getMessage()));
                    if (!$force) {
                        return Command::FAILURE;
                    }
                }
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur inattendue : %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }

    private function syncFromStripe(SymfonyStyle $io, ?string $productId): int
    {
        if (!$productId) {
            return $this->syncAllFromStripe($io);
        }

        try {
            $this->stripeProductService->syncFromStripe($productId);
            $io->success('Produit Stripe synchronisé avec succès');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la synchronisation depuis Stripe: %s', $e->getMessage()));
            $io->error('Vérifiez que l\'ID du produit est correct et que vous avez accès à l\'API Stripe.');
            return Command::FAILURE;
        }
    }

    private function syncAllFromStripe(SymfonyStyle $io): int
    {
        try {
            $this->stripeProductService->syncAllFromStripe();
            $io->success('Tous les produits Stripe ont été synchronisés avec succès');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Erreur lors de la synchronisation depuis Stripe: %s', $e->getMessage()));
            return Command::FAILURE;
        }
    }
}
