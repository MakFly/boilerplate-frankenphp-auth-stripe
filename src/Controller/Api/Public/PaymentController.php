<?php

declare(strict_types=1);

namespace App\Controller\Api\Public;

use App\Attribute\DisablePayment;
use App\Service\JsonRequestService;
use App\Service\Payment\PaymentServiceFactory;
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
        private readonly ValidatorInterface $validator
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
            'description' => new Assert\Optional(new Assert\Length(['max' => 255])),
            'product_name' => new Assert\Optional(new Assert\Length(['max' => 255])),
        ]);
        
        $errors = $this->validator->validate($data, $constraints);
        
        if (count($errors) > 0) {
            return $this->json(['errors' => (string)$errors], Response::HTTP_BAD_REQUEST);
        }
        
        try {
            $currency = $data['currency'] ?? 'eur';
            $metadata = [];
            
            if (isset($data['description'])) {
                $metadata['description'] = $data['description'];
            }
            
            if (isset($data['product_name'])) {
                $metadata['product_name'] = $data['product_name'];
            }
            
            $paymentService = $this->paymentServiceFactory->create('payment_intent');
            $sessionData = $paymentService->createSession(
                $this->getUser(),
                (int)$data['amount'],
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
    public function createSubscription(Request $request): JsonResponse
    {
        $data = $this->jsonRequestService->getContent($request);
        
        $constraints = new Assert\Collection([
            'price_id' => [new Assert\NotBlank()],
            'amount' => [new Assert\NotBlank(), new Assert\Positive()],
            'currency' => [new Assert\Optional(new Assert\Currency())],
            'interval' => new Assert\Optional(new Assert\Choice(['month', 'year'])),
        ]);
        
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