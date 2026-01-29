<?php
/**
 * Classe GoogleCalendar
 * Gestion de l'API Google Calendar via Service Account (JWT)
 */

class GoogleCalendar
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';
    private const SCOPE = 'https://www.googleapis.com/auth/calendar';

    private ?array $credentials = null;
    private ?string $accessToken = null;
    private int $tokenExpiry = 0;

    public function __construct()
    {
        $this->loadCredentials();
    }

    /**
     * Charge les credentials du Service Account
     */
    private function loadCredentials(): void
    {
        $file = defined('GOOGLE_SERVICE_ACCOUNT_FILE') ? GOOGLE_SERVICE_ACCOUNT_FILE : '';

        if (empty($file) || !file_exists($file)) {
            return;
        }

        $json = file_get_contents($file);
        $this->credentials = json_decode($json, true);
    }

    /**
     * Vérifie si le service est configuré
     */
    public function isConfigured(): bool
    {
        return $this->credentials !== null
            && !empty($this->credentials['client_email'])
            && !empty($this->credentials['private_key']);
    }

    /**
     * Encode en base64url (requis pour JWT)
     */
    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Chemin du fichier cache pour le token
     */
    private function getTokenCacheFile(): string
    {
        return dirname(__DIR__) . '/cache/google_token.json';
    }

    /**
     * Charge le token depuis le cache fichier
     */
    private function loadCachedToken(): bool
    {
        $cacheFile = $this->getTokenCacheFile();
        if (!file_exists($cacheFile)) {
            return false;
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        if (!$data || empty($data['access_token']) || empty($data['expiry'])) {
            return false;
        }

        if (time() >= $data['expiry']) {
            return false;
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = $data['expiry'];
        return true;
    }

    /**
     * Sauvegarde le token dans le cache fichier
     */
    private function saveCachedToken(): void
    {
        $cacheFile = $this->getTokenCacheFile();
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        // Protéger le dossier cache
        $htaccess = $cacheDir . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "Deny from all\n");
        }

        @file_put_contents($cacheFile, json_encode([
            'access_token' => $this->accessToken,
            'expiry' => $this->tokenExpiry
        ]));
    }

    /**
     * Génère un JWT et obtient un access token (avec cache fichier)
     */
    private function getAccessToken(): ?string
    {
        // 1. Vérifier le cache mémoire
        if ($this->accessToken && time() < $this->tokenExpiry) {
            return $this->accessToken;
        }

        // 2. Vérifier le cache fichier
        if ($this->loadCachedToken()) {
            return $this->accessToken;
        }

        if (!$this->isConfigured()) {
            return null;
        }

        // 3. Demander un nouveau token
        $now = time();
        $header = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claims = $this->base64UrlEncode(json_encode([
            'iss' => $this->credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600
        ]));

        $signatureInput = $header . '.' . $claims;
        $signature = '';

        $success = openssl_sign(
            $signatureInput,
            $signature,
            $this->credentials['private_key'],
            'SHA256'
        );

        if (!$success) {
            error_log('GoogleCalendar: Erreur openssl_sign');
            return null;
        }

        $jwt = $signatureInput . '.' . $this->base64UrlEncode($signature);

        // Échanger le JWT contre un access token
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('GoogleCalendar: Erreur curl token - ' . $curlError);
            return null;
        }

        if ($httpCode !== 200) {
            error_log('GoogleCalendar: Erreur token HTTP ' . $httpCode . ' - ' . $response);
            return null;
        }

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            error_log('GoogleCalendar: Pas de access_token dans la réponse');
            return null;
        }

        $this->accessToken = $data['access_token'];
        $this->tokenExpiry = $now + ($data['expires_in'] ?? 3600) - 60;

        // 4. Sauvegarder en cache fichier pour les prochaines requêtes
        $this->saveCachedToken();

        return $this->accessToken;
    }

    /**
     * Crée un événement dans Google Calendar
     *
     * @param string $calendarId ID du calendrier Google
     * @param array $eventData Données du RDV : name, email, phone, service, date (Y-m-d), time (H:i), duration (minutes)
     * @return array ['success' => bool, 'event_id' => string|null, 'error' => string|null]
     */
    public function createEvent(string $calendarId, array $eventData): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'event_id' => null,
                'error' => 'Impossible d\'obtenir un token Google'
            ];
        }

        // Construire les dates
        $startDateTime = $eventData['date'] . 'T' . $eventData['time'] . ':00';
        $duration = $eventData['duration'] ?? 60;
        $endTime = date('H:i', strtotime($eventData['time']) + ($duration * 60));
        $endDateTime = $eventData['date'] . 'T' . $endTime . ':00';

        // Description de l'événement
        $description = "Rendez-vous pris via le chatbot\n\n";
        $description .= "Nom : " . ($eventData['name'] ?? 'Non renseigné') . "\n";
        if (!empty($eventData['email'])) {
            $description .= "Email : " . $eventData['email'] . "\n";
        }
        if (!empty($eventData['phone'])) {
            $description .= "Téléphone : " . $eventData['phone'] . "\n";
        }
        if (!empty($eventData['service'])) {
            $description .= "Service : " . $eventData['service'] . "\n";
        }

        $event = [
            'summary' => 'RDV - ' . ($eventData['name'] ?? 'Client') .
                (!empty($eventData['service']) ? ' - ' . $eventData['service'] : ''),
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

        // Appeler l'API (pas d'attendees : nécessite Domain-Wide Delegation)
        $url = self::CALENDAR_API . '/calendars/' . urlencode($calendarId) . '/events';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($event),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json'
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('GoogleCalendar: Erreur curl événement - ' . $curlError);
            return [
                'success' => false,
                'event_id' => null,
                'error' => 'Erreur connexion Google Calendar'
            ];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            $data = json_decode($response, true);
            return [
                'success' => true,
                'event_id' => $data['id'] ?? null,
                'error' => null
            ];
        }

        error_log('GoogleCalendar: Erreur création événement HTTP ' . $httpCode . ' - ' . $response);
        $errorDetail = '';
        $respData = json_decode($response, true);
        if (!empty($respData['error']['message'])) {
            $errorDetail = ' - ' . $respData['error']['message'];
        }
        return [
            'success' => false,
            'event_id' => null,
            'error' => 'Erreur Google Calendar (HTTP ' . $httpCode . ')' . $errorDetail
        ];
    }
}
