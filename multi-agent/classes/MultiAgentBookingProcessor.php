<?php
/**
 * MultiAgentBookingProcessor - Traitement des réservations avec gestion multi-agents
 *
 * Étend BookingProcessor pour ajouter :
 * - Sélection automatique de l'agent selon le mode configuré
 * - Création d'événement sur le calendrier de l'agent
 * - Notification à l'agent spécifique
 */

require_once __DIR__ . '/../../classes/BookingProcessor.php';
require_once __DIR__ . '/../../classes/GoogleCalendar.php';
require_once __DIR__ . '/../../classes/EmailNotifier.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/AgentDistributor.php';

class MultiAgentBookingProcessor extends BookingProcessor
{
    private AgentDistributor $distributor;
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->distributor = new AgentDistributor();
        $this->db = Database::getInstance();
    }

    /**
     * Traite une demande de réservation avec sélection d'agent
     *
     * @param array $bookingData Données du booking (name, email, phone, date, time, service, etc.)
     * @param int $clientId ID du client
     * @param string|null $sessionId ID de session chatbot
     * @param int|null $preferredAgentId ID de l'agent choisi par le visiteur (optionnel)
     * @return array Résultat du traitement
     */
    public function processBookingWithAgent(
        array $bookingData,
        int $clientId,
        ?string $sessionId = null,
        ?int $preferredAgentId = null
    ): array {
        $pdo = $this->db->getConnection();

        // 1. Extraire les informations du booking
        $visitorName = $bookingData['name'] ?? '';
        $visitorEmail = $bookingData['email'] ?? null;
        $visitorPhone = $bookingData['phone'] ?? null;
        $service = $bookingData['service'] ?? null;
        $specialtyRequested = $bookingData['specialty_requested'] ?? $this->detectSpecialty($service);
        $date = $this->parseDate($bookingData['date'] ?? '');
        $time = $this->parseTime($bookingData['time'] ?? '');
        $duration = (int)($bookingData['duration'] ?? 60);
        $preferredAgentId = $preferredAgentId ?? ($bookingData['preferred_agent_id'] ?? null);

        // Validation basique
        if (empty($visitorName) || empty($date) || empty($time)) {
            return [
                'success' => false,
                'error' => 'Données de réservation incomplètes',
                'missing' => array_filter([
                    empty($visitorName) ? 'name' : null,
                    empty($date) ? 'date' : null,
                    empty($time) ? 'time' : null
                ])
            ];
        }

        // 2. Sélectionner l'agent approprié
        $agent = $this->distributor->selectAgent(
            $clientId,
            $specialtyRequested,
            $date,
            $time,
            $preferredAgentId ? (int)$preferredAgentId : null
        );

        if (!$agent) {
            return [
                'success' => false,
                'error' => 'Aucun agent disponible pour ce créneau'
            ];
        }

        // 3. Déterminer la méthode de distribution utilisée
        $config = $this->distributor->getClientConfig($clientId);
        $distributionMethod = $config['distribution_mode'] ?? 'round_robin';
        if ($preferredAgentId && $agent['id'] == $preferredAgentId) {
            $distributionMethod = 'visitor_choice';
        }

        // 4. Créer l'événement Google Calendar si configuré
        $googleEventId = null;
        if (!empty($agent['google_calendar_id'])) {
            try {
                $calendar = new GoogleCalendar();
                if ($calendar->isConfigured()) {
                    $eventResult = $calendar->createEvent(
                        $agent['google_calendar_id'],
                        $this->buildEventData($visitorName, $visitorEmail, $visitorPhone, $service, $date, $time, $duration)
                    );
                    if (!empty($eventResult['id'])) {
                        $googleEventId = $eventResult['id'];
                    }
                }
            } catch (Exception $e) {
                error_log("MultiAgentBookingProcessor: Erreur Google Calendar - " . $e->getMessage());
            }
        }

        // 5. Enregistrer le RDV en base de données
        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointments_v2 (
                    client_id, agent_id, chatbot_type, visitor_name, visitor_email,
                    visitor_phone, service, specialty_requested, appointment_date,
                    appointment_time, duration_minutes, google_event_id,
                    distribution_method, status, session_id
                ) VALUES (?, ?, 'client', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)
            ");

            $stmt->execute([
                $clientId,
                $agent['id'],
                $visitorName,
                $visitorEmail,
                $visitorPhone,
                $service,
                $specialtyRequested,
                $date,
                $time,
                $duration,
                $googleEventId,
                $distributionMethod,
                $sessionId
            ]);

            $appointmentId = $pdo->lastInsertId();

            // Incrémenter le compteur de RDV de l'agent
            $this->incrementAgentAppointments($agent['id']);

        } catch (PDOException $e) {
            error_log("MultiAgentBookingProcessor: Erreur insertion BDD - " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'enregistrement du rendez-vous'
            ];
        }

        // 6. Envoyer les notifications email
        $this->sendNotifications($agent, $visitorName, $visitorEmail, $visitorPhone, $service, $date, $time, $appointmentId);

        // 7. Retourner le résultat
        return [
            'success' => true,
            'appointment_id' => $appointmentId,
            'agent' => [
                'id' => $agent['id'],
                'name' => $agent['name'],
                'email' => $agent['email'],
                'phone' => $agent['phone'] ?? null,
                'photo_url' => $agent['photo_url'] ?? null
            ],
            'booking' => [
                'date' => $date,
                'time' => $time,
                'duration' => $duration,
                'service' => $service
            ],
            'distribution_method' => $distributionMethod,
            'google_event_created' => !empty($googleEventId)
        ];
    }

    /**
     * Détecte la spécialité à partir du service demandé
     */
    private function detectSpecialty(?string $service): ?string
    {
        if (!$service) return null;

        $serviceLower = strtolower($service);

        $keywords = [
            'vente' => ['vente', 'achat', 'acheter', 'vendre', 'acquisition'],
            'location' => ['location', 'louer', 'locataire', 'bail'],
            'estimation' => ['estimation', 'estimer', 'évaluation', 'prix', 'valeur'],
            'gestion' => ['gestion', 'gérer', 'locative', 'syndic'],
            'investissement' => ['investissement', 'investir', 'rendement', 'défiscalisation'],
            'conseil' => ['conseil', 'accompagnement', 'aide', 'information']
        ];

        foreach ($keywords as $specialty => $words) {
            foreach ($words as $word) {
                if (str_contains($serviceLower, $word)) {
                    return $specialty;
                }
            }
        }

        return null;
    }

    /**
     * Parse une date dans différents formats
     */
    private function parseDate(string $date): string
    {
        // Format JJ/MM/AAAA
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $date, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[2], $matches[1]);
        }

        // Format AAAA-MM-JJ
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date)) {
            return $date;
        }

        // Format JJ/MM/AA
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2})$/', $date, $matches)) {
            $year = (int)$matches[3] > 50 ? '19' . $matches[3] : '20' . $matches[3];
            return sprintf('%s-%02d-%02d', $year, $matches[2], $matches[1]);
        }

        return $date;
    }

    /**
     * Parse une heure dans différents formats
     */
    private function parseTime(string $time): string
    {
        // Format HHhMM ou HH:MM
        if (preg_match('/^(\d{1,2})[h:](\d{2})$/', $time, $matches)) {
            return sprintf('%02d:%02d:00', $matches[1], $matches[2]);
        }

        // Format HHh
        if (preg_match('/^(\d{1,2})h?$/', $time, $matches)) {
            return sprintf('%02d:00:00', $matches[1]);
        }

        return $time;
    }

    /**
     * Construit les données pour l'événement Google Calendar
     */
    private function buildEventData(
        string $name,
        ?string $email,
        ?string $phone,
        ?string $service,
        string $date,
        string $time,
        int $duration
    ): array {
        $description = "Rendez-vous pris via le chatbot\n\n";
        $description .= "Nom : $name\n";
        if ($email) $description .= "Email : $email\n";
        if ($phone) $description .= "Téléphone : $phone\n";
        if ($service) $description .= "Service : $service\n";

        $startDateTime = $date . 'T' . (strlen($time) === 5 ? $time . ':00' : $time);
        $endDateTime = date('Y-m-d\TH:i:s', strtotime($startDateTime . " +{$duration} minutes"));

        return [
            'summary' => "RDV - $name" . ($service ? " - $service" : ''),
            'description' => $description,
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => 'Europe/Paris'
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => 'Europe/Paris'
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 30]
                ]
            ]
        ];
    }

    /**
     * Envoie les notifications email
     */
    private function sendNotifications(
        array $agent,
        string $visitorName,
        ?string $visitorEmail,
        ?string $visitorPhone,
        ?string $service,
        string $date,
        string $time,
        int $appointmentId
    ): void {
        try {
            $emailNotifier = new EmailNotifier();

            // Formater la date pour l'affichage
            $dateFormatted = date('d/m/Y', strtotime($date));
            $timeFormatted = date('H:i', strtotime($time));

            // Email à l'agent
            if (!empty($agent['email'])) {
                $emailNotifier->sendAppointmentNotification(
                    $agent['email'],
                    $agent['name'],
                    $visitorName,
                    $visitorEmail,
                    $visitorPhone,
                    $service,
                    $dateFormatted,
                    $timeFormatted
                );

                // Marquer comme notifié
                $this->markAgentNotified($appointmentId);
            }

            // Email au visiteur
            if (!empty($visitorEmail)) {
                $emailNotifier->sendVisitorConfirmation(
                    $visitorEmail,
                    $visitorName,
                    $agent['name'],
                    $service,
                    $dateFormatted,
                    $timeFormatted
                );

                // Marquer comme notifié
                $this->markVisitorNotified($appointmentId);
            }

        } catch (Exception $e) {
            error_log("MultiAgentBookingProcessor: Erreur envoi email - " . $e->getMessage());
        }
    }

    /**
     * Incrémente le compteur de RDV d'un agent
     */
    private function incrementAgentAppointments(int $agentId): void
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("UPDATE agents SET appointments_count = appointments_count + 1 WHERE id = ?");
        $stmt->execute([$agentId]);
    }

    /**
     * Marque le RDV comme notifié à l'agent
     */
    private function markAgentNotified(int $appointmentId): void
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("UPDATE appointments_v2 SET agent_notified_at = NOW() WHERE id = ?");
        $stmt->execute([$appointmentId]);
    }

    /**
     * Marque le RDV comme notifié au visiteur
     */
    private function markVisitorNotified(int $appointmentId): void
    {
        $pdo = $this->db->getConnection();
        $stmt = $pdo->prepare("UPDATE appointments_v2 SET visitor_notified_at = NOW() WHERE id = ?");
        $stmt->execute([$appointmentId]);
    }

    /**
     * Retourne les instructions de booking adaptées au multi-agent
     */
    public static function getMultiAgentBookingInstructions(bool $allowVisitorChoice = false): string
    {
        $baseInstructions = "
INSTRUCTIONS POUR LA PRISE DE RENDEZ-VOUS :
Lorsqu'un utilisateur souhaite prendre rendez-vous, collecte les informations suivantes :
- Nom complet (obligatoire)
- Numéro de téléphone (obligatoire)
- Adresse email (recommandé)
- Type de service souhaité (vente, location, estimation, conseil, etc.)
- Date souhaitée (format JJ/MM/AAAA)
- Heure souhaitée (format HHhMM)

Une fois toutes les informations collectées, génère un bloc de réservation au format suivant :
[BOOKING_REQUEST]{
  \"name\": \"Nom du client\",
  \"phone\": \"0612345678\",
  \"email\": \"email@example.com\",
  \"service\": \"Type de service\",
  \"date\": \"15/02/2026\",
  \"time\": \"10h30\"
}[/BOOKING_REQUEST]

Un conseiller sera automatiquement assigné selon ses disponibilités et compétences.
";

        if ($allowVisitorChoice) {
            $baseInstructions .= "
Si le client demande un conseiller spécifique, ajoute le champ \"preferred_agent_id\" avec l'ID du conseiller.
";
        }

        return $baseInstructions;
    }

    /**
     * Détecte si une réponse contient un marqueur de booking
     */
    public static function detectBookingInResponse(string $response): ?array
    {
        $pattern = '/\[BOOKING_REQUEST\](.*?)\[\/BOOKING_REQUEST\]/s';

        if (preg_match($pattern, $response, $matches)) {
            $jsonData = trim($matches[1]);
            $bookingData = json_decode($jsonData, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return $bookingData;
            }
        }

        return null;
    }

    /**
     * Supprime le marqueur de booking de la réponse
     */
    public static function stripBookingMarker(string $response): string
    {
        return preg_replace('/\[BOOKING_REQUEST\].*?\[\/BOOKING_REQUEST\]/s', '', $response);
    }
}
