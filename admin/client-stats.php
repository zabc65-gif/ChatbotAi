<?php
/**
 * Statistiques détaillées d'un client
 * Affiche les stats de conversation, messages et rendez-vous
 * pour les chatbots simple et multi-agent
 */

$pageTitle = 'Statistiques Client';
require_once 'includes/header.php';

$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$clientId) {
    header('Location: clients.php');
    exit;
}

// Récupérer les informations du client
$client = $db->fetchOne(
    "SELECT c.*, cb.bot_name, cb.multi_agent_enabled, cb.booking_enabled
     FROM clients c
     LEFT JOIN client_chatbots cb ON cb.client_id = c.id
     WHERE c.id = ?",
    [$clientId]
);

if (!$client) {
    header('Location: clients.php');
    exit;
}

$isMultiAgent = !empty($client['multi_agent_enabled']);

// === STATISTIQUES GÉNÉRALES ===

// Période sélectionnée
$period = $_GET['period'] ?? '30';
$periodDays = (int)$period;
if (!in_array($periodDays, [7, 30, 90, 365])) {
    $periodDays = 30;
}

// Total conversations
$totalConversations = $db->fetchOne(
    "SELECT COUNT(*) as total FROM client_conversations WHERE client_id = ?",
    [$clientId]
)['total'] ?? 0;

// Conversations sur la période
$periodConversations = $db->fetchOne(
    "SELECT COUNT(*) as total FROM client_conversations
     WHERE client_id = ? AND started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
    [$clientId, $periodDays]
)['total'] ?? 0;

// Total messages
$totalMessages = $db->fetchOne(
    "SELECT SUM(messages_count) as total FROM client_usage WHERE client_id = ?",
    [$clientId]
)['total'] ?? 0;

// Messages sur la période
$periodMessages = $db->fetchOne(
    "SELECT SUM(messages_count) as total FROM client_usage
     WHERE client_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)",
    [$clientId, $periodDays]
)['total'] ?? 0;

// Moyenne messages/jour sur la période
$avgMessagesPerDay = $periodDays > 0 ? round(($periodMessages ?: 0) / $periodDays, 1) : 0;

// === STATISTIQUES RDV (mode simple) ===
$appointmentsStats = ['total' => 0, 'period' => 0, 'confirmed' => 0, 'cancelled' => 0];
$appointmentsByMonth = [];

// Vérifier si la table appointments existe
$appointmentsTableExists = false;
try {
    $check = $db->fetchOne("SHOW TABLES LIKE 'appointments'");
    $appointmentsTableExists = !empty($check);
} catch (Exception $e) {}

if ($appointmentsTableExists && !$isMultiAgent) {
    // Stats globales RDV
    $appointmentsStats['total'] = $db->fetchOne(
        "SELECT COUNT(*) as total FROM appointments WHERE client_id = ? AND chatbot_type = 'client'",
        [$clientId]
    )['total'] ?? 0;

    $appointmentsStats['period'] = $db->fetchOne(
        "SELECT COUNT(*) as total FROM appointments
         WHERE client_id = ? AND chatbot_type = 'client' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$clientId, $periodDays]
    )['total'] ?? 0;

    $appointmentsStats['confirmed'] = $db->fetchOne(
        "SELECT COUNT(*) as total FROM appointments
         WHERE client_id = ? AND chatbot_type = 'client' AND status = 'confirmed'",
        [$clientId]
    )['total'] ?? 0;

    $appointmentsStats['cancelled'] = $db->fetchOne(
        "SELECT COUNT(*) as total FROM appointments
         WHERE client_id = ? AND chatbot_type = 'client' AND status = 'cancelled'",
        [$clientId]
    )['total'] ?? 0;

    // RDV par mois (6 derniers mois)
    $appointmentsByMonth = $db->fetchAll(
        "SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month,
                DATE_FORMAT(appointment_date, '%b %Y') as month_label,
                COUNT(*) as count
         FROM appointments
         WHERE client_id = ? AND chatbot_type = 'client' AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY month
         ORDER BY month ASC",
        [$clientId]
    );
}

// === STATISTIQUES MULTI-AGENT ===
$multiAgentStats = [];
$agentStats = [];
$appointmentsV2ByMonth = [];

// Vérifier si la table appointments_v2 existe
$appointmentsV2TableExists = false;
try {
    $check = $db->fetchOne("SHOW TABLES LIKE 'appointments_v2'");
    $appointmentsV2TableExists = !empty($check);
} catch (Exception $e) {}

