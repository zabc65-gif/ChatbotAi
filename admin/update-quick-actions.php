<?php
/**
 * Script de mise à jour pour ajouter les questions suggérées (quick actions)
 * aux chatbots de démo et au chatbot principal
 * À exécuter une seule fois puis à supprimer
 */

$secret = 'update_quick_actions_2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Accès non autorisé');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

echo "<pre style='font-family: monospace; padding: 20px;'>";
echo "=== MISE À JOUR QUESTIONS SUGGÉRÉES ===\n\n";

try {
    $db = new Database();
    $pdo = $db->getPdo();

    // 1. Ajouter la colonne quick_actions à la table demo_chatbots si elle n'existe pas
    echo "1. Vérification de la colonne quick_actions dans demo_chatbots... ";

    $columns = $pdo->query("SHOW COLUMNS FROM demo_chatbots LIKE 'quick_actions'")->fetchAll();

    if (empty($columns)) {
        $pdo->exec("
            ALTER TABLE demo_chatbots
            ADD COLUMN quick_actions TEXT NULL COMMENT 'Questions suggérées séparées par des retours à la ligne'
            AFTER redirect_message
        ");
        echo "✓ Colonne ajoutée\n";

        // Mettre à jour les chatbots existants avec des valeurs par défaut
        echo "2. Ajout des questions par défaut aux chatbots existants...\n";

        $defaultQuickActions = [
            'btp' => "Demander un devis\nDisponibilité intervention\nZone d'intervention",
            'immo' => "Estimer mon bien\nPrendre rendez-vous\nTypes de mandats",
            'ecommerce' => "Suivre ma commande\nPolitique de retour\nMoyens de paiement"
        ];

        $stmt = $pdo->prepare("UPDATE demo_chatbots SET quick_actions = ? WHERE slug = ?");
        foreach ($defaultQuickActions as $slug => $actions) {
            $stmt->execute([$actions, $slug]);
            echo "   - {$slug}: ✓\n";
        }

        // Mettre un défaut générique pour les autres chatbots
        $pdo->exec("
            UPDATE demo_chatbots
            SET quick_actions = 'Demander un devis\nEn savoir plus\nContact'
            WHERE quick_actions IS NULL
        ");
        echo "   - Autres chatbots (défaut): ✓\n";

    } else {
        echo "✓ Colonne déjà existante\n";
    }

    // 3. Vérifier si le setting quick_actions existe pour le chatbot principal
    echo "3. Vérification du setting quick_actions pour le chatbot principal... ";

    $setting = $pdo->query("SELECT * FROM settings WHERE setting_key = 'chatbot_quick_actions'")->fetch();

    if (!$setting) {
        $pdo->exec("
            INSERT INTO settings (setting_key, setting_value, setting_type, setting_label, setting_group)
            VALUES (
                'chatbot_quick_actions',
                'Demander un devis\nEn savoir plus\nContact',
                'textarea',
                'Questions suggérées',
                'chatbot'
            )
        ");
        echo "✓ Setting ajouté\n";
    } else {
        echo "✓ Setting déjà existant\n";
    }

    echo "\n=== MISE À JOUR TERMINÉE ===\n";
    echo "\n✅ Les questions suggérées sont maintenant personnalisables :\n";
    echo "   - Chatbot principal : Admin > Chatbot Principal\n";
    echo "   - Chatbots démo : Admin > Chatbots Démo > Modifier\n";
    echo "\n⚠️  IMPORTANT : Supprimez ce fichier après exécution !\n";

} catch (Exception $e) {
    echo "✗ ERREUR : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
