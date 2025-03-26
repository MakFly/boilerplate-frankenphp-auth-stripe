<?php

declare(strict_types=1);

namespace App\Logger;

use Monolog\Level;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DiscordHandlerFactory
{
    private HttpClientInterface $httpClient;
    private string $environment;
    private array $webhooks = [];
    
    /**
     * @param HttpClientInterface $httpClient
     * @param string $environment
     * @param string $errorWebhook URL du webhook pour les erreurs
     * @param string $infoWebhook URL du webhook pour les infos
     * @param string $systemWebhook URL du webhook pour les logs système
     * @param string $defaultUsername Nom d'utilisateur par défaut
     * @param string $defaultAvatarUrl URL de l'avatar par défaut
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $environment,
        string $errorWebhook = '',
        string $infoWebhook = '',
        string $systemWebhook = '',
        string $defaultUsername = 'Symfony Logger',
        string $defaultAvatarUrl = ''
    ) {
        $this->httpClient = $httpClient;
        $this->environment = $environment;
        
        // Configuration directe des webhooks sans passer par DiscordWebhookConfig
        if (!empty($errorWebhook)) {
            $this->webhooks['error'] = [
                'url' => $errorWebhook,
                'username' => $defaultUsername,
                'avatar_url' => $defaultAvatarUrl
            ];
        }
        
        if (!empty($infoWebhook)) {
            $this->webhooks['info'] = [
                'url' => $infoWebhook,
                'username' => $defaultUsername,
                'avatar_url' => $defaultAvatarUrl
            ];
        }
        
        if (!empty($systemWebhook)) {
            $this->webhooks['system'] = [
                'url' => $systemWebhook,
                'username' => $defaultUsername,
                'avatar_url' => $defaultAvatarUrl
            ];
        }
    }
    
    public function createHandler(string $channel, int|string|Level $level = Level::Debug): DiscordHandler
    {
        $webhookConfig = $this->webhooks[$channel] ?? null;
        
        if ($webhookConfig === null) {
            // Retournez un handler avec une URL vide qui ne fera rien
            return new DiscordHandler(
                $this->httpClient,
                '',
                'Symfony Logger',
                '',
                $this->environment,
                $level
            );
        }
        
        return new DiscordHandler(
            $this->httpClient,
            $webhookConfig['url'] ?? '',
            $webhookConfig['username'] ?? 'Symfony Logger',
            $webhookConfig['avatar_url'] ?? '',
            $this->environment,
            $level
        );
    }
} 