<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Attribute\DisablePayment;
use App\Repository\StripeProductsRepository;
use App\Service\JsonRequestService;
use App\Service\Payment\PaymentServiceFactory;
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
        private readonly LoggerInterface $logger
    ) {
    }

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
            $sessionData = $paymentService->createSession(
                $this->getUser(),
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
        
        try {
            $currency = $data['currency'] ?? 'eur';
            $interval = $data['interval'] ?? 'month';
            $metadata = [
                'price_id' => $data['price_id'],
                'interval' => $interval,
            ];
            
            $paymentService = $this->paymentServiceFactory->create('subscription');
            $sessionData = $paymentService->createSession(
                $this->getUser(),
                (int)$data['amount'],
                $currency,
                $metadata
            );
            
            return $this->json(['subscription' => $sessionData]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}