-- =====================================================
-- CHATBOT MULTI-AGENTS V2 - Installation SQL
-- =====================================================
-- Ce script crée les tables nécessaires pour gérer
-- plusieurs agents/commerciaux avec leurs propres agendas
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- Table: agents
-- Stocke les agents/commerciaux de chaque client
-- =====================================================
CREATE TABLE IF NOT EXISTS `agents` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL COMMENT 'FK vers table clients',
    `name` VARCHAR(255) NOT NULL COMMENT 'Nom complet de l''agent',
    `email` VARCHAR(255) NOT NULL COMMENT 'Email de notification',
    `phone` VARCHAR(50) NULL COMMENT 'Téléphone',
    `photo_url` VARCHAR(500) NULL COMMENT 'URL de la photo',
    `google_calendar_id` VARCHAR(255) NULL COMMENT 'ID Google Calendar de l''agent',
    `specialties` JSON NULL COMMENT 'Spécialités: ["vente", "location", "estimation"]',
    `bio` TEXT NULL COMMENT 'Description courte de l''agent',
    `color` VARCHAR(7) NULL DEFAULT '#3498db' COMMENT 'Couleur pour le planning',
    `active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Agent actif ou non',
    `sort_order` INT NOT NULL DEFAULT 0 COMMENT 'Ordre pour round-robin',
    `appointments_count` INT NOT NULL DEFAULT 0 COMMENT 'Compteur RDV total',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_client` (`client_id`),
    INDEX `idx_client_active` (`client_id`, `active`),
    INDEX `idx_sort` (`client_id`, `sort_order`),
    CONSTRAINT `fk_agents_client` FOREIGN KEY (`client_id`)
        REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: agent_schedules
-- Horaires de disponibilité par agent et par jour
-- =====================================================
CREATE TABLE IF NOT EXISTS `agent_schedules` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `agent_id` INT NOT NULL,
    `day_of_week` TINYINT NOT NULL COMMENT '0=Dim, 1=Lun, 2=Mar, 3=Mer, 4=Jeu, 5=Ven, 6=Sam',
    `start_time` TIME NOT NULL COMMENT 'Heure début (ex: 09:00)',
    `end_time` TIME NOT NULL COMMENT 'Heure fin (ex: 18:00)',
    `is_available` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Disponible ce jour',

    INDEX `idx_agent_day` (`agent_id`, `day_of_week`),
    CONSTRAINT `fk_schedules_agent` FOREIGN KEY (`agent_id`)
        REFERENCES `agents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: client_multi_agent_config
-- Configuration multi-agent par client
-- =====================================================
CREATE TABLE IF NOT EXISTS `client_multi_agent_config` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL UNIQUE COMMENT 'FK vers clients (1-to-1)',
    `distribution_mode` ENUM('round_robin', 'availability', 'specialty', 'visitor_choice')
        NOT NULL DEFAULT 'round_robin' COMMENT 'Mode de distribution des RDV',
    `allow_visitor_choice` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Visiteur peut choisir agent',
    `show_agent_photos` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Afficher photos dans widget',
    `show_agent_bios` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Afficher bios dans widget',
    `last_assigned_agent_id` INT NULL COMMENT 'Dernier agent assigné (pour round-robin)',
    `default_specialty` VARCHAR(100) NULL COMMENT 'Spécialité par défaut',
    `available_specialties` JSON NULL COMMENT 'Spécialités disponibles pour ce client',
    `booking_duration_default` INT NOT NULL DEFAULT 60 COMMENT 'Durée RDV par défaut (minutes)',
    `booking_buffer_minutes` INT NOT NULL DEFAULT 15 COMMENT 'Temps tampon entre RDV',
    `max_days_advance` INT NOT NULL DEFAULT 30 COMMENT 'Max jours à l''avance pour RDV',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT `fk_config_client` FOREIGN KEY (`client_id`)
        REFERENCES `clients`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: appointments_v2
-- Rendez-vous avec gestion multi-agents
-- =====================================================
CREATE TABLE IF NOT EXISTS `appointments_v2` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `client_id` INT NOT NULL COMMENT 'FK vers clients',
    `agent_id` INT NULL COMMENT 'FK vers agents (peut être NULL si agent supprimé)',
    `chatbot_type` ENUM('demo', 'client') NOT NULL DEFAULT 'client',
    `chatbot_id` INT NULL,

    -- Informations visiteur
    `visitor_name` VARCHAR(255) NOT NULL,
    `visitor_email` VARCHAR(255) NULL,
    `visitor_phone` VARCHAR(50) NULL,

    -- Informations RDV
    `service` VARCHAR(255) NULL COMMENT 'Service demandé',
    `specialty_requested` VARCHAR(100) NULL COMMENT 'Spécialité demandée',
    `appointment_date` DATE NOT NULL,
    `appointment_time` TIME NOT NULL,
    `duration_minutes` INT NOT NULL DEFAULT 60,

    -- Tracking et intégration
    `google_event_id` VARCHAR(255) NULL COMMENT 'ID événement Google Calendar',
    `distribution_method` VARCHAR(50) NULL COMMENT 'Comment l''agent a été choisi',
    `status` ENUM('pending', 'confirmed', 'cancelled', 'completed', 'no_show')
        NOT NULL DEFAULT 'confirmed',
    `notes` TEXT NULL COMMENT 'Notes internes',
    `visitor_notes` TEXT NULL COMMENT 'Notes du visiteur',
    `session_id` VARCHAR(100) NULL COMMENT 'Session chatbot',

    -- Notifications
    `agent_notified_at` DATETIME NULL,
    `visitor_notified_at` DATETIME NULL,
    `reminder_sent_at` DATETIME NULL,

    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_client` (`client_id`),
    INDEX `idx_agent` (`agent_id`),
    INDEX `idx_date` (`appointment_date`),
    INDEX `idx_status` (`status`),
    INDEX `idx_client_date` (`client_id`, `appointment_date`),
    INDEX `idx_agent_date` (`agent_id`, `appointment_date`),
    CONSTRAINT `fk_appointments_client` FOREIGN KEY (`client_id`)
        REFERENCES `clients`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_appointments_agent` FOREIGN KEY (`agent_id`)
        REFERENCES `agents`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: agent_unavailability
-- Indisponibilités exceptionnelles (congés, etc.)
-- =====================================================
CREATE TABLE IF NOT EXISTS `agent_unavailability` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `agent_id` INT NOT NULL,
    `start_datetime` DATETIME NOT NULL,
    `end_datetime` DATETIME NOT NULL,
    `reason` VARCHAR(255) NULL COMMENT 'Raison (congés, formation, etc.)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_agent_dates` (`agent_id`, `start_datetime`, `end_datetime`),
    CONSTRAINT `fk_unavail_agent` FOREIGN KEY (`agent_id`)
        REFERENCES `agents`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Insertions par défaut
