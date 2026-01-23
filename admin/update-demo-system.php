<?php
/**
 * Script de mise à jour pour le système de chatbots démo dynamiques
 * À exécuter une seule fois puis à supprimer
 */

$secret = 'update_demo_2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Accès non autorisé');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

echo "<pre style='font-family: monospace; padding: 20px;'>";
echo "=== MISE À JOUR SYSTÈME CHATBOTS DÉMO ===\n\n";

try {
    $db = new Database();
    $pdo = $db->getPdo();

    // 1. Créer la table demo_chatbots
    echo "1. Création de la table 'demo_chatbots'... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS demo_chatbots (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(50) NOT NULL UNIQUE,
            name VARCHAR(100) NOT NULL,
            icon VARCHAR(10) DEFAULT '💬',
            color VARCHAR(7) DEFAULT '#6366f1',
            welcome_message TEXT,
            system_prompt TEXT NOT NULL,
            redirect_message TEXT,
            active TINYINT(1) DEFAULT 1,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (active),
            INDEX idx_sort (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    // 2. Créer la table demo_usage pour tracker l'utilisation
    echo "2. Création de la table 'demo_usage'... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS demo_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            identifier VARCHAR(64) NOT NULL,
            chatbot_slug VARCHAR(50) DEFAULT NULL,
            message_count INT DEFAULT 0,
            date DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_usage (identifier, date),
            INDEX idx_date (date),
            INDEX idx_identifier (identifier)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    // 3. Insérer les 3 chatbots par défaut
    echo "3. Insertion des chatbots par défaut... ";

    $defaultChatbots = [
        [
            'slug' => 'btp',
            'name' => 'Artisans & BTP',
            'icon' => '🏠',
            'color' => '#f59e0b',
            'welcome_message' => "Bonjour ! Je suis l'assistant de votre artisan. Comment puis-je vous aider aujourd'hui ? Devis, renseignements sur nos services, prise de rendez-vous... je suis là pour vous !",
            'system_prompt' => "Tu es EXCLUSIVEMENT un assistant virtuel pour un artisan du bâtiment.

RÈGLES STRICTES - TU DOIS LES RESPECTER :
- Tu ne réponds QU'aux questions sur : devis travaux, services BTP, rendez-vous, zone d'intervention, délais, tarifs
- Pour TOUTE question hors sujet (code, maths, rédaction, traduction, actualités, recettes, etc.), tu réponds UNIQUEMENT : \"Je suis l'assistant de cet artisan et je ne peux vous aider que pour vos projets de travaux. Puis-je vous renseigner sur nos services de rénovation, construction ou dépannage ?\"
- Tu ne fais JAMAIS de programmation, traduction, rédaction de texte, calculs scolaires, ou aide aux devoirs
- Tu ne donnes pas de conseils médicaux, juridiques ou financiers

Ce que tu PEUX faire :
- Aider à formuler une demande de devis
- Expliquer les services (rénovation, construction, plomberie, électricité, etc.)
- Proposer un rendez-vous pour visite technique
- Répondre sur les délais et tarifs généraux

Tu es professionnel, rassurant et tu mets en avant la qualité du travail artisanal.",
            'redirect_message' => "Je suis l'assistant de cet artisan du bâtiment et je suis spécialisé dans l'accompagnement de vos projets de travaux. 🏠

Je peux vous aider pour :
• Demander un devis personnalisé
• Obtenir des infos sur nos services
• Prendre rendez-vous

Comment puis-je vous aider avec votre projet ?",
            'sort_order' => 1
        ],
        [
            'slug' => 'immo',
            'name' => 'Agences Immobilières',
            'icon' => '🏡',
            'color' => '#3b82f6',
            'welcome_message' => "Bienvenue ! Je suis l'assistant de notre agence immobilière. Que vous cherchiez à acheter, louer ou vendre un bien, je suis là pour vous accompagner. Comment puis-je vous aider ?",
            'system_prompt' => "Tu es EXCLUSIVEMENT un assistant virtuel pour une agence immobilière.

RÈGLES STRICTES - TU DOIS LES RESPECTER :
- Tu ne réponds QU'aux questions sur : recherche de biens, estimation, visites, processus achat/vente/location, quartiers, prix marché
- Pour TOUTE question hors sujet (code, maths, rédaction, traduction, actualités, etc.), tu réponds UNIQUEMENT : \"Je suis l'assistant de cette agence immobilière et je ne peux vous aider que pour vos projets immobiliers. Cherchez-vous à acheter, louer ou vendre un bien ?\"
- Tu ne fais JAMAIS de programmation, traduction, rédaction de texte, calculs scolaires, ou aide aux devoirs
- Tu ne donnes pas de conseils médicaux, juridiques généraux ou financiers généraux

Ce que tu PEUX faire :
- Aider à définir les critères de recherche d'un bien
- Donner des infos sur le marché immobilier local
- Proposer des rendez-vous de visite
- Expliquer le processus d'achat/vente

Tu es accueillant, à l'écoute et tu cherches à comprendre les besoins du client.",
            'redirect_message' => "Je suis l'assistant de cette agence immobilière et je suis là pour vous accompagner dans vos projets immobiliers. 🏡

Je peux vous aider pour :
• Rechercher un bien à acheter ou louer
• Estimer la valeur d'un bien
• Prendre rendez-vous pour une visite

Quel est votre projet immobilier ?",
            'sort_order' => 2
        ],
        [
            'slug' => 'ecommerce',
            'name' => 'E-commerce',
            'icon' => '🛒',
            'color' => '#10b981',
            'welcome_message' => "Bonjour et bienvenue ! Je suis votre assistant shopping. Je peux vous aider à trouver le produit idéal, suivre votre commande ou répondre à vos questions. Que recherchez-vous ?",
            'system_prompt' => "Tu es EXCLUSIVEMENT un assistant virtuel pour un site e-commerce.

RÈGLES STRICTES - TU DOIS LES RESPECTER :
- Tu ne réponds QU'aux questions sur : produits, commandes, livraison, retours, paiements, disponibilité
- Pour TOUTE question hors sujet (code, maths, rédaction, traduction, actualités, etc.), tu réponds UNIQUEMENT : \"Je suis l'assistant de cette boutique et je ne peux vous aider que pour vos achats. Recherchez-vous un produit ou avez-vous une question sur une commande ?\"
- Tu ne fais JAMAIS de programmation, traduction, rédaction de texte, calculs scolaires, ou aide aux devoirs
- Tu ne donnes pas de conseils médicaux, juridiques ou financiers

Ce que tu PEUX faire :
- Aider à trouver un produit
- Donner des infos sur les caractéristiques produits
- Expliquer le suivi de commande
- Gérer les questions retours/remboursements

Tu es serviable, réactif et tu cherches à maximiser la satisfaction client.",
            'redirect_message' => "Je suis l'assistant de cette boutique en ligne et je suis spécialisé dans l'accompagnement de vos achats. 🛒

Je peux vous aider pour :
• Trouver un produit
• Suivre votre commande
• Gérer un retour

Comment puis-je vous aider avec votre commande ?",
            'sort_order' => 3
        ]
    ];

    $insertStmt = $pdo->prepare("
        INSERT IGNORE INTO demo_chatbots (slug, name, icon, color, welcome_message, system_prompt, redirect_message, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($defaultChatbots as $bot) {
        $insertStmt->execute([
            $bot['slug'],
            $bot['name'],
            $bot['icon'],
            $bot['color'],
            $bot['welcome_message'],
            $bot['system_prompt'],
            $bot['redirect_message'],
            $bot['sort_order']
        ]);
    }
    echo "✓\n";

    // 4. Ajouter le paramètre de limite d'utilisation
    echo "4. Ajout du paramètre de limite d'utilisation... ";
    $pdo->exec("
        INSERT IGNORE INTO settings (setting_key, setting_value, setting_type, setting_group, setting_label)
        VALUES ('demo_daily_limit', '10', 'integer', 'demo', 'Limite messages/jour par utilisateur')
    ");
    echo "✓\n";

    echo "\n=== MISE À JOUR TERMINÉE ===\n";
    echo "\n✅ Tables créées : demo_chatbots, demo_usage\n";
    echo "✅ 3 chatbots par défaut insérés\n";
    echo "✅ Limite par défaut : 10 messages/jour\n";
    echo "\n⚠️  IMPORTANT : Supprimez ce fichier immédiatement !\n";

} catch (Exception $e) {
    echo "✗ ERREUR : " . $e->getMessage() . "\n";
}

echo "</pre>";
