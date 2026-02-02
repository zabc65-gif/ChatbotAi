<?php
/**
 * Liste des rendez-vous multi-agent
 */

$pageTitle = 'Rendez-vous';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../classes/AgentDistributor.php';

$db = Database::getInstance();
$pdo = $db->getConnection();
$distributor = new AgentDistributor();

// Filtres
$filterStatus = $_GET['status'] ?? '';
$filterAgent = (int)($_GET['agent'] ?? 0);
$filterDate = $_GET['date'] ?? '';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $appointmentId = (int)($_POST['appointment_id'] ?? 0);

    switch ($action) {
        case 'update_status':
            $newStatus = $_POST['status'] ?? '';
            if (in_array($newStatus, ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'])) {
                $stmt = $pdo->prepare("UPDATE appointments_v2 SET status = ? WHERE id = ? AND client_id = ?");
                $stmt->execute([$newStatus, $appointmentId, $clientId]);
            }
            header("Location: appointments.php?client_id=$clientId&success=Statut mis à jour");
            exit;

        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM appointments_v2 WHERE id = ? AND client_id = ?");
            $stmt->execute([$appointmentId, $clientId]);
            header("Location: appointments.php?client_id=$clientId&success=Rendez-vous supprimé");
            exit;
    }
}

// Récupérer les agents pour le filtre
$agents = $distributor->getAgentsByClient($clientId, false);

// Construire la requête
$query = "
    SELECT ap.*, a.name as agent_name, a.email as agent_email, a.photo_url as agent_photo, a.color as agent_color
    FROM appointments_v2 ap
    LEFT JOIN agents a ON a.id = ap.agent_id
    WHERE ap.client_id = ?
";
$params = [$clientId];

if ($filterStatus) {
    $query .= " AND ap.status = ?";
    $params[] = $filterStatus;
}

if ($filterAgent) {
    $query .= " AND ap.agent_id = ?";
    $params[] = $filterAgent;
}

if ($filterDate) {
    $query .= " AND ap.appointment_date = ?";
    $params[] = $filterDate;
}

$query .= " ORDER BY ap.appointment_date DESC, ap.appointment_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$stats = $pdo->prepare("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
        COUNT(CASE WHEN appointment_date = CURDATE() THEN 1 END) as today
    FROM appointments_v2
    WHERE client_id = ?
");
$stats->execute([$clientId]);
$stats = $stats->fetch(PDO::FETCH_ASSOC);

$success = $_GET['success'] ?? null;
?>

<!-- Top Bar -->
<div class="top-bar">
    <h1 class="page-title">
        <i class="bi bi-calendar-check me-2"></i>Rendez-vous
    </h1>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-4 mb-4">
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-value"><?= $stats['total'] ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-value text-success"><?= $stats['confirmed'] ?></div>
            <div class="stat-label">Confirmés</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-value text-warning"><?= $stats['pending'] ?></div>
            <div class="stat-label">En attente</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="stat-card">
            <div class="stat-value text-info"><?= $stats['today'] ?></div>
            <div class="stat-label">Aujourd'hui</div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <input type="hidden" name="client_id" value="<?= $clientId ?>">

            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select name="status" class="form-select">
                    <option value="">Tous les statuts</option>
                    <option value="confirmed" <?= $filterStatus === 'confirmed' ? 'selected' : '' ?>>Confirmé</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>En attente</option>
                    <option value="cancelled" <?= $filterStatus === 'cancelled' ? 'selected' : '' ?>>Annulé</option>
                    <option value="completed" <?= $filterStatus === 'completed' ? 'selected' : '' ?>>Terminé</option>
                    <option value="no_show" <?= $filterStatus === 'no_show' ? 'selected' : '' ?>>No show</option>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Agent</label>
                <select name="agent" class="form-select">
                    <option value="">Tous les agents</option>
                    <?php foreach ($agents as $agent): ?>
                        <option value="<?= $agent['id'] ?>" <?= $filterAgent == $agent['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($agent['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?= $filterDate ?>">
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-search me-1"></i>Filtrer
                </button>
                <a href="appointments.php?client_id=<?= $clientId ?>" class="btn btn-outline-secondary">
                    Réinitialiser
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Liste des RDV -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($appointments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                <p class="mt-3 text-muted">Aucun rendez-vous trouvé</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Date / Heure</th>
                            <th>Visiteur</th>
                            <th>Agent</th>
                            <th>Service</th>
                            <th class="text-center">Statut</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $apt): ?>
                            <tr>
                                <td>
                                    <strong><?= date('d/m/Y', strtotime($apt['appointment_date'])) ?></strong>
                                    <br>
                                    <span class="text-muted"><?= date('H:i', strtotime($apt['appointment_time'])) ?></span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($apt['visitor_name']) ?></strong>
                                    <?php if ($apt['visitor_email']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($apt['visitor_email']) ?></small>
                                    <?php endif; ?>
                                    <?php if ($apt['visitor_phone']): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($apt['visitor_phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($apt['agent_name']): ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <?php if ($apt['agent_photo']): ?>
                                                <img src="<?= htmlspecialchars($apt['agent_photo']) ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover;">
                                            <?php else: ?>
                                                <div class="rounded-circle d-flex align-items-center justify-content-center text-white" style="width: 32px; height: 32px; background: <?= htmlspecialchars($apt['agent_color'] ?? '#3498db') ?>; font-size: 12px;">
                                                    <?= strtoupper(substr($apt['agent_name'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($apt['agent_name']) ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Non assigné</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($apt['service'] ?? '-') ?></td>
                                <td class="text-center">
                                    <?php
                                    $statusColors = [
                                        'confirmed' => 'success',
                                        'pending' => 'warning',
                                        'cancelled' => 'danger',
                                        'completed' => 'secondary',
                                        'no_show' => 'dark'
                                    ];
                                    $statusLabels = [
                                        'confirmed' => 'Confirmé',
                                        'pending' => 'En attente',
                                        'cancelled' => 'Annulé',
                                        'completed' => 'Terminé',
                                        'no_show' => 'No show'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $statusColors[$apt['status']] ?? 'secondary' ?>">
                                        <?= $statusLabels[$apt['status']] ?? $apt['status'] ?>
                                    </span>
                                    <?php if ($apt['google_event_id']): ?>
                                        <br><small class="text-success"><i class="bi bi-calendar-check"></i> Synchro</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><h6 class="dropdown-header">Changer le statut</h6></li>
                                            <li>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                    <input type="hidden" name="status" value="confirmed">
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-check-circle text-success me-2"></i>Confirmer</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-check2-all text-secondary me-2"></i>Terminer</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                    <input type="hidden" name="status" value="cancelled">
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-x-circle text-danger me-2"></i>Annuler</button>
                                                </form>
                                            </li>
                                            <li>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="update_status">
                                                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                                                    <input type="hidden" name="status" value="no_show">
                                                    <button type="submit" class="dropdown-item"><i class="bi bi-person-x text-dark me-2"></i>No show</button>
                                                </form>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <button type="button" class="dropdown-item text-danger" onclick="confirmDelete(<?= $apt['id'] ?>)">
                                                    <i class="bi bi-trash me-2"></i>Supprimer
                                                </button>
                                            </li>
                                        </ul>
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
                <p>Êtes-vous sûr de vouloir supprimer ce rendez-vous ?</p>
                <p class="text-danger small">Cette action est irréversible.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="appointment_id" id="deleteAppointmentId">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
function confirmDelete(appointmentId) {
    document.getElementById('deleteAppointmentId').value = appointmentId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>
JS;

require_once __DIR__ . '/includes/footer.php';
?>
