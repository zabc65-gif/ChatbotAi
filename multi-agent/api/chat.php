<?php
/**
 * API Chat Multi-Agent
 *
 * Point d'entrée pour le chatbot avec gestion multi-agents
 * Compatible avec le système existant mais ajoute la sélection d'agent
 */

header('Content-Type: application/json; charset=utf-8');

// Inclure les dépendances
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Chatbot.php';
require_once __DIR__ . '/../../classes/GroqAPI.php';
require_once __DIR__ . '/../classes/AgentDistributor.php';
require_once __DIR__ . '/../classes/MultiAgentBookingProcessor.php';

// Gestion CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$db = Database::getInstance();
$pdo = $db->getConnection();

// Récupérer les domaines autorisés pour ce client
$apiKey = $_REQUEST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';

if ($apiKey) {
    $stmt = $pdo->prepare("
        SELECT c.id, c.active, cb.allowed_domains
        FROM clients c
        LEFT JOIN client_chatbots cb ON cb.client_id = c.id
        WHERE c.api_key = ?
    ");
    $stmt->execute([$apiKey]);
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($client && $client['allowed_domains']) {
        $allowedDomains = array_filter(array_map('trim', explode("\n", $client['allowed_domains'])));
        $allowedOrigins = [];

        foreach ($allowedDomains as $domain) {
            $domain = preg_replace('#^https?://#', '', $domain);
            $domain = rtrim($domain, '/');
            $allowedOrigins[] = 'https://' . $domain;
            $allowedOrigins[] = 'http://' . $domain;
            $allowedOrigins[] = 'https://www.' . $domain;
            $allowedOrigins[] = 'http://www.' . $domain;
        }

        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        }
    }
}

header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Access-Control-Allow-Credentials: true');

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

// Récupérer l'action
$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'init':
        handleInit();
        break;

    case 'message':
        handleMessage();
        break;

    case 'config':
        handleConfig();
        break;

    case 'agents':
        handleAgents();
        break;

    case 'history':
        handleHistory();
        break;

    default:
        jsonResponse(['error' => 'Action non reconnue'], 400);
}

/**
 * Initialisation d'une session
 */
