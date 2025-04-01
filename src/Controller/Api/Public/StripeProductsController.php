<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Repository\StripeProductsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/stripe/products', name: 'api_public_stripe_products_')]
class StripeProductsController extends AbstractController
{
    public function __construct(
        private readonly StripeProductsRepository $productsRepository
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $products = $this->productsRepository->findAll();

        return $this->json($products, 200, [], ['groups' => ['public']]);
    }

    #[Route('/{planId}', name: 'show', methods: ['GET'])]
    public function show(string $planId): JsonResponse
    {
        $product = $this->productsRepository->findOneByPlanId($planId);

        if (!$product) {
            return $this->json(['error' => 'Plan not found'], 404);
        }

        return $this->json($product, 200, [], ['groups' => ['public']]);
    }
}