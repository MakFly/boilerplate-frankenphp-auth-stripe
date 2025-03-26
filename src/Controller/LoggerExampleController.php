<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LoggerExampleController extends AbstractController
{
    private LoggerInterface $discordErrorLogger;
    private LoggerInterface $discordSystemLogger;
    private LoggerInterface $discordInfoLogger;
    
    public function __construct(
        LoggerInterface $discordErrorLogger,
        LoggerInterface $discordSystemLogger,
        LoggerInterface $discordInfoLogger
    ) {
        $this->discordErrorLogger = $discordErrorLogger;
        $this->discordSystemLogger = $discordSystemLogger;
        $this->discordInfoLogger = $discordInfoLogger;
    }

    #[Route('/api/logger/test', name: 'app_logger_test', methods: ['GET'])]
    public function testLogger(): JsonResponse
    {
        // Log vers le canal d'erreurs
        $this->discordErrorLogger->error('Une erreur critique est survenue', [
            'user_id' => 123,
            'action' => 'test_action',
            'details' => [
                'ip' => '127.0.0.1',
                'browser' => 'Example Browser'
            ]
        ]);
        
        // Log vers le canal système
        $this->discordSystemLogger->info('Démarrage du système', [
            'component' => 'API',
            'status' => 'starting'
        ]);
        
        // Log vers le canal d'informations
        $this->discordInfoLogger->info('Nouvel utilisateur inscrit', [
            'user_id' => 456,
            'email' => 'user@example.com'
        ]);
        
        return new JsonResponse([
            'success' => true, 
            'message' => 'Logs envoyés vers les différents canaux Discord'
        ]);
    }
} 