<?php
/**
 * API REST pour les agents
 *
 * Endpoints :
 * GET /agents.php?key=XXX - Liste des agents pour le widget
 * GET /agents.php?key=XXX&id=123 - Détails d'un agent
 * GET /agents.php?key=XXX&action=available&date=YYYY-MM-DD&time=HH:MM - Agents disponibles
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../classes/AgentDistributor.php';
require_once __DIR__ . '/../classes/AgentScheduleManager.php';

// CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
header("Access-Control-Allow-Origin: *"); // Adapter selon les besoins
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Fonction de réponse JSON
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Récupérer l'API key
$apiKey = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

if (!$apiKey) {
    jsonResponse(['error' => 'API key requise'], 401);
}

// Vérifier le client
$db = Database::getInstance();
$pdo = $db->getConnection();

$stmt = $pdo->prepare("SELECT id, active FROM clients WHERE api_key = ?");
$stmt->execute([$apiKey]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    jsonResponse(['error' => 'Client non trouvé'], 404);
}

if (!$client['active']) {
    jsonResponse(['error' => 'Client inactif'], 403);
}

$clientId = $client['id'];
$distributor = new AgentDistributor();
$scheduleManager = new AgentScheduleManager();

// Router les actions
$action = $_GET['action'] ?? 'list';
$agentId = isset($_GET['id']) ? (int)$_GET['id'] : null;

switch ($action) {
    case 'list':
        handleList($clientId, $distributor);
        break;

    case 'detail':
        if (!$agentId) {
            jsonResponse(['error' => 'ID agent requis'], 400);
        }
        handleDetail($clientId, $agentId, $distributor);
        break;

    case 'available':
        handleAvailable($clientId, $distributor, $scheduleManager);
        break;

    case 'slots':
        if (!$agentId) {
            jsonResponse(['error' => 'ID agent requis'], 400);
        }
        handleSlots($agentId, $scheduleManager);
        break;

    default:
        jsonResponse(['error' => 'Action non reconnue'], 400);
}

/**
 * Liste des agents
 */
function handleList($clientId, $distributor) {
    $specialty = $_GET['specialty'] ?? null;

    $agents = $distributor->getAvailableAgentsForDisplay($clientId, $specialty);

    // Formater pour le frontend
    $formattedAgents = array_map(function($agent) {
        return [
            'id' => (int)$agent['id'],
            'name' => $agent['name'],
            'photo' => $agent['photo_url'] ?? null,
            'specialties' => $agent['specialties'] ?? [],
            'bio' => $agent['bio'] ?? null,
            'color' => $agent['color'] ?? '#3498db'
        ];
    }, $agents);

    jsonResponse([
        'success' => true,
        'count' => count($formattedAgents),
        'agents' => $formattedAgents
    ]);
}

/**
 * Détails d'un agent
 */
function handleDetail($clientId, $agentId, $distributor) {
    $agent = $distributor->getAgentById($agentId, $clientId);

    if (!$agent) {
        jsonResponse(['error' => 'Agent non trouvé'], 404);
    }

    // Récupérer les stats de l'agent
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_appointments,
            AVG(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) * 100 as completion_rate
        FROM appointments_v2
        WHERE agent_id = ?
    ");
    $stmt->execute([$agentId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'agent' => [
            'id' => (int)$agent['id'],
            'name' => $agent['name'],
            'email' => $agent['email'],
            'phone' => $agent['phone'],
            'photo' => $agent['photo_url'],
            'specialties' => json_decode($agent['specialties'] ?? '[]', true),
            'bio' => $agent['bio'],
            'color' => $agent['color'],
            'stats' => [
                'total_appointments' => (int)$stats['total_appointments'],
                'completion_rate' => round($stats['completion_rate'] ?? 0, 1)
            ]
        ]
    ]);
}

/**
 * Agents disponibles pour un créneau
 */
function handleAvailable($clientId, $distributor, $scheduleManager) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $time = $_GET['time'] ?? null;
    $specialty = $_GET['specialty'] ?? null;

    // Récupérer tous les agents
    $agents = $distributor->getAgentsByClient($clientId, true);

    $availableAgents = [];

    foreach ($agents as $agent) {
        // Filtrer par spécialité si demandé
        if ($specialty) {
            $agentSpecs = json_decode($agent['specialties'] ?? '[]', true);
            if (!in_array(strtolower($specialty), array_map('strtolower', $agentSpecs))) {
                continue;
            }
        }

        // Vérifier disponibilité
        if ($time) {
            $isAvailable = $scheduleManager->isTimeSlotAvailable($agent['id'], $date, $time);
        } else {
            // Sans heure précise, vérifier s'il y a des créneaux ce jour
            $slots = $scheduleManager->getAvailableSlots($agent['id'], $date);
            $isAvailable = !empty($slots);
        }

        if ($isAvailable) {
            $availableAgents[] = [
                'id' => (int)$agent['id'],
                'name' => $agent['name'],
                'photo' => $agent['photo_url'],
                'specialties' => json_decode($agent['specialties'] ?? '[]', true),
                'color' => $agent['color']
            ];
        }
    }

    jsonResponse([
        'success' => true,
        'date' => $date,
        'time' => $time,
        'count' => count($availableAgents),
        'agents' => $availableAgents
    ]);
}

/**
 * Créneaux disponibles pour un agent
 */
function handleSlots($agentId, $scheduleManager) {
    $date = $_GET['date'] ?? date('Y-m-d');
    $duration = (int)($_GET['duration'] ?? 60);

    $slots = $scheduleManager->getAvailableSlots($agentId, $date, $duration);

    // TODO: Filtrer par les RDV existants et Google Calendar

    jsonResponse([
        'success' => true,
        'date' => $date,
        'duration' => $duration,
        'slots' => $slots
    ]);
}
