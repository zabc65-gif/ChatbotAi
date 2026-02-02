<?php
/**
 * AgentDistributor - Distribue les RDV aux agents selon différents modes
 *
 * Modes disponibles:
 * - round_robin: Tour à tour équitable
 * - availability: Par disponibilité (vérifie Google Calendar)
 * - specialty: Par spécialité/compétence
 * - visitor_choice: Le visiteur choisit l'agent
 */

require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/GoogleCalendar.php';

class AgentDistributor
{
    private Database $db;
    private ?GoogleCalendar $calendar = null;

    // Constantes pour les modes
    const MODE_ROUND_ROBIN = 'round_robin';
    const MODE_AVAILABILITY = 'availability';
    const MODE_SPECIALTY = 'specialty';
    const MODE_VISITOR_CHOICE = 'visitor_choice';

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Sélectionne le meilleur agent selon le mode configuré pour ce client
     *
     * @param int $clientId ID du client
     * @param string|null $requestedSpecialty Spécialité demandée (optionnel)
     * @param string|null $preferredDate Date souhaitée (format Y-m-d)
     * @param string|null $preferredTime Heure souhaitée (format H:i)
     * @param int|null $visitorChoiceAgentId ID de l'agent choisi par le visiteur
     * @return array|null Agent sélectionné ou null si aucun disponible
     */
    public function selectAgent(
        int $clientId,
        ?string $requestedSpecialty = null,
        ?string $preferredDate = null,
        ?string $preferredTime = null,
        ?int $visitorChoiceAgentId = null
    ): ?array {
        // Récupérer la configuration multi-agent du client
        $config = $this->getClientConfig($clientId);

        if (!$config) {
            // Si pas de config, créer une config par défaut
            $this->initClientConfig($clientId);
            $config = $this->getClientConfig($clientId);
        }

        $mode = $config['distribution_mode'] ?? self::MODE_ROUND_ROBIN;

        // Si le visiteur a choisi un agent et que le mode le permet
        if ($visitorChoiceAgentId && ($mode === self::MODE_VISITOR_CHOICE || $config['allow_visitor_choice'])) {
            $agent = $this->getAgentById($visitorChoiceAgentId, $clientId);
            if ($agent && $agent['active']) {
                return $agent;
            }
        }

        // Sélection selon le mode
        switch ($mode) {
            case self::MODE_AVAILABILITY:
                $agent = $this->selectByAvailability($clientId, $preferredDate, $preferredTime);
                break;

            case self::MODE_SPECIALTY:
                $agent = $this->selectBySpecialty($clientId, $requestedSpecialty);
                break;

            case self::MODE_VISITOR_CHOICE:
                // Si pas d'agent choisi, fallback sur round-robin
                $agent = $this->selectByRoundRobin($clientId);
                break;

            case self::MODE_ROUND_ROBIN:
            default:
                $agent = $this->selectByRoundRobin($clientId);
                break;
        }

        // Fallback: premier agent actif si aucun trouvé
        if (!$agent) {
            $agent = $this->getFirstActiveAgent($clientId);
        }

        return $agent;
    }

    /**
     * Mode Round-Robin: Sélectionne le prochain agent dans la liste
     */
    private function selectByRoundRobin(int $clientId): ?array
    {
        $pdo = $this->db->getConnection();

        // Récupérer le dernier agent assigné
        $stmt = $pdo->prepare("
            SELECT last_assigned_agent_id
            FROM client_multi_agent_config
            WHERE client_id = ?
        ");
        $stmt->execute([$clientId]);
        $lastAgentId = $stmt->fetchColumn();

        // Récupérer tous les agents actifs triés par sort_order
        $stmt = $pdo->prepare("
            SELECT * FROM agents
            WHERE client_id = ? AND active = 1
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute([$clientId]);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($agents)) {
            return null;
        }

        // Trouver le prochain agent après le dernier assigné
        $nextAgent = null;
        $foundLast = false;

        foreach ($agents as $agent) {
            if ($foundLast || !$lastAgentId) {
                $nextAgent = $agent;
                break;
            }
            if ($agent['id'] == $lastAgentId) {
                $foundLast = true;
            }
        }

        // Si on a atteint la fin, reprendre au début
        if (!$nextAgent) {
            $nextAgent = $agents[0];
        }

        // Mettre à jour le dernier agent assigné
        $this->updateLastAssignedAgent($clientId, $nextAgent['id']);

        return $nextAgent;
    }

    /**
     * Mode Availability: Vérifie la disponibilité via Google Calendar
     */
    private function selectByAvailability(int $clientId, ?string $date, ?string $time): ?array
    {
        if (!$date || !$time) {
            // Sans date/heure, fallback sur round-robin
            return $this->selectByRoundRobin($clientId);
        }

        $pdo = $this->db->getConnection();

        // Récupérer tous les agents actifs avec Google Calendar configuré
        $stmt = $pdo->prepare("
            SELECT * FROM agents
            WHERE client_id = ? AND active = 1 AND google_calendar_id IS NOT NULL AND google_calendar_id != ''
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$clientId]);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($agents)) {
            // Aucun agent avec Calendar, fallback
            return $this->selectByRoundRobin($clientId);
        }

