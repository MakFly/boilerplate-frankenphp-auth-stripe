<?php

declare(strict_types=1);

namespace App\Service\Stripe;

use App\Entity\StripeProducts;
use App\Repository\StripeProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Product;
use Stripe\Price;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class StripeProductService
{
    private StripeClient $stripe;

    public function __construct(
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private readonly string $stripeSecretKey,
        private readonly EntityManagerInterface $entityManager,
        private readonly StripeProductsRepository $productsRepository
    ) {
        $this->stripe = new StripeClient($this->stripeSecretKey);
    }

    /**
     * Vérifie si le produit existe déjà dans Stripe
     */
    public function checkStripeProducts(): bool
    {
        $stripeProducts = $this->stripe->products->all(['active' => true, 'limit' => 1]);
        return !empty($stripeProducts->data);
    }

    /**
     * Synchronise les produits locaux vers Stripe, en vérifiant d'abord l'existence de produits sur Stripe
     */
    public function syncToStripe(StripeProducts $product, bool $force = false): void
    {
        // Si force est true et qu'il y a des produits sur Stripe, on ne fait rien ici
        // La synchronisation sera gérée par syncFromStripe
        if ($force && $this->checkStripeProducts()) {
            return;
        }

        // Sinon, on procède à la synchronisation vers Stripe
        $stripeProduct = $this->createOrUpdateStripeProduct($product);
        $product->setStripeProductId($stripeProduct->id);

        // Créer ou mettre à jour les prix
        $monthlyPrice = $this->createOrUpdatePrice($product, $stripeProduct->id, 'month');
        $annualPrice = $this->createOrUpdatePrice($product, $stripeProduct->id, 'year');

        $product->setStripeMonthlyPriceId($monthlyPrice->id);
        $product->setStripeAnnualPriceId($annualPrice->id);

        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    /**
     * Supprime tous les produits locaux avant de synchroniser depuis Stripe
     */
    public function syncAllFromStripeWithCleanup(): void
    {
        // Supprime tous les produits existants en base
        $existingProducts = $this->productsRepository->findAll();
        foreach ($existingProducts as $product) {
            $this->entityManager->remove($product);
        }
        $this->entityManager->flush();

        // Synchronise les produits depuis Stripe
        $this->syncAllFromStripe();
    }

    public function syncFromStripe(string $productId): void
    {
        $stripeProduct = $this->stripe->products->retrieve($productId);
        $prices = $this->stripe->prices->all(['product' => $productId]);

        $product = $this->productsRepository->findOneByPlanId($stripeProduct->metadata['plan_id'] ?? '') 
            ?? new StripeProducts();

        $features = isset($stripeProduct->metadata['features']) 
            ? json_decode($stripeProduct->metadata['features'], true) 
            : [];

        $product->setPlanId($stripeProduct->metadata['plan_id'] ?? $stripeProduct->id)
            ->setName($stripeProduct->name)
            ->setDescription($stripeProduct->description ?? '')
            ->setStripeProductId($stripeProduct->id)
            ->setFeatures($features);

        foreach ($prices->data as $price) {
            $amount = $price->unit_amount / 100;
            $recurring = $price->recurring;
            if ($recurring && isset($recurring['interval']) && $recurring['interval'] === 'month') {
                $product->setMonthlyPrice($amount)
                    ->setStripeMonthlyPriceId($price->id);
            } elseif ($recurring && isset($recurring['interval']) && $recurring['interval'] === 'year') {
                $product->setAnnualPrice($amount)
                    ->setStripeAnnualPriceId($price->id);
            }
        }

        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    public function syncAllFromStripe(): void
    {
        $stripeProducts = $this->stripe->products->all(['active' => true]);

        foreach ($stripeProducts->data as $stripeProduct) {
            try {
                $this->syncFromStripe($stripeProduct->id);
            } catch (\Exception $e) {
                // Log l'erreur mais continue la synchronisation
                error_log(sprintf(
                    'Erreur lors de la synchronisation du produit %s: %s',
                    $stripeProduct->id,
                    $e->getMessage()
                ));
            }
        }
    }

    private function createOrUpdateStripeProduct(StripeProducts $product): Product
    {
        $data = [
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'metadata' => [
                'plan_id' => $product->getPlanId(),
                'features' => json_encode($product->getFeatures()),
            ],
        ];

        if ($product->getStripeProductId()) {
            return $this->stripe->products->update($product->getStripeProductId(), $data);
        }

        return $this->stripe->products->create($data);
    }

    private function createOrUpdatePrice(StripeProducts $product, string $productId, string $interval): Price
    {
        $amount = $interval === 'month' ? $product->getMonthlyPrice() : $product->getAnnualPrice();
        $priceId = $interval === 'month' ? $product->getStripeMonthlyPriceId() : $product->getStripeAnnualPriceId();

        $data = [
            'currency' => 'eur',
            'product' => $productId,
            'unit_amount' => (int) ($amount * 100),
            'recurring' => ['interval' => $interval],
        ];

        if ($priceId) {
            // Les prix ne peuvent pas être mis à jour dans Stripe, on doit en créer un nouveau
            $this->stripe->prices->update($priceId, ['active' => false]);
            return $this->stripe->prices->create($data);
        }

        return $this->stripe->prices->create($data);
    }
}