-- =====================================================

-- Spécialités par défaut (à personnaliser selon le secteur)
-- Exemple pour une agence immobilière:
-- INSERT INTO client_multi_agent_config (client_id, distribution_mode, available_specialties)
-- VALUES (1, 'round_robin', '["vente", "location", "estimation", "gestion"]');

-- =====================================================
-- Procédure pour initialiser un client en multi-agent
-- =====================================================
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS `init_multi_agent_config`(IN p_client_id INT)
BEGIN
    INSERT IGNORE INTO client_multi_agent_config (client_id, distribution_mode, available_specialties)
    VALUES (p_client_id, 'round_robin', '["vente", "location", "estimation", "gestion", "conseil"]');
END //

-- =====================================================
-- Procédure pour créer les horaires par défaut d'un agent
-- (Lun-Ven 9h-12h et 14h-18h)
-- =====================================================
CREATE PROCEDURE IF NOT EXISTS `init_agent_default_schedule`(IN p_agent_id INT)
BEGIN
    -- Lundi à Vendredi : 9h-12h
    INSERT INTO agent_schedules (agent_id, day_of_week, start_time, end_time, is_available)
    VALUES
        (p_agent_id, 1, '09:00:00', '12:00:00', 1),
        (p_agent_id, 2, '09:00:00', '12:00:00', 1),
        (p_agent_id, 3, '09:00:00', '12:00:00', 1),
        (p_agent_id, 4, '09:00:00', '12:00:00', 1),
        (p_agent_id, 5, '09:00:00', '12:00:00', 1);

    -- Lundi à Vendredi : 14h-18h
    INSERT INTO agent_schedules (agent_id, day_of_week, start_time, end_time, is_available)
    VALUES
        (p_agent_id, 1, '14:00:00', '18:00:00', 1),
        (p_agent_id, 2, '14:00:00', '18:00:00', 1),
        (p_agent_id, 3, '14:00:00', '18:00:00', 1),
        (p_agent_id, 4, '14:00:00', '18:00:00', 1),
        (p_agent_id, 5, '14:00:00', '18:00:00', 1);

    -- Samedi : 9h-12h (optionnel)
    INSERT INTO agent_schedules (agent_id, day_of_week, start_time, end_time, is_available)
    VALUES (p_agent_id, 6, '09:00:00', '12:00:00', 0);

    -- Dimanche : fermé
    INSERT INTO agent_schedules (agent_id, day_of_week, start_time, end_time, is_available)
    VALUES (p_agent_id, 0, '00:00:00', '00:00:00', 0);
END //

DELIMITER ;

-- =====================================================
-- Vue pour statistiques agents
-- =====================================================
CREATE OR REPLACE VIEW `v_agent_stats` AS
SELECT
    a.id,
    a.client_id,
    a.name,
    a.email,
    a.active,
    COUNT(DISTINCT ap.id) as total_appointments,
    COUNT(DISTINCT CASE WHEN ap.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ap.id END) as appointments_last_30_days,
    COUNT(DISTINCT CASE WHEN ap.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN ap.id END) as appointments_last_7_days,
    COUNT(DISTINCT CASE WHEN ap.status = 'completed' THEN ap.id END) as completed_appointments,
    COUNT(DISTINCT CASE WHEN ap.status = 'cancelled' THEN ap.id END) as cancelled_appointments,
    COUNT(DISTINCT CASE WHEN ap.status = 'no_show' THEN ap.id END) as no_show_appointments
FROM agents a
LEFT JOIN appointments_v2 ap ON ap.agent_id = a.id
GROUP BY a.id, a.client_id, a.name, a.email, a.active;

-- =====================================================
-- Message de fin
-- =====================================================
SELECT 'Installation Multi-Agent V2 terminée avec succès!' AS message;
SELECT 'Tables créées: agents, agent_schedules, client_multi_agent_config, appointments_v2, agent_unavailability' AS tables;
SELECT 'Procédures créées: init_multi_agent_config, init_agent_default_schedule' AS procedures;
SELECT 'Vue créée: v_agent_stats' AS views;
