<?php
/**
 * Classe RateLimiter
 * Protection contre les attaques par force brute
 */

class RateLimiter
{
    private Database $db;
    private string $identifier;
    private int $maxAttempts;
    private int $decayMinutes;

    public function __construct(Database $db, int $maxAttempts = 5, int $decayMinutes = 15)
    {
        $this->db = $db;
        $this->maxAttempts = $maxAttempts;
        $this->decayMinutes = $decayMinutes;
        $this->identifier = $this->getIdentifier();

        // Créer la table si nécessaire
        $this->ensureTable();
    }

    /**
     * Génère un identifiant unique basé sur l'IP
     */
    private function getIdentifier(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return hash('sha256', $ip . 'login_attempt');
    }

    /**
     * Crée la table de rate limiting si elle n'existe pas
     */
    private function ensureTable(): void
    {
        try {
            $this->db->query("
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    identifier VARCHAR(64) NOT NULL,
                    attempts INT DEFAULT 1,
                    last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    blocked_until TIMESTAMP NULL,
                    INDEX idx_identifier (identifier)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (Exception $e) {
            // Table might already exist, ignore
        }
    }

    /**
     * Vérifie si l'utilisateur est bloqué
     */
    public function isBlocked(): bool
    {
        $this->cleanup();

        $record = $this->db->fetchOne(
            "SELECT attempts, blocked_until FROM login_attempts WHERE identifier = ?",
            [$this->identifier]
        );

        if (!$record) {
            return false;
        }

        // Vérifier si le blocage est actif
        if ($record['blocked_until'] && strtotime($record['blocked_until']) > time()) {
            return true;
        }

        return false;
    }

    /**
     * Retourne le nombre de secondes restantes avant déblocage
     */
    public function getRemainingLockTime(): int
    {
        $record = $this->db->fetchOne(
            "SELECT blocked_until FROM login_attempts WHERE identifier = ?",
            [$this->identifier]
        );

        if ($record && $record['blocked_until']) {
            $remaining = strtotime($record['blocked_until']) - time();
            return max(0, $remaining);
        }

        return 0;
    }

    /**
     * Retourne le nombre de tentatives restantes
     */
    public function getRemainingAttempts(): int
    {
        $record = $this->db->fetchOne(
            "SELECT attempts FROM login_attempts WHERE identifier = ?",
            [$this->identifier]
        );

        if (!$record) {
            return $this->maxAttempts;
        }

        return max(0, $this->maxAttempts - $record['attempts']);
    }

    /**
     * Enregistre une tentative échouée
     */
    public function recordFailedAttempt(): void
    {
        $record = $this->db->fetchOne(
            "SELECT id, attempts FROM login_attempts WHERE identifier = ?",
            [$this->identifier]
        );

        if (!$record) {
            // Première tentative
            $this->db->query(
                "INSERT INTO login_attempts (identifier, attempts, last_attempt) VALUES (?, 1, NOW())",
                [$this->identifier]
            );
        } else {
            $newAttempts = $record['attempts'] + 1;
            $blockedUntil = null;

            // Bloquer après le max de tentatives
            if ($newAttempts >= $this->maxAttempts) {
                $blockedUntil = date('Y-m-d H:i:s', time() + ($this->decayMinutes * 60));
            }

            $this->db->query(
                "UPDATE login_attempts SET attempts = ?, last_attempt = NOW(), blocked_until = ? WHERE id = ?",
                [$newAttempts, $blockedUntil, $record['id']]
            );
        }
    }

    /**
     * Réinitialise les tentatives après une connexion réussie
     */
    public function reset(): void
    {
        $this->db->query(
            "DELETE FROM login_attempts WHERE identifier = ?",
            [$this->identifier]
        );
    }

    /**
     * Nettoie les anciennes entrées
     */
    private function cleanup(): void
    {
        // Supprimer les entrées expirées (blocage terminé et pas de tentative récente)
        $this->db->query(
            "DELETE FROM login_attempts
             WHERE (blocked_until IS NOT NULL AND blocked_until < NOW())
                OR (blocked_until IS NULL AND last_attempt < DATE_SUB(NOW(), INTERVAL ? MINUTE))",
            [$this->decayMinutes]
        );
    }
}
