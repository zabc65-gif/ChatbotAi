<?php
/**
 * Script de diagnostic - À supprimer après utilisation
 */

$secret = 'debug_chatbot_2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Accès non autorisé');
}

require_once __DIR__ . '/config.php';

echo "<pre>";
echo "=== DIAGNOSTIC CHATBOT ===\n\n";

// Test connexion BDD
echo "1. Test connexion base de données...\n";
try {
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', DB_HOST, DB_NAME, DB_CHARSET);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "   ✓ Connexion OK\n\n";
} catch (PDOException $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n\n";
    exit;
}

// Vérifier les tables
echo "2. Vérification des tables...\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $table) {
    echo "   - $table\n";
}
echo "\n";

// Structure des tables
echo "3. Structure des tables...\n";
foreach (['conversations', 'chatbot_stats', 'rate_limits'] as $table) {
    echo "\n   Table: $table\n";
    try {
        $cols = $pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($cols as $col) {
            echo "     - {$col['Field']} ({$col['Type']})\n";
        }
    } catch (Exception $e) {
        echo "     ✗ Erreur: " . $e->getMessage() . "\n";
    }
}

// Test insertion conversation
echo "\n4. Test insertion conversation...\n";
try {
    $stmt = $pdo->prepare("INSERT INTO conversations (session_id, role, content) VALUES (?, ?, ?)");
    $stmt->execute(['test_debug_' . time(), 'user', 'Test message']);
    echo "   ✓ Insertion OK\n";
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
}

// Test rate_limits
echo "\n5. Test rate_limits...\n";
try {
    // Vérifier si la colonne ip_address a le bon type
    $stmt = $pdo->prepare("INSERT INTO rate_limits (ip_address, request_count) VALUES (?, 1)
                           ON DUPLICATE KEY UPDATE request_count = request_count + 1");
    $stmt->execute(['127.0.0.1']);
    echo "   ✓ Rate limit OK\n";
} catch (Exception $e) {
    echo "   ✗ Erreur: " . $e->getMessage() . "\n";
}

// Test API Groq
echo "\n6. Test API Groq...\n";
echo "   Clé API: " . substr(GROQ_API_KEY, 0, 10) . "...\n";
$ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . GROQ_API_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode([
        'model' => GROQ_MODEL,
        'messages' => [['role' => 'user', 'content' => 'Dis simplement "OK"']],
        'max_tokens' => 10
    ]),
    CURLOPT_TIMEOUT => 30,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "   HTTP Code: $httpCode\n";
if ($httpCode === 200) {
    echo "   ✓ API Groq fonctionnelle\n";
    $data = json_decode($response, true);
    echo "   Réponse: " . ($data['choices'][0]['message']['content'] ?? 'N/A') . "\n";
} else {
    echo "   ✗ Erreur: $response\n";
}

echo "\n=== FIN DIAGNOSTIC ===\n";
echo "</pre>";
