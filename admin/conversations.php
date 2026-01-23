<?php
/**
 * Visualisation des conversations
 */

$pageTitle = 'Conversations';
require_once 'includes/header.php';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filtres
$dateFilter = $_GET['date'] ?? '';
$searchFilter = $_GET['search'] ?? '';

try {
    // Construire la requête
    $whereClause = "WHERE role != 'system'";
    $params = [];

    if ($dateFilter) {
        $whereClause .= " AND DATE(created_at) = ?";
        $params[] = $dateFilter;
    }

    if ($searchFilter) {
        $whereClause .= " AND content LIKE ?";
        $params[] = '%' . $searchFilter . '%';
    }

    // Compter le total
    $countQuery = "SELECT COUNT(DISTINCT session_id) as total FROM conversations $whereClause";
    $totalResult = $db->fetchOne($countQuery, $params);
    $totalSessions = $totalResult['total'] ?? 0;
    $totalPages = ceil($totalSessions / $perPage);

    // Récupérer les sessions
    $sessionsQuery = "
        SELECT
            session_id,
            MIN(created_at) as started_at,
            MAX(created_at) as last_message,
            COUNT(*) as message_count
        FROM conversations
        $whereClause
        GROUP BY session_id
        ORDER BY started_at DESC
        LIMIT $perPage OFFSET $offset
    ";
    $sessions = $db->fetchAll($sessionsQuery, $params);

} catch (Exception $e) {
    $sessions = [];
    $totalPages = 0;
    $error = "Erreur : " . $e->getMessage();
}

// Récupérer les détails d'une conversation si demandé
$selectedSession = $_GET['session'] ?? null;
$conversationDetails = [];

if ($selectedSession) {
    try {
        $conversationDetails = $db->fetchAll(
            "SELECT * FROM conversations WHERE session_id = ? AND role != 'system' ORDER BY created_at ASC",
            [$selectedSession]
        );
    } catch (Exception $e) {
        $conversationDetails = [];
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Conversations</h1>
    <p class="page-subtitle">Consultez l'historique des échanges avec le chatbot</p>
</div>

<!-- Filtres -->
<div class="card" style="margin-bottom: 24px;">
    <form method="GET" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label">Date</label>
            <input type="date" name="date" class="form-input" value="<?= htmlspecialchars($dateFilter) ?>">
        </div>
        <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
            <label class="form-label">Rechercher</label>
            <input type="text" name="search" class="form-input" placeholder="Rechercher dans les messages..."
                   value="<?= htmlspecialchars($searchFilter) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Filtrer</button>
        <?php if ($dateFilter || $searchFilter): ?>
            <a href="conversations.php" class="btn btn-secondary">Réinitialiser</a>
        <?php endif; ?>
    </form>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid-2">
    <!-- Liste des sessions -->
    <div class="card">
        <h2 class="card-title">Sessions (<?= $totalSessions ?>)</h2>

        <?php if (empty($sessions)): ?>
            <p style="color: var(--text-light);">Aucune conversation trouvée.</p>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 8px;">
                <?php foreach ($sessions as $session): ?>
                    <a href="?session=<?= urlencode($session['session_id']) ?><?= $dateFilter ? '&date=' . urlencode($dateFilter) : '' ?><?= $searchFilter ? '&search=' . urlencode($searchFilter) : '' ?>"
                       class="session-item <?= $selectedSession === $session['session_id'] ? 'active' : '' ?>">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <code style="font-size: 12px; color: var(--text-light);">
                                <?= htmlspecialchars(substr($session['session_id'], 0, 16)) ?>...
                            </code>
                            <span style="background: var(--primary); color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px;">
                                <?= $session['message_count'] ?> msg
                            </span>
                        </div>
                        <div style="font-size: 13px; color: var(--text-light); margin-top: 4px;">
                            <?= date('d/m/Y H:i', strtotime($session['started_at'])) ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div style="display: flex; gap: 8px; margin-top: 20px; justify-content: center;">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?><?= $dateFilter ? '&date=' . urlencode($dateFilter) : '' ?><?= $searchFilter ? '&search=' . urlencode($searchFilter) : '' ?>"
                           class="btn btn-secondary" style="padding: 8px 16px;">←</a>
                    <?php endif; ?>
                    <span style="padding: 8px 16px; color: var(--text-light);">
                        Page <?= $page ?> / <?= $totalPages ?>
                    </span>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?><?= $dateFilter ? '&date=' . urlencode($dateFilter) : '' ?><?= $searchFilter ? '&search=' . urlencode($searchFilter) : '' ?>"
                           class="btn btn-secondary" style="padding: 8px 16px;">→</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Détails de la conversation -->
    <div class="card">
        <h2 class="card-title">Détails de la conversation</h2>

        <?php if (!$selectedSession): ?>
            <p style="color: var(--text-light);">Sélectionnez une session pour voir les messages.</p>
        <?php elseif (empty($conversationDetails)): ?>
            <p style="color: var(--text-light);">Aucun message dans cette conversation.</p>
        <?php else: ?>
            <div class="conversation-view">
                <?php foreach ($conversationDetails as $msg): ?>
                    <div class="message <?= $msg['role'] === 'user' ? 'message-user' : 'message-bot' ?>">
                        <div class="message-header">
                            <strong><?= $msg['role'] === 'user' ? 'Visiteur' : 'Chatbot' ?></strong>
                            <span style="font-size: 11px; color: var(--text-light);">
                                <?= date('H:i:s', strtotime($msg['created_at'])) ?>
                                <?php if ($msg['ai_service']): ?>
                                    • <?= htmlspecialchars($msg['ai_service']) ?>
                                <?php endif; ?>
                                <?php if ($msg['tokens_used']): ?>
                                    • <?= $msg['tokens_used'] ?> tokens
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="message-content">
                            <?= nl2br(htmlspecialchars($msg['content'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .session-item {
        display: block;
        padding: 12px;
        background: #f8fafc;
        border-radius: 8px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s;
        border: 2px solid transparent;
    }
    .session-item:hover {
        background: #f1f5f9;
    }
    .session-item.active {
        border-color: var(--primary);
        background: #eef2ff;
    }
    .conversation-view {
        display: flex;
        flex-direction: column;
        gap: 12px;
        max-height: 500px;
        overflow-y: auto;
        padding-right: 8px;
    }
    .message {
        padding: 12px;
        border-radius: 12px;
    }
    .message-user {
        background: #eef2ff;
        margin-left: 20px;
    }
    .message-bot {
        background: #f8fafc;
        margin-right: 20px;
    }
    .message-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-size: 13px;
    }
    .message-content {
        font-size: 14px;
        line-height: 1.5;
    }
</style>

<?php require_once 'includes/footer.php'; ?>
