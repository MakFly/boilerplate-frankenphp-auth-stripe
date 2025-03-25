<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Attribute\DisablePayment;
use App\Service\Payment\PaymentServiceFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * EventSubscriber qui intercepte les requêtes et vérifie si le système de paiement 
 * doit être désactivé pour le contrôleur ou la méthode demandée.
 */
final class DisablePaymentSubscriber implements EventSubscriberInterface
{
    private bool $paymentSystemEnabled;

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        // On peut récupérer la configuration du système de paiement ici
        $this->paymentSystemEnabled = filter_var(
            $_ENV['PAYMENT_SYSTEM_ENABLED'] ?? true,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    /**
     * Vérifie si le contrôleur ou la méthode est annotée avec DisablePayment
     * et lance une exception NotFoundHttpException si c'est le cas et que le système est désactivé.
     */
    public function onKernelController(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $controller = $event->getController();

        // Pour les contrôleurs définis comme des callables (tableau [instance, méthode])
        if (is_array($controller)) {
            $controllerClass = $controller[0];
            $controllerMethod = $controller[1];

            // Vérifie l'attribut sur la méthode du contrôleur
            $reflectionMethod = new \ReflectionMethod($controllerClass, $controllerMethod);
            $methodAttributes = $reflectionMethod->getAttributes(DisablePayment::class);
            
            if (!empty($methodAttributes)) {
                $this->handleDisabledPayment($methodAttributes[0]->newInstance());
                return;
            }

            // Vérifie l'attribut sur la classe du contrôleur
            $reflectionClass = new \ReflectionClass($controllerClass);
            $classAttributes = $reflectionClass->getAttributes(DisablePayment::class);
            
            if (!empty($classAttributes)) {
                $this->handleDisabledPayment($classAttributes[0]->newInstance());
                return;
            }
        }
        
        // Pour les contrôleurs définis comme un service
        if (is_object($controller) && method_exists($controller, '__invoke')) {
            $reflectionClass = new \ReflectionClass($controller);
            $classAttributes = $reflectionClass->getAttributes(DisablePayment::class);
            
            if (!empty($classAttributes)) {
                $this->handleDisabledPayment($classAttributes[0]->newInstance());
                return;
            }
        }
    }

    /**
     * Gère la désactivation du paiement basée sur l'état du système.
     */
    private function handleDisabledPayment(DisablePayment $attribute): void
    {
        if (!$this->paymentSystemEnabled) {
            $reason = $attribute->getReason() ?: 'Système de paiement désactivé';
            $this->logger->info('Tentative d\'accès à une fonctionnalité de paiement désactivée', [
                'reason' => $reason
            ]);
            throw new NotFoundHttpException($reason);
        }
    }
}