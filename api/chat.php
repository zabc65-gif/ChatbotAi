<?php
/**
 * Endpoint API du Chatbot
 * Point d'entrée pour les requêtes AJAX du widget
 */

// Démarrer la session en premier (avant tout output)
@session_start();

// Headers CORS et JSON
header('Content-Type: application/json; charset=utf-8');

// CORS sécurisé - Liste des origines autorisées
$allowedOrigins = [
    'https://chatbot.myziggi.pro',
    'http://chatbot.myziggi.pro',
    'https://www.chatbot.myziggi.pro'
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowedOrigins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} elseif (empty($origin)) {
    // Requêtes same-origin (pas de header Origin)
    header('Access-Control-Allow-Origin: https://chatbot.myziggi.pro');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');

// Gérer les requêtes OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Vérifier la méthode HTTP
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Méthode non autorisée']);
    exit;
}

// Charger la configuration et les classes
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/HistoryManager.php';
require_once __DIR__ . '/../classes/AIServiceInterface.php';
require_once __DIR__ . '/../classes/GroqAPI.php';
require_once __DIR__ . '/../classes/GeminiAPI.php';
require_once __DIR__ . '/../classes/Chatbot.php';
require_once __DIR__ . '/../classes/Settings.php';
require_once __DIR__ . '/../classes/Auth.php';

// Charger les paramètres depuis la BDD
$settingsDb = new Database();
$settingsManager = new Settings($settingsDb);

// Vérifier si un admin est connecté (pas de limite pour les admins)
$isAdmin = false;
try {
    if (isset($_SESSION['user_id'])) {
        $auth = new Auth($settingsDb);
        $currentUser = $auth->getCurrentUser();
        $isAdmin = $currentUser && in_array($currentUser['role'], ['admin', 'editor']);
    }
} catch (Exception $e) {
    // Ignorer les erreurs d'auth, continuer sans privilèges admin
}

// Récupérer les données de la requête
$input = json_decode(file_get_contents('php://input'), true);

// Identifier l'utilisateur (IP + fingerprint)
$userIdentifier = getUserIdentifier($input['fingerprint'] ?? null);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Données invalides']);
    exit;
}

$action = $input['action'] ?? 'message';

