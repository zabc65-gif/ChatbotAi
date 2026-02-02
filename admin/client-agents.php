<?php
/**
 * Gestion des agents d'un client - Interface d'administration
 * Permet de créer, modifier et gérer les agents/commerciaux d'un client
 */

$pageTitle = 'Agents du Client';
require_once 'includes/header.php';
require_once __DIR__ . '/../multi-agent/classes/AgentDistributor.php';
require_once __DIR__ . '/../multi-agent/classes/AgentScheduleManager.php';

$success = '';
$error = '';
$editAgent = null;

// Récupérer le client
$clientId = (int)($_GET['id'] ?? 0);
if (!$clientId) {
    header('Location: clients.php');
    exit;
}

$client = $db->fetchOne("SELECT c.*, cb.multi_agent_enabled FROM clients c LEFT JOIN client_chatbots cb ON cb.client_id = c.id WHERE c.id = ?", [$clientId]);
if (!$client) {
    header('Location: clients.php');
    exit;
}

$distributor = new AgentDistributor();
$scheduleManager = new AgentScheduleManager();

// Vérifier si les tables existent
try {
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'agents'");
    if (!$tableExists) {
        $error = "Le système multi-agent n'est pas installé. <a href='setup-multi-agent.php?key=setup_multiagent_2024' style='color: #3b82f6;'>Cliquez ici pour l'installer</a>.";
    }
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données.";
}

// Récupérer ou créer la config multi-agent
$config = $distributor->getClientConfig($clientId);
if (!$config && empty($error)) {
    $distributor->initClientConfig($clientId);
    $config = $distributor->getClientConfig($clientId);
}

// Spécialités disponibles
$availableSpecialties = $config['available_specialties'] ?? ['vente', 'location', 'estimation', 'gestion', 'conseil'];
if (is_string($availableSpecialties)) {
    $availableSpecialties = json_decode($availableSpecialties, true) ?: ['vente', 'location', 'estimation', 'gestion', 'conseil'];
}

