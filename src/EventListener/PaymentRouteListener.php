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
        
        // Vérifie si la route commence par /api/payment/
        if (!str_starts_with($request->getPathInfo(), '/api/payment/')) {
            return;
        }

        /** @var User|null $user */
        $user = $this->security->getUser();
        
        if (!$user) {
            $event->setResponse(new JsonResponse(
                ['message' => 'Utilisateur non authentifié'],
                Response::HTTP_UNAUTHORIZED
            ));
            return;
        }

        if (!$user->getStripeCustomerId()) {
            $this->logger->error('User without Stripe account', [
                'user_id' => $user->getId(),
            ]);

            $this->stripeService->createStripeAccount($user);
            return;
        }
    }
}