<?php
/**
 * Gestion des clients - Interface d'administration
 * Permet de créer, modifier et gérer les clients et leurs chatbots
 */

$pageTitle = 'Mes Clients';
require_once 'includes/header.php';

$success = '';
$error = '';
$editClient = null;

// Vérifier si les tables existent
try {
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'clients'");
    if (!$tableExists) {
        $error = "Le système multi-clients n'est pas installé. <a href='setup-clients-system.php?key=setup_clients_2024' style='color: #3b82f6;'>Cliquez ici pour l'installer</a>.";
    }
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données.";
}

// Générer une clé API unique
function generateApiKey(): string {
    return bin2hex(random_bytes(32));
}

// Générer un mot de passe aléatoire lisible
function generatePassword(int $length = 12): string {
    $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $password;
}

// Générer mot de passe pour nouveau client
$generatedPassword = generatePassword();

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    CSRF::verify();
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add':
            case 'edit':
                $id = $_POST['id'] ?? null;
                $name = trim($_POST['name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $company = trim($_POST['company'] ?? '');
                $website = trim($_POST['website'] ?? '');
                $phone = trim($_POST['phone'] ?? '');
                $notes = trim($_POST['notes'] ?? '');
                $active = isset($_POST['active']) ? 1 : 0;

                // Chatbot settings
                $chatbotName = trim($_POST['chatbot_name'] ?? 'Assistant');
                $chatbotIcon = trim($_POST['chatbot_icon'] ?? '💬');
                $chatbotColor = $_POST['chatbot_color'] ?? '#6366f1';
                $welcomeMessage = trim($_POST['welcome_message'] ?? '');
                $systemPrompt = trim($_POST['system_prompt'] ?? '');
                $redirectMessage = trim($_POST['redirect_message'] ?? '');
                $quickActions = trim($_POST['quick_actions'] ?? '');
                $allowedDomains = trim($_POST['allowed_domains'] ?? '');
                $showOnSite = isset($_POST['show_on_site']) ? 1 : 0;
                $showFace = isset($_POST['show_face']) ? 1 : 0;
                $showHat = isset($_POST['show_hat']) ? 1 : 0;
                $faceColor = $_POST['face_color'] ?? '#6366f1';
                $hatColor = $_POST['hat_color'] ?? '#1e293b';
                $bookingEnabled = isset($_POST['booking_enabled']) ? 1 : 0;
                $googleCalendarId = trim($_POST['google_calendar_id'] ?? '');
                $notificationEmail = trim($_POST['notification_email'] ?? '');
                $multiAgentEnabled = isset($_POST['multi_agent_enabled']) ? 1 : 0;

                if (empty($name) || empty($email)) {
                    throw new Exception('Le nom et l\'email sont obligatoires.');
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Email invalide.');
                }

                if ($action === 'add') {
                    if (empty($password)) {
                        throw new Exception('Le mot de passe est obligatoire pour un nouveau client.');
                    }

                    // Vérifier si l'email existe déjà
                    $existing = $db->fetchOne("SELECT id FROM clients WHERE email = ?", [$email]);
                    if ($existing) {
                        throw new Exception('Cet email est déjà utilisé.');
                    }

                    $apiKey = generateApiKey();
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                    // Créer le client
                    $db->query(
                        "INSERT INTO clients (name, email, password, company, website, phone, api_key, notes, active)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$name, $email, $hashedPassword, $company, $website, $phone, $apiKey, $notes, $active]
                    );
                    $clientId = $db->getPdo()->lastInsertId();

                    // Créer le chatbot du client
                    $db->query(
                        "INSERT INTO client_chatbots (client_id, bot_name, icon, primary_color, welcome_message, system_prompt, redirect_message, quick_actions, allowed_domains, show_on_site, show_face, show_hat, face_color, hat_color, booking_enabled, google_calendar_id, notification_email, multi_agent_enabled)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        [$clientId, $chatbotName, $chatbotIcon, $chatbotColor, $welcomeMessage, $systemPrompt, $redirectMessage, $quickActions, $allowedDomains, $showOnSite, $showFace, $showHat, $faceColor, $hatColor, $bookingEnabled, $googleCalendarId, $notificationEmail, $multiAgentEnabled]
                    );

                    $success = "Client \"$name\" créé avec succès !";

                } else if ($id) {
                    // Vérifier si l'email existe déjà pour un autre client
                    $existing = $db->fetchOne("SELECT id FROM clients WHERE email = ? AND id != ?", [$email, $id]);
                    if ($existing) {
                        throw new Exception('Cet email est déjà utilisé par un autre client.');
                    }

                    // Mettre à jour le client
                    $updateFields = "name=?, email=?, company=?, website=?, phone=?, notes=?, active=?";
                    $params = [$name, $email, $company, $website, $phone, $notes, $active];

                    if (!empty($password)) {
                        $updateFields .= ", password=?";
                        $params[] = password_hash($password, PASSWORD_DEFAULT);
                    }

                    $params[] = $id;
                    $db->query("UPDATE clients SET $updateFields WHERE id=?", $params);

                    // Mettre à jour le chatbot
                    $db->query(
                        "UPDATE client_chatbots SET bot_name=?, icon=?, primary_color=?, welcome_message=?, system_prompt=?, redirect_message=?, quick_actions=?, allowed_domains=?, show_on_site=?, show_face=?, show_hat=?, face_color=?, hat_color=?, booking_enabled=?, google_calendar_id=?, notification_email=?, multi_agent_enabled=? WHERE client_id=?",
                        [$chatbotName, $chatbotIcon, $chatbotColor, $welcomeMessage, $systemPrompt, $redirectMessage, $quickActions, $allowedDomains, $showOnSite, $showFace, $showHat, $faceColor, $hatColor, $bookingEnabled, $googleCalendarId, $notificationEmail, $multiAgentEnabled, $id]
                    );

                    $success = "Client \"$name\" mis à jour !";
                }
                break;

            case 'delete':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $client = $db->fetchOne("SELECT name FROM clients WHERE id = ?", [$id]);
                    $db->query("DELETE FROM clients WHERE id = ?", [$id]);
                    $success = "Client \"" . ($client['name'] ?? '') . "\" supprimé !";
                }
                break;

            case 'toggle':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $db->query("UPDATE clients SET active = NOT active WHERE id = ?", [$id]);
                    $success = "Statut mis à jour !";
                }
                break;

            case 'regenerate_key':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $newKey = generateApiKey();
                    $db->query("UPDATE clients SET api_key = ? WHERE id = ?", [$newKey, $id]);
                    $success = "Clé API régénérée ! Pensez à mettre à jour le code d'intégration chez le client.";
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer le client à éditer
if (isset($_GET['edit']) && empty($error)) {
    $editClient = $db->fetchOne(
        "SELECT c.*, cb.bot_name as chatbot_name, cb.icon as chatbot_icon, cb.primary_color as chatbot_color,
                cb.welcome_message, cb.system_prompt, cb.redirect_message, cb.quick_actions, cb.allowed_domains, cb.show_on_site, cb.show_face, cb.show_hat,
                cb.face_color, cb.hat_color, cb.booking_enabled, cb.google_calendar_id, cb.notification_email, cb.multi_agent_enabled
         FROM clients c
         LEFT JOIN client_chatbots cb ON cb.client_id = c.id
         WHERE c.id = ?",
        [$_GET['edit']]
    );
}

