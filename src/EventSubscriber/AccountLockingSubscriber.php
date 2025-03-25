<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Attribute\AccountLocking;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class AccountLockingSubscriber implements EventSubscriberInterface
{
    private bool $isEnabled;

    public function __construct()
    {
        // Default value
        $this->isEnabled = filter_var(
            $_ENV['ACCOUNT_LOCKING'] ?? true,
            FILTER_VALIDATE_BOOLEAN
        );
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER_ARGUMENTS => 'onKernelControllerArguments',
        ];
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        $request = $event->getRequest();
        $controller = $event->getController();
        
        // Par défaut on utilise la configuration globale
        $lockingEnabled = $this->isEnabled;
        
        // Si le contrôleur est un tableau (méthode de classe)
        if (is_array($controller) && count($controller) === 2) {
            $object = $controller[0];
            $method = $controller[1];
            
            // On utilise la réflexion pour lire l'attribut sur la méthode
            $reflection = new \ReflectionMethod($object, $method);
            $attributes = $reflection->getAttributes(AccountLocking::class);
            
            if (!empty($attributes)) {
                $attribute = $attributes[0]->newInstance();
                $lockingEnabled = $attribute->enabled;
            }
        }
        
        // Définir la valeur dans les attributs de la requête
        $request->attributes->set('account_locking_enabled', $lockingEnabled);
    }
}