function handleInit() {
    global $pdo, $apiKey;

    if (!$apiKey) {
        jsonResponse(['error' => 'API key requise'], 401);
    }

    $stmt = $pdo->prepare("
        SELECT c.id as client_id, c.active, cb.*
        FROM clients c
        LEFT JOIN client_chatbots cb ON cb.client_id = c.id
        WHERE c.api_key = ?
    ");
    $stmt->execute([$apiKey]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data || !$data['active']) {
        jsonResponse(['error' => 'Client inactif ou non trouvé'], 403);
    }

    // Générer un session ID
    $sessionId = 'ma_' . time() . '_' . bin2hex(random_bytes(8));

    // Récupérer la config multi-agent
    $distributor = new AgentDistributor();
    $config = $distributor->getClientConfig($data['client_id']);

    jsonResponse([
        'success' => true,
        'session_id' => $sessionId,
        'config' => [
            'bot_name' => $data['bot_name'] ?? 'Assistant',
            'welcome_message' => $data['welcome_message'] ?? 'Bonjour ! Comment puis-je vous aider ?',
            'primary_color' => $data['primary_color'] ?? '#3498db',
            'quick_actions' => array_filter(explode("\n", $data['quick_actions'] ?? '')),
            'show_face' => (bool)($data['show_face'] ?? true),
            'show_hat' => (bool)($data['show_hat'] ?? false),
            'booking_enabled' => (bool)($data['booking_enabled'] ?? false),
            'multi_agent' => [
                'enabled' => true,
                'allow_visitor_choice' => (bool)($config['allow_visitor_choice'] ?? false),
                'show_agent_photos' => (bool)($config['show_agent_photos'] ?? true),
                'show_agent_bios' => (bool)($config['show_agent_bios'] ?? true),
                'distribution_mode' => $config['distribution_mode'] ?? 'round_robin'
            ]
        ]
    ]);
}

/**
 * Traitement d'un message
 */
function handleMessage() {
    global $pdo, $apiKey;

    if (!$apiKey) {
        jsonResponse(['error' => 'API key requise'], 401);
    }

    // Récupérer les données
    $message = trim($_POST['message'] ?? '');
    $sessionId = $_POST['session_id'] ?? '';
    $preferredAgentId = $_POST['preferred_agent_id'] ?? null;

    if (empty($message)) {
        jsonResponse(['error' => 'Message vide'], 400);
    }

    // Récupérer le client
    $stmt = $pdo->prepare("
        SELECT c.id as client_id, c.active, cb.*
        FROM clients c
        LEFT JOIN client_chatbots cb ON cb.client_id = c.id
        WHERE c.api_key = ?
    ");
    $stmt->execute([$apiKey]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data || !$data['active']) {
        jsonResponse(['error' => 'Client inactif'], 403);
    }

    $clientId = $data['client_id'];

    // Charger le chatbot et générer la réponse
    try {
        // Construire le system prompt avec les instructions booking
        $systemPrompt = $data['system_prompt'] ?? '';

        // Ajouter les instructions multi-agent pour le booking
        $distributor = new AgentDistributor();
        $config = $distributor->getClientConfig($clientId);

        if ($data['booking_enabled']) {
            $systemPrompt .= "\n\n" . MultiAgentBookingProcessor::getMultiAgentBookingInstructions(
                (bool)($config['allow_visitor_choice'] ?? false)
            );
        }

        // Charger les connaissances du client
        $knowledge = loadClientKnowledge($clientId);
        if ($knowledge) {
            $systemPrompt .= "\n\n" . $knowledge;
        }

        // Initialiser le chatbot
        $chatbot = new Chatbot($systemPrompt, $sessionId);

        // Générer la réponse
        $response = $chatbot->chat($message);

        // Vérifier si la réponse contient un booking
        $bookingResult = null;
        $bookingData = MultiAgentBookingProcessor::detectBookingInResponse($response);

        if ($bookingData && $data['booking_enabled']) {
            $processor = new MultiAgentBookingProcessor();
            $bookingResult = $processor->processBookingWithAgent(
                $bookingData,
                $clientId,
                $sessionId,
                $preferredAgentId ? (int)$preferredAgentId : null
            );

            // Retirer le marqueur de la réponse
            $response = MultiAgentBookingProcessor::stripBookingMarker($response);
        }

        // Construire la réponse
        $result = [
            'success' => true,
            'message' => $response,
            'session_id' => $sessionId
        ];

        if ($bookingResult) {
            $result['booking'] = $bookingResult;
        }

        jsonResponse($result);

    } catch (Exception $e) {
        error_log("Multi-Agent Chat Error: " . $e->getMessage());
        jsonResponse(['error' => 'Erreur lors du traitement'], 500);
    }
}

/**
 * Récupérer la configuration du widget
 */
function handleConfig() {
    global $pdo, $apiKey;

    $apiKey = $_GET['key'] ?? $apiKey;

    if (!$apiKey) {
        jsonResponse(['error' => 'API key requise'], 401);
    }

    $stmt = $pdo->prepare("
        SELECT c.id as client_id, cb.*
        FROM clients c
        LEFT JOIN client_chatbots cb ON cb.client_id = c.id
        WHERE c.api_key = ? AND c.active = 1
    ");
    $stmt->execute([$apiKey]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        jsonResponse(['error' => 'Client non trouvé'], 404);
    }

    $distributor = new AgentDistributor();
    $config = $distributor->getClientConfig($data['client_id']);

    jsonResponse([
        'success' => true,
        'config' => [
            'bot_name' => $data['bot_name'] ?? 'Assistant',
            'welcome_message' => $data['welcome_message'] ?? 'Bonjour !',
            'primary_color' => $data['primary_color'] ?? '#3498db',
            'icon' => $data['icon'] ?? '🤖',
            'quick_actions' => array_filter(array_map('trim', explode("\n", $data['quick_actions'] ?? ''))),
            'show_face' => (bool)($data['show_face'] ?? true),
            'show_hat' => (bool)($data['show_hat'] ?? false),
            'face_color' => $data['face_color'] ?? '#FFD93D',
            'hat_color' => $data['hat_color'] ?? '#E74C3C',
            'booking_enabled' => (bool)($data['booking_enabled'] ?? false),
            'multi_agent' => [
                'enabled' => true,
                'allow_visitor_choice' => (bool)($config['allow_visitor_choice'] ?? false),
                'show_photos' => (bool)($config['show_agent_photos'] ?? true),
                'show_bios' => (bool)($config['show_agent_bios'] ?? true)
            ]
        ]
    ]);
}

/**
 * Récupérer la liste des agents pour le widget
 */
function handleAgents() {
    global $pdo, $apiKey;

    $apiKey = $_GET['key'] ?? $apiKey;

    if (!$apiKey) {
        jsonResponse(['error' => 'API key requise'], 401);
    }

    // Récupérer le client
    $stmt = $pdo->prepare("SELECT id FROM clients WHERE api_key = ? AND active = 1");
    $stmt->execute([$apiKey]);
    $clientId = $stmt->fetchColumn();

    if (!$clientId) {
        jsonResponse(['error' => 'Client non trouvé'], 404);
    }

    $specialty = $_GET['specialty'] ?? null;

    $distributor = new AgentDistributor();
    $agents = $distributor->getAvailableAgentsForDisplay($clientId, $specialty);

    jsonResponse([
        'success' => true,
        'agents' => $agents
    ]);
}

/**
 * Récupérer l'historique de conversation
 */
function handleHistory() {
    global $pdo;

    $sessionId = $_GET['session_id'] ?? '';

    if (!$sessionId) {
        jsonResponse(['error' => 'Session ID requis'], 400);
    }

    $stmt = $pdo->prepare("
        SELECT role, content, created_at
        FROM conversations
        WHERE session_id = ?
        ORDER BY created_at ASC
        LIMIT 50
    ");
    $stmt->execute([$sessionId]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'success' => true,
        'messages' => $messages
    ]);
}

/**
 * Charger les connaissances du client
 */
function loadClientKnowledge(int $clientId): string {
    global $pdo;

    $knowledge = "";

    // Charger les champs personnalisés
    $stmt = $pdo->prepare("
        SELECT field_key, field_value
        FROM client_chatbot_field_values
        WHERE client_id = ?
    ");
    $stmt->execute([$clientId]);
    $fields = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($fields) {
        $knowledge .= "INFORMATIONS SUR L'ENTREPRISE :\n";
        foreach ($fields as $field) {
            $knowledge .= "- " . ucfirst(str_replace('_', ' ', $field['field_key'])) . " : " . $field['field_value'] . "\n";
        }
    }

    // Charger la base de connaissances
    $stmt = $pdo->prepare("
        SELECT type, question, answer
        FROM client_chatbot_knowledge
        WHERE client_id = ? AND active = 1
        ORDER BY sort_order
    ");
    $stmt->execute([$clientId]);
    $faqs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($faqs) {
        $knowledge .= "\nBASE DE CONNAISSANCES :\n";
        foreach ($faqs as $faq) {
            if ($faq['type'] === 'faq' && $faq['question']) {
                $knowledge .= "Q: " . $faq['question'] . "\n";
                $knowledge .= "R: " . $faq['answer'] . "\n\n";
            } else {
                $knowledge .= $faq['answer'] . "\n";
            }
        }
    }

    return $knowledge;
}
