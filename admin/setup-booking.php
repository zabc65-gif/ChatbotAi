<?php
/**
 * Migration: Système de prise de rendez-vous
 * Crée la table appointments et ajoute les colonnes booking aux chatbots
 */

$pageTitle = 'Installation - Rendez-vous';
require_once 'includes/header.php';

// Clé de sécurité
$key = $_GET['key'] ?? '';
if ($key !== 'setup_booking_2024') {
    echo '<div class="alert alert-error">Accès non autorisé. Ajoutez ?key=setup_booking_2024 à l\'URL.</div>';
    require_once 'includes/footer.php';
    exit;
}

$results = [];

try {
    // 1. Créer la table appointments
    $db->query("CREATE TABLE IF NOT EXISTS appointments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NULL,
        chatbot_type ENUM('demo', 'client') NOT NULL DEFAULT 'demo',
        chatbot_id INT NULL,
        visitor_name VARCHAR(255) NOT NULL,
        visitor_email VARCHAR(255) NULL,
        visitor_phone VARCHAR(50) NULL,
        service VARCHAR(255) NULL,
        appointment_date DATE NOT NULL,
        appointment_time TIME NOT NULL,
        duration_minutes INT NOT NULL DEFAULT 60,
        google_event_id VARCHAR(255) NULL,
        status ENUM('pending', 'confirmed', 'cancelled') NOT NULL DEFAULT 'confirmed',
        notes TEXT NULL,
        session_id VARCHAR(100) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_client (client_id),
        INDEX idx_date (appointment_date),
        INDEX idx_status (status),
        INDEX idx_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $results[] = ['success' => true, 'message' => 'Table "appointments" créée'];

    // 2. Ajouter les colonnes booking à demo_chatbots
    $columns = $db->fetchAll("SHOW COLUMNS FROM demo_chatbots LIKE 'booking_enabled'");
    if (empty($columns)) {
        $db->query("ALTER TABLE demo_chatbots
            ADD COLUMN booking_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN google_calendar_id VARCHAR(255) NULL,
            ADD COLUMN notification_email VARCHAR(255) NULL");
        $results[] = ['success' => true, 'message' => 'Colonnes booking ajoutées à "demo_chatbots"'];
    } else {
        $results[] = ['success' => true, 'message' => 'Colonnes booking déjà présentes dans "demo_chatbots"'];
    }

    // 3. Ajouter les colonnes booking à client_chatbots
    $columns = $db->fetchAll("SHOW COLUMNS FROM client_chatbots LIKE 'booking_enabled'");
    if (empty($columns)) {
        $db->query("ALTER TABLE client_chatbots
            ADD COLUMN booking_enabled TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN google_calendar_id VARCHAR(255) NULL,
            ADD COLUMN notification_email VARCHAR(255) NULL");
        $results[] = ['success' => true, 'message' => 'Colonnes booking ajoutées à "client_chatbots"'];
    } else {
        $results[] = ['success' => true, 'message' => 'Colonnes booking déjà présentes dans "client_chatbots"'];
    }

} catch (Exception $e) {
    $results[] = ['success' => false, 'message' => 'Erreur : ' . $e->getMessage()];
}
?>

<div class="page-header">
    <h1 class="page-title">Installation - Système de Rendez-vous</h1>
    <p class="page-subtitle">Migration de la base de données</p>
</div>

<div class="card">
    <h2 class="card-title">Résultats de la migration</h2>

    <?php foreach ($results as $result): ?>
        <div class="alert <?= $result['success'] ? 'alert-success' : 'alert-error' ?>" style="margin-bottom: 12px;">
            <?= $result['success'] ? '✓' : '✗' ?> <?= htmlspecialchars($result['message']) ?>
        </div>
    <?php endforeach; ?>

    <div style="margin-top: 24px; padding: 16px; background: #eff6ff; border-radius: 8px;">
        <h4 style="margin-bottom: 8px; color: #1d4ed8;">Prochaines étapes</h4>
        <ol style="padding-left: 20px; color: #1e40af; font-size: 14px;">
            <li>Créer un projet Google Cloud et activer l'API Google Calendar</li>
            <li>Créer un Service Account et télécharger le fichier JSON</li>
            <li>Uploader le fichier dans <code>credentials/google-service-account.json</code></li>
            <li>Activer la prise de RDV sur vos chatbots dans l'admin</li>
            <li>Partager le Google Calendar avec l'email du Service Account</li>
        </ol>
    </div>

    <a href="demo-chatbots.php" class="btn btn-primary" style="margin-top: 16px;">Retour aux chatbots</a>
</div>

<?php require_once 'includes/footer.php'; ?>
