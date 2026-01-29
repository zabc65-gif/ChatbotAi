<?php
/**
 * Diagnostic Google Calendar
 */
$pageTitle = 'Test Google Calendar';
require_once 'includes/header.php';
require_once __DIR__ . '/../classes/GoogleCalendar.php';

$results = [];

// Traiter le vidage du debug log
$debugFile = __DIR__ . '/../logs/booking-debug.log';
if (isset($_POST['clear_debug'])) {
    CSRF::verify();
    @unlink($debugFile);
    echo '<script>window.location.href = window.location.pathname;</script>';
}

// Récupérer un Calendar ID par défaut pour pré-remplir le formulaire
$defaultCalendarId = '';
try {
    $row = $db->fetchOne("SELECT google_calendar_id FROM client_chatbots WHERE booking_enabled = 1 AND google_calendar_id != '' LIMIT 1");
    if ($row) $defaultCalendarId = $row['google_calendar_id'];
    if (empty($defaultCalendarId)) {
        $row = $db->fetchOne("SELECT google_calendar_id FROM demo_chatbots WHERE booking_enabled = 1 AND google_calendar_id != '' LIMIT 1");
        if ($row) $defaultCalendarId = $row['google_calendar_id'];
    }
} catch (Exception $e) {}

// 1. Vérifier le fichier credentials
$credFile = defined('GOOGLE_SERVICE_ACCOUNT_FILE') ? GOOGLE_SERVICE_ACCOUNT_FILE : 'NON DEFINI';
$results[] = [
    'test' => 'Constante GOOGLE_SERVICE_ACCOUNT_FILE',
    'value' => $credFile,
    'ok' => defined('GOOGLE_SERVICE_ACCOUNT_FILE')
];

$fileExists = file_exists($credFile);
$results[] = [
    'test' => 'Fichier credentials existe',
    'value' => $fileExists ? 'Oui' : 'Non - ' . $credFile,
    'ok' => $fileExists
];

if ($fileExists) {
    $json = json_decode(file_get_contents($credFile), true);
    $results[] = [
        'test' => 'JSON valide',
        'value' => $json ? 'Oui (type: ' . ($json['type'] ?? '?') . ')' : 'Non',
        'ok' => !empty($json)
    ];
    $results[] = [
        'test' => 'Client email',
        'value' => $json['client_email'] ?? 'MANQUANT',
        'ok' => !empty($json['client_email'])
    ];
    $results[] = [
        'test' => 'Private key présente',
        'value' => !empty($json['private_key']) ? 'Oui (' . strlen($json['private_key']) . ' chars)' : 'Non',
        'ok' => !empty($json['private_key'])
    ];
}

// 2. Tester la classe GoogleCalendar
$calendar = new GoogleCalendar();
$results[] = [
    'test' => 'GoogleCalendar::isConfigured()',
    'value' => $calendar->isConfigured() ? 'Oui' : 'Non',
    'ok' => $calendar->isConfigured()
];

