<?php
/**
 * Script de mise à jour pour permettre les champs du chatbot principal
 * Supprime la contrainte de clé étrangère pour autoriser chatbot_id = 0
 * À exécuter une seule fois puis à supprimer
 */

$secret = 'update_main_fields_2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Accès non autorisé');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

echo "<pre style='font-family: monospace; padding: 20px;'>";
echo "=== MISE À JOUR CHAMPS CHATBOT PRINCIPAL ===\n\n";

try {
    $db = new Database();
    $pdo = $db->getPdo();

    // 1. Trouver et supprimer la contrainte de clé étrangère
    echo "1. Recherche de la contrainte de clé étrangère... ";

    // Récupérer le nom de la contrainte
    $constraints = $pdo->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'chatbot_field_values'
        AND REFERENCED_TABLE_NAME = 'demo_chatbots'
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($constraints)) {
        foreach ($constraints as $constraint) {
            $constraintName = $constraint['CONSTRAINT_NAME'];
            echo "\n   Suppression de la contrainte '{$constraintName}'... ";
            $pdo->exec("ALTER TABLE chatbot_field_values DROP FOREIGN KEY `{$constraintName}`");
            echo "✓";
        }
        echo "\n";
    } else {
        echo "Aucune contrainte trouvée (déjà supprimée ou non créée). ✓\n";
    }

    // 2. Modifier le commentaire de la colonne pour documenter le changement
    echo "2. Mise à jour de la documentation de la table... ";
    $pdo->exec("
        ALTER TABLE chatbot_field_values
        MODIFY COLUMN chatbot_id INT NOT NULL COMMENT 'ID du chatbot (0 = principal, autre = demo_chatbots.id)'
    ");
    echo "✓\n";

    // 3. Ajouter les champs généraux s'ils n'existent pas déjà (ils devraient exister)
    echo "3. Vérification des champs généraux... ";
    $generalFieldsCount = $pdo->query("
        SELECT COUNT(*) FROM chatbot_field_definitions WHERE sector = 'general'
    ")->fetchColumn();
    echo "{$generalFieldsCount} champs généraux disponibles ✓\n";

    echo "\n=== MISE À JOUR TERMINÉE ===\n";
    echo "\n✅ Le chatbot principal peut maintenant avoir des informations personnalisées\n";
    echo "✅ Accédez à Admin > Chatbot Principal > 📋 Informations pour les renseigner\n";
    echo "\n⚠️  IMPORTANT : Supprimez ce fichier après exécution !\n";

} catch (Exception $e) {
    echo "✗ ERREUR : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
