<?php
/**
 * Planning des agents avec FullCalendar
 */

$pageTitle = 'Planning des Agents';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../classes/AgentDistributor.php';
require_once __DIR__ . '/../classes/AgentScheduleManager.php';

$db = Database::getInstance();
$pdo = $db->getConnection();
$distributor = new AgentDistributor();
$scheduleManager = new AgentScheduleManager();

// Agent sélectionné (0 = vue globale tous les agents)
$selectedAgentId = (int)($_GET['agent_id'] ?? 0);

// Récupérer tous les agents pour le filtre
$agents = $distributor->getAgentsByClient($clientId, false);

// Si un agent est sélectionné, vérifier qu'il existe
$selectedAgent = null;
if ($selectedAgentId > 0) {
    foreach ($agents as $a) {
        if ($a['id'] == $selectedAgentId) {
            $selectedAgent = $a;
            break;
        }
    }
}

// API pour récupérer les événements (appelé par FullCalendar)
if (isset($_GET['action']) && $_GET['action'] === 'get_events') {
    header('Content-Type: application/json');

    $start = $_GET['start'] ?? date('Y-m-d');
    $end = $_GET['end'] ?? date('Y-m-d', strtotime('+30 days'));

    if ($selectedAgentId > 0) {
        $events = $scheduleManager->getCalendarEvents($selectedAgentId, $start, $end);
    } else {
        $events = $scheduleManager->getAllAgentsCalendarEvents($clientId, $start, $end);
    }

    echo json_encode($events);
    exit;
}

// API pour sauvegarder les horaires
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'save_schedule':
            $agentId = (int)$_POST['agent_id'];
            $schedules = json_decode($_POST['schedules'], true);

            if ($scheduleManager->saveSchedulesFromArray($agentId, $schedules)) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur lors de la sauvegarde']);
            }
            exit;

        case 'add_unavailability':
            $agentId = (int)$_POST['agent_id'];
            $start = $_POST['start'];
            $end = $_POST['end'];
            $reason = $_POST['reason'] ?? null;

            $id = $scheduleManager->addUnavailability($agentId, $start, $end, $reason);
            echo json_encode(['success' => true, 'id' => $id]);
            exit;

        case 'delete_unavailability':
            $id = (int)$_POST['id'];
            $scheduleManager->deleteUnavailability($id);
            echo json_encode(['success' => true]);
            exit;
    }
}
?>

