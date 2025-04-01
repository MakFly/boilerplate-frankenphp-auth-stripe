<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Attribute\DisablePayment;
use App\Entity\User;
use App\Repository\StripeProductsRepository;
use App\Service\JsonRequestService;
use App\Service\Payment\PaymentServiceFactory;
use App\Service\Stripe\StripeService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/payment', name: 'api_payment_')]
// #[DisablePayment("Fonctionnalité temporairement indisponible")]
class PaymentController extends AbstractController
{
    public function __construct(
        private readonly PaymentServiceFactory $paymentServiceFactory,
        private readonly JsonRequestService $jsonRequestService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
        private readonly StripeService $stripeService
    ) {}

    #[Route('/create-payment-intent', name: 'create_payment_intent', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    // #[DisablePayment("Paiement par carte temporairement indisponible")]
    public function createPaymentIntent(Request $request): JsonResponse
    {
        $data = $this->jsonRequestService->getContent($request);

        $constraints = new Assert\Collection([
            'amount' => [new Assert\NotBlank(), new Assert\Positive()],
            'currency' => [new Assert\Optional(new Assert\Currency())],
            'metadata' => new Assert\Optional(new Assert\Type('array'))
        ]);

        $errors = $this->validator->validate($data, $constraints);

        if (count($errors) > 0) {
            return $this->json(['errors' => (string)$errors], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        // Vérifier que l'utilisateur a un compte Stripe
        if (!$user->getStripeCustomerId()) {
            $this->logger->error('Tentative de création de paiement sans compte Stripe', [
                'user_id' => $user->getId(),
            ]);
            
            return $this->json(['error' => 'Aucun compte client Stripe associé à votre profil.'], 
                              Response::HTTP_BAD_REQUEST);
        }

        try {
            $filteredData = $data;
            $currency = $data['currency'] ?? 'eur';
            $metadata = $data['metadata'] ?? [];

            if (isset($filteredData['description'])) {
                $metadata['description'] = $filteredData['description'];
            }

            if (isset($filteredData['product_name'])) {
                $metadata['product_name'] = $filteredData['product_name'];
            }

            $paymentService = $this->paymentServiceFactory->create('payment_intent');
            
            // Ensure type safety for static analysis
            if (!$user instanceof User) {
                throw new \LogicException('User must be an instance of App\Entity\User');
            }
            
            $sessionData = $paymentService->createSession(
                $user,
                (int)$filteredData['amount'],
                $currency,
                $metadata
            );

            return $this->json(['payment' => $sessionData]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/create-subscription', name: 'create_subscription', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createSubscription(Request $request, StripeProductsRepository $stripeProductsRepository): JsonResponse
    {
        $data = $this->jsonRequestService->getContent($request);

        $constraints = new Assert\Collection([
            'plan_id' => [new Assert\NotBlank(), new Assert\Length(['max' => 50])],
            'success_url' => [new Assert\NotBlank(), new Assert\Url()],
            'cancel_url' => [new Assert\NotBlank(), new Assert\Url()],
            'price_id' => [new Assert\NotBlank()],
            'amount' => [new Assert\NotBlank(), new Assert\Positive()],
            'currency' => [new Assert\Optional(new Assert\Currency())],
            'interval' => new Assert\Optional(new Assert\Choice(['monthly', 'annual'])),
        ]);

        // récupéré le price_id via $data
        $priceId = '';
        $amount = 0;
        if ($stripeProductsRepository->findOneBy(['planId' => $data['plan_id']]) && $data['interval'] === 'monthly') {
            $priceId = $stripeProductsRepository->findOneBy(['planId' => $data['plan_id']])->getStripeMonthlyPriceId();
            $amount = $stripeProductsRepository->findOneBy(['planId' => $data['plan_id']])->getMonthlyPrice();
        } elseif ($stripeProductsRepository->findOneBy(['planId' => $data['plan_id']]) && $data['interval'] === 'annual') {
            $priceId = $stripeProductsRepository->findOneBy(['planId' => $data['plan_id']])->getStripeAnnualPriceId();
            $amount = $stripeProductsRepository->findOneBy(['planId' => $data['plan_id']])->getAnnualPrice();
        }

        $data['price_id'] = $priceId ?? '';
        $data['amount'] = $amount ?? 0;

        if (empty($data['price_id'])) {
            return $this->json(['error' => 'Invalid plan ID or interval'], Response::HTTP_BAD_REQUEST);
        }

        $errors = $this->validator->validate($data, $constraints);

        if (count($errors) > 0) {
            return $this->json(['errors' => (string)$errors], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        // Vérifier que l'utilisateur a un compte Stripe
        if (!$user->getStripeCustomerId()) {
            $this->logger->error('Tentative de création d\'abonnement sans compte Stripe', [
                'user_id' => $user->getId(),
            ]);
            
            return $this->json(['error' => 'Aucun compte client Stripe associé à votre profil.'], 
                              Response::HTTP_BAD_REQUEST);
        }

        try {
            $currency = $data['currency'] ?? 'eur';
            $interval = $data['interval'] ?? 'month';
            $metadata = [
                'price_id' => $data['price_id'],
                'interval' => $interval,
            ];

            $paymentService = $this->paymentServiceFactory->create('subscription');
            
            // Ensure type safety for static analysis
            if (!$user instanceof User) {
                throw new \LogicException('User must be an instance of App\Entity\User');
            }
            
            $sessionData = $paymentService->createSession(
                $user,
                (int)$data['amount'],
                $currency,
                $metadata
            );

            return $this->json(['subscription' => $sessionData]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/billing-portal', name: 'billing_portal', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createBillingPortalSession(Request $request): JsonResponse
    {
        $data = $this->jsonRequestService->getContent($request);

        $constraints = new Assert\Collection([
            'return_url' => [new Assert\NotBlank(), new Assert\Url()],
        ]);

        $errors = $this->validator->validate($data, $constraints);

        if (count($errors) > 0) {
            return $this->json(['errors' => (string)$errors], Response::HTTP_BAD_REQUEST);
        }

        /** @var User $user */
        $user = $this->getUser();

        // Vérification que l'utilisateur a bien un compte Stripe
        if (!$user->getStripeCustomerId()) {
            $this->logger->error('Tentative d\'accès au Billing Portal sans compte Stripe', [
                'user_id' => $user->getId(),
            ]);
            
            return $this->json(['error' => 'Aucun compte client Stripe associé à votre profil.'], 
                              Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->logger->info('Création d\'une session Stripe Billing Portal', [
                'user_id' => $user->getId(),
            ]);

            $session = $this->stripeService->createBillingPortalSession(
                $user->getStripeCustomerId(),
                $data['return_url']
            );

            $this->logger->debug('Session Stripe Billing Portal créée', [
                'session_id' => $session['id'],
                'url' => $session['url']
            ]);

            return $this->json(['url' => $session['url']]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de la création de la session Stripe Billing Portal', [
                'error' => $e->getMessage(),
                'user_id' => $user->getId(),
            ]);

            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
