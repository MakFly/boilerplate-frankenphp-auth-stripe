<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\StripeProducts;
use App\Repository\StripeProductsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Product;
use Stripe\Price;
use Stripe\StripeClient;

class StripeProductService
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly EntityManagerInterface $entityManager,
        private readonly StripeProductsRepository $productsRepository
    ) {
    }

    public function syncToStripe(StripeProducts $product): void
    {
        // Créer ou mettre à jour le produit Stripe
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

    public function syncFromStripe(string $productId): void
    {
        $stripeProduct = $this->stripe->products->retrieve($productId);
        $prices = $this->stripe->prices->all(['product' => $productId]);

        $product = $this->productsRepository->findOneByPlanId($stripeProduct->metadata['plan_id'] ?? '') 
            ?? new StripeProducts();

        $product->setPlanId($stripeProduct->metadata['plan_id'] ?? $stripeProduct->id)
            ->setName($stripeProduct->name)
            ->setDescription($stripeProduct->description ?? '')
            ->setStripeProductId($stripeProduct->id)
            ->setFeatures(json_decode($stripeProduct->metadata['features'] ?? '[]', true));

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

    private function createOrUpdateStripeProduct(StripeProducts $product): Product
    {
        // Seuls les paramètres valides de l'API Stripe sont inclus ici
        $data = [
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'metadata' => [
                'plan_id' => $product->getPlanId(),
                'features' => json_encode($product->getFeatures(), JSON_UNESCAPED_UNICODE)
            ]
        ];

        if ($product->getStripeProductId()) {
            return $this->stripe->products->update($product->getStripeProductId(), $data);
        }

        return $this->stripe->products->create([
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'default_price_data' => [
                'currency' => 'eur',
                'unit_amount' => (int) ($product->getMonthlyPrice() * 100),
                'recurring' => ['interval' => 'month']
            ],
            'metadata' => [
                'plan_id' => $product->getPlanId(),
                'features' => json_encode($product->getFeatures(), JSON_UNESCAPED_UNICODE)
            ]
        ]);
    }

    private function createOrUpdatePrice(StripeProducts $product, string $productId, string $interval): Price
    {
        $amount = $interval === 'month' ? $product->getMonthlyPrice() : $product->getAnnualPrice();
        $priceId = $interval === 'month' ? $product->getStripeMonthlyPriceId() : $product->getStripeAnnualPriceId();

        $data = [
            'currency' => 'eur',
            'product' => $productId,
            'unit_amount' => (int) ($amount * 100),
            'recurring' => ['interval' => $interval]
        ];

        if ($priceId) {
            // Les prix ne peuvent pas être mis à jour dans Stripe, on doit en créer un nouveau
            $this->stripe->prices->update($priceId, ['active' => false]);
            return $this->stripe->prices->create($data);
        }

        return $this->stripe->prices->create($data);
    }
}