if ($isMultiAgent && $appointmentsV2TableExists) {
    // Stats globales RDV multi-agent
    $multiAgentStats['total'] = $db->fetchOne(
        "SELECT COUNT(*) as total FROM appointments_v2 WHERE client_id = ?",
        [$clientId]
    )['total'] ?? 0;

    $multiAgentStats['period'] = $db->fetchOne(
        "SELECT COUNT(*) as total FROM appointments_v2
         WHERE client_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)",
        [$clientId, $periodDays]
    )['total'] ?? 0;

    $multiAgentStats['confirmed'] = $db->fetchOne(
        "SELECT COUNT(*) as total FROM appointments_v2
         WHERE client_id = ? AND status = 'confirmed'",
        [$clientId]
    )['total'] ?? 0;

    $multiAgentStats['cancelled'] = $db->fetchOne(
        "SELECT COUNT(*) as total FROM appointments_v2
         WHERE client_id = ? AND status = 'cancelled'",
        [$clientId]
    )['total'] ?? 0;

    // Stats par agent
    $agentStats = $db->fetchAll(
        "SELECT a.id, a.name, a.email, a.photo_url, a.color,
                COUNT(ap.id) as total_appointments,
                SUM(CASE WHEN ap.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN ap.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN ap.appointment_date >= CURDATE() AND ap.status = 'confirmed' THEN 1 ELSE 0 END) as upcoming
         FROM agents a
         LEFT JOIN appointments_v2 ap ON ap.agent_id = a.id
         WHERE a.client_id = ? AND a.active = 1
         GROUP BY a.id
         ORDER BY total_appointments DESC",
        [$clientId]
    );

    // RDV par mois (6 derniers mois) - multi-agent
    $appointmentsV2ByMonth = $db->fetchAll(
        "SELECT DATE_FORMAT(appointment_date, '%Y-%m') as month,
                DATE_FORMAT(appointment_date, '%b %Y') as month_label,
                COUNT(*) as count
         FROM appointments_v2
         WHERE client_id = ? AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY month
         ORDER BY month ASC",
        [$clientId]
    );

    // Distribution par méthode
    $distributionStats = $db->fetchAll(
        "SELECT distribution_method, COUNT(*) as count
         FROM appointments_v2
         WHERE client_id = ? AND distribution_method IS NOT NULL
         GROUP BY distribution_method",
        [$clientId]
    );
}

// === ÉVOLUTION MESSAGES PAR JOUR (30 derniers jours) ===
$messagesByDay = $db->fetchAll(
    "SELECT date, messages_count, conversations_count
     FROM client_usage
     WHERE client_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
     ORDER BY date ASC",
    [$clientId]
);

// Convertir en JSON pour les graphiques
$chartLabels = [];
$chartMessages = [];
$chartConversations = [];

// Remplir les 30 derniers jours (même si pas de données)
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('d/m', strtotime($date));
    $chartMessages[] = 0;
    $chartConversations[] = 0;
}

foreach ($messagesByDay as $day) {
    $index = array_search(date('d/m', strtotime($day['date'])), $chartLabels);
    if ($index !== false) {
        $chartMessages[$index] = (int)$day['messages_count'];
        $chartConversations[$index] = (int)$day['conversations_count'];
    }
}

// Fonction formatage date
function formatDateFr(string $date): string {
    $ts = strtotime($date);
    return date('d/m/Y', $ts);
}
?>

<div class="page-header">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 class="page-title">Statistiques : <?= htmlspecialchars($client['name']) ?></h1>
            <p class="page-subtitle">
                <?php if ($isMultiAgent): ?>
                    <span style="background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Mode Multi-Agent</span>
                <?php else: ?>
                    <span style="background: #dbeafe; color: #1d4ed8; padding: 2px 8px; border-radius: 4px; font-size: 12px;">Mode Simple</span>
                <?php endif; ?>
                Chatbot : <?= htmlspecialchars($client['bot_name'] ?: 'Assistant') ?>
            </p>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <!-- Sélecteur de période -->
            <form method="GET" style="display: flex; gap: 8px; align-items: center;">
                <input type="hidden" name="id" value="<?= $clientId ?>">
                <label style="font-size: 13px; color: var(--text-light);">Période :</label>
                <select name="period" class="form-select" style="width: auto; padding: 8px 12px;" onchange="this.form.submit()">
                    <option value="7" <?= $periodDays === 7 ? 'selected' : '' ?>>7 jours</option>
                    <option value="30" <?= $periodDays === 30 ? 'selected' : '' ?>>30 jours</option>
                    <option value="90" <?= $periodDays === 90 ? 'selected' : '' ?>>90 jours</option>
                    <option value="365" <?= $periodDays === 365 ? 'selected' : '' ?>>1 an</option>
                </select>
            </form>
            <a href="clients.php" class="btn btn-secondary">Retour aux clients</a>
        </div>
    </div>