// Couleurs disponibles pour les agents
$agentColors = ['#3498db', '#e74c3c', '#2ecc71', '#9b59b6', '#f39c12', '#1abc9c', '#34495e', '#e91e63'];

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    CSRF::verify();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add':
            case 'edit':
                $agentId = $_POST['agent_id'] ?? null;
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $bio = trim($_POST['bio'] ?? '');
                $googleCalendarId = trim($_POST['google_calendar_id'] ?? '');
                $specialties = $_POST['specialties'] ?? [];
                $color = $_POST['color'] ?? '#3498db';
                $active = isset($_POST['active']) ? 1 : 0;

                if (empty($name) || empty($email)) {
                    throw new Exception('Le nom et l\'email sont obligatoires.');
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email invalide.');
                }

                // Gestion de la photo
                $photoUrl = '';
                if ($agentId) {
                    $existingAgent = $distributor->getAgentById($agentId, $clientId);
                    $photoUrl = $existingAgent['photo_url'] ?? '';
                }

                if (!empty($_FILES['photo']['tmp_name'])) {
                    $uploadDir = __DIR__ . '/../multi-agent/uploads/agents/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }

                    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                        $filename = 'agent_' . $clientId . '_' . time() . '_' . uniqid() . '.' . $ext;
                        if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                            $photoUrl = '/multi-agent/uploads/agents/' . $filename;
                        }
                    }
                }

                $pdo = $db->getPdo();

                if ($action === 'add') {
                    // Obtenir le prochain sort_order
                    $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM agents WHERE client_id = ?");
                    $stmt->execute([$clientId]);
                    $sortOrder = $stmt->fetchColumn();

                    $stmt = $pdo->prepare("
                        INSERT INTO agents (client_id, name, email, phone, bio, google_calendar_id, specialties, color, photo_url, active, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $clientId, $name, $email, $phone, $bio,
                        $googleCalendarId, json_encode($specialties),
                        $color, $photoUrl, $active, $sortOrder
                    ]);
                    $newAgentId = $pdo->lastInsertId();

                    // Initialiser les horaires par défaut
                    $scheduleManager->initDefaultSchedules($newAgentId);

                    $success = "Agent \"$name\" créé avec succès !";
                } else if ($agentId) {
                    $stmt = $pdo->prepare("
                        UPDATE agents SET
                            name = ?, email = ?, phone = ?, bio = ?,
                            google_calendar_id = ?, specialties = ?,
                            color = ?, photo_url = ?, active = ?
                        WHERE id = ? AND client_id = ?
                    ");
                    $stmt->execute([
                        $name, $email, $phone, $bio,
                        $googleCalendarId, json_encode($specialties),
                        $color, $photoUrl, $active,
                        $agentId, $clientId
                    ]);
                    $success = "Agent \"$name\" mis à jour !";
                }
                break;

            case 'delete':
                $agentId = $_POST['agent_id'] ?? null;
                if ($agentId) {
                    $agent = $distributor->getAgentById($agentId, $clientId);
                    $pdo = $db->getPdo();
                    $stmt = $pdo->prepare("DELETE FROM agents WHERE id = ? AND client_id = ?");
                    $stmt->execute([$agentId, $clientId]);
                    $success = "Agent \"" . ($agent['name'] ?? '') . "\" supprimé !";
                }
                break;

            case 'toggle':
                $agentId = $_POST['agent_id'] ?? null;
                if ($agentId) {
                    $pdo = $db->getPdo();
                    $stmt = $pdo->prepare("UPDATE agents SET active = NOT active WHERE id = ? AND client_id = ?");
                    $stmt->execute([$agentId, $clientId]);
                    $success = "Statut mis à jour !";
                }
                break;

            case 'update_config':
                $distributionMode = $_POST['distribution_mode'] ?? 'round_robin';
                $allowVisitorChoice = isset($_POST['allow_visitor_choice']) ? 1 : 0;
                $showAgentPhotos = isset($_POST['show_agent_photos']) ? 1 : 0;
                $showAgentBios = isset($_POST['show_agent_bios']) ? 1 : 0;

                $pdo = $db->getPdo();
                $stmt = $pdo->prepare("
                    UPDATE client_multi_agent_config SET
                        distribution_mode = ?,
                        allow_visitor_choice = ?,
                        show_agent_photos = ?,
                        show_agent_bios = ?
                    WHERE client_id = ?
                ");
                $stmt->execute([$distributionMode, $allowVisitorChoice, $showAgentPhotos, $showAgentBios, $clientId]);
                $config = $distributor->getClientConfig($clientId);
                $success = "Configuration mise à jour !";
                break;

            case 'update_order':
                $orders = json_decode($_POST['orders'] ?? '[]', true);
                $pdo = $db->getPdo();
                foreach ($orders as $order) {
                    $stmt = $pdo->prepare("UPDATE agents SET sort_order = ? WHERE id = ? AND client_id = ?");
                    $stmt->execute([$order['order'], $order['id'], $clientId]);
                }
                header('Content-Type: application/json');
                echo json_encode(['success' => true]);
                exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer l'agent à éditer
if (isset($_GET['edit_agent']) && empty($error)) {
    $editAgent = $distributor->getAgentById((int)$_GET['edit_agent'], $clientId);
    if ($editAgent) {
        $editAgent['specialties'] = json_decode($editAgent['specialties'] ?? '[]', true) ?: [];
    }
}

// Récupérer tous les agents avec stats
$agents = [];
if (empty($error) || strpos($error, 'installé') === false) {
    try {
        $pdo = $db->getPdo();
        $stmt = $pdo->prepare("
            SELECT a.*,
                   COUNT(DISTINCT ap.id) as total_appointments,
                   COUNT(DISTINCT CASE WHEN ap.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ap.id END) as appointments_30d
            FROM agents a
            LEFT JOIN appointments_v2 ap ON ap.agent_id = a.id
            WHERE a.client_id = ?
            GROUP BY a.id
            ORDER BY a.sort_order ASC, a.id ASC
        ");
        $stmt->execute([$clientId]);
        $agents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Tables might not exist yet
    }
}

// Modes de distribution
$distributionModes = [
    'round_robin' => ['label' => 'Tour à tour', 'desc' => 'Distribue équitablement les RDV à chaque agent actif'],
    'availability' => ['label' => 'Par disponibilité', 'desc' => 'Vérifie Google Calendar et assigne au premier disponible'],
    'specialty' => ['label' => 'Par spécialité', 'desc' => 'Match le service demandé avec les compétences de l\'agent'],
    'visitor_choice' => ['label' => 'Choix du visiteur', 'desc' => 'Le visiteur choisit son agent dans une liste']
];
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <a href="clients.php" style="color: var(--text-light); text-decoration: none; font-size: 13px;">← Retour aux clients</a>
            <h1 class="page-title" style="margin-top: 8px;">Agents de <?= htmlspecialchars($client['name']) ?></h1>
            <p class="page-subtitle">Gérez les commerciaux et leurs agendas</p>
        </div>
        <a href="?id=<?= $clientId ?>&edit_agent=0#form-agent" class="btn btn-primary" onclick="resetAgentForm()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Nouvel Agent
        </a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<?php if (!$client['multi_agent_enabled']): ?>
    <div class="alert alert-warning" style="background: #fef3c7; color: #92400e; border-left: 4px solid #f59e0b;">
        <strong>⚠️ Mode Multi-Agent non activé</strong><br>
        Le mode multi-agent n'est pas activé pour ce client. <a href="clients.php?edit=<?= $clientId ?>#form-client" style="color: #92400e;">Activez-le dans les paramètres du client</a> pour que les agents soient pris en compte.
    </div>
<?php endif; ?>

<!-- Configuration du mode de distribution -->
<div class="card" style="margin-bottom: 24px;">
    <h2 class="card-title" style="font-size: 16px;">⚙️ Configuration de la distribution</h2>
    <form method="POST">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="action" value="update_config">

        <div class="grid-2" style="gap: 20px; margin-bottom: 16px;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Mode de distribution des RDV</label>
                <select name="distribution_mode" class="form-input">
                    <?php foreach ($distributionModes as $mode => $info): ?>
                        <option value="<?= $mode ?>" <?= ($config['distribution_mode'] ?? 'round_robin') === $mode ? 'selected' : '' ?>>
                            <?= $info['label'] ?> - <?= $info['desc'] ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="allow_visitor_choice" value="1" <?= ($config['allow_visitor_choice'] ?? false) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span>Permettre au visiteur de choisir son agent</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="show_agent_photos" value="1" <?= ($config['show_agent_photos'] ?? true) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span>Afficher les photos des agents</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="show_agent_bios" value="1" <?= ($config['show_agent_bios'] ?? true) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span>Afficher les bios des agents</span>
                </label>
            </div>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm">Enregistrer la configuration</button>
    </form>
</div>

<!-- Stats globales -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-value"><?= count($agents) ?></div>
        <div class="stat-label">Agents</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count(array_filter($agents, fn($a) => $a['active'])) ?></div>
        <div class="stat-label">Actifs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format(array_sum(array_column($agents, 'appointments_30d')) ?: 0) ?></div>
        <div class="stat-label">RDV (30j)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format(array_sum(array_column($agents, 'total_appointments')) ?: 0) ?></div>
        <div class="stat-label">RDV Total</div>
    </div>
</div>

<!-- Liste des agents -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 class="card-title" style="margin-bottom: 0; padding-bottom: 0; border: none;">Agents (<?= count($agents) ?>)</h2>
        <small style="color: var(--text-light);">Glissez pour réordonner (mode tour à tour)</small>
    </div>

    <?php if (empty($agents)): ?>
        <p style="color: var(--text-light); text-align: center; padding: 40px;">Aucun agent pour le moment. Créez votre premier agent !</p>
    <?php else: ?>
        <div class="agents-grid" id="agents-list">
            <?php foreach ($agents as $agent): ?>
                <div class="agent-card <?= $agent['active'] ? '' : 'inactive' ?>" data-id="<?= $agent['id'] ?>">
                    <div class="agent-header">
                        <span class="drag-handle" style="cursor: grab; color: var(--text-light);">⋮⋮</span>
                        <?php if ($agent['photo_url']): ?>
                            <img src="<?= htmlspecialchars($agent['photo_url']) ?>" alt="" class="agent-photo">
                        <?php else: ?>
                            <div class="agent-photo-placeholder" style="background: <?= htmlspecialchars($agent['color'] ?? '#3498db') ?>;">
                                <?= strtoupper(substr($agent['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        <div class="agent-info">
                            <div class="agent-name"><?= htmlspecialchars($agent['name']) ?></div>
                            <div class="agent-email"><?= htmlspecialchars($agent['email']) ?></div>
                            <?php if ($agent['phone']): ?>
                                <div class="agent-phone"><?= htmlspecialchars($agent['phone']) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="agent-status <?= $agent['active'] ? 'active' : '' ?>">
                            <?= $agent['active'] ? 'Actif' : 'Inactif' ?>
                        </span>
                    </div>

                    <div class="agent-specialties">
                        <?php
                        $specs = json_decode($agent['specialties'] ?? '[]', true) ?: [];
                        foreach (array_slice($specs, 0, 3) as $spec):
                        ?>
                            <span class="specialty-badge"><?= htmlspecialchars(ucfirst($spec)) ?></span>
                        <?php endforeach; ?>
                        <?php if (count($specs) > 3): ?>
                            <span style="color: var(--text-light); font-size: 12px;">+<?= count($specs) - 3 ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="agent-stats">
                        <div class="agent-stat">
                            <span class="stat-num"><?= $agent['appointments_30d'] ?: 0 ?></span>
                            <span class="stat-txt">RDV (30j)</span>
                        </div>
                        <div class="agent-stat">
                            <span class="stat-num"><?= $agent['total_appointments'] ?: 0 ?></span>
                            <span class="stat-txt">Total</span>
                        </div>
                        <?php if ($agent['google_calendar_id']): ?>
                            <div class="agent-stat">
                                <span class="stat-num" style="color: #10b981;">📅</span>
                                <span class="stat-txt">Calendar</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="agent-actions">
                        <a href="?id=<?= $clientId ?>&edit_agent=<?= $agent['id'] ?>#form-agent" class="btn btn-secondary btn-sm">Modifier</a>
                        <form method="POST" style="display: inline;">
                            <?= CSRF::inputField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm"><?= $agent['active'] ? 'Désactiver' : 'Activer' ?></button>
                        </form>
                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteAgent(<?= $agent['id'] ?>, '<?= htmlspecialchars(addslashes($agent['name'])) ?>')">Supprimer</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Formulaire agent -->
<div class="card" id="form-agent">
    <h2 class="card-title"><?= $editAgent ? 'Modifier : ' . htmlspecialchars($editAgent['name']) : 'Nouvel Agent' ?></h2>

    <form method="POST" enctype="multipart/form-data">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="action" value="<?= $editAgent ? 'edit' : 'add' ?>">
        <?php if ($editAgent): ?>
            <input type="hidden" name="agent_id" value="<?= $editAgent['id'] ?>">
        <?php endif; ?>

        <div class="grid-2" style="gap: 20px;">
            <div>
                <div class="form-group">
                    <label class="form-label">Nom complet *</label>
                    <input type="text" name="name" class="form-input" required
                           value="<?= htmlspecialchars($editAgent['name'] ?? '') ?>"
                           placeholder="Ex: Jean Dupont">
                </div>

                <div class="form-group">
                    <label class="form-label">Email (notifications) *</label>
                    <input type="email" name="email" class="form-input" required
                           value="<?= htmlspecialchars($editAgent['email'] ?? '') ?>"
                           placeholder="agent@exemple.com">
                </div>

                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="text" name="phone" class="form-input"
                           value="<?= htmlspecialchars($editAgent['phone'] ?? '') ?>"
                           placeholder="06 12 34 56 78">
                </div>

                <div class="form-group">
                    <label class="form-label">Google Calendar ID</label>
                    <input type="text" name="google_calendar_id" class="form-input"
                           value="<?= htmlspecialchars($editAgent['google_calendar_id'] ?? '') ?>"
                           placeholder="exemple@group.calendar.google.com">
                    <p class="form-hint">L'agenda de cet agent. Les RDV seront créés sur ce calendrier.</p>
                </div>
            </div>

            <div>
                <div class="form-group">
                    <label class="form-label">Photo</label>
                    <?php if ($editAgent && $editAgent['photo_url']): ?>
                        <div style="margin-bottom: 8px;">
                            <img src="<?= htmlspecialchars($editAgent['photo_url']) ?>" alt="" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="photo" class="form-input" accept="image/*">
                </div>

                <div class="form-group">
                    <label class="form-label">Couleur</label>
                    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                        <?php foreach ($agentColors as $color): ?>
                            <label style="cursor: pointer;">
                                <input type="radio" name="color" value="<?= $color ?>"
                                       <?= ($editAgent['color'] ?? '#3498db') === $color ? 'checked' : '' ?>
                                       style="display: none;">
                                <span style="display: inline-block; width: 32px; height: 32px; border-radius: 50%; background: <?= $color ?>; border: 3px solid <?= ($editAgent['color'] ?? '#3498db') === $color ? '#333' : 'transparent' ?>;"></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Bio / Description</label>
                    <textarea name="bio" class="form-textarea" rows="3" placeholder="Courte présentation..."><?= htmlspecialchars($editAgent['bio'] ?? '') ?></textarea>
                </div>

                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="active" value="1" <?= ($editAgent['active'] ?? true) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span>Agent actif</span>
                </label>
            </div>
        </div>

        <div class="form-group" style="margin-top: 16px;">
            <label class="form-label">Spécialités</label>
            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <?php foreach ($availableSpecialties as $spec): ?>
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; padding: 8px 12px; background: #f1f5f9; border-radius: 20px;">
                        <input type="checkbox" name="specialties[]" value="<?= htmlspecialchars($spec) ?>"
                               <?= in_array($spec, $editAgent['specialties'] ?? []) ? 'checked' : '' ?>
                               style="width: 16px; height: 16px;">
                        <span><?= htmlspecialchars(ucfirst($spec)) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="display: flex; gap: 12px; margin-top: 24px;">
            <button type="submit" class="btn btn-primary">
                <?= $editAgent ? 'Mettre à jour' : 'Créer l\'agent' ?>
            </button>
            <a href="?id=<?= $clientId ?>" class="btn btn-secondary">Annuler</a>
        </div>
    </form>
</div>

<!-- Formulaire de suppression -->
<form id="delete-agent-form" method="POST" style="display: none;">
    <?= CSRF::inputField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="agent_id" id="delete-agent-id">
</form>

<style>
.agents-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
.agent-card { background: #f8fafc; border-radius: 12px; padding: 16px; border-left: 4px solid var(--primary); }
.agent-card.inactive { opacity: 0.6; border-left-color: #94a3b8; }
.agent-header { display: flex; gap: 12px; align-items: center; margin-bottom: 12px; }
.agent-photo { width: 50px; height: 50px; border-radius: 50%; object-fit: cover; }
.agent-photo-placeholder { width: 50px; height: 50px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: 600; }
.agent-info { flex: 1; }
.agent-name { font-weight: 600; font-size: 15px; }
.agent-email, .agent-phone { font-size: 12px; color: var(--text-light); }
.agent-status { font-size: 11px; padding: 4px 10px; border-radius: 12px; background: #fee2e2; color: #991b1b; }
.agent-status.active { background: #dcfce7; color: #166534; }
.agent-specialties { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 12px; }
.specialty-badge { font-size: 11px; padding: 3px 8px; border-radius: 12px; background: #e0f2fe; color: #0369a1; }
.agent-stats { display: flex; gap: 16px; margin-bottom: 12px; padding: 8px; background: white; border-radius: 8px; }
.agent-stat { text-align: center; flex: 1; }
.agent-stat .stat-num { display: block; font-size: 16px; font-weight: 700; color: var(--primary); }
.agent-stat .stat-txt { font-size: 10px; color: var(--text-light); }
.agent-actions { display: flex; gap: 8px; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-danger { background: #fee2e2; color: #991b1b; }
.btn-danger:hover { background: #fecaca; }
.drag-handle { padding: 4px; }
.agent-card.sortable-ghost { opacity: 0.5; background: #e0f2fe; }
.alert-warning { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
function deleteAgent(agentId, agentName) {
    if (confirm('Supprimer l\'agent "' + agentName + '" ? Cette action est irréversible.')) {
        document.getElementById('delete-agent-id').value = agentId;
        document.getElementById('delete-agent-form').submit();
    }
}

function resetAgentForm() {
    // Le formulaire sera réinitialisé par le rechargement de la page
}

// Drag & Drop pour réordonner
document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('agents-list');
    if (list) {
        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                const orders = [];
                list.querySelectorAll('.agent-card').forEach((card, index) => {
                    orders.push({
                        id: parseInt(card.dataset.id),
                        order: index
                    });
                });

                fetch('?id=<?= $clientId ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: '<?= CSRF::inputField() ?>&action=update_order&orders=' + encodeURIComponent(JSON.stringify(orders))
                });
            }
        });
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>