        // Vérifier la disponibilité de chaque agent
        foreach ($agents as $agent) {
            if ($this->isAgentAvailable($agent, $date, $time)) {
                $this->updateLastAssignedAgent($clientId, $agent['id']);
                return $agent;
            }
        }

        // Aucun agent disponible à ce créneau, retourner le premier
        $this->updateLastAssignedAgent($clientId, $agents[0]['id']);
        return $agents[0];
    }

    /**
     * Mode Specialty: Filtre par spécialité puis round-robin
     */
    private function selectBySpecialty(int $clientId, ?string $specialty): ?array
    {
        if (!$specialty) {
            // Sans spécialité, fallback sur round-robin
            return $this->selectByRoundRobin($clientId);
        }

        $pdo = $this->db->getConnection();
        $specialtyLower = strtolower(trim($specialty));

        // Récupérer tous les agents actifs
        $stmt = $pdo->prepare("
            SELECT * FROM agents
            WHERE client_id = ? AND active = 1
            ORDER BY sort_order ASC
        ");
        $stmt->execute([$clientId]);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filtrer par spécialité
        $matchingAgents = [];
        foreach ($agents as $agent) {
            $agentSpecialties = json_decode($agent['specialties'] ?? '[]', true);
            if (is_array($agentSpecialties)) {
                $agentSpecialtiesLower = array_map('strtolower', $agentSpecialties);
                if (in_array($specialtyLower, $agentSpecialtiesLower)) {
                    $matchingAgents[] = $agent;
                }
            }
        }

        if (empty($matchingAgents)) {
            // Aucun agent avec cette spécialité, fallback sur tous les agents
            return $this->selectByRoundRobin($clientId);
        }

        // Round-robin parmi les agents matchés
        $config = $this->getClientConfig($clientId);
        $lastAgentId = $config['last_assigned_agent_id'] ?? null;

        $nextAgent = null;
        $foundLast = false;

        foreach ($matchingAgents as $agent) {
            if ($foundLast || !$lastAgentId) {
                $nextAgent = $agent;
                break;
            }
            if ($agent['id'] == $lastAgentId) {
                $foundLast = true;
            }
        }

        if (!$nextAgent) {
            $nextAgent = $matchingAgents[0];
        }

        $this->updateLastAssignedAgent($clientId, $nextAgent['id']);
        return $nextAgent;
    }

    /**
     * Vérifie si un agent est disponible (horaires + Google Calendar)
     */
    public function isAgentAvailable(array $agent, string $date, string $time): bool
    {
        // 1. Vérifier les horaires de travail
        if (!$this->isWithinWorkingHours($agent['id'], $date, $time)) {
            return false;
        }

        // 2. Vérifier les indisponibilités exceptionnelles
        if ($this->hasUnavailability($agent['id'], $date, $time)) {
            return false;
        }

        // 3. Vérifier Google Calendar si configuré
        if (!empty($agent['google_calendar_id'])) {
            if (!$this->isAvailableInGoogleCalendar($agent['google_calendar_id'], $date, $time)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifie si le créneau est dans les horaires de travail de l'agent
     */
    private function isWithinWorkingHours(int $agentId, string $date, string $time): bool
    {
        $pdo = $this->db->getConnection();

        $dayOfWeek = (int)date('w', strtotime($date)); // 0=Dimanche, 6=Samedi

        $stmt = $pdo->prepare("
            SELECT * FROM agent_schedules
            WHERE agent_id = ? AND day_of_week = ? AND is_available = 1
        ");
        $stmt->execute([$agentId, $dayOfWeek]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($schedules)) {
            return false;
        }

        $requestedTime = strtotime($time);

        foreach ($schedules as $schedule) {
            $startTime = strtotime($schedule['start_time']);
            $endTime = strtotime($schedule['end_time']);

            if ($requestedTime >= $startTime && $requestedTime < $endTime) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie les indisponibilités exceptionnelles
     */
    private function hasUnavailability(int $agentId, string $date, string $time): bool
    {
        $pdo = $this->db->getConnection();

        $datetime = $date . ' ' . $time;

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM agent_unavailability
            WHERE agent_id = ?
            AND ? BETWEEN start_datetime AND end_datetime
        ");
        $stmt->execute([$agentId, $datetime]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Vérifie la disponibilité dans Google Calendar
     */
    private function isAvailableInGoogleCalendar(string $calendarId, string $date, string $time): bool
    {
        try {
            if (!$this->calendar) {
                $this->calendar = new GoogleCalendar();
            }

            if (!$this->calendar->isConfigured()) {
                return true; // Si pas configuré, on considère disponible
            }

            // Construire les dates de début et fin
            $startDateTime = $date . 'T' . $time . ':00';
            $endDateTime = date('Y-m-d\TH:i:s', strtotime($startDateTime . ' +1 hour'));

            // Récupérer les événements dans cette plage
            $events = $this->calendar->getEvents($calendarId, $startDateTime, $endDateTime);

            // Si des événements existent, l'agent n'est pas disponible
            return empty($events);

        } catch (Exception $e) {
            error_log("AgentDistributor: Erreur Google Calendar - " . $e->getMessage());
            return true; // En cas d'erreur, on considère disponible
        }
    }

    /**
     * Retourne la liste des agents disponibles pour affichage dans le widget
     */
    public function getAvailableAgentsForDisplay(int $clientId, ?string $specialty = null): array
    {
        $pdo = $this->db->getConnection();
        $config = $this->getClientConfig($clientId);

        $query = "SELECT id, name, email, phone, photo_url, specialties, bio, color
                  FROM agents
                  WHERE client_id = ? AND active = 1
                  ORDER BY sort_order ASC";
        $stmt = $pdo->prepare($query);
        $stmt->execute([$clientId]);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Filtrer par spécialité si demandé
        if ($specialty) {
            $specialtyLower = strtolower(trim($specialty));
            $agents = array_filter($agents, function ($agent) use ($specialtyLower) {
                $specs = json_decode($agent['specialties'] ?? '[]', true);
                if (!is_array($specs)) return false;
                return in_array($specialtyLower, array_map('strtolower', $specs));
            });
            $agents = array_values($agents);
        }

        // Formater pour l'affichage
        foreach ($agents as &$agent) {
            $agent['specialties'] = json_decode($agent['specialties'] ?? '[]', true) ?: [];

            // Masquer certaines infos selon la config
            if (!($config['show_agent_bios'] ?? true)) {
                unset($agent['bio']);
            }
            if (!($config['show_agent_photos'] ?? true)) {
                unset($agent['photo_url']);
            }
        }

        return $agents;
    }

    /**
     * Récupère un agent par son ID
     */
    public function getAgentById(int $agentId, ?int $clientId = null): ?array
    {
        $pdo = $this->db->getConnection();

        $query = "SELECT * FROM agents WHERE id = ?";
        $params = [$agentId];

        if ($clientId) {
            $query .= " AND client_id = ?";
            $params[] = $clientId;
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $agent = $stmt->fetch(PDO::FETCH_ASSOC);

        return $agent ?: null;
    }

    /**
     * Récupère le premier agent actif du client
     */
    private function getFirstActiveAgent(int $clientId): ?array
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            SELECT * FROM agents
            WHERE client_id = ? AND active = 1
            ORDER BY sort_order ASC, id ASC
            LIMIT 1
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère la configuration multi-agent du client
     */
    public function getClientConfig(int $clientId): ?array
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM client_multi_agent_config WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($config && $config['available_specialties']) {
            $config['available_specialties'] = json_decode($config['available_specialties'], true);
        }

        return $config ?: null;
    }

    /**
     * Initialise la configuration multi-agent pour un client
     */
    public function initClientConfig(int $clientId): bool
    {
        $pdo = $this->db->getConnection();

        try {
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO client_multi_agent_config
                (client_id, distribution_mode, available_specialties)
                VALUES (?, 'round_robin', ?)
            ");
            return $stmt->execute([
                $clientId,
                json_encode(['vente', 'location', 'estimation', 'gestion', 'conseil'])
            ]);
        } catch (PDOException $e) {
            error_log("AgentDistributor: Erreur init config - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Met à jour le dernier agent assigné (pour round-robin)
     */
    private function updateLastAssignedAgent(int $clientId, int $agentId): void
    {
        $pdo = $this->db->getConnection();

        $stmt = $pdo->prepare("
            UPDATE client_multi_agent_config
            SET last_assigned_agent_id = ?
            WHERE client_id = ?
        ");
        $stmt->execute([$agentId, $clientId]);
    }

    /**
     * Récupère tous les agents d'un client
     */
    public function getAgentsByClient(int $clientId, bool $activeOnly = true): array
    {
        $pdo = $this->db->getConnection();

        $query = "SELECT * FROM agents WHERE client_id = ?";
        if ($activeOnly) {
            $query .= " AND active = 1";
        }
        $query .= " ORDER BY sort_order ASC, id ASC";

        $stmt = $pdo->prepare($query);
        $stmt->execute([$clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compte les agents d'un client
     */
    public function countAgents(int $clientId, bool $activeOnly = true): int
    {
        $pdo = $this->db->getConnection();

        $query = "SELECT COUNT(*) FROM agents WHERE client_id = ?";
        if ($activeOnly) {
            $query .= " AND active = 1";
        }

        $stmt = $pdo->prepare($query);
        $stmt->execute([$clientId]);
        return (int)$stmt->fetchColumn();
    }
}