</div>

<!-- Stats principales -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= number_format($periodConversations) ?></div>
        <div class="stat-label">Conversations (<?= $periodDays ?>j)</div>
        <div style="font-size: 12px; color: var(--text-light); margin-top: 4px;">
            Total : <?= number_format($totalConversations) ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($periodMessages ?: 0) ?></div>
        <div class="stat-label">Messages (<?= $periodDays ?>j)</div>
        <div style="font-size: 12px; color: var(--text-light); margin-top: 4px;">
            Total : <?= number_format($totalMessages ?: 0) ?>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $avgMessagesPerDay ?></div>
        <div class="stat-label">Moy. messages/jour</div>
    </div>
    <?php if ($client['booking_enabled']): ?>
        <div class="stat-card">
            <div class="stat-value" style="color: #059669;">
                <?= $isMultiAgent ? number_format($multiAgentStats['period'] ?? 0) : number_format($appointmentsStats['period']) ?>
            </div>
            <div class="stat-label">RDV (<?= $periodDays ?>j)</div>
            <div style="font-size: 12px; color: var(--text-light); margin-top: 4px;">
                Total : <?= $isMultiAgent ? number_format($multiAgentStats['total'] ?? 0) : number_format($appointmentsStats['total']) ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Graphique évolution -->
<div class="card">
    <h2 class="card-title">Activité des 30 derniers jours</h2>
    <canvas id="activityChart" style="width: 100%; height: 300px;"></canvas>
</div>

<?php if ($client['booking_enabled']): ?>
<!-- Stats RDV détaillées -->
<div class="grid-2">
    <div class="card">
        <h2 class="card-title">Rendez-vous - Statuts</h2>
        <?php if ($isMultiAgent): ?>
            <div style="display: flex; justify-content: space-around; text-align: center; padding: 20px 0;">
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #059669;"><?= number_format($multiAgentStats['confirmed'] ?? 0) ?></div>
                    <div style="font-size: 13px; color: var(--text-light);">Confirmés</div>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #dc2626;"><?= number_format($multiAgentStats['cancelled'] ?? 0) ?></div>
                    <div style="font-size: 13px; color: var(--text-light);">Annulés</div>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: var(--primary);"><?= number_format($multiAgentStats['total'] ?? 0) ?></div>
                    <div style="font-size: 13px; color: var(--text-light);">Total</div>
                </div>
            </div>
        <?php else: ?>
            <div style="display: flex; justify-content: space-around; text-align: center; padding: 20px 0;">
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #059669;"><?= number_format($appointmentsStats['confirmed']) ?></div>
                    <div style="font-size: 13px; color: var(--text-light);">Confirmés</div>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: #dc2626;"><?= number_format($appointmentsStats['cancelled']) ?></div>
                    <div style="font-size: 13px; color: var(--text-light);">Annulés</div>
                </div>
                <div>
                    <div style="font-size: 28px; font-weight: 700; color: var(--primary);"><?= number_format($appointmentsStats['total']) ?></div>
                    <div style="font-size: 13px; color: var(--text-light);">Total</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 class="card-title">RDV par mois (6 derniers mois)</h2>
        <?php
        $monthlyData = $isMultiAgent ? $appointmentsV2ByMonth : $appointmentsByMonth;
        if (empty($monthlyData)):
        ?>
            <p style="color: var(--text-light); text-align: center; padding: 40px;">Aucune donnée disponible.</p>
        <?php else: ?>
            <canvas id="monthlyChart" style="width: 100%; height: 200px;"></canvas>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($isMultiAgent && !empty($agentStats)): ?>
