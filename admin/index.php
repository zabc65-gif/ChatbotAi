<?php
/**
 * Dashboard Administration
 */

$pageTitle = 'Dashboard';
require_once 'includes/header.php';

// Récupérer les statistiques
try {
    // Stats conversations
    $totalConversations = $db->fetchOne("SELECT COUNT(DISTINCT session_id) as count FROM conversations")['count'] ?? 0;
    $todayConversations = $db->fetchOne("SELECT COUNT(DISTINCT session_id) as count FROM conversations WHERE DATE(created_at) = CURDATE()")['count'] ?? 0;
    $totalMessages = $db->fetchOne("SELECT COUNT(*) as count FROM conversations WHERE role != 'system'")['count'] ?? 0;

    // Stats du jour
    $todayStats = $db->fetchOne("SELECT * FROM chatbot_stats WHERE date = CURDATE()");

    // Dernières conversations
    $recentConversations = $db->fetchAll("
        SELECT session_id, MIN(created_at) as started_at, COUNT(*) as message_count
        FROM conversations
        WHERE role != 'system'
        GROUP BY session_id
        ORDER BY started_at DESC
        LIMIT 5
    ");

} catch (Exception $e) {
    $totalConversations = 0;
    $todayConversations = 0;
    $totalMessages = 0;
    $todayStats = null;
    $recentConversations = [];
}
?>

<div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">Vue d'ensemble de votre chatbot</p>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totalConversations) ?></div>
        <div class="stat-label">Conversations totales</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($todayConversations) ?></div>
        <div class="stat-label">Conversations aujourd'hui</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($totalMessages) ?></div>
        <div class="stat-label">Messages échangés</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format($todayStats['total_tokens'] ?? 0) ?></div>
        <div class="stat-label">Tokens utilisés (aujourd'hui)</div>
    </div>
</div>

<div class="grid-2">
    <!-- Dernières conversations -->
    <div class="card">
        <h2 class="card-title">Dernières conversations</h2>
        <?php if (empty($recentConversations)): ?>
            <p style="color: var(--text-light);">Aucune conversation pour le moment.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 1px solid #e2e8f0;">
                        <th style="text-align: left; padding: 12px 0; color: var(--text-light); font-size: 12px; text-transform: uppercase;">Session</th>
                        <th style="text-align: left; padding: 12px 0; color: var(--text-light); font-size: 12px; text-transform: uppercase;">Messages</th>
                        <th style="text-align: left; padding: 12px 0; color: var(--text-light); font-size: 12px; text-transform: uppercase;">Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentConversations as $conv): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 12px 0;">
                                <code style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                    <?= htmlspecialchars(substr($conv['session_id'], 0, 20)) ?>...
                                </code>
                            </td>
                            <td style="padding: 12px 0; font-weight: 500;"><?= $conv['message_count'] ?></td>
                            <td style="padding: 12px 0; color: var(--text-light); font-size: 14px;">
                                <?= date('d/m/Y H:i', strtotime($conv['started_at'])) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <a href="conversations.php" class="btn btn-secondary" style="margin-top: 16px;">Voir toutes les conversations</a>
        <?php endif; ?>
    </div>

    <!-- Accès rapides -->
    <div class="card">
        <h2 class="card-title">Accès rapides</h2>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <a href="chatbot-settings.php" class="btn btn-primary" style="justify-content: flex-start;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
                </svg>
                Configurer le chatbot
            </a>
            <a href="landing-texts.php" class="btn btn-secondary" style="justify-content: flex-start;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/>
                </svg>
                Modifier les textes
            </a>
            <a href="../index.php" target="_blank" class="btn btn-secondary" style="justify-content: flex-start;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M19 19H5V5h7V3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2v-7h-2v7zM14 3v2h3.59l-9.83 9.83 1.41 1.41L19 6.41V10h2V3h-7z"/>
                </svg>
                Voir le site
            </a>
            <a href="../demo.php" target="_blank" class="btn btn-secondary" style="justify-content: flex-start;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/>
                </svg>
                Tester la démo
            </a>
        </div>
    </div>
</div>

<!-- Info services IA -->
<div class="card">
    <h2 class="card-title">Services IA configurés</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
        <div style="padding: 20px; background: #f8fafc; border-radius: 12px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 40px; height: 40px; background: #10b981; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <div>
                    <div style="font-weight: 600;">Groq</div>
                    <div style="font-size: 12px; color: var(--text-light);">Service principal</div>
                </div>
            </div>
            <div style="font-size: 13px; color: var(--text-light);">
                Modèle : <?= GROQ_MODEL ?>
            </div>
        </div>

        <div style="padding: 20px; background: #f8fafc; border-radius: 12px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px;">
                <div style="width: 40px; height: 40px; background: #10b981; border-radius: 10px; display: flex; align-items: center; justify-content: center;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                </div>
                <div>
                    <div style="font-weight: 600;">Gemini</div>
                    <div style="font-size: 12px; color: var(--text-light);">Service backup</div>
                </div>
            </div>
            <div style="font-size: 13px; color: var(--text-light);">
                Modèle : <?= GEMINI_MODEL ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
