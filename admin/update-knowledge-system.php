<?php
/**
 * Script de mise à jour - Système d'apprentissage/connaissances
 * Crée la table chatbot_knowledge pour stocker les FAQ et informations
 */

$pageTitle = 'Mise à jour - Système de Connaissances';
require_once 'includes/header.php';

$key = $_GET['key'] ?? '';
$executed = false;
$errors = [];
$success = [];

if ($key === 'install_knowledge_2024') {
    try {
        // Créer la table chatbot_knowledge
        // chatbot_id NULL = chatbot principal, sinon = ID du chatbot démo
        $sql = "CREATE TABLE IF NOT EXISTS chatbot_knowledge (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chatbot_id INT DEFAULT NULL COMMENT 'ID du chatbot (NULL = chatbot principal)',
            type ENUM('faq', 'info', 'response') NOT NULL DEFAULT 'faq' COMMENT 'Type de connaissance',
            question VARCHAR(500) DEFAULT NULL COMMENT 'Question (pour FAQ)',
            answer TEXT NOT NULL COMMENT 'Réponse ou information',
            keywords VARCHAR(500) DEFAULT NULL COMMENT 'Mots-clés pour la recherche',
            active TINYINT(1) DEFAULT 1 COMMENT 'Actif/Inactif',
            sort_order INT DEFAULT 0 COMMENT 'Ordre d''affichage',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

            INDEX idx_chatbot_id (chatbot_id),
            INDEX idx_type (type),
            INDEX idx_active (active),
            INDEX idx_chatbot_active (chatbot_id, active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $db->query($sql);
        $success[] = "Table 'chatbot_knowledge' créée avec succès !";

        // Vérifier si la table existe
        $check = $db->fetchOne("SHOW TABLES LIKE 'chatbot_knowledge'");
        if ($check) {
            $success[] = "Vérification OK - La table existe.";
        }

        $executed = true;

    } catch (Exception $e) {
        $errors[] = "Erreur : " . $e->getMessage();
    }
}
?>

<div class="card">
    <h1 class="card-title">Mise à jour : Système d'Apprentissage</h1>

    <?php if (!$executed): ?>
        <div style="background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
            <h3 style="color: #92400e; margin: 0 0 8px 0;">Cette mise à jour va créer :</h3>
            <ul style="margin: 0; color: #92400e;">
                <li>Table <code>chatbot_knowledge</code> pour stocker les FAQ et informations personnalisées</li>
            </ul>
        </div>

        <p>Cette table permet d'ajouter des connaissances spécifiques à chaque chatbot :</p>
        <ul>
            <li><strong>FAQ</strong> : Questions fréquentes avec leurs réponses</li>
            <li><strong>Info</strong> : Informations générales sur l'entreprise</li>
            <li><strong>Response</strong> : Réponses personnalisées pour des cas spécifiques</li>
        </ul>

        <a href="?key=install_knowledge_2024" class="btn btn-primary" onclick="return confirm('Lancer la mise à jour ?')">
            Exécuter la mise à jour
        </a>
    <?php else: ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <strong>Mise à jour réussie !</strong>
                <ul style="margin: 8px 0 0 0;">
                    <?php foreach ($success as $msg): ?>
                        <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <strong>Erreurs :</strong>
                <ul style="margin: 8px 0 0 0;">
                    <?php foreach ($errors as $msg): ?>
                        <li><?= htmlspecialchars($msg) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div style="margin-top: 20px;">
            <a href="demo-chatbots.php" class="btn btn-primary">Retour aux chatbots</a>
        </div>
    <?php endif; ?>
</div>

<?php require_once 'includes/footer.php'; ?>