try {
    $chatbot = new Chatbot();

    switch ($action) {
        case 'message':
            // Envoyer un message
            $sessionId = $input['session_id'] ?? null;
            $message = $input['message'] ?? '';
            $context = $input['context'] ?? null; // Contexte métier (btp, immo, ecommerce)

            if (empty($message)) {
                echo json_encode(['success' => false, 'error' => 'Message requis']);
                exit;
            }

            // Créer ou récupérer la session
            $sessionId = $chatbot->getOrCreateSession($sessionId);

            // Vérifier la limite d'utilisation pour les démos (sauf admins)
            if ($context && !$isAdmin) {
                $usageCheck = checkUsageLimit($userIdentifier, $context);
                if (!$usageCheck['allowed']) {
                    echo json_encode([
                        'success' => true,
                        'session_id' => $sessionId,
                        'response' => $usageCheck['message'],
                        'service' => 'limit',
                        'limited' => true,
                        'remaining' => 0
                    ]);
                    exit;
                }
            }

            // Si contexte métier spécifié, adapter le message système
            if ($context) {
                adaptSystemMessage($context);
            } else {
                // Chatbot principal : charger la base de connaissances
                adaptMainChatbotMessage();
            }

            // Vérifier si le message est hors sujet (anti-abus)
            $abuseCheck = checkForAbuse($message, $context);
            if ($abuseCheck['is_abuse']) {
                // Incrémenter quand même l'usage (pour éviter le spam de questions hors sujet)
                if ($context) {
                    incrementUsage($userIdentifier);
                }
                $remaining = $context ? getRemainingMessages($userIdentifier) : null;
                echo json_encode([
                    'success' => true,
                    'session_id' => $sessionId,
                    'response' => $abuseCheck['redirect_message'],
                    'service' => 'filter',
                    'filtered' => true,
                    'remaining' => $remaining
                ]);
                exit;
            }

            // Traiter le message
            $response = $chatbot->processMessage($sessionId, $message);

            // Incrémenter l'usage pour les démos (sauf admins)
            if ($context && !$isAdmin) {
                incrementUsage($userIdentifier);
                $response['remaining'] = getRemainingMessages($userIdentifier);
            } elseif ($context && $isAdmin) {
                $response['remaining'] = null; // Illimité pour les admins
                $response['is_admin'] = true;
            }

            echo json_encode($response);
            break;

        case 'init':
            // Initialiser une nouvelle session
            $context = $input['context'] ?? null;
            $sessionId = $chatbot->getOrCreateSession();

            // Si contexte métier, adapter le système
            if ($context) {
                adaptSystemMessage($context);
            }

            $response = [
                'success' => true,
                'session_id' => $sessionId,
                'welcome_message' => getWelcomeMessage($context)
            ];

            // Ajouter les infos de limite pour les démos
            if ($context) {
                if ($isAdmin) {
                    $response['remaining'] = null; // Illimité pour les admins
                    $response['is_admin'] = true;
                } else {
                    $response['remaining'] = getRemainingMessages($userIdentifier);
                    $response['daily_limit'] = getDailyLimit();
                }
            }

            echo json_encode($response);
            break;

        case 'history':
            // Récupérer l'historique
            $sessionId = $input['session_id'] ?? null;

            if (!$sessionId) {
                echo json_encode(['success' => false, 'error' => 'Session ID requis']);
                exit;
            }

            $history = $chatbot->getDisplayHistory($sessionId);
            echo json_encode([
                'success' => true,
                'history' => array_values($history)
            ]);
            break;

        case 'clear':
            // Effacer l'historique
            $sessionId = $input['session_id'] ?? null;

            if (!$sessionId) {
                echo json_encode(['success' => false, 'error' => 'Session ID requis']);
                exit;
            }

            $chatbot->clearSession($sessionId);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Action inconnue']);
    }

} catch (Exception $e) {
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Une erreur est survenue']);
    }
}

/**
 * Adapte le message système selon le contexte métier
 * Charge les prompts depuis la table demo_chatbots et intègre la base de connaissances + champs personnalisés
 */
function adaptSystemMessage(string $context): void
{
    global $settingsDb;

    // Charger depuis la table demo_chatbots
    $chatbot = $settingsDb->fetchOne(
        "SELECT id, system_prompt FROM demo_chatbots WHERE slug = ? AND active = 1",
        [$context]
    );

    if ($chatbot && !empty($chatbot['system_prompt'])) {
        $systemPrompt = $chatbot['system_prompt'];

        // Charger et intégrer les champs personnalisés
        $fieldsBlock = getChatbotFields($chatbot['id']);
        if (!empty($fieldsBlock)) {
            // Remplacer le placeholder {CHATBOT_FIELDS} ou ajouter à la fin
            if (strpos($systemPrompt, '{CHATBOT_FIELDS}') !== false) {
                $systemPrompt = str_replace('{CHATBOT_FIELDS}', $fieldsBlock, $systemPrompt);
            } else {
                $systemPrompt .= "\n\n" . $fieldsBlock;
            }
        } else {
            // Supprimer le placeholder s'il n'y a pas de champs
            $systemPrompt = str_replace('{CHATBOT_FIELDS}', '', $systemPrompt);
        }

        // Charger et intégrer la base de connaissances
        $knowledge = getKnowledgeBase($chatbot['id']);
        if (!empty($knowledge)) {
            $systemPrompt .= "\n\n" . $knowledge;
        }

        $GLOBALS['CUSTOM_SYSTEM_MESSAGE'] = $systemPrompt;
    }
}

/**
 * Récupère et formate les champs personnalisés d'un chatbot
 * @param int $chatbotId ID du chatbot
 * @return string Bloc formaté des informations
 */
