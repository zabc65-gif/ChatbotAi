<?php
/**
 * Classe GeminiAPI
 * Gestion des appels à l'API Google Gemini
 */

class GeminiAPI implements AIServiceInterface
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s';
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = GEMINI_API_KEY;
        $this->model = GEMINI_MODEL;
    }

    /**
     * Envoie une requête à l'API Gemini
     */
    public function sendRequest(array $messages): array
    {
        // Convertir le format OpenAI vers le format Gemini
        $geminiMessages = $this->convertMessages($messages);

        $payload = [
            'contents' => $geminiMessages,
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 1024,
            ],
        ];

        $response = $this->makeRequest($payload);

        if (!$response['success']) {
            return $response;
        }

        $data = $response['data'];

        // Extraire la réponse
        $content = '';
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $content = $data['candidates'][0]['content']['parts'][0]['text'];
        }

        // Gemini ne retourne pas toujours le compte de tokens
        $tokensUsed = 0;
        if (isset($data['usageMetadata'])) {
            $tokensUsed = ($data['usageMetadata']['promptTokenCount'] ?? 0) +
                          ($data['usageMetadata']['candidatesTokenCount'] ?? 0);
        }

        return [
            'success' => true,
            'content' => $content,
            'tokens_used' => $tokensUsed,
            'service' => $this->getName()
        ];
    }

    /**
     * Convertit les messages du format OpenAI vers le format Gemini
     */
    private function convertMessages(array $messages): array
    {
        $geminiMessages = [];
        $systemPrompt = '';

        foreach ($messages as $msg) {
            // Gemini gère le system prompt différemment
            if ($msg['role'] === 'system') {
                $systemPrompt = $msg['content'];
                continue;
            }

            $role = $msg['role'] === 'assistant' ? 'model' : 'user';

            $geminiMessages[] = [
                'role' => $role,
                'parts' => [
                    ['text' => $msg['content']]
                ]
            ];
        }

        // Ajouter le system prompt au premier message user si présent
        if ($systemPrompt && !empty($geminiMessages)) {
            $firstUserIdx = null;
            foreach ($geminiMessages as $idx => $msg) {
                if ($msg['role'] === 'user') {
                    $firstUserIdx = $idx;
                    break;
                }
            }

            if ($firstUserIdx !== null) {
                $originalText = $geminiMessages[$firstUserIdx]['parts'][0]['text'];
                $geminiMessages[$firstUserIdx]['parts'][0]['text'] =
                    "[Instructions: {$systemPrompt}]\n\n" . $originalText;
            }
        }

        // S'assurer que la conversation commence par un message user
        if (!empty($geminiMessages) && $geminiMessages[0]['role'] !== 'user') {
            array_unshift($geminiMessages, [
                'role' => 'user',
                'parts' => [['text' => 'Bonjour']]
            ]);
        }

        return $geminiMessages;
    }

    /**
     * Effectue la requête HTTP vers l'API
     */
    private function makeRequest(array $payload): array
    {
        $url = sprintf(self::API_URL, $this->model, $this->apiKey);

        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
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
               $this->apiKey !== 'votre_cle_gemini_ici';
    }

    /**
     * Retourne le nom du service
     */
    public function getName(): string
    {
        return 'gemini';
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
                "[{$timestamp}] [Gemini] {$message}\n",
                FILE_APPEND
            );
        }
    }
}
