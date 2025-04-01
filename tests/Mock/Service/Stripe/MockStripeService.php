<?php

declare(strict_types=1);

namespace App\Tests\Mock\Service\Stripe;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Mock implementation of StripeService for testing
 * This avoids making actual Stripe API calls during tests
 */
final class MockStripeService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private readonly string $stripeSecretKey,
        private readonly ?CacheInterface $cache = null,
    ) {
        // No real Stripe client initialization here
    }

    public function createStripeAccount(User $user): array
    {
        // Just return mock data
        if (!$user->getStripeCustomerId()) {
            $mockCustomerId = 'cus_mock_' . uniqid();
            $user->setStripeCustomerId($mockCustomerId);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return [
                'id' => $mockCustomerId,
                'email' => $user->getEmail(),
                'name' => $user->getUsername(),
            ];
        }

        return [
            'id' => $user->getStripeCustomerId(),
            'email' => $user->getEmail(),
            'name' => $user->getUsername(),
        ];
    }

    public function getStripeAccount(string $accountId): array
    {
        // Return mock data
        return [
            'id' => $accountId,
            'object' => 'customer',
        ];
    }

    public function updateStripeAccount(User $user): array
    {
        // Return mock data
        return [
            'id' => $user->getStripeCustomerId(),
            'email' => $user->getEmail(),
            'name' => $user->getUsername(),
        ];
    }
    
    public function createBillingPortalSession(string $customerId, string $returnUrl): array
    {
        // Return mock data
        return [
            'id' => 'bps_mock_' . uniqid(),
            'url' => 'https://mock-billing-portal.example.com/' . $customerId,
        ];
    }
} 