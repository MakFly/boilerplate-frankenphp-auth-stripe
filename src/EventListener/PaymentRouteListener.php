<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use App\Service\Stripe\StripeService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class PaymentRouteListener
{
    public function __construct(
        private readonly Security $security,
        private readonly LoggerInterface $logger,
        private readonly StripeService $stripeService,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        
        // Vérifier si l'utilisateur a un compte Stripe pour les routes de paiement
        // Si la route n'est pas liée au paiement, on quitte la méthode
        if (str_starts_with($request->getPathInfo(), '/api/payment/')) {
            /** @var User|null $user */
            $user = $this->security->getUser();
            
            if (!$user) {
                $event->setResponse(new JsonResponse(
                    ['message' => 'Utilisateur non authentifié'],
                    Response::HTTP_UNAUTHORIZED
                ));
                return;
            }
    
            $stripeCustomerId = $user->getStripeCustomerId();
            
            if (!$stripeCustomerId) {
                $this->stripeService->createStripeAccount($user);
                return;
            }
    
            // Vérifier que le stripeCustomerId est associé à son compte Stripe
            if (!$this->stripeService->getStripeAccount($stripeCustomerId)) {
                $this->stripeService->updateStripeAccount($user);
            }
        }

    }
}