// Récupérer tous les clients avec stats
$clients = [];
if (empty($error) || strpos($error, 'installé') === false) {
    try {
        $clients = $db->fetchAll(
            "SELECT c.*,
                    cb.bot_name as chatbot_name, cb.primary_color as chatbot_color, cb.icon as chatbot_icon,
                    (SELECT SUM(messages_count) FROM client_usage WHERE client_id = c.id AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as messages_30d,
                    (SELECT COUNT(*) FROM client_conversations WHERE client_id = c.id AND started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as conversations_30d
             FROM clients c
             LEFT JOIN client_chatbots cb ON cb.client_id = c.id
             ORDER BY c.created_at DESC"
        );
    } catch (Exception $e) {
        // Tables might not exist yet
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Mes Clients</h1>
    <p class="page-subtitle">Gérez les chatbots de vos clients avec intégration simple</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<!-- Stats globales -->
<div class="stats-grid" style="margin-bottom: 24px;">
    <div class="stat-card">
        <div class="stat-value"><?= count($clients) ?></div>
        <div class="stat-label">Clients</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= count(array_filter($clients, fn($c) => $c['active'])) ?></div>
        <div class="stat-label">Actifs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format(array_sum(array_column($clients, 'messages_30d')) ?: 0) ?></div>
        <div class="stat-label">Messages (30j)</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= number_format(array_sum(array_column($clients, 'conversations_30d')) ?: 0) ?></div>
        <div class="stat-label">Conversations (30j)</div>
    </div>
</div>

<!-- Liste des clients -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 class="card-title" style="margin-bottom: 0; padding-bottom: 0; border: none;">Clients (<?= count($clients) ?>)</h2>
        <a href="#form-client" class="btn btn-primary" onclick="resetForm()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Nouveau Client
        </a>
    </div>

    <?php if (empty($clients)): ?>
        <p style="color: var(--text-light); text-align: center; padding: 40px;">Aucun client pour le moment. Créez votre premier client !</p>
    <?php else: ?>
        <div class="clients-grid">
            <?php foreach ($clients as $client): ?>
                <div class="client-card <?= $client['active'] ? '' : 'inactive' ?>">
                    <div class="client-header">
                        <span class="client-icon"><?= htmlspecialchars($client['chatbot_icon'] ?? '💬') ?></span>
                        <div class="client-info">
                            <div class="client-name"><?= htmlspecialchars($client['name']) ?></div>
                            <div class="client-email"><?= htmlspecialchars($client['email']) ?></div>
                            <?php if ($client['website']): ?>
                                <a href="<?= htmlspecialchars($client['website']) ?>" target="_blank" class="client-website"><?= htmlspecialchars(parse_url($client['website'], PHP_URL_HOST)) ?></a>
                            <?php endif; ?>
                        </div>
                        <span class="client-status <?= $client['active'] ? 'active' : '' ?>">
                            <?= $client['active'] ? 'Actif' : 'Inactif' ?>
                        </span>
                    </div>

                    <div class="client-stats">
                        <div class="client-stat">
                            <span class="stat-num"><?= number_format($client['messages_30d'] ?: 0) ?></span>
                            <span class="stat-txt">messages</span>
                        </div>
                        <div class="client-stat">
                            <span class="stat-num"><?= number_format($client['conversations_30d'] ?: 0) ?></span>
                            <span class="stat-txt">conversations</span>
                        </div>
                    </div>

                    <div class="client-actions">
                        <a href="?edit=<?= $client['id'] ?>#form-client" class="btn btn-secondary btn-sm">Modifier</a>
                        <a href="client-stats.php?id=<?= $client['id'] ?>" class="btn btn-stats btn-sm" title="Statistiques détaillées">📊 Stats</a>
                        <a href="client-chatbot-fields.php?id=<?= $client['id'] ?>" class="btn btn-info btn-sm" title="Informations métier">📋 Infos</a>
                        <a href="client-chatbot-knowledge.php?id=<?= $client['id'] ?>" class="btn btn-knowledge btn-sm" title="Base de connaissances">📚 Apprentissage</a>
                        <a href="client-agents.php?id=<?= $client['id'] ?>" class="btn btn-agents btn-sm" title="Gestion des agents commerciaux">👥 Agents</a>
                        <button type="button" class="btn btn-primary btn-sm" onclick="showClientConfig(<?= htmlspecialchars(json_encode([
                            'name' => $client['name'],
                            'email' => $client['email'],
                            'apiKey' => $client['api_key']
                        ])) ?>)">
                            ⚙️ Config
                        </button>
                        <form method="POST" style="display: inline;">
                            <?= CSRF::inputField() ?>
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $client['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm"><?= $client['active'] ? 'Désactiver' : 'Activer' ?></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Formulaire client -->
<div class="card" id="form-client">
    <h2 class="card-title"><?= $editClient ? 'Modifier : ' . htmlspecialchars($editClient['name']) : 'Nouveau Client' ?></h2>

    <form method="POST">
        <?= CSRF::inputField() ?>
        <input type="hidden" name="action" value="<?= $editClient ? 'edit' : 'add' ?>">
        <?php if ($editClient): ?>
            <input type="hidden" name="id" value="<?= $editClient['id'] ?>">
        <?php endif; ?>

        <!-- Informations client -->
        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: var(--text);">Informations Client</h3>

            <div class="grid-2" style="gap: 20px;">
                <div>
                    <div class="form-group">
                        <label class="form-label">Nom du client / Entreprise *</label>
                        <input type="text" name="name" class="form-input" required
                               value="<?= htmlspecialchars($editClient['name'] ?? '') ?>"
                               placeholder="Ex: Restaurant Le Gourmet">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email (identifiant de connexion) *</label>
                        <input type="email" name="email" class="form-input" required
                               value="<?= htmlspecialchars($editClient['email'] ?? '') ?>"
                               placeholder="client@exemple.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mot de passe <?= $editClient ? '(laisser vide pour ne pas changer)' : '*' ?></label>
                        <div style="display: flex; gap: 8px;">
                            <input type="text" name="password" id="password-field" class="form-input"
                                   <?= $editClient ? '' : 'required' ?>
                                   value="<?= $editClient ? '' : htmlspecialchars($generatedPassword) ?>"
                                   placeholder="<?= $editClient ? 'Laisser vide pour conserver' : '' ?>">
                            <?php if (!$editClient): ?>
                                <button type="button" class="btn btn-secondary" onclick="regeneratePassword()" title="Générer un nouveau mot de passe">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.65 6.35C16.2 4.9 14.21 4 12 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08c-.82 2.33-3.04 4-5.65 4-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/></svg>
                                </button>
                            <?php endif; ?>
                        </div>
                        <?php if (!$editClient): ?>
                            <p class="form-hint">Mot de passe généré automatiquement. Vous pouvez le modifier.</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div>
                    <div class="form-group">
                        <label class="form-label">Site web</label>
                        <input type="url" name="website" class="form-input"
                               value="<?= htmlspecialchars($editClient['website'] ?? '') ?>"
                               placeholder="https://www.exemple.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Téléphone</label>
                        <input type="text" name="phone" class="form-input"
                               value="<?= htmlspecialchars($editClient['phone'] ?? '') ?>"
                               placeholder="06 12 34 56 78">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Raison sociale</label>
                        <input type="text" name="company" class="form-input"
                               value="<?= htmlspecialchars($editClient['company'] ?? '') ?>"
                               placeholder="SARL Le Gourmet">
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Notes internes</label>
                <textarea name="notes" class="form-textarea" rows="2" placeholder="Notes visibles uniquement par vous..."><?= htmlspecialchars($editClient['notes'] ?? '') ?></textarea>
            </div>

            <div style="display: flex; gap: 24px; flex-wrap: wrap;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="active" value="1" <?= ($editClient['active'] ?? true) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span>Client actif</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="show_on_site" value="1" <?= ($editClient['show_on_site'] ?? false) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span>Afficher sur la page démo</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="show_face" value="1" <?= ($editClient['show_face'] ?? false) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span>Visage animé</span>
                </label>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="show_hat" value="1" <?= ($editClient['show_hat'] ?? false) ? 'checked' : '' ?> style="width: 18px; height: 18px;">
                    <span>Chapeau</span>
                </label>
            </div>
            <p class="form-hint" style="margin-top: 8px;">
                <strong>Actif</strong> = le chatbot fonctionne |
                <strong>Démo</strong> = visible sur la page démo |
                <strong>Visage</strong> = yeux et bouche animés |
                <strong>Chapeau</strong> = chapeau sur le visage
            </p>

            <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 16px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Couleur du visage</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="color" name="face_color"
                               value="<?= htmlspecialchars($editClient['face_color'] ?? '#6366f1') ?>"
                               style="width: 50px; height: 36px; border: none; cursor: pointer;">
                        <input type="text" class="form-input" style="width: 100px; height: 36px;"
                               value="<?= htmlspecialchars($editClient['face_color'] ?? '#6366f1') ?>"
                               onchange="this.previousElementSibling.value = this.value"
                               oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Couleur du chapeau</label>
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <input type="color" name="hat_color"
                               value="<?= htmlspecialchars($editClient['hat_color'] ?? '#1e293b') ?>"
                               style="width: 50px; height: 36px; border: none; cursor: pointer;">
                        <input type="text" class="form-input" style="width: 100px; height: 36px;"
                               value="<?= htmlspecialchars($editClient['hat_color'] ?? '#1e293b') ?>"
                               onchange="this.previousElementSibling.value = this.value"
                               oninput="this.previousElementSibling.value = this.value">
                    </div>
                </div>
            </div>
        </div>

        <!-- Configuration chatbot -->
        <div style="background: #eff6ff; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
            <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 16px; color: #1e40af;">Configuration du Chatbot</h3>

            <div class="grid-2" style="gap: 20px;">
                <div>
                    <div style="display: flex; gap: 12px;">
                        <div class="form-group" style="width: 100px;">
                            <label class="form-label">Icône</label>
                            <input type="text" name="chatbot_icon" class="form-input" maxlength="4"
                                   value="<?= htmlspecialchars($editClient['chatbot_icon'] ?? '💬') ?>"
                                   style="font-size: 24px; text-align: center;">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label class="form-label">Nom du chatbot</label>
                            <input type="text" name="chatbot_name" class="form-input"
                                   value="<?= htmlspecialchars($editClient['chatbot_name'] ?? 'Assistant') ?>"
                                   placeholder="Assistant">
                        </div>
                        <div class="form-group" style="width: 80px;">
                            <label class="form-label">Couleur</label>
                            <input type="color" name="chatbot_color" style="width: 100%; height: 42px; border: none; cursor: pointer;"
                                   value="<?= htmlspecialchars($editClient['chatbot_color'] ?? '#6366f1') ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Message de bienvenue</label>
                        <textarea name="welcome_message" class="form-textarea" rows="2"><?= htmlspecialchars($editClient['welcome_message'] ?? "Bonjour ! Comment puis-je vous aider ?") ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Questions suggérées</label>
                        <textarea name="quick_actions" class="form-textarea" rows="3" placeholder="Une question par ligne"><?= htmlspecialchars($editClient['quick_actions'] ?? "Demander un devis\nEn savoir plus\nContact") ?></textarea>
                        <p class="form-hint">Boutons affichés en bas du chat (une par ligne)</p>
                    </div>
                </div>

                <div>
                    <div class="form-group">
                        <label class="form-label">Domaines autorisés</label>
                        <textarea name="allowed_domains" class="form-textarea" rows="3" placeholder="Un domaine par ligne"><?= htmlspecialchars($editClient['allowed_domains'] ?? '') ?></textarea>
                        <p class="form-hint">Sécurité : seuls ces domaines pourront utiliser le chatbot (laisser vide = tous)</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Message anti-abus</label>
                        <textarea name="redirect_message" class="form-textarea" rows="3"><?= htmlspecialchars($editClient['redirect_message'] ?? "Je suis un assistant spécialisé pour ce site. Comment puis-je vous aider concernant nos services ?") ?></textarea>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Prompt Système (comportement de l'IA)</label>
                <textarea name="system_prompt" class="form-textarea" rows="8" style="font-family: monospace; font-size: 13px;"><?= htmlspecialchars($editClient['system_prompt'] ?? "Tu es l'assistant virtuel de cette entreprise. Tu aides les visiteurs avec leurs questions sur les services, les tarifs et la prise de rendez-vous.

Tu es professionnel, amical et concis. Tu réponds uniquement aux questions en rapport avec l'entreprise.

Pour toute question hors sujet, tu réponds poliment que tu es spécialisé pour cette entreprise.") ?></textarea>
            </div>

            <!-- Prise de rendez-vous -->
            <div style="background: #ecfdf5; padding: 16px; border-radius: 8px; margin-top: 16px; border-left: 3px solid #10b981;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #065f46;">Prise de Rendez-vous</h4>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 12px;">
                    <input type="checkbox" name="booking_enabled" value="1"
                           <?= ($editClient['booking_enabled'] ?? false) ? 'checked' : '' ?>
                           style="width: 18px; height: 18px;"
                           onchange="document.getElementById('client-booking-fields').style.display = this.checked ? '' : 'none'">
                    <span style="font-weight: 500;">Activer la prise de RDV</span>
                </label>
                <div id="client-booking-fields" style="<?= ($editClient['booking_enabled'] ?? false) ? '' : 'display: none;' ?>">
                    <div class="grid-2" style="gap: 12px;">
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Google Calendar ID</label>
                            <input type="text" name="google_calendar_id" class="form-input"
                                   value="<?= htmlspecialchars($editClient['google_calendar_id'] ?? '') ?>"
                                   placeholder="exemple@group.calendar.google.com">
                        </div>
                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label">Email de notification</label>
                            <input type="email" name="notification_email" class="form-input"
                                   value="<?= htmlspecialchars($editClient['notification_email'] ?? '') ?>"
                                   placeholder="client@email.com">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mode Multi-Agent -->
            <div style="background: #fef3c7; padding: 16px; border-radius: 8px; margin-top: 16px; border-left: 3px solid #f59e0b;">
                <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px; color: #92400e;">
                    👥 Mode Multi-Agent
                    <span style="font-weight: 400; font-size: 12px; color: #b45309;">(Agences, équipes commerciales)</span>
                </h4>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 12px;">
                    <input type="checkbox" name="multi_agent_enabled" value="1"
                           <?= ($editClient['multi_agent_enabled'] ?? false) ? 'checked' : '' ?>
                           style="width: 18px; height: 18px;"
                           onchange="document.getElementById('multi-agent-info').style.display = this.checked ? '' : 'none'">
                    <span style="font-weight: 500;">Activer le mode Multi-Agent</span>
                </label>
                <div id="multi-agent-info" style="<?= ($editClient['multi_agent_enabled'] ?? false) ? '' : 'display: none;' ?>">
                    <p style="font-size: 13px; color: #92400e; margin: 0 0 12px 0;">
                        Ce mode permet de gérer plusieurs commerciaux/agents, chacun avec son propre agenda Google Calendar.
                        Les RDV seront automatiquement distribués selon le mode choisi (tour à tour, par disponibilité, par spécialité, ou choix du visiteur).
                    </p>
                    <?php if ($editClient): ?>
                        <a href="client-agents.php?id=<?= $editClient['id'] ?>" class="btn btn-secondary btn-sm" style="background: #fef3c7; color: #92400e; border: 1px solid #f59e0b;">
                            👥 Gérer les agents de ce client
                        </a>
                    <?php else: ?>
                        <p style="font-size: 12px; color: #b45309; margin: 0;">
                            <em>Enregistrez le client d'abord, puis vous pourrez ajouter des agents.</em>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($editClient): ?>
            <!-- Clé API -->
            <div style="background: #fef3c7; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #92400e;">Clé API & Intégration</h3>
                <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                    <code style="background: white; padding: 10px 16px; border-radius: 8px; font-size: 12px; flex: 1; min-width: 200px; overflow: auto;"><?= htmlspecialchars($editClient['api_key']) ?></code>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="copyToClipboard('<?= htmlspecialchars($editClient['api_key']) ?>')">Copier</button>
                    <button type="button" class="btn btn-secondary btn-sm" onclick="showEmbedCode('<?= htmlspecialchars($editClient['api_key']) ?>', '<?= htmlspecialchars($editClient['chatbot_name'] ?? 'Assistant') ?>')">Voir le code</button>
                </div>
                <div style="margin-top: 12px;">
                    <button type="button" class="btn btn-secondary btn-sm" style="background: #fef3c7; color: #92400e;" onclick="regenerateApiKey()">Régénérer la clé</button>
                </div>
            </div>

            <!-- Configuration à envoyer au client -->
            <div style="background: #ecfdf5; padding: 20px; border-radius: 12px; margin-bottom: 24px;">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 12px; color: #065f46;">Configuration à envoyer au client</h3>
                <p style="color: #047857; font-size: 13px; margin-bottom: 16px;">Sélectionnez les informations à inclure puis copiez le texte généré.</p>

                <div style="display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="include-credentials" checked style="width: 18px; height: 18px;">
                        <span>Identifiants de connexion</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="include-portal-link" checked style="width: 18px; height: 18px;">
                        <span>Lien espace client</span>
                    </label>
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" id="include-embed-code" checked style="width: 18px; height: 18px;">
                        <span>Code d'intégration</span>
                    </label>
                </div>

                <div style="background: white; padding: 16px; border-radius: 8px; margin-bottom: 12px;">
                    <pre id="client-config-text" style="white-space: pre-wrap; font-size: 13px; margin: 0; color: var(--text);"></pre>
                </div>

                <button type="button" class="btn btn-primary" onclick="copyClientConfig()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M16 1H4c-1.1 0-2 .9-2 2v14h2V3h12V1zm3 4H8c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h11c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm0 16H8V7h11v14z"/></svg>
                    Copier la configuration
                </button>
            </div>

            <script>
            // Données du client pour la config
            const clientData = {
                name: <?= json_encode($editClient['name']) ?>,
                email: <?= json_encode($editClient['email']) ?>,
                apiKey: <?= json_encode($editClient['api_key']) ?>,
                portalUrl: 'https://chatbot.myziggi.pro/client/login',
                embedCode: '<scr' + 'ipt src="https://chatbot.myziggi.pro/widget.js?v=<?= WIDGET_VERSION ?>" data-key="<?= htmlspecialchars($editClient['api_key']) ?>"></scr' + 'ipt>',
                embedCodeWP: '<scr' + 'ipt>window.ChatbotConfig = { apiKey: \'' + '<?= htmlspecialchars($editClient['api_key']) ?>' + '\' };</scr' + 'ipt>\n<scr' + 'ipt src="https://chatbot.myziggi.pro/widget.js?v=<?= WIDGET_VERSION ?>"></scr' + 'ipt>'
            };

            function updateClientConfig() {
                const includeCredentials = document.getElementById('include-credentials').checked;
                const includePortalLink = document.getElementById('include-portal-link').checked;
                const includeEmbedCode = document.getElementById('include-embed-code').checked;

                let config = `Configuration ChatBot IA pour ${clientData.name}\n`;
                config += '═'.repeat(50) + '\n\n';

                if (includeCredentials) {
                    config += `📧 IDENTIFIANTS DE CONNEXION\n`;
                    config += `Email : ${clientData.email}\n`;
                    config += `Mot de passe : (celui défini à la création)\n\n`;
                }

                if (includePortalLink) {
                    config += `🔗 ESPACE CLIENT\n`;
                    config += `${clientData.portalUrl}\n`;
                    config += `Connectez-vous pour configurer votre chatbot.\n\n`;
                }

                if (includeEmbedCode) {
                    config += `💻 CODE D'INTÉGRATION\n`;
                    config += `Copiez ce code juste avant </body> sur votre site :\n\n`;
                    config += `${clientData.embedCode}\n\n`;
                    config += `⚠️ WordPress avec plugin de cache (WP Rocket, Autoptimize...) :\n`;
                    config += `${clientData.embedCodeWP}\n\n`;
                    config += `WordPress : Installez le plugin "WPCode" et collez le code dans Footer.\n`;
                    config += `IMPORTANT : Excluez le widget.js de la minification si possible.\n`;
                }

                document.getElementById('client-config-text').textContent = config;
            }

            function copyClientConfig() {
                const config = document.getElementById('client-config-text').textContent;
                navigator.clipboard.writeText(config).then(() => {
                    alert('Configuration copiée !');
                });
            }

            // Mettre à jour quand les cases changent
            document.getElementById('include-credentials').addEventListener('change', updateClientConfig);
            document.getElementById('include-portal-link').addEventListener('change', updateClientConfig);
            document.getElementById('include-embed-code').addEventListener('change', updateClientConfig);

            // Initialiser
            updateClientConfig();
            </script>
        <?php endif; ?>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <button type="submit" class="btn btn-primary">
                <?= $editClient ? 'Mettre à jour' : 'Créer le client' ?>
            </button>
            <?php if ($editClient): ?>
                <a href="clients.php" class="btn btn-secondary">Annuler</a>
                <button type="button" class="btn btn-danger" style="margin-left: auto;" onclick="deleteClient(<?= $editClient['id'] ?>)">Supprimer</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($editClient): ?>
<!-- Formulaire de suppression séparé -->
<form id="delete-form" method="POST" style="display: none;">
    <?= CSRF::inputField() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" value="<?= $editClient['id'] ?>">
</form>
<!-- Formulaire de régénération de clé séparé -->
<form id="regenerate-key-form" method="POST" style="display: none;">
    <?= CSRF::inputField() ?>
    <input type="hidden" name="action" value="regenerate_key">
    <input type="hidden" name="id" value="<?= $editClient['id'] ?>">
</form>
<?php endif; ?>

<!-- Modal code d'intégration -->
<div id="embed-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 32px; border-radius: 16px; max-width: 700px; width: 90%; max-height: 90vh; overflow: auto;">
        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">Code d'intégration</h3>
        <p style="color: var(--text-light); margin-bottom: 20px;">Copiez ce code et collez-le dans le site de votre client, juste avant la balise <code>&lt;/body&gt;</code></p>

        <div style="background: #1e293b; padding: 20px; border-radius: 12px; margin-bottom: 20px;">
            <pre id="embed-code" style="color: #e2e8f0; font-size: 13px; white-space: pre-wrap; margin: 0;"></pre>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="button" class="btn btn-primary" onclick="copyEmbedCode()">Copier le code</button>
            <button type="button" class="btn btn-secondary" onclick="closeEmbedModal()">Fermer</button>
        </div>

        <div style="margin-top: 20px; padding: 16px; background: #f0fdf4; border-radius: 8px;">
            <h4 style="font-size: 14px; font-weight: 600; color: #166534; margin-bottom: 8px;">WordPress</h4>
            <p style="font-size: 13px; color: #15803d; margin: 0;">Installez le plugin "WPCode" ou "Insert Headers and Footers", puis collez ce code dans la section Footer.</p>
        </div>
    </div>
</div>

<!-- Modal configuration client -->
<div id="config-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; padding: 32px; border-radius: 16px; max-width: 700px; width: 90%; max-height: 90vh; overflow: auto;">
        <h3 style="font-size: 20px; font-weight: 600; margin-bottom: 16px;">Configuration à envoyer</h3>
        <p style="color: var(--text-light); margin-bottom: 20px;">Sélectionnez les informations à inclure :</p>

        <div style="display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 16px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="modal-include-credentials" checked style="width: 18px; height: 18px;">
                <span>Identifiants de connexion</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="modal-include-portal" checked style="width: 18px; height: 18px;">
                <span>Lien espace client</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" id="modal-include-embed" checked style="width: 18px; height: 18px;">
                <span>Code d'intégration</span>
            </label>
        </div>

        <div style="background: #f8fafc; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
            <pre id="modal-config-text" style="white-space: pre-wrap; font-size: 13px; margin: 0; color: var(--text);"></pre>
        </div>

        <div style="display: flex; gap: 12px;">
            <button type="button" class="btn btn-primary" onclick="copyModalConfig()">Copier la configuration</button>
            <button type="button" class="btn btn-secondary" onclick="closeConfigModal()">Fermer</button>
        </div>
    </div>
</div>

<style>
.clients-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 20px; }
.client-card { background: #f8fafc; border-radius: 12px; padding: 20px; border-left: 4px solid var(--primary); }
.client-card.inactive { opacity: 0.6; border-left-color: #94a3b8; }
.client-header { display: flex; gap: 12px; margin-bottom: 16px; }
.client-icon { font-size: 32px; }
.client-info { flex: 1; }
.client-name { font-weight: 600; font-size: 16px; }
.client-email { font-size: 13px; color: var(--text-light); }
.client-website { font-size: 12px; color: var(--primary); text-decoration: none; }
.client-status { font-size: 11px; padding: 4px 10px; border-radius: 12px; background: #fee2e2; color: #991b1b; height: fit-content; }
.client-status.active { background: #dcfce7; color: #166534; }
.client-stats { display: flex; gap: 24px; margin-bottom: 16px; padding: 12px; background: white; border-radius: 8px; }
.client-stat { text-align: center; }
.stat-num { display: block; font-size: 20px; font-weight: 700; color: var(--primary); }
.stat-txt { font-size: 11px; color: var(--text-light); }
.client-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.btn-sm { padding: 6px 12px; font-size: 12px; }
.btn-danger { background: #fee2e2; color: #991b1b; }
.btn-danger:hover { background: #fecaca; }
.btn-info { background: #d1fae5; color: #047857; }
.btn-info:hover { background: #a7f3d0; }
.btn-knowledge { background: #dbeafe; color: #1d4ed8; }
.btn-knowledge:hover { background: #bfdbfe; }
.btn-agents { background: #fef3c7; color: #92400e; }
.btn-agents:hover { background: #fde68a; }
.btn-stats { background: #f3e8ff; color: #7c3aed; }
.btn-stats:hover { background: #e9d5ff; }
</style>

<script>
function resetForm() {
    const form = document.querySelector('#form-client form');
    form.reset();
    form.querySelector('input[name="action"]').value = 'add';
    const idInput = form.querySelector('input[name="id"]');
    if (idInput) idInput.remove();
    document.querySelector('#form-client .card-title').textContent = 'Nouveau Client';
}

function deleteClient(id) {
    if (confirm('Supprimer ce client et toutes ses données ?')) {
        document.getElementById('delete-form').submit();
    }
}

function regenerateApiKey() {
    if (confirm('Régénérer la clé API ? Le code d\'intégration devra être mis à jour chez le client.')) {
        document.getElementById('regenerate-key-form').submit();
    }
}

function showEmbedCode(apiKey, chatbotName) {
    const standardCode = '<!-- Chatbot Widget (Standard) -->\n' +
        '<scr' + 'ipt src="https://chatbot.myziggi.pro/widget.js?v=<?= WIDGET_VERSION ?>" data-key="' + apiKey + '"></scr' + 'ipt>';

    const wpCode = '<!-- Chatbot Widget (WordPress avec cache) -->\n' +
        '<scr' + 'ipt>window.ChatbotConfig = { apiKey: \'' + apiKey + '\' };</scr' + 'ipt>\n' +
        '<scr' + 'ipt src="https://chatbot.myziggi.pro/widget.js?v=<?= WIDGET_VERSION ?>"></scr' + 'ipt>';

    const fullCode = `${standardCode}\n\n─── OU si le site WordPress a un plugin de cache/minification ───\n\n${wpCode}`;

    document.getElementById('embed-code').textContent = fullCode;
    document.getElementById('embed-modal').style.display = 'flex';
}

function closeEmbedModal() {
    document.getElementById('embed-modal').style.display = 'none';
}

function copyEmbedCode() {
    const code = document.getElementById('embed-code').textContent;
    navigator.clipboard.writeText(code).then(() => {
        alert('Code copié !');
    });
}

function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        alert('Copié !');
    });
}

function regeneratePassword() {
    const chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('password-field').value = password;
}

// Fermer modal en cliquant dehors
document.getElementById('embed-modal').addEventListener('click', function(e) {
    if (e.target === this) closeEmbedModal();
});

document.getElementById('config-modal').addEventListener('click', function(e) {
    if (e.target === this) closeConfigModal();
});

// Configuration client depuis la liste
let currentClientConfig = null;

function showClientConfig(client) {
    currentClientConfig = client;
    updateModalConfig();
    document.getElementById('config-modal').style.display = 'flex';
}

function closeConfigModal() {
    document.getElementById('config-modal').style.display = 'none';
}

function updateModalConfig() {
    if (!currentClientConfig) return;

    const includeCredentials = document.getElementById('modal-include-credentials').checked;
    const includePortal = document.getElementById('modal-include-portal').checked;
    const includeEmbed = document.getElementById('modal-include-embed').checked;

    const embedCode = '<scr' + 'ipt src="https://chatbot.myziggi.pro/widget.js?v=<?= WIDGET_VERSION ?>" data-key="' + currentClientConfig.apiKey + '"></scr' + 'ipt>';

    let config = `Configuration ChatBot IA pour ${currentClientConfig.name}\n`;
    config += '═'.repeat(50) + '\n\n';

    if (includeCredentials) {
        config += `📧 IDENTIFIANTS DE CONNEXION\n`;
        config += `Email : ${currentClientConfig.email}\n`;
        config += `Mot de passe : (communiqué séparément)\n\n`;
    }

    if (includePortal) {
        config += `🔗 ESPACE CLIENT\n`;
        config += `https://chatbot.myziggi.pro/client/login\n`;
        config += `Connectez-vous pour configurer votre chatbot.\n\n`;
    }

    if (includeEmbed) {
        const standardCode = '<scr' + 'ipt src="https://chatbot.myziggi.pro/widget.js?v=<?= WIDGET_VERSION ?>" data-key="' + currentClientConfig.apiKey + '"></scr' + 'ipt>';
        const wpCode = '<scr' + 'ipt>window.ChatbotConfig = { apiKey: \'' + currentClientConfig.apiKey + '\' };</scr' + 'ipt>\n' +
            '<scr' + 'ipt src="https://chatbot.myziggi.pro/widget.js?v=<?= WIDGET_VERSION ?>"></scr' + 'ipt>';

        config += `💻 CODE D'INTÉGRATION\n`;
        config += `Copiez ce code juste avant </body> sur votre site :\n\n`;
        config += `${standardCode}\n\n`;
        config += `⚠️ WordPress avec plugin de cache (WP Rocket, Autoptimize...) :\n`;
        config += `${wpCode}\n\n`;
        config += `WordPress : Installez le plugin "WPCode" et collez le code dans Footer.\n`;
        config += `IMPORTANT : Excluez le widget.js de la minification si possible.\n`;
    }

    document.getElementById('modal-config-text').textContent = config;
}

function copyModalConfig() {
    const config = document.getElementById('modal-config-text').textContent;
    navigator.clipboard.writeText(config).then(() => {
        alert('Configuration copiée !');
    });
}

// Mettre à jour quand les cases changent
document.getElementById('modal-include-credentials').addEventListener('change', updateModalConfig);
document.getElementById('modal-include-portal').addEventListener('change', updateModalConfig);
document.getElementById('modal-include-embed').addEventListener('change', updateModalConfig);
</script>

<?php require_once 'includes/footer.php'; ?>