function getChatbotFields(int $chatbotId): string
{
    global $settingsDb;

    // Vérifier si les tables existent
    try {
        $tableExists = $settingsDb->fetchOne("SHOW TABLES LIKE 'chatbot_field_values'");
        if (!$tableExists) {
            return '';
        }
    } catch (Exception $e) {
        return '';
    }

    // Récupérer les valeurs avec leurs labels
    $fields = $settingsDb->fetchAll(
        "SELECT d.field_key, d.field_label, d.field_group, d.field_type, v.field_value
         FROM chatbot_field_values v
         JOIN chatbot_field_definitions d ON d.field_key = v.field_key
         WHERE v.chatbot_id = ? AND v.field_value IS NOT NULL AND v.field_value != ''
         ORDER BY d.field_group, d.sort_order",
        [$chatbotId]
    );

    if (empty($fields)) {
        return '';
    }

    // Grouper par catégorie
    $groups = [];
    $groupLabels = [
        'agence' => 'INFORMATIONS AGENCE',
        'entreprise' => 'INFORMATIONS ENTREPRISE',
        'boutique' => 'INFORMATIONS BOUTIQUE',
        'mandats' => 'TYPES DE MANDATS',
        'honoraires' => 'HONORAIRES ET TARIFS',
        'services' => 'SERVICES PROPOSÉS',
        'zone' => 'ZONE D\'INTERVENTION',
        'documents' => 'DOCUMENTS ET FORMALITÉS',
        'processus' => 'PROCESSUS ET ÉTAPES',
        'metier' => 'MÉTIER ET SPÉCIALITÉS',
        'prestations' => 'PRESTATIONS',
        'livraison' => 'LIVRAISON',
        'retours' => 'RETOURS ET ÉCHANGES',
        'paiement' => 'MOYENS DE PAIEMENT',
        'produits' => 'PRODUITS',
        'general' => 'INFORMATIONS GÉNÉRALES',
    ];

    foreach ($fields as $field) {
        $group = $field['field_group'] ?: 'general';
        if (!isset($groups[$group])) {
            $groups[$group] = [];
        }

        // Formater la valeur selon le type
        $value = $field['field_value'];
        if ($field['field_type'] === 'checkbox') {
            $value = $value ? 'Oui' : 'Non';
        }

        $groups[$group][] = [
            'label' => $field['field_label'],
            'value' => $value
        ];
    }

    // Construire le bloc texte
    $output = "";

    foreach ($groups as $groupKey => $groupFields) {
        $groupTitle = $groupLabels[$groupKey] ?? strtoupper($groupKey);
        $output .= "--- {$groupTitle} ---\n";

        foreach ($groupFields as $field) {
            // Si la valeur contient des retours à la ligne, l'indenter
            if (strpos($field['value'], "\n") !== false) {
                $output .= "• {$field['label']} :\n  " . str_replace("\n", "\n  ", $field['value']) . "\n";
            } else {
                $output .= "• {$field['label']} : {$field['value']}\n";
            }
        }
        $output .= "\n";
    }

    return trim($output);
}

/**
 * Récupère et formate la base de connaissances d'un chatbot
 * @param int|null $chatbotId ID du chatbot (null = chatbot principal)
 */
