<?php
/**
 * Classe GroqAPI
 * Gestion des appels à l'API Groq
 */

class GroqAPI implements AIServiceInterface
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = GROQ_API_KEY;
        $this->model = GROQ_MODEL;
    }

    /**
     * Envoie une requête à l'API Groq
     */
    public function sendRequest(array $messages): array
    {
        $payload = [
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 1024,
        ];

        $response = $this->makeRequest($payload);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];

        return [
            'success' => true,
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'tokens_used' => $data['usage']['total_tokens'] ?? 0,
            'service' => $this->getName()
        ];
    }

    /**
     * Effectue la requête HTTP vers l'API
     */
    private function makeRequest(array $payload): array
    {
        $ch = curl_init(self::API_URL);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Erreur cURL
        if ($error) {
            $this->logError("cURL error: {$error}");
            return [
                'success' => false,
                'error' => 'Erreur de connexion au service IA',
                'error_type' => 'connection'
            ];
        }

        $data = json_decode($response, true);

        // Rate limit atteint (429)
        if ($httpCode === 429) {
            $this->logError("Rate limit exceeded");
            return [
                'success' => false,
                'error' => 'Limite de requêtes atteinte',
                'error_type' => 'rate_limit'
            ];
        }

        // Autre erreur HTTP
        if ($httpCode !== 200) {
            $errorMsg = $data['error']['message'] ?? 'Erreur inconnue';
            $this->logError("API error ({$httpCode}): {$errorMsg}");
            return [
                'success' => false,
                'error' => $errorMsg,
                'error_type' => 'api_error'
            ];
        }

        return [
            'success' => true,
            'data' => $data
        ];
    }

    /**
     * Vérifie si le service est disponible (clé API configurée)
     */
    public function isAvailable(): bool
    {
        return !empty($this->apiKey) &&
               $this->apiKey !== 'gsk_votre_cle_groq_ici';
    }

    /**
     * Retourne le nom du service
     */
    public function getName(): string
    {
        return 'groq';
    }

    /**
     * Log des erreurs
     */
    private function logError(string $message): void
    {
        if (defined('LOG_FILE') && LOG_FILE) {
            $logDir = dirname(LOG_FILE);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents(
                LOG_FILE,
                "[{$timestamp}] [Groq] {$message}\n",
                FILE_APPEND
            );
        }
    }
}
