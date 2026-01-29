<?php
/**
 * Classe BookingProcessor
 * Détecte et traite les demandes de rendez-vous dans les réponses IA
 */

class BookingProcessor
{
    private Database $db;
    private GoogleCalendar $calendar;
    private EmailNotifier $emailNotifier;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->calendar = new GoogleCalendar();
        $this->emailNotifier = new EmailNotifier();
    }

    /**
     * Vérifie si un marqueur de booking existe dans la réponse
     */
    public function hasBookingMarker(string $aiResponse): bool
    {
        return preg_match('/\[BOOKING_REQUEST\].*?\[\/BOOKING_REQUEST\]/s', $aiResponse) === 1;
    }

    /**
     * Détecte un marqueur de booking dans la réponse IA
     * Retourne les données de booking ou null si pas de booking
     */
    public function detectBooking(string $aiResponse): ?array
    {
        $result = $this->detectBookingWithErrors($aiResponse);
        return $result['valid'] ? $result['data'] : null;
    }

    /**
     * Détecte et valide un marqueur de booking avec détails d'erreur
     * Retourne ['valid' => bool, 'data' => array|null, 'errors' => array]
     */
    public function detectBookingWithErrors(string $aiResponse): array
    {
        $result = ['valid' => false, 'data' => null, 'errors' => []];
        $pattern = '/\[BOOKING_REQUEST\](.*?)\[\/BOOKING_REQUEST\]/s';

        if (!preg_match($pattern, $aiResponse, $matches)) {
            return $result;
        }

        $jsonStr = trim($matches[1]);
        $data = json_decode($jsonStr, true);

        if (!$data || !is_array($data)) {
            $result['errors'][] = 'Format de données invalide';
            error_log('BookingProcessor: JSON invalide dans le marqueur - ' . $jsonStr);
            return $result;
        }

        // Valider les champs obligatoires
        if (empty($data['name'])) {
            $result['errors'][] = 'Le nom est manquant';
        }
        if (empty($data['date'])) {
            $result['errors'][] = 'La date est manquante';
        }
        if (empty($data['time'])) {
            $result['errors'][] = "L'heure est manquante";
        }

        if (!empty($result['errors'])) {
            return $result;
        }

        // Parser et normaliser la date
        $originalDate = $data['date'];
        $originalTime = $data['time'];
        $data['date'] = $this->parseDate($data['date']);
        $data['time'] = $this->parseTime($data['time']);

        if (!$data['date']) {
            $result['errors'][] = "La date \"$originalDate\" n'est pas au bon format (utilisez JJ/MM/AAAA, ex: 15/02/2026)";
        }
        if (!$data['time']) {
            $result['errors'][] = "L'heure \"$originalTime\" n'est pas au bon format (utilisez HHhMM, ex: 10h30)";
        }

        if (!empty($result['errors'])) {
            error_log('BookingProcessor: Erreurs de validation - ' . implode(', ', $result['errors']));
            return $result;
        }

        $result['valid'] = true;
        $result['data'] = $data;
        return $result;
    }

    /**
     * Supprime le marqueur de booking de la réponse IA
     */
    public function stripBookingMarker(string $aiResponse): string
    {
        return trim(preg_replace(
            '/\[BOOKING_REQUEST\].*?\[\/BOOKING_REQUEST\]/s',
            '',
            $aiResponse
        ));
    }

    /**
     * Traite un booking complet : DB + Google Calendar + Email
     *
     * @param array $bookingData Données du booking (name, email, phone, service, date, time)
     * @param string $chatbotType 'demo' ou 'client'
     * @param int|null $chatbotId ID du chatbot demo ou null
     * @param int|null $clientId ID du client ou null
     * @param string|null $sessionId Session ID
     * @param string|null $calendarId Google Calendar ID
     * @param string|null $notificationEmail Email de notification
     * @param string $chatbotName Nom du chatbot
     * @return array ['success' => bool, 'appointment_id' => int|null, 'google_event' => bool, 'email_sent' => bool]
     */
    public function processBooking(
        array $bookingData,
        string $chatbotType,
        ?int $chatbotId,
        ?int $clientId,
        ?string $sessionId,
        ?string $calendarId,
        ?string $notificationEmail,
        string $chatbotName = 'Chatbot'
    ): array {
        $result = [
            'success' => false,
            'appointment_id' => null,
            'google_event' => false,
            'email_sent' => false,
            'visitor_email_sent' => false
        ];

        try {
            // Debug logging
            $debugLog = __DIR__ . '/../logs/booking-debug.log';
            $debugDir = dirname($debugLog);
            if (!is_dir($debugDir)) { @mkdir($debugDir, 0755, true); }
            $ts = date('Y-m-d H:i:s');
            @file_put_contents($debugLog, "\n[$ts] === processBooking ===\n", FILE_APPEND);
            @file_put_contents($debugLog, "[$ts] chatbotType=$chatbotType clientId=$clientId chatbotId=$chatbotId\n", FILE_APPEND);
            @file_put_contents($debugLog, "[$ts] calendarId=" . ($calendarId ?? 'NULL') . "\n", FILE_APPEND);
            @file_put_contents($debugLog, "[$ts] isConfigured=" . ($this->calendar->isConfigured() ? 'YES' : 'NO') . "\n", FILE_APPEND);
            @file_put_contents($debugLog, "[$ts] bookingData=" . json_encode($bookingData) . "\n", FILE_APPEND);

            // 1. Sauvegarder en base de données
            $googleEventId = null;

            // 2. Créer l'événement Google Calendar si configuré
            if (!empty($calendarId) && $this->calendar->isConfigured()) {
                @file_put_contents($debugLog, "[$ts] Appel createEvent...\n", FILE_APPEND);
                $eventResult = $this->calendar->createEvent($calendarId, [
                    'name' => $bookingData['name'],
                    'email' => $bookingData['email'] ?? null,
                    'phone' => $bookingData['phone'] ?? null,
                    'service' => $bookingData['service'] ?? null,
                    'date' => $bookingData['date'],
                    'time' => $bookingData['time'],
                    'duration' => $bookingData['duration'] ?? 60
                ]);
                @file_put_contents($debugLog, "[$ts] createEvent result=" . json_encode($eventResult) . "\n", FILE_APPEND);

                if ($eventResult['success']) {
                    $googleEventId = $eventResult['event_id'];
                    $result['google_event'] = true;
                } else {
                    error_log('BookingProcessor: Erreur Google Calendar - ' . ($eventResult['error'] ?? 'inconnue'));
                }
            } else {
                @file_put_contents($debugLog, "[$ts] SKIP Google Calendar: calendarId=" . ($calendarId ?? 'VIDE') . " isConfigured=" . ($this->calendar->isConfigured() ? 'YES' : 'NO') . "\n", FILE_APPEND);
            }

            // 3. Insérer le RDV en base
            $this->db->query(
                "INSERT INTO appointments (client_id, chatbot_type, chatbot_id, visitor_name, visitor_email, visitor_phone, service, appointment_date, appointment_time, duration_minutes, google_event_id, status, session_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', ?)",
                [
                    $clientId,
                    $chatbotType,
                    $chatbotId,
                    $bookingData['name'],
                    $bookingData['email'] ?? null,
                    $bookingData['phone'] ?? null,
                    $bookingData['service'] ?? null,
                    $bookingData['date'],
                    $bookingData['time'],
                    $bookingData['duration'] ?? 60,
                    $googleEventId,
                    $sessionId
                ]
            );
            $result['appointment_id'] = $this->db->getPdo()->lastInsertId();
            $result['success'] = true;

            // 4. Envoyer l'email de notification au propriétaire
            if (!empty($notificationEmail)) {
                $result['email_sent'] = $this->emailNotifier->sendAppointmentNotification([
                    'visitor_name' => $bookingData['name'],
                    'visitor_email' => $bookingData['email'] ?? null,
                    'visitor_phone' => $bookingData['phone'] ?? null,
                    'service' => $bookingData['service'] ?? null,
                    'appointment_date' => $bookingData['date'],
                    'appointment_time' => $bookingData['time']
                ], $notificationEmail, $chatbotName);
            }

            // 5. Envoyer l'email de confirmation au visiteur
            if (!empty($bookingData['email'])) {
                $result['visitor_email_sent'] = $this->emailNotifier->sendVisitorConfirmation([
                    'visitor_name' => $bookingData['name'],
                    'visitor_email' => $bookingData['email'],
                    'service' => $bookingData['service'] ?? null,
                    'appointment_date' => $bookingData['date'],
                    'appointment_time' => $bookingData['time']
                ], $chatbotName);
            }

        } catch (Exception $e) {
            error_log('BookingProcessor: Erreur processBooking - ' . $e->getMessage());
        }

        return $result;
    }

    /**
     * Parse une date en différents formats français vers Y-m-d
     * Supporte : 15/02/2026, 15-02-2026, 2026-02-15
     */
    private function parseDate(string $input): ?string
    {
        $input = trim($input);

        // Format ISO (2026-02-15)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
            $ts = strtotime($input);
            return $ts ? date('Y-m-d', $ts) : null;
        }

        // Format français (15/02/2026 ou 15-02-2026)
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $input, $m)) {
            $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[1], (int)$m[3]);
            return $ts ? date('Y-m-d', $ts) : null;
        }

        // Format court (15/02/26)
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2})$/', $input, $m)) {
            $year = (int)$m[3] + 2000;
            $ts = mktime(0, 0, 0, (int)$m[2], (int)$m[1], $year);
            return $ts ? date('Y-m-d', $ts) : null;
        }

        return null;
    }

    /**
     * Parse une heure en différents formats vers H:i
     * Supporte : 10h30, 10:30, 10h, 14h00
     */
    private function parseTime(string $input): ?string
    {
        $input = trim(strtolower($input));

        // Format HhMM (10h30, 14h00, 9h)
        if (preg_match('/^(\d{1,2})h(\d{0,2})$/', $input, $m)) {
            $h = (int)$m[1];
            $min = !empty($m[2]) ? (int)$m[2] : 0;
            if ($h >= 0 && $h <= 23 && $min >= 0 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        // Format HH:MM (10:30, 14:00)
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $input, $m)) {
            $h = (int)$m[1];
            $min = (int)$m[2];
            if ($h >= 0 && $h <= 23 && $min >= 0 && $min <= 59) {
                return sprintf('%02d:%02d', $h, $min);
            }
        }

        return null;
    }

    /**
     * Retourne les instructions de booking à injecter dans le system prompt
     */
    public static function getBookingInstructions(): string
    {
        return <<<PROMPT

=== PRISE DE RENDEZ-VOUS ===
Tu peux aider les visiteurs à prendre rendez-vous. Pour cela, tu dois collecter les informations suivantes :
1. **Nom complet** du visiteur
2. **Numéro de téléphone**
3. **Adresse email**
4. **Service souhaité** (type de prestation)
5. **Date souhaitée** (au format JJ/MM/AAAA)
6. **Heure souhaitée** (au format HHhMM, ex: 10h30, 14h00)

Procédure :
- Collecte les informations naturellement au fil de la conversation
- Une fois toutes les informations obtenues, fais un **résumé récapitulatif** au visiteur et demande confirmation
- Si le visiteur confirme, génère le bloc technique ci-dessous (il sera traité automatiquement et invisible pour le visiteur)

Quand le visiteur confirme le rendez-vous, ajoute CE BLOC à la fin de ta réponse (le visiteur ne le verra pas) :
[BOOKING_REQUEST]{"name":"NOM","phone":"TELEPHONE","email":"EMAIL","date":"JJ/MM/AAAA","time":"HHhMM","service":"SERVICE"}[/BOOKING_REQUEST]

IMPORTANT :
- Propose des créneaux pendant les heures de bureau (9h-18h, lundi-vendredi) sauf indication contraire
- Ne confirme jamais un RDV sans avoir obtenu au minimum le nom, la date et l'heure
- Sois naturel et conversationnel, ne demande pas toutes les infos d'un coup
PROMPT;
    }
}
