<?php

namespace App\Logger;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DiscordHandler extends AbstractProcessingHandler
{
    private HttpClientInterface $httpClient;
    private string $webhookUrl;
    private string $username;
    private string $avatarUrl;
    private string $environment;

    public function __construct(
        HttpClientInterface $httpClient,
        string $webhookUrl,
        string $username = 'Symfony Logger',
        string $avatarUrl = '',
        string $environment = 'dev',
        int|string|Level $level = Level::Debug,
        bool $bubble = true
    ) {
        parent::__construct($level, $bubble);
        $this->httpClient = $httpClient;
        $this->webhookUrl = $webhookUrl;
        $this->username = $username;
        $this->avatarUrl = $avatarUrl;
        $this->environment = $environment;
    }

    protected function write(LogRecord $record): void
    {
        if (empty($this->webhookUrl)) {
            return;
        }

        $levelColor = $this->getLevelColor($record->level->name);
        
        $embedData = [
            'title' => 'Log: ' . $record->level->name,
            'description' => $record->message,
            'color' => $levelColor,
            'fields' => [],
            'timestamp' => $record->datetime->format('c'),
            'footer' => [
                'text' => 'Environment: ' . $this->environment
            ]
        ];

        // Add context as fields if available
        if (!empty($record->context)) {
            foreach ($record->context as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $value = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                }
                
                if (is_string($value) && strlen($value) > 1024) {
                    $value = substr($value, 0, 1021) . '...';
                }
                
                $embedData['fields'][] = [
                    'name' => $key,
                    'value' => (string) $value,
                    'inline' => false
                ];
            }
        }

        $payload = [
            'username' => $this->username,
            'embeds' => [$embedData]
        ];

        if (!empty($this->avatarUrl)) {
            $payload['avatar_url'] = $this->avatarUrl;
        }

        try {
            $this->httpClient->request('POST', $this->webhookUrl, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);
        } catch (\Throwable $e) {
            // Fail silently - we don't want logging errors to break the application
        }
    }

    private function getLevelColor(string $level): int
    {
        return match ($level) {
            'DEBUG' => 0x7289DA, // Discord blue
            'INFO' => 0x3498DB,  // Light blue
            'NOTICE' => 0x2ECC71, // Green
            'WARNING' => 0xF1C40F, // Yellow
            'ERROR' => 0xE74C3C, // Red
            'CRITICAL' => 0x992D22, // Dark red
            'ALERT' => 0x7D3C98, // Purple
            'EMERGENCY' => 0x000000, // Black
            default => 0x95A5A6, // Grey
        };
    }
} 