<?php
/**
 * AgentScheduleManager - Gestion des horaires et disponibilités des agents
 */

require_once __DIR__ . '/../../classes/Database.php';

class AgentScheduleManager
{
    private Database $db;

    // Jours de la semaine
    const DAYS = [
        0 => 'Dimanche',
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi'
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Récupère les horaires d'un agent
     */
    public function getSchedules(int $agentId): array
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT * FROM agent_schedules
            WHERE agent_id = ?
            ORDER BY day_of_week ASC, start_time ASC
        ");
        $stmt->execute([$agentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les horaires d'un agent groupés par jour
     */
    public function getSchedulesByDay(int $agentId): array
    {
        $schedules = $this->getSchedules($agentId);
        $byDay = [];

        foreach (self::DAYS as $dayNum => $dayName) {
            $byDay[$dayNum] = [
                'name' => $dayName,
                'slots' => []
            ];
        }

        foreach ($schedules as $schedule) {
            $byDay[$schedule['day_of_week']]['slots'][] = [
                'id' => $schedule['id'],
                'start' => $schedule['start_time'],
                'end' => $schedule['end_time'],
                'available' => (bool)$schedule['is_available']
            ];
        }

        return $byDay;
    }

    /**
     * Ajoute un créneau horaire
     */
    public function addSchedule(int $agentId, int $dayOfWeek, string $startTime, string $endTime, bool $isAvailable = true): int
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO agent_schedules (agent_id, day_of_week, start_time, end_time, is_available)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$agentId, $dayOfWeek, $startTime, $endTime, $isAvailable ? 1 : 0]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Met à jour un créneau horaire
     */
    public function updateSchedule(int $scheduleId, string $startTime, string $endTime, bool $isAvailable): bool
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            UPDATE agent_schedules
            SET start_time = ?, end_time = ?, is_available = ?
            WHERE id = ?
        ");
        return $stmt->execute([$startTime, $endTime, $isAvailable ? 1 : 0, $scheduleId]);
    }

    /**
     * Supprime un créneau horaire
     */
    public function deleteSchedule(int $scheduleId): bool
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM agent_schedules WHERE id = ?");
        return $stmt->execute([$scheduleId]);
    }

