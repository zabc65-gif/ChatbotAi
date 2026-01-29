<?php
/**
 * Gestion des rendez-vous
 */

$pageTitle = 'Rendez-vous';
require_once 'includes/header.php';

$success = '';
$error = '';

// Vérifier si la table existe
$tableExists = false;
try {
    $check = $db->fetchOne("SHOW TABLES LIKE 'appointments'");
    $tableExists = !empty($check);
} catch (Exception $e) {
    // Table n'existe pas
}

if (!$tableExists) {
    $error = "Le système de rendez-vous n'est pas installé. <a href='setup-booking.php?key=setup_booking_2024' style='color: #3b82f6;'>Cliquez ici pour l'installer</a>.";
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tableExists) {
    CSRF::verify();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'cancel':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $db->query("UPDATE appointments SET status = 'cancelled' WHERE id = ?", [$id]);
                    $success = "Rendez-vous annulé.";
                }
                break;

            case 'confirm':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $db->query("UPDATE appointments SET status = 'confirmed' WHERE id = ?", [$id]);
                    $success = "Rendez-vous confirmé.";
                }
                break;

            case 'delete':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $db->query("DELETE FROM appointments WHERE id = ?", [$id]);
                    $success = "Rendez-vous supprimé.";
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Filtres
$filterType = $_GET['type'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterDate = $_GET['date'] ?? '';

// Récupérer les rendez-vous
$appointments = [];
$stats = ['total' => 0, 'upcoming' => 0, 'today' => 0];

if ($tableExists) {
    try {
        $where = [];
        $params = [];

        if ($filterType === 'demo') {
            $where[] = "a.chatbot_type = 'demo'";
        } elseif ($filterType === 'client') {
            $where[] = "a.chatbot_type = 'client'";
        }

        if ($filterStatus === 'confirmed') {
            $where[] = "a.status = 'confirmed'";
        } elseif ($filterStatus === 'cancelled') {
            $where[] = "a.status = 'cancelled'";
        }

        if ($filterDate === 'today') {
            $where[] = "a.appointment_date = CURDATE()";
        } elseif ($filterDate === 'upcoming') {
            $where[] = "a.appointment_date >= CURDATE()";
        } elseif ($filterDate === 'past') {
            $where[] = "a.appointment_date < CURDATE()";
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $appointments = $db->fetchAll(
            "SELECT a.*,
                    CASE
                        WHEN a.chatbot_type = 'demo' THEN (SELECT name FROM demo_chatbots WHERE id = a.chatbot_id)
                        WHEN a.chatbot_type = 'client' THEN (SELECT name FROM clients WHERE id = a.client_id)
                        ELSE 'Inconnu'
                    END as chatbot_name
             FROM appointments a
             {$whereClause}
             ORDER BY a.appointment_date DESC, a.appointment_time DESC",
            $params
        );

        // Stats
        $stats['total'] = count($appointments);
        $today = date('Y-m-d');
        foreach ($appointments as $apt) {
            if ($apt['appointment_date'] >= $today && $apt['status'] !== 'cancelled') {
                $stats['upcoming']++;
            }
            if ($apt['appointment_date'] === $today && $apt['status'] !== 'cancelled') {
                $stats['today']++;
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fonction de formatage date français
function formatDateFr(string $date): string {
    $jours = ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'];
    $mois = ['', 'jan', 'fév', 'mar', 'avr', 'mai', 'juin', 'juil', 'août', 'sep', 'oct', 'nov', 'déc'];
    $ts = strtotime($date);
    return $jours[(int)date('w', $ts)] . ' ' . (int)date('d', $ts) . ' ' . $mois[(int)date('m', $ts)] . ' ' . date('Y', $ts);
}

function formatTimeFr(string $time): string {
    $parts = explode(':', $time);
    return $parts[0] . 'h' . $parts[1];
}
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 class="page-title">Rendez-vous</h1>
            <p class="page-subtitle">Tous les rendez-vous pris via les chatbots</p>
        </div>
        <a href="test-calendar.php" class="btn btn-secondary">Diagnostic Google Calendar</a>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<?php if ($tableExists): ?>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-value"><?= $stats['total'] ?></div>
        <div class="stat-label">Total RDV</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: #059669;"><?= $stats['today'] ?></div>
        <div class="stat-label">Aujourd'hui</div>
    </div>
    <div class="stat-card">
        <div class="stat-value" style="color: #3b82f6;"><?= $stats['upcoming'] ?></div>
        <div class="stat-label">A venir</div>
    </div>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
        <span style="font-weight: 500; color: var(--text-light);">Filtrer :</span>
        <a href="appointments.php" class="btn btn-sm <?= empty($filterDate) && empty($filterType) && empty($filterStatus) ? 'btn-primary' : 'btn-secondary' ?>">Tous</a>
        <a href="?date=today" class="btn btn-sm <?= $filterDate === 'today' ? 'btn-primary' : 'btn-secondary' ?>">Aujourd'hui</a>
        <a href="?date=upcoming" class="btn btn-sm <?= $filterDate === 'upcoming' ? 'btn-primary' : 'btn-secondary' ?>">A venir</a>
        <a href="?date=past" class="btn btn-sm <?= $filterDate === 'past' ? 'btn-primary' : 'btn-secondary' ?>">Passés</a>
        <span style="color: #e2e8f0;">|</span>
        <a href="?type=demo" class="btn btn-sm <?= $filterType === 'demo' ? 'btn-primary' : 'btn-secondary' ?>">Démo</a>
        <a href="?type=client" class="btn btn-sm <?= $filterType === 'client' ? 'btn-primary' : 'btn-secondary' ?>">Clients</a>
        <span style="color: #e2e8f0;">|</span>
        <a href="?status=confirmed" class="btn btn-sm <?= $filterStatus === 'confirmed' ? 'btn-primary' : 'btn-secondary' ?>">Confirmés</a>
        <a href="?status=cancelled" class="btn btn-sm <?= $filterStatus === 'cancelled' ? 'btn-primary' : 'btn-secondary' ?>">Annulés</a>
    </div>
</div>

<!-- Liste des RDV -->
<div class="card">
    <h2 class="card-title">Rendez-vous (<?= count($appointments) ?>)</h2>

    <?php if (empty($appointments)): ?>
        <p style="color: var(--text-light); text-align: center; padding: 40px;">Aucun rendez-vous trouvé.</p>
    <?php else: ?>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Date & Heure</th>
                        <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Visiteur</th>
                        <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Service</th>
                        <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Chatbot</th>
                        <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Statut</th>
                        <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $apt): ?>
                        <?php
                        $isPast = $apt['appointment_date'] < date('Y-m-d');
                        $isToday = $apt['appointment_date'] === date('Y-m-d');
                        $rowStyle = $apt['status'] === 'cancelled' ? 'opacity: 0.5;' : ($isToday ? 'background: #f0fdf4;' : '');
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9; <?= $rowStyle ?>">
                            <td style="padding: 12px 8px;">
                                <div style="font-weight: 600; color: var(--text);"><?= formatDateFr($apt['appointment_date']) ?></div>
                                <div style="font-size: 14px; color: var(--primary); font-weight: 500;"><?= formatTimeFr($apt['appointment_time']) ?></div>
                                <?php if ($isToday): ?>
                                    <span style="font-size: 10px; background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 4px;">Aujourd'hui</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 8px;">
                                <div style="font-weight: 500;"><?= htmlspecialchars($apt['visitor_name']) ?></div>
                                <?php if ($apt['visitor_email']): ?>
                                    <div style="font-size: 12px; color: var(--text-light);"><?= htmlspecialchars($apt['visitor_email']) ?></div>
                                <?php endif; ?>
                                <?php if ($apt['visitor_phone']): ?>
                                    <div style="font-size: 12px; color: var(--text-light);"><?= htmlspecialchars($apt['visitor_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 8px; font-size: 13px; color: var(--text);">
                                <?= htmlspecialchars($apt['service'] ?: '-') ?>
                            </td>
                            <td style="padding: 12px 8px;">
                                <span style="font-size: 12px; padding: 3px 8px; border-radius: 6px; background: <?= $apt['chatbot_type'] === 'client' ? '#eff6ff' : '#fef3c7' ?>; color: <?= $apt['chatbot_type'] === 'client' ? '#1d4ed8' : '#92400e' ?>;">
                                    <?= htmlspecialchars($apt['chatbot_name'] ?: $apt['chatbot_type']) ?>
                                </span>
                                <?php if ($apt['google_event_id']): ?>
                                    <span title="Synchronisé Google Calendar" style="font-size: 10px;">📅</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 8px;">
                                <?php if ($apt['status'] === 'confirmed'): ?>
                                    <span style="font-size: 11px; padding: 4px 8px; border-radius: 12px; background: #dcfce7; color: #166534;">Confirmé</span>
                                <?php elseif ($apt['status'] === 'cancelled'): ?>
                                    <span style="font-size: 11px; padding: 4px 8px; border-radius: 12px; background: #fee2e2; color: #991b1b;">Annulé</span>
                                <?php else: ?>
                                    <span style="font-size: 11px; padding: 4px 8px; border-radius: 12px; background: #fef3c7; color: #92400e;">En attente</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 12px 8px;">
                                <div style="display: flex; gap: 4px;">
                                    <?php if ($apt['status'] === 'confirmed'): ?>
                                        <form method="POST" style="display: inline;">
                                            <?= CSRF::inputField() ?>
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="id" value="<?= $apt['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm" onclick="return confirm('Annuler ce RDV ?')">Annuler</button>
                                        </form>
                                    <?php elseif ($apt['status'] === 'cancelled'): ?>
                                        <form method="POST" style="display: inline;">
                                            <?= CSRF::inputField() ?>
                                            <input type="hidden" name="action" value="confirm">
                                            <input type="hidden" name="id" value="<?= $apt['id'] ?>">
                                            <button type="submit" class="btn btn-secondary btn-sm">Réactiver</button>
                                        </form>
                                    <?php endif; ?>
                                    <form method="POST" style="display: inline;">
                                        <?= CSRF::inputField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $apt['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Supprimer définitivement ?')">Suppr.</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<style>
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-danger { background: #fee2e2; color: #991b1b; }
.btn-danger:hover { background: #fecaca; }
</style>

<?php require_once 'includes/footer.php'; ?>