<!-- Top Bar -->
<div class="top-bar">
    <h1 class="page-title">
        <i class="bi bi-calendar3 me-2"></i>Planning
        <?php if ($selectedAgent): ?>
            - <?= htmlspecialchars($selectedAgent['name']) ?>
        <?php endif; ?>
    </h1>
    <div class="d-flex gap-2">
        <!-- Sélecteur d'agent -->
        <select id="agentSelector" class="form-select" style="width: 200px;">
            <option value="0" <?= $selectedAgentId == 0 ? 'selected' : '' ?>>Tous les agents</option>
            <?php foreach ($agents as $a): ?>
                <option value="<?= $a['id'] ?>" <?= $selectedAgentId == $a['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($a['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <?php if ($selectedAgentId > 0): ?>
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#scheduleModal">
                <i class="bi bi-clock me-1"></i>Horaires
            </button>
            <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#unavailabilityModal">
                <i class="bi bi-calendar-x me-1"></i>Indisponibilité
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Légende -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-4 align-items-center">
            <span class="text-muted small">Légende :</span>
            <span><span class="badge" style="background: rgba(52,152,219,0.3);">&nbsp;&nbsp;&nbsp;</span> Disponible</span>
            <span><span class="badge bg-success">&nbsp;&nbsp;&nbsp;</span> RDV Confirmé</span>
            <span><span class="badge bg-warning">&nbsp;&nbsp;&nbsp;</span> RDV En attente</span>
            <span><span class="badge bg-danger">&nbsp;&nbsp;&nbsp;</span> RDV Annulé</span>
            <span><span class="badge bg-secondary">&nbsp;&nbsp;&nbsp;</span> Indisponible</span>
        </div>
    </div>
</div>

<!-- Calendrier -->
<div class="card">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<?php if ($selectedAgentId > 0): ?>
<!-- Modal Horaires de travail -->
<div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-clock me-2"></i>Horaires de travail</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">Définissez les plages horaires de disponibilité pour <strong><?= htmlspecialchars($selectedAgent['name']) ?></strong></p>

                <div id="scheduleForm">
                    <?php
                    $schedules = $scheduleManager->getSchedulesByDay($selectedAgentId);
                    foreach ($schedules as $dayNum => $day):
                    ?>
                        <div class="mb-3 pb-3 border-bottom">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <strong><?= $day['name'] ?></strong>
                                <button type="button" class="btn btn-sm btn-outline-primary add-slot" data-day="<?= $dayNum ?>">
                                    <i class="bi bi-plus"></i> Ajouter
                                </button>
                            </div>
                            <div class="slots-container" data-day="<?= $dayNum ?>">
                                <?php if (empty($day['slots'])): ?>
                                    <div class="text-muted small">Aucun créneau (fermé)</div>
                                <?php else: ?>
                                    <?php foreach ($day['slots'] as $slot): ?>
                                        <div class="slot-row d-flex align-items-center gap-2 mb-2">
                                            <input type="time" class="form-control form-control-sm slot-start" value="<?= substr($slot['start'], 0, 5) ?>" style="width: 120px;">
                                            <span>à</span>
                                            <input type="time" class="form-control form-control-sm slot-end" value="<?= substr($slot['end'], 0, 5) ?>" style="width: 120px;">
                                            <div class="form-check">
                                                <input class="form-check-input slot-available" type="checkbox" <?= $slot['available'] ? 'checked' : '' ?>>
                                                <label class="form-check-label">Actif</label>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-slot">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" id="saveSchedule">
                    <i class="bi bi-check-lg me-1"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Indisponibilité -->
<div class="modal fade" id="unavailabilityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-x me-2"></i>Ajouter une indisponibilité</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="unavailabilityForm">
                    <div class="mb-3">
                        <label class="form-label">Date et heure de début</label>
                        <input type="datetime-local" class="form-control" name="start" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Date et heure de fin</label>
                        <input type="datetime-local" class="form-control" name="end" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Raison (optionnel)</label>
                        <input type="text" class="form-control" name="reason" placeholder="Ex: Congés, Formation...">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-warning" id="saveUnavailability">
                    <i class="bi bi-check-lg me-1"></i>Ajouter
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$agentId = $selectedAgentId;
$extraScripts = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialiser FullCalendar
    const calendarEl = document.getElementById('calendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        locale: 'fr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        slotDuration: '00:30:00',
        allDaySlot: false,
        weekends: true,
        nowIndicator: true,
        height: 'auto',
        events: function(info, successCallback, failureCallback) {
            fetch(`agent-schedule.php?client_id={$clientId}&agent_id={$agentId}&action=get_events&start=` + info.startStr + '&end=' + info.endStr)
                .then(response => response.json())
                .then(data => successCallback(data))
                .catch(error => failureCallback(error));
        },
        eventClick: function(info) {
            if (info.event.extendedProps.type === 'appointment') {
                // TODO: Afficher les détails du RDV
                alert('RDV: ' + info.event.title);
            }
        }
    });
    calendar.render();

    // Changement d'agent
    document.getElementById('agentSelector').addEventListener('change', function() {
        window.location.href = 'agent-schedule.php?client_id={$clientId}&agent_id=' + this.value;
    });

    // Gestion des horaires
    document.querySelectorAll('.add-slot').forEach(btn => {
        btn.addEventListener('click', function() {
            const day = this.dataset.day;
            const container = document.querySelector(`.slots-container[data-day="\${day}"]`);
            container.innerHTML = '';

            const row = document.createElement('div');
            row.className = 'slot-row d-flex align-items-center gap-2 mb-2';
            row.innerHTML = `
                <input type="time" class="form-control form-control-sm slot-start" value="09:00" style="width: 120px;">
                <span>à</span>
                <input type="time" class="form-control form-control-sm slot-end" value="12:00" style="width: 120px;">
                <div class="form-check">
                    <input class="form-check-input slot-available" type="checkbox" checked>
                    <label class="form-check-label">Actif</label>
                </div>
                <button type="button" class="btn btn-sm btn-outline-danger remove-slot">
                    <i class="bi bi-trash"></i>
                </button>
            `;
            container.appendChild(row);

            row.querySelector('.remove-slot').addEventListener('click', function() {
                row.remove();
            });
        });
    });

    document.querySelectorAll('.remove-slot').forEach(btn => {
        btn.addEventListener('click', function() {
            this.closest('.slot-row').remove();
        });
    });

    // Sauvegarder les horaires
    document.getElementById('saveSchedule')?.addEventListener('click', function() {
        const schedules = [];

        document.querySelectorAll('.slots-container').forEach(container => {
            const day = container.dataset.day;

            container.querySelectorAll('.slot-row').forEach(row => {
                const start = row.querySelector('.slot-start').value;
                const end = row.querySelector('.slot-end').value;
                const available = row.querySelector('.slot-available').checked;

                if (start && end) {
                    schedules.push({
                        day_of_week: parseInt(day),
                        start_time: start + ':00',
                        end_time: end + ':00',
                        is_available: available
                    });
                }
            });
        });

        fetch('agent-schedule.php?client_id={$clientId}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=save_schedule&agent_id={$agentId}&schedules=' + encodeURIComponent(JSON.stringify(schedules))
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('scheduleModal')).hide();
                calendar.refetchEvents();
            } else {
                alert('Erreur: ' + (data.error || 'Erreur inconnue'));
            }
        });
    });

    // Ajouter une indisponibilité
    document.getElementById('saveUnavailability')?.addEventListener('click', function() {
        const form = document.getElementById('unavailabilityForm');
        const formData = new FormData(form);
        formData.append('action', 'add_unavailability');
        formData.append('agent_id', {$agentId});

        fetch('agent-schedule.php?client_id={$clientId}', {
            method: 'POST',
            body: new URLSearchParams(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('unavailabilityModal')).hide();
                form.reset();
                calendar.refetchEvents();
            }
        });
    });
});
</script>
JS;

require_once __DIR__ . '/includes/footer.php';
?>