function getKnowledgeBase(?int $chatbotId): string
{
    global $settingsDb;

    // Vérifier si la table existe
    try {
        $tableExists = $settingsDb->fetchOne("SHOW TABLES LIKE 'chatbot_knowledge'");
        if (!$tableExists) {
            return '';
        }
    } catch (Exception $e) {
        return '';
    }

    // Charger les connaissances actives
    if ($chatbotId === null) {
        // Chatbot principal : chatbot_id IS NULL
        $items = $settingsDb->fetchAll(
            "SELECT type, question, answer, keywords FROM chatbot_knowledge
             WHERE chatbot_id IS NULL AND active = 1
             ORDER BY type ASC, sort_order ASC"
        );
    } else {
        // Chatbot de démo spécifique
        $items = $settingsDb->fetchAll(
            "SELECT type, question, answer, keywords FROM chatbot_knowledge
             WHERE chatbot_id = ? AND active = 1
             ORDER BY type ASC, sort_order ASC",
            [$chatbotId]
        );
    }

    if (empty($items)) {
        return '';
    }

    // Formater les connaissances par type
    $faqs = [];
    $infos = [];
    $responses = [];

    foreach ($items as $item) {
        switch ($item['type']) {
            case 'faq':
                if ($item['question']) {
                    $faqs[] = "Q: " . $item['question'] . "\nR: " . $item['answer'];
                }
                break;
            case 'info':
                $infos[] = $item['answer'];
                break;
            case 'response':
                $responses[] = $item['answer'];
                break;
        }
    }

    // Construire le bloc de connaissances
    $knowledgeBlock = "=== BASE DE CONNAISSANCES ===\n";
    $knowledgeBlock .= "Utilise ces informations pour répondre aux questions des visiteurs.\n\n";

    if (!empty($infos)) {
        $knowledgeBlock .= "--- INFORMATIONS ---\n";
        $knowledgeBlock .= implode("\n\n", $infos) . "\n\n";
    }

    if (!empty($faqs)) {
        $knowledgeBlock .= "--- QUESTIONS FRÉQUENTES ---\n";
        $knowledgeBlock .= implode("\n\n", $faqs) . "\n\n";
    }

    if (!empty($responses)) {
        $knowledgeBlock .= "--- RÉPONSES PERSONNALISÉES ---\n";
        $knowledgeBlock .= implode("\n\n", $responses) . "\n";
    }

    return $knowledgeBlock;

}

/**
 * Adapte le message système pour le chatbot principal (sans contexte de démo)
 * Intègre la base de connaissances du chatbot principal
 */
function adaptMainChatbotMessage(): void
{
    global $settingsManager;

    // Charger le prompt système personnalisé depuis les settings
    $customPrompt = $settingsManager->get('chatbot_system_prompt');

    if ($customPrompt) {
        $systemPrompt = $customPrompt;
    } else {
        // Utiliser le prompt par défaut (constante SYSTEM_MESSAGE)
        $systemPrompt = defined('SYSTEM_MESSAGE') ? SYSTEM_MESSAGE : '';
    }

    // Charger et intégrer les champs personnalisés du chatbot principal (ID = 0)
    $fieldsBlock = getMainChatbotFields();
    if (!empty($fieldsBlock)) {
        // Remplacer le placeholder {CHATBOT_FIELDS} ou ajouter à la fin
        if (strpos($systemPrompt, '{CHATBOT_FIELDS}') !== false) {
            $systemPrompt = str_replace('{CHATBOT_FIELDS}', $fieldsBlock, $systemPrompt);
        } else {
            $systemPrompt .= "\n\n" . $fieldsBlock;
        }
    } else {
        // Supprimer le placeholder s'il n'y a pas de champs
        $systemPrompt = str_replace('{CHATBOT_FIELDS}', '', $systemPrompt);
    }

    // Charger et intégrer la base de connaissances du chatbot principal
    $knowledge = getKnowledgeBase(null);
    if (!empty($knowledge)) {
        $systemPrompt .= "\n\n" . $knowledge;
    }

    // Appliquer si on a des personnalisations
    if (!empty($fieldsBlock) || !empty($knowledge) || $customPrompt) {
        $GLOBALS['CUSTOM_SYSTEM_MESSAGE'] = $systemPrompt;
    }
}

/**
 * Récupère et formate les champs personnalisés du chatbot principal
 * @return string Bloc formaté des informations
 */
