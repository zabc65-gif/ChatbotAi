-- ============================================
-- SCRIPT D'INSTALLATION - CHATBOT IA
-- ============================================
-- Exécuter ce script dans phpMyAdmin ou via CLI MySQL
-- pour créer la structure de base de données nécessaire
-- ============================================

-- Création de la table des conversations
CREATE TABLE IF NOT EXISTS conversations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(255) NOT NULL COMMENT 'Identifiant unique de la conversation',
    role ENUM('user', 'assistant', 'system') NOT NULL COMMENT 'Qui parle',
    content TEXT NOT NULL COMMENT 'Contenu du message',
    ai_service VARCHAR(50) DEFAULT NULL COMMENT 'Service IA utilisé (groq, gemini)',
    tokens_used INT DEFAULT 0 COMMENT 'Nombre de tokens consommés',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date/heure du message',

    -- Index pour optimiser les requêtes
    INDEX idx_session_id (session_id),
    INDEX idx_created_at (created_at),
    INDEX idx_session_created (session_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table optionnelle pour les statistiques et le monitoring
CREATE TABLE IF NOT EXISTS chatbot_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date DATE NOT NULL COMMENT 'Date des statistiques',
    total_requests INT DEFAULT 0 COMMENT 'Nombre total de requêtes',
    groq_requests INT DEFAULT 0 COMMENT 'Requêtes via Groq',
    gemini_requests INT DEFAULT 0 COMMENT 'Requêtes via Gemini',
    total_tokens INT DEFAULT 0 COMMENT 'Total tokens consommés',
    unique_sessions INT DEFAULT 0 COMMENT 'Sessions uniques',
    errors_count INT DEFAULT 0 COMMENT 'Nombre d\'erreurs',

    UNIQUE KEY idx_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table pour le rate limiting (anti-spam)
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL COMMENT 'Adresse IP du visiteur',
    request_count INT DEFAULT 1 COMMENT 'Nombre de requêtes',
    window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Début de la fenêtre de temps',

    UNIQUE KEY idx_ip (ip_address),
    INDEX idx_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Procédure pour nettoyer les anciennes conversations (à exécuter périodiquement)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS cleanup_old_conversations(IN days_to_keep INT)
BEGIN
    DELETE FROM conversations
    WHERE created_at < DATE_SUB(NOW(), INTERVAL days_to_keep DAY);

    DELETE FROM rate_limits
    WHERE window_start < DATE_SUB(NOW(), INTERVAL 1 HOUR);
END //
DELIMITER ;

-- Commentaire: Pour exécuter le nettoyage manuellement:
-- CALL cleanup_old_conversations(30);  -- Supprime les conversations de plus de 30 jours
