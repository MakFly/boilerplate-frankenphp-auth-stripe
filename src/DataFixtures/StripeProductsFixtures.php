<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\StripeProducts;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class StripeProductsFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $plans = [
            [
                'id' => 'basic',
                'name' => 'Basic Plan',
                'description' => 'Essential features for individuals',
                'price' => [
                    'monthly' => 9.99,
                    'annual' => 99.99,
                ],
                'features' => [
                    'Access to all basic features',
                    'Priority support during business hours',
                    'Up to 10GB storage',
                    'Monthly newsletter'
                ]
            ],
            [
                'id' => 'premium',
                'name' => 'Premium Plan',
                'description' => 'Advanced features for professionals',
                'price' => [
                    'monthly' => 19.99,
                    'annual' => 199.99,
                ],
                'features' => [
                    'Everything in Basic',
                    'Priority 24/7 support',
                    'Up to 100GB storage',
                    'Weekly exclusive content',
                    'Early access to new features',
                    'API access',
                    'Custom integrations'
                ]
            ]
        ];

        foreach ($plans as $plan) {
            $product = new StripeProducts();
            $product->setPlanId($plan['id'])
                ->setName($plan['name'])
                ->setDescription($plan['description'])
                ->setMonthlyPrice($plan['price']['monthly'])
                ->setAnnualPrice($plan['price']['annual'])
                ->setFeatures($plan['features']);

            $manager->persist($product);
        }

        $manager->flush();
    }
}