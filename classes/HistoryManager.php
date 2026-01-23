<?php
/**
 * Classe HistoryManager
 * Gestion de l'historique des conversations avec optimisation des tokens
 */

class HistoryManager
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Récupère l'historique complet d'une session
     */
    public function getHistory(string $sessionId): array
    {
        $sql = "SELECT role, content FROM conversations
                WHERE session_id = ?
                ORDER BY created_at ASC";

        return $this->db->fetchAll($sql, [$sessionId]);
    }

    /**
     * Sauvegarde un message dans l'historique
     */
    public function saveMessage(
        string $sessionId,
        string $role,
        string $content,
        ?string $aiService = null,
        int $tokensUsed = 0
    ): int {
        return $this->db->insert('conversations', [
            'session_id' => $sessionId,
            'role' => $role,
            'content' => $content,
            'ai_service' => $aiService,
            'tokens_used' => $tokensUsed
        ]);
    }

    /**
     * Prépare l'historique pour l'envoi à l'IA avec compression intelligente
     * Applique les optimisations pour réduire la consommation de tokens
     */
    public function prepareForAI(string $sessionId): array
    {
        $history = $this->getHistory($sessionId);

        // Si l'historique est court, on le garde entier
        if (count($history) <= MAX_HISTORY_MESSAGES) {
            return $this->addSystemMessage($history);
        }

        // Compression intelligente
        return $this->compressHistory($history);
    }

    /**
     * Compression intelligente de l'historique
     * Garde : message système + premiers échanges + derniers messages
     */
    private function compressHistory(array $history): array
    {
        $compressed = [];

        // Séparer le message système s'il existe
        $systemMessage = null;
        $conversationMessages = [];

        foreach ($history as $msg) {
            if ($msg['role'] === 'system') {
                $systemMessage = $msg;
            } else {
                $conversationMessages[] = $msg;
            }
        }

        // Garder les premiers échanges (contexte d'introduction)
        $firstMessages = array_slice($conversationMessages, 0, KEEP_FIRST_EXCHANGES);

        // Garder les derniers messages (contexte récent)
        $recentMessages = array_slice($conversationMessages, -KEEP_RECENT_MESSAGES);

        // Éviter les doublons si l'historique est court
        if (count($conversationMessages) <= KEEP_FIRST_EXCHANGES + KEEP_RECENT_MESSAGES) {
            $compressed = $conversationMessages;
        } else {
            $compressed = array_merge($firstMessages, $recentMessages);
        }

        // Vérifier la limite de tokens
        $compressed = $this->trimToTokenLimit($compressed);

        // Ajouter le message système au début
        return $this->addSystemMessage($compressed);
    }

    /**
     * Réduit l'historique pour respecter la limite de tokens
     */
    private function trimToTokenLimit(array $messages): array
    {
        $estimatedTokens = $this->estimateTokens($messages);

        // Si on est dans la limite, on garde tout
        if ($estimatedTokens <= MAX_TOKENS) {
            return $messages;
        }

        // Sinon, on garde seulement les messages récents
        while ($estimatedTokens > MAX_TOKENS && count($messages) > 2) {
            // Retirer le message le plus ancien (après les tout premiers)
            array_splice($messages, 0, 1);
            $estimatedTokens = $this->estimateTokens($messages);
        }

        return $messages;
    }

    /**
     * Ajoute le message système au début de l'historique
     */
    private function addSystemMessage(array $history): array
    {
        // Utiliser le message personnalisé si défini, sinon le message par défaut
        $systemContent = $GLOBALS['CUSTOM_SYSTEM_MESSAGE'] ?? SYSTEM_MESSAGE;

        $systemMessage = [
            'role' => 'system',
            'content' => $systemContent
        ];

        // Vérifier si un message système existe déjà
        if (!empty($history) && $history[0]['role'] === 'system') {
            return $history;
        }

        array_unshift($history, $systemMessage);
        return $history;
    }

    /**
     * Estime le nombre de tokens d'un historique
     * Règle : 1 token ≈ 4 caractères en français
     */
    public function estimateTokens(array $messages): int
    {
        $totalChars = 0;

        foreach ($messages as $msg) {
            $totalChars += strlen($msg['content'] ?? '');
            $totalChars += strlen($msg['role'] ?? '');
            $totalChars += 10; // Overhead pour la structure JSON
        }

        return (int) ceil($totalChars / 4);
    }

    /**
     * Compte le nombre de messages dans une session
     */
    public function getMessageCount(string $sessionId): int
    {
        $sql = "SELECT COUNT(*) as count FROM conversations WHERE session_id = ?";
        $result = $this->db->fetchOne($sql, [$sessionId]);
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Supprime l'historique d'une session
     */
    public function clearHistory(string $sessionId): bool
    {
        $sql = "DELETE FROM conversations WHERE session_id = ?";
        $this->db->query($sql, [$sessionId]);
        return true;
    }

    /**
     * Génère un nouvel identifiant de session unique
     */
    public static function generateSessionId(): string
    {
        return 'chat_' . date('Ymd') . '_' . bin2hex(random_bytes(8));
    }
}