    /**
     * Supprime tous les horaires d'un agent
     */
    public function deleteAllSchedules(int $agentId): bool
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM agent_schedules WHERE agent_id = ?");
        return $stmt->execute([$agentId]);
    }

    /**
     * Initialise les horaires par défaut (Lun-Ven 9h-12h et 14h-18h)
     */
    public function initDefaultSchedules(int $agentId): void
    {
        // Supprimer les horaires existants
        $this->deleteAllSchedules($agentId);

        // Lundi à Vendredi
        for ($day = 1; $day <= 5; $day++) {
            // Matin
            $this->addSchedule($agentId, $day, '09:00:00', '12:00:00', true);
            // Après-midi
            $this->addSchedule($agentId, $day, '14:00:00', '18:00:00', true);
        }

        // Samedi matin (désactivé par défaut)
        $this->addSchedule($agentId, 6, '09:00:00', '12:00:00', false);
    }

    /**
     * Sauvegarde les horaires depuis un tableau de données
     */
    public function saveSchedulesFromArray(int $agentId, array $schedules): bool
    {
        $pdo = $this->db->getConnection();

        try {
            $pdo->beginTransaction();

            // Supprimer les anciens horaires
            $this->deleteAllSchedules($agentId);

            // Insérer les nouveaux
            foreach ($schedules as $schedule) {
                if (!empty($schedule['start_time']) && !empty($schedule['end_time'])) {
                    $this->addSchedule(
                        $agentId,
                        (int)$schedule['day_of_week'],
                        $schedule['start_time'],
                        $schedule['end_time'],
                        isset($schedule['is_available']) ? (bool)$schedule['is_available'] : true
                    );
                }
            }

            $pdo->commit();
            return true;

        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("AgentScheduleManager: Erreur sauvegarde - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Vérifie si un créneau est dans les horaires de travail
     */
    public function isTimeSlotAvailable(int $agentId, string $date, string $time, int $duration = 60): bool
    {
        $dayOfWeek = (int)date('w', strtotime($date));
        $requestedStart = strtotime($time);
        $requestedEnd = strtotime($time . " +{$duration} minutes");

        $schedules = $this->getSchedules($agentId);

        foreach ($schedules as $schedule) {
            if ($schedule['day_of_week'] != $dayOfWeek || !$schedule['is_available']) {
                continue;
            }

            $slotStart = strtotime($schedule['start_time']);
            $slotEnd = strtotime($schedule['end_time']);

            // Le créneau demandé doit être entièrement dans le slot
            if ($requestedStart >= $slotStart && $requestedEnd <= $slotEnd) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupère les créneaux disponibles pour un jour donné
     */
    public function getAvailableSlots(int $agentId, string $date, int $slotDuration = 60, int $buffer = 15): array
    {
        $dayOfWeek = (int)date('w', strtotime($date));
        $schedules = $this->getSchedules($agentId);
        $slots = [];

        foreach ($schedules as $schedule) {
            if ($schedule['day_of_week'] != $dayOfWeek || !$schedule['is_available']) {
                continue;
            }

            $currentTime = strtotime($schedule['start_time']);
            $endTime = strtotime($schedule['end_time']);

            while ($currentTime + ($slotDuration * 60) <= $endTime) {
                $slots[] = date('H:i', $currentTime);
                $currentTime += ($slotDuration + $buffer) * 60;
            }
        }

        return $slots;
    }

    // ==================== GESTION DES INDISPONIBILITÉS ====================

    /**
     * Ajoute une indisponibilité exceptionnelle
     */
    public function addUnavailability(int $agentId, string $startDatetime, string $endDatetime, ?string $reason = null): int
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            INSERT INTO agent_unavailability (agent_id, start_datetime, end_datetime, reason)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$agentId, $startDatetime, $endDatetime, $reason]);

        return (int)$pdo->lastInsertId();
    }

    /**
     * Récupère les indisponibilités d'un agent
     */
    public function getUnavailabilities(int $agentId, ?string $fromDate = null, ?string $toDate = null): array
    {
        $pdo = $this->db->getConnection();

        $query = "SELECT * FROM agent_unavailability WHERE agent_id = ?";
        $params = [$agentId];

        if ($fromDate) {
            $query .= " AND end_datetime >= ?";
            $params[] = $fromDate;
        }

        if ($toDate) {
            $query .= " AND start_datetime <= ?";
            $params[] = $toDate;
        }

        $query .= " ORDER BY start_datetime ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime une indisponibilité
     */
    public function deleteUnavailability(int $unavailabilityId): bool
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("DELETE FROM agent_unavailability WHERE id = ?");
        return $stmt->execute([$unavailabilityId]);
    }

    /**
     * Vérifie si un agent a une indisponibilité à un moment donné
     */
    public function hasUnavailability(int $agentId, string $datetime): bool
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM agent_unavailability
            WHERE agent_id = ?
            AND ? BETWEEN start_datetime AND end_datetime
        ");
        $stmt->execute([$agentId, $datetime]);

        return $stmt->fetchColumn() > 0;
    }

    // ==================== DONNÉES POUR LE CALENDRIER ====================

    /**
     * Récupère les événements pour FullCalendar (horaires + RDV + indispos)
     */
    public function getCalendarEvents(int $agentId, string $startDate, string $endDate): array
    {
        $events = [];
        $pdo = $this->db->getConnection();

        // Récupérer l'agent pour sa couleur
        $stmt = $pdo->prepare("SELECT name, color FROM agents WHERE id = ?");
        $stmt->execute([$agentId]);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);
        $color = $agent['color'] ?? '#3498db';

        // 1. Générer les plages de disponibilité
        $schedules = $this->getSchedulesByDay($agentId);
        $currentDate = new DateTime($startDate);
        $endDateObj = new DateTime($endDate);

        while ($currentDate <= $endDateObj) {
            $dayOfWeek = (int)$currentDate->format('w');
            $dateStr = $currentDate->format('Y-m-d');

            foreach ($schedules[$dayOfWeek]['slots'] as $slot) {
                if ($slot['available']) {
                    $events[] = [
                        'id' => 'schedule_' . $slot['id'] . '_' . $dateStr,
                        'title' => 'Disponible',
                        'start' => $dateStr . 'T' . $slot['start'],
                        'end' => $dateStr . 'T' . $slot['end'],
                        'color' => $color,
                        'display' => 'background',
                        'type' => 'availability'
                    ];
                }
            }

            $currentDate->modify('+1 day');
        }

        // 2. Récupérer les RDV
        $stmt = $pdo->prepare("
            SELECT id, visitor_name, service, appointment_date, appointment_time, duration_minutes, status
            FROM appointments_v2
            WHERE agent_id = ? AND appointment_date BETWEEN ? AND ?
            ORDER BY appointment_date, appointment_time
        ");
        $stmt->execute([$agentId, $startDate, $endDate]);
        $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($appointments as $apt) {
            $startTime = $apt['appointment_date'] . 'T' . $apt['appointment_time'];
            $endTime = date('Y-m-d\TH:i:s', strtotime($startTime . " +{$apt['duration_minutes']} minutes"));

            $statusColors = [
                'confirmed' => '#27ae60',
                'pending' => '#f39c12',
                'cancelled' => '#e74c3c',
                'completed' => '#95a5a6',
                'no_show' => '#c0392b'
            ];

            $events[] = [
                'id' => 'apt_' . $apt['id'],
                'title' => $apt['visitor_name'] . ($apt['service'] ? ' - ' . $apt['service'] : ''),
                'start' => $startTime,
                'end' => $endTime,
                'color' => $statusColors[$apt['status']] ?? '#3498db',
                'type' => 'appointment',
                'status' => $apt['status'],
                'extendedProps' => [
                    'appointmentId' => $apt['id'],
                    'visitorName' => $apt['visitor_name'],
                    'service' => $apt['service']
                ]
            ];
        }

        // 3. Récupérer les indisponibilités
        $unavailabilities = $this->getUnavailabilities($agentId, $startDate, $endDate);

        foreach ($unavailabilities as $unavail) {
            $events[] = [
                'id' => 'unavail_' . $unavail['id'],
                'title' => $unavail['reason'] ?? 'Indisponible',
                'start' => $unavail['start_datetime'],
                'end' => $unavail['end_datetime'],
                'color' => '#95a5a6',
                'type' => 'unavailability',
                'extendedProps' => [
                    'unavailabilityId' => $unavail['id'],
                    'reason' => $unavail['reason']
                ]
            ];
        }

        return $events;
    }

    /**
     * Récupère les événements de tous les agents d'un client pour vue globale
     */
    public function getAllAgentsCalendarEvents(int $clientId, string $startDate, string $endDate): array
    {
        $pdo = $this->db->getConnection();

        // Récupérer tous les agents actifs
        $stmt = $pdo->prepare("SELECT id, name, color FROM agents WHERE client_id = ? AND active = 1");
        $stmt->execute([$clientId]);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $allEvents = [];

        foreach ($agents as $agent) {
            $events = $this->getCalendarEvents($agent['id'], $startDate, $endDate);

            // Ajouter le nom de l'agent à chaque événement
            foreach ($events as &$event) {
                $event['agentId'] = $agent['id'];
                $event['agentName'] = $agent['name'];
                if ($event['type'] === 'appointment') {
                    $event['title'] = '[' . $agent['name'] . '] ' . $event['title'];
                }
            }

            $allEvents = array_merge($allEvents, $events);
        }

        return $allEvents;
    }
}
