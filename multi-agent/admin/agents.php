<?php
/**
 * Liste et gestion des agents
 */

$pageTitle = 'Gestion des Agents';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../classes/AgentDistributor.php';

$db = Database::getInstance();
$pdo = $db->getConnection();
$distributor = new AgentDistributor();

// Messages flash
$success = $_GET['success'] ?? null;
$error = $_GET['error'] ?? null;

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $agentId = (int)($_POST['agent_id'] ?? 0);

    try {
        switch ($action) {
            case 'toggle_active':
                $stmt = $pdo->prepare("UPDATE agents SET active = NOT active WHERE id = ? AND client_id = ?");
                $stmt->execute([$agentId, $clientId]);
                header("Location: agents.php?client_id=$clientId&success=Agent mis à jour");
                exit;

            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM agents WHERE id = ? AND client_id = ?");
                $stmt->execute([$agentId, $clientId]);
                header("Location: agents.php?client_id=$clientId&success=Agent supprimé");
                exit;

            case 'update_order':
                $orders = json_decode($_POST['orders'] ?? '[]', true);
                foreach ($orders as $order) {
                    $stmt = $pdo->prepare("UPDATE agents SET sort_order = ? WHERE id = ? AND client_id = ?");
                    $stmt->execute([$order['order'], $order['id'], $clientId]);
                }
                echo json_encode(['success' => true]);
                exit;
        }
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
    }
}

// Récupérer les agents avec stats
$agents = $pdo->prepare("
    SELECT a.*,
           COUNT(DISTINCT ap.id) as total_appointments,
           COUNT(DISTINCT CASE WHEN ap.appointment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ap.id END) as appointments_30d
    FROM agents a
    LEFT JOIN appointments_v2 ap ON ap.agent_id = a.id
    WHERE a.client_id = ?
    GROUP BY a.id
    ORDER BY a.sort_order ASC, a.id ASC
");
$agents->execute([$clientId]);
$agents = $agents->fetchAll(PDO::FETCH_ASSOC);

// Stats globales
$totalAgents = count($agents);
$activeAgents = count(array_filter($agents, fn($a) => $a['active']));
$totalAppointments = array_sum(array_column($agents, 'appointments_30d'));
?>

<!-- Top Bar -->
<div class="top-bar">
    <h1 class="page-title">
        <i class="bi bi-people me-2"></i>Gestion des Agents
    </h1>
    <a href="agent-edit.php?client_id=<?= $clientId ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nouvel Agent
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats Cards -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value"><?= $totalAgents ?></div>
            <div class="stat-label">Agents Total</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value text-success"><?= $activeAgents ?></div>
            <div class="stat-label">Agents Actifs</div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card">
            <div class="stat-value text-info"><?= $totalAppointments ?></div>
            <div class="stat-label">RDV (30 jours)</div>
        </div>
    </div>
</div>

<!-- Agents List -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list me-2"></i>Liste des Agents</span>
        <small class="text-muted">Glissez-déposez pour réordonner</small>
    </div>
    <div class="card-body p-0">
        <?php if (empty($agents)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">Aucun agent configuré</p>
                <a href="agent-edit.php?client_id=<?= $clientId ?>" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Ajouter le premier agent
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50"></th>
                            <th>Agent</th>
                            <th>Email</th>
                            <th>Spécialités</th>
                            <th class="text-center">RDV (30j)</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="agents-list">
                        <?php foreach ($agents as $agent): ?>
                            <tr data-id="<?= $agent['id'] ?>" class="<?= !$agent['active'] ? 'table-secondary' : '' ?>">
                                <td class="text-center">
                                    <i class="bi bi-grip-vertical text-muted drag-handle" style="cursor: grab;"></i>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if ($agent['photo_url']): ?>
                                            <img src="<?= htmlspecialchars($agent['photo_url']) ?>" alt="" class="rounded-circle" style="width: 45px; height: 45px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="rounded-circle d-flex align-items-center justify-content-center text-white fw-bold" style="width: 45px; height: 45px; background: <?= htmlspecialchars($agent['color'] ?? '#3498db') ?>;">
                                                <?= strtoupper(substr($agent['name'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <strong><?= htmlspecialchars($agent['name']) ?></strong>
                                            <?php if ($agent['phone']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($agent['phone']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <a href="mailto:<?= htmlspecialchars($agent['email']) ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($agent['email']) ?>
                                    </a>
                                    <?php if ($agent['google_calendar_id']): ?>
                                        <br><small class="text-success"><i class="bi bi-calendar-check"></i> Calendar configuré</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $specialties = json_decode($agent['specialties'] ?? '[]', true) ?: [];
                                    foreach (array_slice($specialties, 0, 3) as $spec):
                                    ?>
                                        <span class="specialty-badge me-1"><?= htmlspecialchars(ucfirst($spec)) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($specialties) > 3): ?>
                                        <span class="text-muted">+<?= count($specialties) - 3 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= $agent['appointments_30d'] ?></span>
                                </td>
                                <td class="text-center">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_active">
                                        <input type="hidden" name="agent_id" value="<?= $agent['id'] ?>">
                                        <button type="submit" class="btn btn-sm <?= $agent['active'] ? 'btn-success' : 'btn-secondary' ?>">
                                            <?= $agent['active'] ? 'Actif' : 'Inactif' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <a href="agent-edit.php?client_id=<?= $clientId ?>&id=<?= $agent['id'] ?>" class="btn btn-outline-primary" title="Modifier">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="agent-schedule.php?client_id=<?= $clientId ?>&agent_id=<?= $agent['id'] ?>" class="btn btn-outline-info" title="Planning">
                                            <i class="bi bi-calendar3"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-danger" title="Supprimer" onclick="confirmDelete(<?= $agent['id'] ?>, '<?= htmlspecialchars(addslashes($agent['name'])) ?>')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer l'agent <strong id="deleteAgentName"></strong> ?</p>
                <p class="text-danger small">Cette action est irréversible. Tous les RDV associés seront conservés mais l'agent sera déréférencé.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="agent_id" id="deleteAgentId">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
// Confirmation de suppression
function confirmDelete(agentId, agentName) {
    document.getElementById('deleteAgentId').value = agentId;
    document.getElementById('deleteAgentName').textContent = agentName;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Drag & Drop pour réordonner
document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('agents-list');
    if (list) {
        new Sortable(list, {
            handle: '.drag-handle',
            animation: 150,
            onEnd: function() {
                const orders = [];
                list.querySelectorAll('tr').forEach((row, index) => {
                    orders.push({
                        id: parseInt(row.dataset.id),
                        order: index
                    });
                });

                fetch('agents.php?client_id=<?= $clientId ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=update_order&orders=' + encodeURIComponent(JSON.stringify(orders))
                });
            }
        });
    }
});
</script>
JS;

require_once __DIR__ . '/includes/footer.php';
?>
