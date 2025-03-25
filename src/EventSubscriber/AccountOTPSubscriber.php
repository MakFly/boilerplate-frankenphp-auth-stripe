<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Attribute\AccountOTP;
use ReflectionMethod;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class AccountOTPSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $params
    ) {}

    public function onKernelController(ControllerEvent $event): void
    {
        $controller = $event->getController();

        // Get the environment variable value (default is true)
        $globalOtpEnabled = $this->params->get('app.otp.enabled');
        $otpEnabled = $globalOtpEnabled;

        // $controller is usually an array [object, method]
        if (is_array($controller)) {
            [$object, $methodName] = $controller;
            $reflectionMethod = new ReflectionMethod($object, $methodName);
            $attributes = $reflectionMethod->getAttributes(AccountOTP::class);

            $this->logger->debug('AccountOTPSubscriber: Checking attributes', [
                'controller' => get_class($object),
                'method' => $methodName,
                'has_attributes' => count($attributes) > 0,
                'global_otp_enabled' => $globalOtpEnabled
            ]);

            if (count($attributes) > 0) {
                /** @var AccountOTP $attributeInstance */
                $attributeInstance = $attributes[0]->newInstance();
                $otpEnabled = $attributeInstance->enabled && $globalOtpEnabled;
                
                $this->logger->debug('AccountOTPSubscriber: Found OTP attribute', [
                    'enabled' => $otpEnabled
                ]);
            }
        }

        // Always set the value in request attributes
        $event->getRequest()->attributes->set('account_otp_enabled', $otpEnabled);
        
        $this->logger->debug('AccountOTPSubscriber: Final OTP setting', [
            'otp_enabled' => $otpEnabled,
            'request_uri' => $event->getRequest()->getRequestUri()
        ]);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }
}
