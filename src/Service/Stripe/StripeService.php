<?php

declare(strict_types=1);

namespace App\Service\Stripe;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class StripeService
{
    private StripeClient $stripeClient;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private readonly string $stripeSecretKey,
    ) {
        $this->stripeClient = new StripeClient($this->stripeSecretKey);
    }

    /**
     * @throws ApiErrorException
     */
    public function createStripeAccount(User $user): array
    {
        if (!$user->getStripeCustomerId()) {
            $customer = $this->stripeClient->customers->create([
                'email' => $user->getEmail(),
                'name' => $user->getUsername(),
                'metadata' => [
                    'user_id' => $user->getId(),
                ],
            ]);

            $user->setStripeCustomerId($customer->id);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $customer->toArray();
        }

        return $this->getStripeAccount($user->getStripeCustomerId());
    }

    /**
     * @throws ApiErrorException
     */
    public function getStripeAccount(string $accountId): array
    {
        $customer = $this->stripeClient->customers->retrieve($accountId);
        
        return $customer->toArray();
    }
}