function getMainChatbotFields(): string
{
    global $settingsDb;

    // Vérifier si les tables existent
    try {
        $tableExists = $settingsDb->fetchOne("SHOW TABLES LIKE 'chatbot_field_values'");
        if (!$tableExists) {
            return '';
        }
    } catch (Exception $e) {
        return '';
    }

    // Récupérer les valeurs du chatbot principal (chatbot_id = 0)
    $fields = $settingsDb->fetchAll(
        "SELECT d.field_key, d.field_label, d.field_group, d.field_type, v.field_value
         FROM chatbot_field_values v
         JOIN chatbot_field_definitions d ON d.field_key = v.field_key
         WHERE v.chatbot_id = 0 AND v.field_value IS NOT NULL AND v.field_value != ''
         ORDER BY d.field_group, d.sort_order"
    );

    if (empty($fields)) {
        return '';
    }

    // Grouper par catégorie
    $groups = [];
    $groupLabels = [
        'entreprise' => 'INFORMATIONS ENTREPRISE',
        'prestations' => 'PRESTATIONS',
        'zone' => 'ZONE D\'INTERVENTION',
        'general' => 'INFORMATIONS GÉNÉRALES',
    ];

    foreach ($fields as $field) {
        $group = $field['field_group'] ?: 'general';
        if (!isset($groups[$group])) {
            $groups[$group] = [];
        }

        // Formater la valeur selon le type
        $value = $field['field_value'];
        if ($field['field_type'] === 'checkbox') {
            $value = $value ? 'Oui' : 'Non';
        }

        $groups[$group][] = [
            'label' => $field['field_label'],
            'value' => $value
        ];
    }

    // Construire le bloc texte
    $output = "";

    foreach ($groups as $groupKey => $groupFields) {
        $groupTitle = $groupLabels[$groupKey] ?? strtoupper($groupKey);
        $output .= "--- {$groupTitle} ---\n";

        foreach ($groupFields as $field) {
            if (strpos($field['value'], "\n") !== false) {
                $output .= "• {$field['label']} :\n  " . str_replace("\n", "\n  ", $field['value']) . "\n";
            } else {
                $output .= "• {$field['label']} : {$field['value']}\n";
            }
        }
        $output .= "\n";
    }

    return trim($output);
}

/**
 * Retourne le message de bienvenue selon le contexte
 * Charge depuis la table demo_chatbots
 */
function getWelcomeMessage(?string $context): string
{
    global $settingsDb;

    if ($context) {
        $chatbot = $settingsDb->fetchOne(
            "SELECT welcome_message FROM demo_chatbots WHERE slug = ? AND active = 1",
            [$context]
        );

        if ($chatbot && !empty($chatbot['welcome_message'])) {
            return $chatbot['welcome_message'];
        }
    }

    return "Bonjour ! Je suis un assistant virtuel intelligent. Comment puis-je vous aider aujourd'hui ?";
}

/**
 * Vérifie si le message est une tentative d'abus (utilisation hors contexte)
 */
