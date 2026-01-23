<?php
/**
 * Script de correction de la base de données
 */

$secret = 'fix_chatbot_2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Accès non autorisé');
}

require_once __DIR__ . '/config.php';

echo "<pre>";
echo "=== CORRECTION BASE DE DONNÉES ===\n\n";

try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    // Supprimer l'ancienne table conversations
    echo "1. Suppression de l'ancienne table conversations... ";
    $pdo->exec("DROP TABLE IF EXISTS conversations");
    echo "✓\n";

    // Recréer avec la bonne structure
    echo "2. Création de la nouvelle table conversations... ";
    $pdo->exec("
        CREATE TABLE conversations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(255) NOT NULL,
            role ENUM('user', 'assistant', 'system') NOT NULL,
            content TEXT NOT NULL,
            ai_service VARCHAR(50) DEFAULT NULL,
            tokens_used INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_session_id (session_id),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    // Supprimer aussi l'ancienne table chat_analytics si elle existe
    echo "3. Nettoyage table chat_analytics... ";
    $pdo->exec("DROP TABLE IF EXISTS chat_analytics");
    echo "✓\n";

    echo "\n=== CORRECTION TERMINÉE ===\n";
    echo "\n⚠️  Supprimez ce fichier immédiatement !\n";

} catch (PDOException $e) {
    echo "✗ ERREUR: " . $e->getMessage() . "\n";
}

echo "</pre>";
