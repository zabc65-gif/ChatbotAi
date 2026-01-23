<?php
/**
 * Classe Chatbot
 * Classe principale orchestrant le chatbot avec fallback automatique
 */

class Chatbot
{
    private Database $db;
    private HistoryManager $historyManager;
    private array $aiServices = [];

    public function __construct()
    {
        $this->db = new Database();
        $this->historyManager = new HistoryManager($this->db);
        $this->initializeServices();
    }

    /**
     * Initialise les services IA dans l'ordre de priorité
     */
    private function initializeServices(): void
    {
        // Ordre de priorité : Groq (rapide) > Gemini (backup)
        $this->aiServices = [
            new GroqAPI(),
            new GeminiAPI(),
        ];
    }

    /**
     * Traite un message utilisateur et retourne la réponse de l'IA
     */
    public function processMessage(string $sessionId, string $userMessage): array
    {
        // Validation du message
        $validation = $this->validateMessage($userMessage);
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error']
            ];
        }

        // Vérifier le rate limiting
        if (!$this->checkRateLimit()) {
            return [
                'success' => false,
                'error' => 'Trop de messages envoyés. Veuillez patienter.'
            ];
        }

        // Sauvegarder le message utilisateur
        $this->historyManager->saveMessage($sessionId, 'user', $userMessage);

        // Préparer l'historique optimisé pour l'IA
        $messages = $this->historyManager->prepareForAI($sessionId);

        // Envoyer à l'IA avec fallback automatique
        $response = $this->sendToAI($messages);

        if (!$response['success']) {
            return $response;
        }

        // Sauvegarder la réponse de l'IA
        $this->historyManager->saveMessage(
            $sessionId,
            'assistant',
            $response['content'],
            $response['service'],
            $response['tokens_used']
        );

        // Mettre à jour les statistiques
        $this->updateStats($response['service'], $response['tokens_used']);

        return [
            'success' => true,
            'message' => $response['content'],
            'service' => $response['service'],
            'session_id' => $sessionId
        ];
    }

    /**
     * Envoie le message à l'IA avec système de fallback
     */
    private function sendToAI(array $messages): array
    {
        $lastError = null;

        foreach ($this->aiServices as $service) {
            // Vérifier si le service est configuré
            if (!$service->isAvailable()) {
                continue;
            }

            $response = $service->sendRequest($messages);

            // Si succès, retourner la réponse
            if ($response['success']) {
                return $response;
            }

            // Si rate limit, passer au service suivant
            if (($response['error_type'] ?? '') === 'rate_limit') {
                $this->logInfo("Fallback: {$service->getName()} rate limited, trying next service");
                $lastError = $response;
                continue;
            }

            // Autre erreur, on réessaie avec le prochain service
            $lastError = $response;
        }

        // Aucun service n'a répondu
        return [
            'success' => false,
            'error' => $lastError['error'] ?? 'Tous les services IA sont indisponibles. Veuillez réessayer plus tard.'
        ];
    }

    /**
     * Valide le message utilisateur
     */
    private function validateMessage(string $message): array
    {
        $message = trim($message);

        if (empty($message)) {
            return ['valid' => false, 'error' => 'Le message ne peut pas être vide'];
        }

        if (strlen($message) > MAX_MESSAGE_LENGTH) {
            return [
                'valid' => false,
                'error' => 'Message trop long (max ' . MAX_MESSAGE_LENGTH . ' caractères)'
            ];
        }

        return ['valid' => true];
    }

    /**
     * Vérifie le rate limiting pour l'IP courante
     */
    private function checkRateLimit(): bool
    {
        $ip = $this->getClientIP();

        // Nettoyer les anciennes entrées
        $sql = "DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
        try {
            $this->db->query($sql);
        } catch (Exception $e) {
            // Ignorer les erreurs de nettoyage
        }

        // Vérifier le compteur actuel
        $sql = "SELECT request_count FROM rate_limits WHERE ip_address = ?";
        $result = $this->db->fetchOne($sql, [$ip]);

        if ($result) {
            if ($result['request_count'] >= RATE_LIMIT_PER_MINUTE) {
                return false;
            }

            // Incrémenter le compteur
            $sql = "UPDATE rate_limits SET request_count = request_count + 1 WHERE ip_address = ?";
            $this->db->query($sql, [$ip]);
        } else {
            // Nouvelle entrée
            $this->db->insert('rate_limits', [
                'ip_address' => $ip,
                'request_count' => 1
            ]);
        }

        return true;
    }

    /**
     * Met à jour les statistiques d'utilisation
     */
    private function updateStats(string $service, int $tokensUsed): void
    {
        $today = date('Y-m-d');

        $sql = "INSERT INTO chatbot_stats (date, total_requests, {$service}_requests, total_tokens, unique_sessions)
                VALUES (?, 1, 1, ?, 0)
                ON DUPLICATE KEY UPDATE
                    total_requests = total_requests + 1,
                    {$service}_requests = {$service}_requests + 1,
                    total_tokens = total_tokens + ?";

        try {
            $this->db->query($sql, [$today, $tokensUsed, $tokensUsed]);
        } catch (Exception $e) {
            // Statistiques non critiques, on ignore les erreurs
        }
    }

    /**
     * Récupère l'adresse IP du client
     */
    private function getClientIP(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Prendre la première IP si plusieurs
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    /**
     * Démarre ou récupère une session de chat
     */
    public function getOrCreateSession(?string $sessionId = null): string
    {
        if ($sessionId && $this->isValidSession($sessionId)) {
            return $sessionId;
        }

        return HistoryManager::generateSessionId();
    }

    /**
     * Vérifie si une session existe
     */
    private function isValidSession(string $sessionId): bool
    {
        return $this->historyManager->getMessageCount($sessionId) > 0;
    }

    /**
     * Récupère l'historique formaté pour l'affichage
     */
    public function getDisplayHistory(string $sessionId): array
    {
        $history = $this->historyManager->getHistory($sessionId);

        // Filtrer le message système (pas besoin de l'afficher)
        return array_filter($history, function ($msg) {
            return $msg['role'] !== 'system';
        });
    }

    /**
     * Efface l'historique d'une session
     */
    public function clearSession(string $sessionId): bool
    {
        return $this->historyManager->clearHistory($sessionId);
    }

    /**
     * Log d'information
     */
    private function logInfo(string $message): void
    {
        if (defined('LOG_FILE') && LOG_FILE) {
            $logDir = dirname(LOG_FILE);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            $timestamp = date('Y-m-d H:i:s');
            @file_put_contents(
                LOG_FILE,
                "[{$timestamp}] [INFO] {$message}\n",
                FILE_APPEND
            );
        }
    }
}