<!-- Stats par agent -->
<div class="card">
    <h2 class="card-title">Performance par agent</h2>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 12px 8px; text-align: left; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Agent</th>
                    <th style="padding: 12px 8px; text-align: center; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Total RDV</th>
                    <th style="padding: 12px 8px; text-align: center; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Confirmés</th>
                    <th style="padding: 12px 8px; text-align: center; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Annulés</th>
                    <th style="padding: 12px 8px; text-align: center; font-size: 12px; color: var(--text-light); text-transform: uppercase;">À venir</th>
                    <th style="padding: 12px 8px; text-align: center; font-size: 12px; color: var(--text-light); text-transform: uppercase;">Taux confirm.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($agentStats as $agent): ?>
                    <?php
                    $confirmRate = $agent['total_appointments'] > 0
                        ? round(($agent['confirmed'] / $agent['total_appointments']) * 100)
                        : 0;
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                        <td style="padding: 12px 8px;">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <?php if ($agent['photo_url']): ?>
                                    <img src="<?= htmlspecialchars($agent['photo_url']) ?>" alt="" style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <div style="width: 40px; height: 40px; border-radius: 50%; background: <?= htmlspecialchars($agent['color'] ?: '#6366f1') ?>; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                                        <?= strtoupper(substr($agent['name'], 0, 2)) ?>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($agent['name']) ?></div>
                                    <div style="font-size: 12px; color: var(--text-light);"><?= htmlspecialchars($agent['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td style="padding: 12px 8px; text-align: center; font-weight: 600;"><?= number_format($agent['total_appointments']) ?></td>
                        <td style="padding: 12px 8px; text-align: center; color: #059669;"><?= number_format($agent['confirmed']) ?></td>
                        <td style="padding: 12px 8px; text-align: center; color: #dc2626;"><?= number_format($agent['cancelled']) ?></td>
                        <td style="padding: 12px 8px; text-align: center; color: #3b82f6;"><?= number_format($agent['upcoming']) ?></td>
                        <td style="padding: 12px 8px; text-align: center;">
                            <div style="display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <div style="width: 60px; height: 8px; background: #e2e8f0; border-radius: 4px; overflow: hidden;">
                                    <div style="width: <?= $confirmRate ?>%; height: 100%; background: <?= $confirmRate >= 80 ? '#059669' : ($confirmRate >= 50 ? '#f59e0b' : '#dc2626') ?>;"></div>
                                </div>
                                <span style="font-size: 13px; font-weight: 500;"><?= $confirmRate ?>%</span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!empty($distributionStats)): ?>
<!-- Distribution par méthode -->
<div class="card">
    <h2 class="card-title">Méthode de distribution des RDV</h2>
    <div style="display: flex; gap: 24px; flex-wrap: wrap; padding: 20px 0;">
        <?php
        $methodLabels = [
            'round_robin' => 'Tour à tour',
            'availability' => 'Par disponibilité',
            'specialty' => 'Par spécialité',
            'visitor_choice' => 'Choix visiteur'
        ];
        foreach ($distributionStats as $dist):
            $label = $methodLabels[$dist['distribution_method']] ?? $dist['distribution_method'];
        ?>
            <div style="background: #f8fafc; padding: 16px 24px; border-radius: 12px; text-align: center; min-width: 140px;">
                <div style="font-size: 24px; font-weight: 700; color: var(--primary);"><?= number_format($dist['count']) ?></div>
                <div style="font-size: 13px; color: var(--text-light);"><?= htmlspecialchars($label) ?></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Graphique d'activité
const activityCtx = document.getElementById('activityChart').getContext('2d');
new Chart(activityCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode($chartLabels) ?>,
        datasets: [
            {
                label: 'Messages',
                data: <?= json_encode($chartMessages) ?>,
                borderColor: '#6366f1',
                backgroundColor: 'rgba(99, 102, 241, 0.1)',
                fill: true,
                tension: 0.3
            },
            {
                label: 'Conversations',
                data: <?= json_encode($chartConversations) ?>,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                fill: true,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

<?php
$monthlyData = $isMultiAgent ? $appointmentsV2ByMonth : $appointmentsByMonth;
if ($client['booking_enabled'] && !empty($monthlyData)):
?>
// Graphique mensuel RDV
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
new Chart(monthlyCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthlyData, 'month_label')) ?>,
        datasets: [{
            label: 'Rendez-vous',
            data: <?= json_encode(array_map('intval', array_column($monthlyData, 'count'))) ?>,
            backgroundColor: '#6366f1',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<style>
.stat-card {
    position: relative;
}
.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 4px;
    height: 100%;
    background: var(--primary);
    border-radius: 4px 0 0 4px;
}
</style>

<?php require_once 'includes/footer.php'; ?>