// 2b. Tester l'obtention d'un token Google (test JWT)
$tokenTestResult = null;
if ($calendar->isConfigured() && $fileExists && !empty($json)) {
    $now = time();
    $base64UrlEncode = function(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    };

    $jwtHeader = $base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $jwtClaims = $base64UrlEncode(json_encode([
        'iss' => $json['client_email'],
        'scope' => 'https://www.googleapis.com/auth/calendar',
        'aud' => 'https://oauth2.googleapis.com/token',
        'iat' => $now,
        'exp' => $now + 3600
    ]));

    $signatureInput = $jwtHeader . '.' . $jwtClaims;
    $signature = '';
    $signOk = openssl_sign($signatureInput, $signature, $json['private_key'], 'SHA256');

    $results[] = [
        'test' => 'openssl_sign (JWT)',
        'value' => $signOk ? 'OK' : 'ECHEC - ' . openssl_error_string(),
        'ok' => $signOk
    ];

    if ($signOk) {
        $jwt = $signatureInput . '.' . $base64UrlEncode($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
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

        $tokenResponse = curl_exec($ch);
        $tokenHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $tokenCurlError = curl_error($ch);
        curl_close($ch);

        if ($tokenCurlError) {
            $results[] = [
                'test' => 'Token Google (curl)',
                'value' => 'Erreur curl: ' . $tokenCurlError,
                'ok' => false
            ];
        } else {
            $tokenData = json_decode($tokenResponse, true);
            $hasToken = !empty($tokenData['access_token']);
            $results[] = [
                'test' => 'Token Google (HTTP ' . $tokenHttpCode . ')',
                'value' => $hasToken
                    ? 'OK - Token obtenu (' . strlen($tokenData['access_token']) . ' chars)'
                    : 'ECHEC - ' . ($tokenData['error'] ?? '') . ' ' . ($tokenData['error_description'] ?? $tokenResponse),
                'ok' => $hasToken
            ];
        }
    }
}

// 3. Vérifier les chatbots avec booking activé
$demoBots = $db->fetchAll("SELECT id, name, slug, booking_enabled, google_calendar_id, notification_email FROM demo_chatbots WHERE booking_enabled = 1");
$clientBots = $db->fetchAll("SELECT cb.id, cb.bot_name, cb.booking_enabled, cb.google_calendar_id, cb.notification_email, c.name as client_name FROM client_chatbots cb LEFT JOIN clients c ON c.id = cb.client_id WHERE cb.booking_enabled = 1");

$results[] = [
    'test' => 'Chatbots démo avec booking',
    'value' => count($demoBots) . ' trouvé(s)',
    'ok' => count($demoBots) > 0
];

foreach ($demoBots as $bot) {
    $results[] = [
        'test' => '  → ' . $bot['name'] . ' - Calendar ID',
        'value' => $bot['google_calendar_id'] ?: 'VIDE',
        'ok' => !empty($bot['google_calendar_id'])
    ];
}

$results[] = [
    'test' => 'Chatbots client avec booking',
    'value' => count($clientBots) . ' trouvé(s)',
    'ok' => true
];

foreach ($clientBots as $bot) {
    $results[] = [
        'test' => '  → ' . ($bot['client_name'] ?? $bot['bot_name']) . ' - Calendar ID',
        'value' => $bot['google_calendar_id'] ?: 'VIDE',
        'ok' => !empty($bot['google_calendar_id'])
    ];
}

// 4. Tester la création d'événement si demandé
$testResult = null;
$testSubmitted = isset($_POST['test_calendar_id']);
if ($testSubmitted) {
    CSRF::verify();
    $testCalendarId = trim($_POST['test_calendar_id'] ?? '');
    if (empty($testCalendarId)) {
        $testResult = [
            'success' => false,
            'event_id' => null,
            'error' => 'Veuillez saisir un Calendar ID'
        ];
    } else {
        try {
            $testEventDate = date('Y-m-d', strtotime('+1 day'));
            $testEventTime = '10:00';
            $testResult = $calendar->createEvent($testCalendarId, [
                'name' => 'Test Chatbot - lancé le ' . date('d/m/Y') . ' à ' . date('H\hi'),
                'email' => null,
                'phone' => '0600000000',
                'service' => 'Test diagnostic',
                'date' => $testEventDate,
                'time' => $testEventTime,
                'duration' => 30
            ]);
        } catch (Exception $e) {
            $testResult = [
                'success' => false,
                'event_id' => null,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}

// 5. Vérifier les RDV récents
$recentAppts = [];
try {
    $recentAppts = $db->fetchAll("SELECT id, visitor_name, appointment_date, appointment_time, google_event_id, status, created_at FROM appointments ORDER BY created_at DESC LIMIT 5");
} catch (Exception $e) {}
?>

<div class="page-header">
    <h1 class="page-title">Diagnostic Google Calendar</h1>
</div>

<div class="card">
    <h2 class="card-title">Tests de configuration</h2>
    <table style="width: 100%; border-collapse: collapse;">
        <?php foreach ($results as $r): ?>
        <tr style="border-bottom: 1px solid #f1f5f9;">
            <td style="padding: 10px; font-weight: 500;"><?= htmlspecialchars($r['test']) ?></td>
            <td style="padding: 10px; font-family: monospace; font-size: 13px; word-break: break-all;"><?= htmlspecialchars($r['value']) ?></td>
            <td style="padding: 10px; text-align: center;"><?= $r['ok'] ? '<span style="color: #059669;">OK</span>' : '<span style="color: #dc2626;">ERREUR</span>' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>

<!-- Test de création d'événement -->
<div class="card">
    <h2 class="card-title">Tester la création d'un événement</h2>

    <?php if ($testResult): ?>
        <?php if ($testResult['success']): ?>
            <?php
            $testDate = date('d/m/Y', strtotime('+1 day'));
            $testTime = '10h00';
            ?>
            <div class="alert alert-success">
                Événement créé avec succès ! ID: <?= htmlspecialchars($testResult['event_id'] ?? 'N/A') ?>
                <br>Vérifiez votre Google Calendar le <strong><?= $testDate ?></strong> à <strong><?= $testTime ?></strong> (30 min).
            </div>
        <?php else: ?>
            <div class="alert alert-error">
                Erreur : <?= htmlspecialchars($testResult['error'] ?? 'Inconnue') ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="POST">
        <?= CSRF::inputField() ?>
        <div class="form-group">
            <label class="form-label">Calendar ID à tester</label>
            <input type="text" name="test_calendar_id" class="form-input"
                   placeholder="votre-email@gmail.com ou xxx@group.calendar.google.com"
                   value="<?= htmlspecialchars($_POST['test_calendar_id'] ?? $defaultCalendarId) ?>">
            <p class="form-hint">Créera un événement test demain à 10h (30 min). Assurez-vous que le calendrier est partagé avec <code>chatbotai@myziggi.iam.gserviceaccount.com</code></p>
        </div>
        <button type="submit" class="btn btn-primary">Tester</button>
    </form>
</div>

<!-- RDV récents -->
<?php if (!empty($recentAppts)): ?>
<div class="card">
    <h2 class="card-title">5 derniers RDV</h2>
    <table style="width: 100%; border-collapse: collapse;">
        <tr style="border-bottom: 2px solid #e2e8f0;">
            <th style="padding: 8px; text-align: left; font-size: 12px;">ID</th>
            <th style="padding: 8px; text-align: left; font-size: 12px;">Nom</th>
            <th style="padding: 8px; text-align: left; font-size: 12px;">Date</th>
            <th style="padding: 8px; text-align: left; font-size: 12px;">Google Event ID</th>
            <th style="padding: 8px; text-align: left; font-size: 12px;">Créé le</th>
        </tr>
        <?php foreach ($recentAppts as $apt): ?>
        <tr style="border-bottom: 1px solid #f1f5f9;">
            <td style="padding: 8px;"><?= $apt['id'] ?></td>
            <td style="padding: 8px;"><?= htmlspecialchars($apt['visitor_name']) ?></td>
            <td style="padding: 8px;"><?= $apt['appointment_date'] ?> <?= $apt['appointment_time'] ?></td>
            <td style="padding: 8px; font-family: monospace; font-size: 12px;">
                <?= $apt['google_event_id'] ? '<span style="color: #059669;">' . htmlspecialchars($apt['google_event_id']) . '</span>' : '<span style="color: #dc2626;">NON</span>' ?>
            </td>
            <td style="padding: 8px; font-size: 12px;"><?= $apt['created_at'] ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<!-- Debug Log -->
<?php if (file_exists($debugFile)):
    $debugContent = file_get_contents($debugFile);
?>
<div class="card">
    <h2 class="card-title">Debug Log (booking)</h2>
    <pre style="background: #1e293b; color: #e2e8f0; padding: 16px; border-radius: 8px; font-size: 12px; overflow-x: auto; max-height: 400px; overflow-y: auto; white-space: pre-wrap;"><?= htmlspecialchars($debugContent) ?></pre>
    <form method="POST" style="margin-top: 8px;">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="clear_debug" value="1">
        <button type="submit" class="btn btn-secondary btn-sm">Vider le log</button>
    </form>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
