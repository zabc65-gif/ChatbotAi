<?php
/**
 * Script d'installation de la base de données
 * À exécuter une seule fois puis à supprimer
 */

// Protection basique
$secret = 'install_chatbot_2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Accès non autorisé');
}

require_once __DIR__ . '/config.php';

echo "<pre>";
echo "=== Installation de la base de données ChatBot IA ===\n\n";

try {
    // Connexion à la BDD
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "✓ Connexion à la base de données réussie\n\n";

    // Table conversations
    echo "Création de la table 'conversations'... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL COMMENT 'Identifiant unique de la conversation',
            role ENUM('user', 'assistant', 'system') NOT NULL COMMENT 'Qui parle',
            content TEXT NOT NULL COMMENT 'Contenu du message',
            ai_service VARCHAR(50) DEFAULT NULL COMMENT 'Service IA utilisé (groq, gemini)',
            tokens_used INT DEFAULT 0 COMMENT 'Nombre de tokens consommés',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Date/heure du message',
            INDEX idx_session_id (session_id),
            INDEX idx_created_at (created_at),
            INDEX idx_session_created (session_id, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    // Table statistiques
    echo "Création de la table 'chatbot_stats'... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chatbot_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL COMMENT 'Date des statistiques',
            total_requests INT DEFAULT 0 COMMENT 'Nombre total de requêtes',
            groq_requests INT DEFAULT 0 COMMENT 'Requêtes via Groq',
            gemini_requests INT DEFAULT 0 COMMENT 'Requêtes via Gemini',
            total_tokens INT DEFAULT 0 COMMENT 'Total tokens consommés',
            unique_sessions INT DEFAULT 0 COMMENT 'Sessions uniques',
            errors_count INT DEFAULT 0 COMMENT 'Nombre d erreurs',
            UNIQUE KEY idx_date (date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    // Table rate limiting
    echo "Création de la table 'rate_limits'... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ip_address VARCHAR(45) NOT NULL COMMENT 'Adresse IP du visiteur',
            request_count INT DEFAULT 1 COMMENT 'Nombre de requêtes',
            window_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Début de la fenêtre de temps',
            UNIQUE KEY idx_ip (ip_address),
            INDEX idx_window (window_start)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    echo "\n=== INSTALLATION TERMINÉE AVEC SUCCÈS ===\n";
    echo "\n⚠️  IMPORTANT: Supprimez ce fichier (install_db.php) immédiatement !\n";
    echo "\n🔗 Votre chatbot est accessible sur: https://chatbot.myziggi.pro\n";

} catch (PDOException $e) {
    echo "✗ ERREUR: " . $e->getMessage() . "\n";
}

echo "</pre>";