function checkForAbuse(string $message, ?string $context): array
{
    $messageLower = mb_strtolower($message);

    // Patterns de détection d'abus (utilisation comme ChatGPT généraliste)
    $abusePatterns = [
        // Programmation / Code
        'patterns_code' => [
            'écris.*code', 'write.*code', 'programme.*en', 'function.*php',
            'javascript', 'python', 'html.*css', 'sql.*query', 'debug',
            'compile', 'algorithm', 'regex', 'api.*rest', 'json.*parse',
            'class.*public', 'variable', 'boucle.*for', 'loop', 'array'
        ],
        // Rédaction / Création de contenu
        'patterns_redaction' => [
            'rédige.*article', 'écris.*texte', 'write.*essay', 'dissertation',
            'rédaction', 'compose.*lettre', 'écris.*mail', 'écris.*histoire',
            'poème', 'poem', 'story.*write', 'résume.*livre', 'résumé'
        ],
        // Devoirs / Exercices scolaires
        'patterns_devoirs' => [
            'exercice.*math', 'résous.*équation', 'calcule', 'théorème',
            'devoir.*maison', 'homework', 'dissertation.*philo', 'analyse.*texte',
            'commentaire.*composé', 'fiche.*lecture', 'exposé.*sur'
        ],
        // Traduction
        'patterns_traduction' => [
            'traduis', 'translate', 'traduction', 'en anglais', 'en espagnol',
            'in english', 'in french', 'traduire'
        ],
        // Questions générales hors contexte
        'patterns_general' => [
            'qui.*président', 'capitale.*de', 'recette.*cuisine', 'recipe',
            'météo', 'weather', 'horoscope', 'actualité', 'news.*today',
            'film.*regarder', 'série.*netflix', 'jeu.*vidéo', 'game'
        ],
        // Requêtes de contenu sensible
        'patterns_sensible' => [
            'pirater', 'hack', 'mot.*passe', 'password.*crack', 'virus',
            'malware', 'illegal', 'drogue', 'arme'
        ]
    ];

    // Messages de redirection selon le contexte (chargés depuis demo_chatbots)
    global $settingsDb;

    $defaultRedirect = "Je suis un assistant spécialisé pour ce site et je ne peux répondre qu'aux questions en rapport avec nos services. Comment puis-je vous aider concernant notre activité ?";

    $redirectMessage = $defaultRedirect;

    if ($context) {
        $chatbot = $settingsDb->fetchOne(
            "SELECT redirect_message FROM demo_chatbots WHERE slug = ? AND active = 1",
            [$context]
        );

        if ($chatbot && !empty($chatbot['redirect_message'])) {
            $redirectMessage = $chatbot['redirect_message'];
        }
    }

    // Vérifier chaque catégorie de patterns
    foreach ($abusePatterns as $category => $patterns) {
        foreach ($patterns as $pattern) {
            if (preg_match('/' . $pattern . '/iu', $messageLower)) {
                return [
                    'is_abuse' => true,
                    'category' => $category,
                    'redirect_message' => $redirectMessage
                ];
            }
        }
    }

    // Vérifier la longueur du message (les prompts d'abus sont souvent très longs)
    if (mb_strlen($message) > 500 && !$context) {
        return [
            'is_abuse' => true,
            'category' => 'long_message',
            'redirect_message' => $redirectMessage
        ];
    }

    return ['is_abuse' => false];
}

/**
 * Génère un identifiant unique pour l'utilisateur basé sur IP + fingerprint
 */
function getUserIdentifier(?string $fingerprint): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $data = $ip . ($fingerprint ?? '');
    return hash('sha256', $data);
}

/**
 * Récupère la limite quotidienne de messages
 */
function getDailyLimit(): int
{
    global $settingsManager;
    return (int)($settingsManager->get('demo_daily_limit') ?: 10);
}

/**
 * Récupère le nombre de messages restants pour un utilisateur
 */
function getRemainingMessages(string $identifier): int
{
    global $settingsDb;

    $limit = getDailyLimit();

    $usage = $settingsDb->fetchOne(
        "SELECT message_count FROM demo_usage WHERE identifier = ? AND date = CURDATE()",
        [$identifier]
    );

    $used = $usage ? (int)$usage['message_count'] : 0;
    return max(0, $limit - $used);
}

/**
 * Vérifie si l'utilisateur peut encore envoyer des messages
 */
function checkUsageLimit(string $identifier, string $context): array
{
    $remaining = getRemainingMessages($identifier);

    if ($remaining <= 0) {
        return [
            'allowed' => false,
            'message' => "⚠️ Vous avez atteint la limite de " . getDailyLimit() . " messages par jour pour cette démo.\n\nPour continuer à utiliser le chatbot sans limite, contactez-nous pour obtenir votre propre assistant personnalisé !\n\n📧 bruno@myziggi.fr\n📱 06 72 38 64 24"
        ];
    }

    return ['allowed' => true, 'remaining' => $remaining];
}

/**
 * Incrémente le compteur d'utilisation
 */
function incrementUsage(string $identifier): void
{
    global $settingsDb;

    // Utiliser INSERT ... ON DUPLICATE KEY UPDATE pour l'atomicité
    $settingsDb->query(
        "INSERT INTO demo_usage (identifier, message_count, date)
         VALUES (?, 1, CURDATE())
         ON DUPLICATE KEY UPDATE message_count = message_count + 1, updated_at = NOW()",
        [$identifier]
    );
}
