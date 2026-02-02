<?php
/**
 * Script d'installation du système Multi-Agent
 *
 * Ce script ajoute les colonnes et tables nécessaires pour le mode multi-agent
 * sans casser le système existant.
 *
 * Accès : setup-multi-agent.php?key=setup_multiagent_2024
 */
// Vérification de sécurité
$key = $_GET['key'] ?? $_POST['key'] ?? $_SERVER['HTTP_X_SETUP_KEY'] ?? '';
if ($key !== 'setup_multiagent_2024') {
    die('Accès refusé. Clé de sécurité incorrecte.');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

$db = new Database();
$pdo = $db->getPdo();

$results = [];
$errors = [];

try {
    // 1. Ajouter la colonne multi_agent_enabled à client_chatbots si elle n'existe pas
    $columns = $pdo->query("SHOW COLUMNS FROM client_chatbots LIKE 'multi_agent_enabled'")->fetch();
    if (!$columns) {
        $pdo->exec("ALTER TABLE client_chatbots ADD COLUMN multi_agent_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER notification_email");
        $results[] = "✅ Colonne 'multi_agent_enabled' ajoutée à client_chatbots";
    } else {
        $results[] = "ℹ️ Colonne 'multi_agent_enabled' existe déjà";
    }

    // 2. Créer la table agents si elle n'existe pas
    $tableExists = $pdo->query("SHOW TABLES LIKE 'agents'")->fetch();
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE agents (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL COMMENT 'FK vers table clients',
                name VARCHAR(255) NOT NULL COMMENT 'Nom complet de l''agent',
                email VARCHAR(255) NOT NULL COMMENT 'Email de notification',
                phone VARCHAR(50) NULL COMMENT 'Téléphone',
                photo_url VARCHAR(500) NULL COMMENT 'URL de la photo',
                google_calendar_id VARCHAR(255) NULL COMMENT 'ID Google Calendar de l''agent',
                specialties JSON NULL COMMENT 'Spécialités: [\"vente\", \"location\", \"estimation\"]',
                bio TEXT NULL COMMENT 'Description courte de l''agent',
                color VARCHAR(7) NULL DEFAULT '#3498db' COMMENT 'Couleur pour le planning',
                active TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Agent actif ou non',
                sort_order INT NOT NULL DEFAULT 0 COMMENT 'Ordre pour round-robin',
                appointments_count INT NOT NULL DEFAULT 0 COMMENT 'Compteur RDV total',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_client (client_id),
                INDEX idx_client_active (client_id, active),
                INDEX idx_sort (client_id, sort_order),
                CONSTRAINT fk_agents_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $results[] = "✅ Table 'agents' créée";
    } else {
        $results[] = "ℹ️ Table 'agents' existe déjà";
    }

    // 3. Créer la table agent_schedules si elle n'existe pas
    $tableExists = $pdo->query("SHOW TABLES LIKE 'agent_schedules'")->fetch();
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE agent_schedules (
                id INT AUTO_INCREMENT PRIMARY KEY,
                agent_id INT NOT NULL,
                day_of_week TINYINT NOT NULL COMMENT '0=Dim, 1=Lun, 2=Mar, 3=Mer, 4=Jeu, 5=Ven, 6=Sam',
                start_time TIME NOT NULL COMMENT 'Heure début (ex: 09:00)',
                end_time TIME NOT NULL COMMENT 'Heure fin (ex: 18:00)',
                is_available TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Disponible ce jour',
                INDEX idx_agent_day (agent_id, day_of_week),
                CONSTRAINT fk_schedules_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $results[] = "✅ Table 'agent_schedules' créée";
    } else {
        $results[] = "ℹ️ Table 'agent_schedules' existe déjà";
    }

    // 4. Créer la table client_multi_agent_config si elle n'existe pas
    $tableExists = $pdo->query("SHOW TABLES LIKE 'client_multi_agent_config'")->fetch();
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE client_multi_agent_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL UNIQUE COMMENT 'FK vers clients (1-to-1)',
                distribution_mode ENUM('round_robin', 'availability', 'specialty', 'visitor_choice') NOT NULL DEFAULT 'round_robin' COMMENT 'Mode de distribution des RDV',
                allow_visitor_choice TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Visiteur peut choisir agent',
                show_agent_photos TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Afficher photos dans widget',
                show_agent_bios TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Afficher bios dans widget',
                last_assigned_agent_id INT NULL COMMENT 'Dernier agent assigné (pour round-robin)',
                default_specialty VARCHAR(100) NULL COMMENT 'Spécialité par défaut',
                available_specialties JSON NULL COMMENT 'Spécialités disponibles pour ce client',
                booking_duration_default INT NOT NULL DEFAULT 60 COMMENT 'Durée RDV par défaut (minutes)',
                booking_buffer_minutes INT NOT NULL DEFAULT 15 COMMENT 'Temps tampon entre RDV',
                max_days_advance INT NOT NULL DEFAULT 30 COMMENT 'Max jours à l''avance pour RDV',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                CONSTRAINT fk_config_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $results[] = "✅ Table 'client_multi_agent_config' créée";
    } else {
        $results[] = "ℹ️ Table 'client_multi_agent_config' existe déjà";
    }

    // 5. Créer la table appointments_v2 si elle n'existe pas
    $tableExists = $pdo->query("SHOW TABLES LIKE 'appointments_v2'")->fetch();
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE appointments_v2 (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL COMMENT 'FK vers clients',
                agent_id INT NULL COMMENT 'FK vers agents (peut être NULL si agent supprimé)',
                chatbot_type ENUM('demo', 'client') NOT NULL DEFAULT 'client',
                chatbot_id INT NULL,
                visitor_name VARCHAR(255) NOT NULL,
                visitor_email VARCHAR(255) NULL,
                visitor_phone VARCHAR(50) NULL,
                service VARCHAR(255) NULL COMMENT 'Service demandé',
                specialty_requested VARCHAR(100) NULL COMMENT 'Spécialité demandée',
                appointment_date DATE NOT NULL,
                appointment_time TIME NOT NULL,
                duration_minutes INT NOT NULL DEFAULT 60,
                google_event_id VARCHAR(255) NULL COMMENT 'ID événement Google Calendar',
                distribution_method VARCHAR(50) NULL COMMENT 'Comment l''agent a été choisi',
                status ENUM('pending', 'confirmed', 'cancelled', 'completed', 'no_show') NOT NULL DEFAULT 'confirmed',
                notes TEXT NULL COMMENT 'Notes internes',
                visitor_notes TEXT NULL COMMENT 'Notes du visiteur',
                session_id VARCHAR(100) NULL COMMENT 'Session chatbot',
                agent_notified_at DATETIME NULL,
                visitor_notified_at DATETIME NULL,
                reminder_sent_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_client (client_id),
                INDEX idx_agent (agent_id),
                INDEX idx_date (appointment_date),
                INDEX idx_status (status),
                INDEX idx_client_date (client_id, appointment_date),
                INDEX idx_agent_date (agent_id, appointment_date),
                CONSTRAINT fk_appointments_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
                CONSTRAINT fk_appointments_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $results[] = "✅ Table 'appointments_v2' créée";
    } else {
        $results[] = "ℹ️ Table 'appointments_v2' existe déjà";
    }

    // 6. Créer la table agent_unavailability si elle n'existe pas
    $tableExists = $pdo->query("SHOW TABLES LIKE 'agent_unavailability'")->fetch();
    if (!$tableExists) {
        $pdo->exec("
            CREATE TABLE agent_unavailability (
                id INT AUTO_INCREMENT PRIMARY KEY,
                agent_id INT NOT NULL,
                start_datetime DATETIME NOT NULL,
                end_datetime DATETIME NOT NULL,
                reason VARCHAR(255) NULL COMMENT 'Raison (congés, formation, etc.)',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_agent_dates (agent_id, start_datetime, end_datetime),
                CONSTRAINT fk_unavail_agent FOREIGN KEY (agent_id) REFERENCES agents(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $results[] = "✅ Table 'agent_unavailability' créée";
    } else {
        $results[] = "ℹ️ Table 'agent_unavailability' existe déjà";
    }

} catch (PDOException $e) {
    $errors[] = "❌ Erreur SQL : " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation Multi-Agent</title>
    <style>
        body { font-family: -apple-system, sans-serif; max-width: 800px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .card { background: white; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1e293b; margin-bottom: 8px; }
        .subtitle { color: #64748b; margin-bottom: 24px; }
        .result { padding: 12px 16px; border-radius: 8px; margin-bottom: 8px; }
        .success { background: #dcfce7; color: #166534; }
        .info { background: #e0f2fe; color: #0369a1; }
        .error { background: #fee2e2; color: #991b1b; }
        .steps { background: #f0fdf4; padding: 20px; border-radius: 8px; margin-top: 24px; }
        .steps h3 { color: #166534; margin-top: 0; }
        .steps ol { margin: 0; padding-left: 20px; color: #15803d; }
        .steps li { margin-bottom: 8px; }
        a { color: #3b82f6; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Installation Multi-Agent</h1>
        <p class="subtitle">Configuration du système multi-agents pour vos clients</p>

        <?php foreach ($results as $result): ?>
            <div class="result <?= strpos($result, '✅') !== false ? 'success' : 'info' ?>">
                <?= $result ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($errors as $error): ?>
            <div class="result error"><?= $error ?></div>
        <?php endforeach; ?>

        <?php if (empty($errors)): ?>
            <div class="steps">
                <h3>Prochaines étapes</h3>
                <ol>
                    <li>Retournez dans <a href="clients.php">Gestion des Clients</a></li>
                    <li>Éditez un client et activez le <strong>Mode Multi-Agent</strong></li>
                    <li>Cliquez sur <strong>👥 Agents</strong> pour ajouter des commerciaux</li>
                    <li>Configurez les horaires et spécialités de chaque agent</li>
                    <li>Le widget détectera automatiquement le mode et s'adaptera</li>
                </ol>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h3>Tables créées</h3>
        <ul>
            <li><code>agents</code> - Les commerciaux/agents de chaque client</li>
            <li><code>agent_schedules</code> - Horaires de disponibilité par agent</li>
            <li><code>client_multi_agent_config</code> - Configuration du mode de distribution</li>
            <li><code>appointments_v2</code> - RDV avec assignation d'agent</li>
            <li><code>agent_unavailability</code> - Congés et indisponibilités</li>
        </ul>
        <p><small>Colonne <code>multi_agent_enabled</code> ajoutée à <code>client_chatbots</code></small></p>
    </div>
</body>
</html>
