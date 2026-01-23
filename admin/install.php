<?php
/**
 * Script d'installation de l'administration
 * À exécuter une seule fois puis à supprimer
 */

$secret = 'install_admin_2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Accès non autorisé');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/Auth.php';

echo "<pre style='font-family: monospace; padding: 20px;'>";
echo "=== INSTALLATION ADMINISTRATION CHATBOT IA ===\n\n";

try {
    $db = new Database();
    $pdo = $db->getPdo();

    // 1. Créer la table users
    echo "1. Création de la table 'users'... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin', 'editor', 'viewer') DEFAULT 'viewer',
            active TINYINT(1) DEFAULT 1,
            last_login DATETIME DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_active (active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    // 2. Créer la table settings
    echo "2. Création de la table 'settings'... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            setting_type VARCHAR(20) DEFAULT 'string',
            setting_group VARCHAR(50) DEFAULT 'general',
            setting_label VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_group (setting_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    // 3. Créer l'utilisateur admin
    echo "3. Création de l'utilisateur admin... ";
    $auth = new Auth($db);

    if ($auth->emailExists('bruno@myziggi.fr')) {
        echo "(déjà existant) ✓\n";
    } else {
        $auth->createUser('BueBe', 'bruno@myziggi.fr', 'ChatbotBueBe79$', 'admin');
        echo "✓\n";
    }

    // 4. Insérer les paramètres par défaut
    echo "4. Insertion des paramètres par défaut... ";

    $defaultSettings = [
        // Chatbot
        ['chatbot_name', 'Assistant IA', 'string', 'chatbot', 'Nom du chatbot'],
        ['chatbot_welcome_message', 'Bonjour ! Je suis votre assistant virtuel. Comment puis-je vous aider ?', 'text', 'chatbot', 'Message de bienvenue'],
        ['chatbot_system_prompt', "Tu es un assistant virtuel amical et professionnel. Tu réponds en français de manière claire et concise. Tu aides les visiteurs du site avec leurs questions.", 'text', 'chatbot', 'Prompt système (comportement IA)'],
        ['chatbot_placeholder', 'Écrivez votre message...', 'string', 'chatbot', 'Placeholder du champ de saisie'],
        ['chatbot_primary_color', '#6366f1', 'string', 'chatbot', 'Couleur principale'],

        // Site
        ['site_name', 'ChatBot IA', 'string', 'site', 'Nom du site'],
        ['site_description', 'Assistant virtuel intelligent pour votre site web', 'string', 'site', 'Description du site'],
        ['site_tagline', "L'assistant intelligent qui transforme vos visiteurs en clients.", 'string', 'site', 'Slogan'],

        // Landing page - Hero
        ['hero_title', 'Boostez vos conversions avec un', 'string', 'landing', 'Titre Hero (partie 1)'],
        ['hero_title_highlight', 'Assistant IA', 'string', 'landing', 'Titre Hero (partie colorée)'],
        ['hero_description', 'Un chatbot intelligent disponible 24h/24 pour répondre à vos clients, qualifier vos leads et augmenter vos ventes. Simple à installer, puissant en résultats.', 'text', 'landing', 'Description Hero'],

        // Contact
        ['contact_email', 'bruno@myziggi.fr', 'string', 'contact', 'Email de contact'],
        ['contact_phone', '06 72 38 64 24', 'string', 'contact', 'Téléphone'],

        // Tarifs
        ['price_starter', '0', 'string', 'pricing', 'Prix Starter'],
        ['price_pro', '49', 'string', 'pricing', 'Prix Pro (€/mois)'],
        ['price_enterprise', 'Sur mesure', 'string', 'pricing', 'Prix Enterprise'],
    ];

    foreach ($defaultSettings as $setting) {
        $sql = "INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label)
                VALUES (?, ?, ?, ?, ?)";
        $pdo->prepare($sql)->execute($setting);
    }
    echo "✓\n";

    echo "\n=== INSTALLATION TERMINÉE ===\n";
    echo "\n✅ Accès admin : https://chatbot.myziggi.pro/admin/\n";
    echo "   Email : bruno@myziggi.fr\n";
    echo "   Mot de passe : ChatbotBueBe79\$\n";
    echo "\n⚠️  IMPORTANT : Supprimez ce fichier immédiatement !\n";

} catch (Exception $e) {
    echo "✗ ERREUR : " . $e->getMessage() . "\n";
}

echo "</pre>";
