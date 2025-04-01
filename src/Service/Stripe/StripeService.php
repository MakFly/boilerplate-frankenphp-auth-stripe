<?php

declare(strict_types=1);

namespace App\Service\Stripe;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class StripeService
{
    private StripeClient $stripeClient;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(env: 'STRIPE_SECRET_KEY')]
        private readonly string $stripeSecretKey,
        private readonly ?CacheInterface $cache = null,
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
     * @return array
     */
    public function getStripeAccount(string $accountId): array
    {
        $customer = $this->stripeClient->customers->retrieve($accountId);
        
        return $customer->toArray();
    }

    /**
     * @throws ApiErrorException
     * @return array
     */
    public function updateStripeAccount(User $user): array
    {
        $customer = $this->stripeClient->customers->update($user->getStripeCustomerId(), [
            'email' => $user->getEmail(),
            'name' => $user->getUsername(),
        ]);

        return $customer->toArray();
    }
    
    /**
     * Creates a billing portal session for a Stripe customer
     * 
     * @param string $customerId The Stripe customer ID
     * @param string $returnUrl The URL to which the customer will be redirected after they are done
     * @return array The session data with id and url
     * @throws ApiErrorException
     */
    public function createBillingPortalSession(string $customerId, string $returnUrl): array
    {
        // Utiliser un cache de courte durée (5 minutes) pour éviter de créer trop de sessions
        if ($this->cache) {
            $cacheKey = 'billing_portal_session_' . md5($customerId . $returnUrl);
            
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($customerId, $returnUrl) {
                // Définir la durée de vie du cache à 5 minutes
                $item->expiresAfter(300);
                
                // Créer une nouvelle session
                $session = $this->stripeClient->billingPortal->sessions->create([
                    'customer' => $customerId,
                    'return_url' => $returnUrl,
                    'configuration' => $this->getBillingPortalConfiguration(),
                ]);
                
                return [
                    'id' => $session->id,
                    'url' => $session->url,
                ];
            });
        }
        
        // Comportement original si le cache n'est pas disponible
        $session = $this->stripeClient->billingPortal->sessions->create([
            'customer' => $customerId,
            'return_url' => $returnUrl,
            'configuration' => $this->getBillingPortalConfiguration(),
        ]);
        
        return [
            'id' => $session->id,
            'url' => $session->url,
        ];
    }

    /**
     * Creates or retrieves a billing portal configuration
     * 
     * @return string The billing portal configuration ID
     * @throws ApiErrorException
     */
    private function getBillingPortalConfiguration(): string
    {
        // Utiliser le cache si disponible
        if ($this->cache) {
            $cacheKey = 'stripe_billing_portal_config';
            
            // Tenter de récupérer l'ID de configuration du cache
            return $this->cache->get($cacheKey, function (ItemInterface $item) {
                // Définir la durée de vie du cache à 24 heures
                $item->expiresAfter(86400);
                
                // Chercher les configurations existantes
                $configurations = $this->stripeClient->billingPortal->configurations->all(['limit' => 1]);
                
                // Utiliser la configuration existante si disponible
                if (!empty($configurations->data)) {
                    return $configurations->data[0]->id;
                }
                
                // Sinon, créer une nouvelle configuration
                $configuration = $this->stripeClient->billingPortal->configurations->create([
                    'business_profile' => [
                        'headline' => 'Gérez votre abonnement',
                        'privacy_policy_url' => 'https://example.com/privacy',
                        'terms_of_service_url' => 'https://example.com/terms',
                    ],
                    'features' => [
                        'customer_update' => [
                            'allowed_updates' => ['email', 'address', 'phone', 'shipping', 'tax_id'],
                            'enabled' => true,
                        ],
                        'invoice_history' => [
                            'enabled' => true,
                        ],
                        'payment_method_update' => [
                            'enabled' => true,
                        ],
                        'subscription_cancel' => [
                            'enabled' => true,
                            'mode' => 'at_period_end',
                            'proration_behavior' => 'none',
                        ],
                        'subscription_update' => [
                            'enabled' => true,
                            'default_allowed_updates' => ['price', 'quantity', 'promotion_code'],
                            'proration_behavior' => 'always_invoice',
                            'products' => $this->getProductsConfiguration(),
                        ],
                    ],
                ]);
                
                return $configuration->id;
            });
        }
        
        // Comportement original si le cache n'est pas disponible
        $configurations = $this->stripeClient->billingPortal->configurations->all(['limit' => 1]);
        
        // Utiliser la configuration existante si disponible
        if (!empty($configurations->data)) {
            return $configurations->data[0]->id;
        }
        
        // Sinon, créer une nouvelle configuration
        $configuration = $this->stripeClient->billingPortal->configurations->create([
            'business_profile' => [
                'headline' => 'Gérez votre abonnement',
                'privacy_policy_url' => 'https://example.com/privacy',
                'terms_of_service_url' => 'https://example.com/terms',
            ],
            'features' => [
                'customer_update' => [
                    'allowed_updates' => ['email', 'address', 'phone', 'shipping', 'tax_id'],
                    'enabled' => true,
                ],
                'invoice_history' => [
                    'enabled' => true,
                ],
                'payment_method_update' => [
                    'enabled' => true,
                ],
                'subscription_cancel' => [
                    'enabled' => true,
                    'mode' => 'at_period_end',
                    'proration_behavior' => 'none',
                ],
                'subscription_update' => [
                    'enabled' => true,
                    'default_allowed_updates' => ['price', 'quantity', 'promotion_code'],
                    'proration_behavior' => 'always_invoice',
                    'products' => $this->getProductsConfiguration(),
                ],
            ],
        ]);
        
        return $configuration->id;
    }
    
    /**
     * Gets the products configuration for the billing portal
     * 
     * @return array Products configuration for the billing portal
     */
    private function getProductsConfiguration(): array
    {
        try {
            // Récupérer tous les produits Stripe actifs
            $stripeProducts = $this->stripeClient->products->all([
                'active' => true,
                'limit' => 100,
            ]);

            $products = [];
            
            // Pour chaque produit, récupérer ses prix
            foreach ($stripeProducts->data as $product) {
                $prices = $this->stripeClient->prices->all([
                    'product' => $product->id,
                    'active' => true,
                    'limit' => 100,
                ]);
                
                // Ne pas inclure les produits sans prix actifs
                if (empty($prices->data)) {
                    continue;
                }
                
                // Collecter les IDs de prix
                $priceIds = array_map(function ($price) {
                    return $price->id;
                }, $prices->data);
                
                // Ajouter à la configuration
                $products[] = [
                    'product' => $product->id,
                    'prices' => $priceIds,
                ];
            }
            
            return $products;
        } catch (\Exception $e) {
            // En cas d'erreur, log et retourne un tableau vide
            return [];
        }
    }
}
