<?php
/**
 * Gestion des chatbots de démonstration
 */

$pageTitle = 'Chatbots Démo';
require_once 'includes/header.php';

$success = '';
$error = '';
$editBot = null;

// Vérifier si la table existe
try {
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'demo_chatbots'");
    if (!$tableExists) {
        $error = "Les tables n'ont pas été créées. <a href='update-demo-system.php?key=update_demo_2024'>Cliquez ici pour lancer la mise à jour</a>.";
    }
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données.";
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add':
            case 'edit':
                $id = $_POST['id'] ?? null;
                $slug = trim($_POST['slug'] ?? '');
                $name = trim($_POST['name'] ?? '');
                $icon = trim($_POST['icon'] ?? '💬');
                $color = $_POST['color'] ?? '#6366f1';
                $welcome = trim($_POST['welcome_message'] ?? '');
                $prompt = trim($_POST['system_prompt'] ?? '');
                $redirect = trim($_POST['redirect_message'] ?? '');
                $quickActions = trim($_POST['quick_actions'] ?? '');
                $active = isset($_POST['active']) ? 1 : 0;
                $sortOrder = intval($_POST['sort_order'] ?? 0);

                if (empty($slug) || empty($name) || empty($prompt)) {
                    throw new Exception('Le slug, le nom et le prompt système sont obligatoires.');
                }

                if (!preg_match('/^[a-z0-9_]+$/', $slug)) {
                    throw new Exception('Le slug ne doit contenir que des lettres minuscules, chiffres et underscores.');
                }

                if ($action === 'edit' && $id) {
                    $sql = "UPDATE demo_chatbots SET slug=?, name=?, icon=?, color=?, welcome_message=?, system_prompt=?, redirect_message=?, quick_actions=?, active=?, sort_order=? WHERE id=?";
                    $db->query($sql, [$slug, $name, $icon, $color, $welcome, $prompt, $redirect, $quickActions, $active, $sortOrder, $id]);
                    $success = "Chatbot \"$name\" mis à jour !";
                } else {
                    $sql = "INSERT INTO demo_chatbots (slug, name, icon, color, welcome_message, system_prompt, redirect_message, quick_actions, active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $db->query($sql, [$slug, $name, $icon, $color, $welcome, $prompt, $redirect, $quickActions, $active, $sortOrder]);
                    $success = "Chatbot \"$name\" créé !";
                }
                break;

            case 'delete':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $db->query("DELETE FROM demo_chatbots WHERE id = ?", [$id]);
                    $success = "Chatbot supprimé !";
                }
                break;

            case 'toggle':
                $id = $_POST['id'] ?? null;
                if ($id) {
                    $db->query("UPDATE demo_chatbots SET active = NOT active WHERE id = ?", [$id]);
                    $success = "Statut mis à jour !";
                }
                break;

            case 'update_limit':
                $limit = intval($_POST['daily_limit'] ?? 10);
                $settings->set('demo_daily_limit', $limit, 'integer', 'demo', 'Limite messages/jour');
                $success = "Limite mise à jour : $limit messages/jour";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer le chatbot à éditer
if (isset($_GET['edit']) && empty($error)) {
    $editBot = $db->fetchOne("SELECT * FROM demo_chatbots WHERE id = ?", [$_GET['edit']]);
}

// Récupérer tous les chatbots
$chatbots = [];
$todayUsage = ['users' => 0, 'messages' => 0];
$dailyLimit = 10;

if (empty($error) || strpos($error, 'tables') === false) {
    try {
        $chatbots = $db->fetchAll("SELECT * FROM demo_chatbots ORDER BY sort_order ASC, name ASC");
        $dailyLimit = $settings->get('demo_daily_limit') ?: 10;

        // Stats d'utilisation
        $usageTable = $db->fetchOne("SHOW TABLES LIKE 'demo_usage'");
        if ($usageTable) {
            $todayUsage = $db->fetchOne("SELECT COUNT(DISTINCT identifier) as users, COALESCE(SUM(message_count), 0) as messages FROM demo_usage WHERE date = CURDATE()") ?: ['users' => 0, 'messages' => 0];
        }
    } catch (Exception $e) {
        // Silently fail
    }
}
?>

<div class="page-header">
    <h1 class="page-title">Chatbots de Démonstration</h1>
    <p class="page-subtitle">Gérez les chatbots sectoriels et la limite d'utilisation</p>
</div>

<!-- Guide de configuration -->
<div class="card guide-card" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-left: 4px solid #f59e0b; margin-bottom: 24px;">
    <details>
        <summary style="cursor: pointer; display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 28px;">📖</span>
            <div>
                <h2 style="margin: 0; font-size: 18px; color: #92400e;">Guide de configuration du chatbot</h2>
                <p style="margin: 4px 0 0 0; font-size: 14px; color: #a16207;">Cliquez pour voir les étapes de configuration</p>
            </div>
        </summary>
        <div style="margin-top: 20px; background: white; border-radius: 12px; padding: 20px;">
            <div class="guide-steps">
                <div class="guide-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Créer le chatbot</h4>
                        <p>Remplissez le formulaire ci-dessous avec le slug (identifiant unique), le nom, l'icône et la couleur du chatbot.</p>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Configurer le prompt système</h4>
                        <p>C'est le coeur du chatbot ! Définissez :</p>
                        <ul>
                            <li><strong>L'identité</strong> : Rôle et secteur d'activité</li>
                            <li><strong>Les règles</strong> : Ce que le chatbot peut/ne peut pas faire</li>
                            <li><strong>Le placeholder</strong> : Ajoutez <code>{CHATBOT_FIELDS}</code> pour injecter automatiquement les informations métier</li>
                        </ul>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Personnaliser les messages</h4>
                        <ul>
                            <li><strong>Message de bienvenue</strong> : Premier message affiché au visiteur</li>
                            <li><strong>Message anti-abus</strong> : Réponse quand la question est hors sujet</li>
                        </ul>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>📋 Renseigner les informations métier</h4>
                        <p>Cliquez sur <strong>"📋 Informations"</strong> pour remplir les données de l'entreprise :</p>
                        <ul>
                            <li>Coordonnées (adresse, téléphone, email, horaires)</li>
                            <li>Tarifs et prestations</li>
                            <li>Zone d'intervention</li>
                            <li>Informations légales (SIRET, assurances...)</li>
                        </ul>
                        <p style="margin-top: 8px; font-size: 13px; color: #059669;"><strong>Ces informations sont automatiquement utilisées par l'IA pour répondre aux visiteurs.</strong></p>
                    </div>
                </div>
                <div class="guide-step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <h4>📚 Enrichir avec l'apprentissage</h4>
                        <p>Ajoutez des FAQ et informations spécifiques dans la <strong>Base de Connaissances</strong> pour des réponses encore plus précises.</p>
                        <a href="chatbot-knowledge.php" class="btn btn-primary btn-sm" style="margin-top: 8px;">Accéder à l'apprentissage</a>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; padding: 16px; background: #eff6ff; border-radius: 8px;">
                <h4 style="margin: 0 0 8px 0; color: #1d4ed8;">Conseils pour un chatbot efficace</h4>
                <ul style="margin: 0; padding-left: 20px; color: #1e40af;">
                    <li><strong>Remplissez les informations métier</strong> : elles sont injectées automatiquement dans l'IA</li>
                    <li>Utilisez le placeholder <code>{CHATBOT_FIELDS}</code> dans le prompt pour positionner les infos</li>
                    <li>Ajoutez des FAQ dans l'apprentissage pour les questions fréquentes</li>
                    <li>Testez régulièrement le chatbot avec différentes questions</li>
                </ul>
            </div>
        </div>
    </details>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<!-- Stats et Configuration -->
<div class="grid-2" style="margin-bottom: 24px;">
    <div class="card">
        <h2 class="card-title">Utilisation Aujourd'hui</h2>
        <div style="display: flex; gap: 40px;">
            <div>
                <div style="font-size: 36px; font-weight: 700; color: var(--primary);"><?= number_format($todayUsage['users'] ?? 0) ?></div>
                <div style="color: var(--text-light); font-size: 14px;">Utilisateurs</div>
            </div>
            <div>
                <div style="font-size: 36px; font-weight: 700; color: var(--success);"><?= number_format($todayUsage['messages'] ?? 0) ?></div>
                <div style="color: var(--text-light); font-size: 14px;">Messages</div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 class="card-title">Limite d'Utilisation</h2>
        <form method="POST" style="display: flex; gap: 12px; align-items: flex-end;">
            <input type="hidden" name="action" value="update_limit">
            <div class="form-group" style="margin-bottom: 0; flex: 1;">
                <label class="form-label">Messages max / jour / utilisateur</label>
                <input type="number" name="daily_limit" class="form-input" min="1" max="100" value="<?= $dailyLimit ?>">
            </div>
            <button type="submit" class="btn btn-primary">Enregistrer</button>
        </form>
        <p class="form-hint" style="margin-top: 8px;">Identifié par IP + fingerprint navigateur. Réinitialisation à minuit.</p>
    </div>
</div>

<!-- Liste des chatbots -->
<div class="card">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h2 class="card-title" style="margin-bottom: 0; padding-bottom: 0; border: none;">Chatbots (<?= count($chatbots) ?>)</h2>
        <a href="#form-edit" class="btn btn-primary" onclick="resetForm()">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            Nouveau Chatbot
        </a>
    </div>

    <?php if (empty($chatbots)): ?>
        <p style="color: var(--text-light); text-align: center; padding: 40px;">Aucun chatbot configuré.</p>
    <?php else: ?>
        <div class="chatbots-grid">
            <?php foreach ($chatbots as $bot): ?>
                <div class="chatbot-card <?= $bot['active'] ? '' : 'inactive' ?>" style="border-left: 4px solid <?= htmlspecialchars($bot['color']) ?>;">
                    <div class="chatbot-header">
                        <span class="chatbot-icon"><?= htmlspecialchars($bot['icon']) ?></span>
                        <div>
                            <div class="chatbot-name"><?= htmlspecialchars($bot['name']) ?></div>
                            <code class="chatbot-slug"><?= htmlspecialchars($bot['slug']) ?></code>
                        </div>
                        <span class="chatbot-status <?= $bot['active'] ? 'active' : '' ?>">
                            <?= $bot['active'] ? 'Actif' : 'Inactif' ?>
                        </span>
                    </div>
                    <div class="chatbot-preview">
                        <?= htmlspecialchars(mb_substr($bot['welcome_message'] ?: 'Pas de message de bienvenue', 0, 80)) ?>...
                    </div>
                    <div class="chatbot-actions">
                        <a href="?edit=<?= $bot['id'] ?>#form-edit" class="btn btn-secondary btn-sm">Modifier</a>
                        <a href="chatbot-fields.php?id=<?= $bot['id'] ?>" class="btn btn-info btn-sm" title="Informations métier">📋 Informations</a>
                        <a href="chatbot-knowledge.php?id=<?= $bot['id'] ?>" class="btn btn-knowledge btn-sm" title="Base de connaissances">📚 Apprentissage</a>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= $bot['id'] ?>">
                            <button type="submit" class="btn btn-secondary btn-sm"><?= $bot['active'] ? 'Désactiver' : 'Activer' ?></button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer ce chatbot ?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $bot['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Formulaire -->
<div class="card" id="form-edit">
    <h2 class="card-title"><?= $editBot ? 'Modifier : ' . htmlspecialchars($editBot['name']) : 'Nouveau Chatbot' ?></h2>

    <form method="POST">
        <input type="hidden" name="action" value="<?= $editBot ? 'edit' : 'add' ?>">
        <?php if ($editBot): ?>
            <input type="hidden" name="id" value="<?= $editBot['id'] ?>">
        <?php endif; ?>

        <div class="grid-2" style="gap: 24px;">
            <div>
                <div class="form-group">
                    <label class="form-label">Slug (identifiant) *</label>
                    <input type="text" name="slug" class="form-input" required pattern="[a-z0-9_]+"
                           value="<?= htmlspecialchars($editBot['slug'] ?? '') ?>"
                           placeholder="ex: restaurant, garage, coiffure">
                    <p class="form-hint">Minuscules, chiffres, underscores</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Nom *</label>
                    <input type="text" name="name" class="form-input" required
                           value="<?= htmlspecialchars($editBot['name'] ?? '') ?>"
                           placeholder="ex: Restaurant & Traiteur">
                </div>

                <div style="display: flex; gap: 12px;">
                    <div class="form-group" style="width: 80px;">
                        <label class="form-label">Icône</label>
                        <input type="text" name="icon" class="form-input" maxlength="4"
                               value="<?= htmlspecialchars($editBot['icon'] ?? '💬') ?>"
                               style="font-size: 24px; text-align: center;">
                    </div>
                    <div class="form-group" style="width: 80px;">
                        <label class="form-label">Couleur</label>
                        <input type="color" name="color" style="width: 100%; height: 42px; border: none; cursor: pointer;"
                               value="<?= htmlspecialchars($editBot['color'] ?? '#6366f1') ?>">
                    </div>
                    <div class="form-group" style="width: 80px;">
                        <label class="form-label">Ordre</label>
                        <input type="number" name="sort_order" class="form-input" min="0"
                               value="<?= htmlspecialchars($editBot['sort_order'] ?? '0') ?>">
                    </div>
                    <div class="form-group" style="flex: 1; display: flex; align-items: flex-end;">
                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px;">
                            <input type="checkbox" name="active" value="1"
                                   <?= ($editBot['active'] ?? true) ? 'checked' : '' ?>
                                   style="width: 18px; height: 18px;">
                            <span>Actif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div>
                <div class="form-group">
                    <label class="form-label">Message de bienvenue</label>
                    <textarea name="welcome_message" class="form-textarea" rows="3"><?= htmlspecialchars($editBot['welcome_message'] ?? "Bonjour ! Je suis l'assistant de [NOM ENTREPRISE]. Comment puis-je vous aider aujourd'hui ?") ?></textarea>
                    <p class="form-hint">Premier message affiché au visiteur</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Message de redirection (anti-abus)</label>
                    <textarea name="redirect_message" class="form-textarea" rows="4"><?= htmlspecialchars($editBot['redirect_message'] ?? "Je suis l'assistant de [NOM ENTREPRISE] et je suis spécialisé dans [DOMAINE].

Je peux vous aider pour :
• Obtenir des informations sur nos services
• Demander un devis
• Prendre rendez-vous

Comment puis-je vous aider ?") ?></textarea>
                    <p class="form-hint">Affiché quand l'utilisateur pose une question hors sujet</p>
                </div>

                <div class="form-group">
                    <label class="form-label">Questions suggérées (Quick Actions)</label>
                    <textarea name="quick_actions" class="form-textarea" rows="3" placeholder="Une question par ligne"><?= htmlspecialchars($editBot['quick_actions'] ?? "Demander un devis\nEn savoir plus\nContact") ?></textarea>
                    <p class="form-hint">Boutons affichés en bas du chat. Une question par ligne (3 à 5 suggérées).</p>
                </div>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Prompt Système (Comportement IA) *</label>
            <textarea name="system_prompt" class="form-textarea" rows="12" required
                      style="font-family: monospace; font-size: 13px;"><?= htmlspecialchars($editBot['system_prompt'] ?? "Tu es EXCLUSIVEMENT l'assistant virtuel de [NOM ENTREPRISE], spécialisé dans [SECTEUR].

=== INFORMATIONS ===
- Entreprise : [Nom complet]
- Adresse : [Adresse]
- Téléphone : [Numéro]
- Horaires : [Horaires d'ouverture]

=== NOS PRESTATIONS ===
1. [Prestation 1] - [Description] - À partir de [Prix]
2. [Prestation 2] - [Description] - À partir de [Prix]

=== RÈGLES STRICTES ===
- Tu ne réponds QU'aux questions sur nos services
- Pour toute question hors sujet : \"Je suis l'assistant de [X] et je ne peux vous aider que pour [DOMAINE].\"
- Tu ne fais JAMAIS de programmation, traduction, devoirs ou rédaction

Tu es [PERSONNALITÉ : professionnel, chaleureux, etc.].") ?></textarea>
        </div>

        <details style="background: #f8fafc; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
            <summary style="cursor: pointer; font-weight: 600; color: var(--primary);">📋 Template basique (anti-abus)</summary>
            <pre id="template-basic" style="background: white; padding: 16px; border-radius: 8px; margin-top: 12px; font-size: 12px; white-space: pre-wrap;">Tu es EXCLUSIVEMENT un assistant virtuel pour [SECTEUR].

RÈGLES STRICTES :
- Tu ne réponds QU'aux questions sur : [SUJETS AUTORISÉS]
- Pour TOUTE question hors sujet, tu réponds : "Je suis l'assistant de [X] et je ne peux vous aider que pour [DOMAINE]."
- Tu ne fais JAMAIS de programmation, traduction, devoirs, rédaction
- Tu ne donnes pas de conseils médicaux, juridiques ou financiers

Ce que tu PEUX faire :
- [ACTION 1]
- [ACTION 2]

Tu es [PERSONNALITÉ].</pre>
            <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 12px;" onclick="document.querySelector('textarea[name=system_prompt]').value = document.getElementById('template-basic').textContent;">Utiliser ce template</button>
        </details>

        <details style="background: #f0fdf4; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
            <summary style="cursor: pointer; font-weight: 600; color: #166534;">📦 Template complet avec prestations</summary>
            <pre id="template-full" style="background: white; padding: 16px; border-radius: 8px; margin-top: 12px; font-size: 12px; white-space: pre-wrap;">Tu es EXCLUSIVEMENT l'assistant virtuel de [NOM ENTREPRISE], spécialisé dans [SECTEUR].

=== INFORMATIONS SUR L'ENTREPRISE ===
- Nom : [Nom complet de l'entreprise]
- Adresse : [Adresse complète]
- Téléphone : [Numéro de téléphone]
- Email : [Adresse email]
- Horaires : [Ex: Lun-Ven 9h-18h, Sam 9h-12h]
- Site web : [URL du site]

=== NOS PRESTATIONS / SERVICES ===
1. [Prestation 1]
   - Description : [Détail du service]
   - Tarif : À partir de [Prix]€
   - Délai : [Délai moyen]

2. [Prestation 2]
   - Description : [Détail du service]
   - Tarif : À partir de [Prix]€
   - Délai : [Délai moyen]

3. [Prestation 3]
   - Description : [Détail du service]
   - Tarif : Sur devis
   - Délai : [Délai moyen]

=== ZONE D'INTERVENTION / LIVRAISON ===
[Détails géographiques : villes, rayon km, etc.]

=== MOYENS DE PAIEMENT ===
[CB, espèces, chèque, virement, facilités de paiement...]

=== RÈGLES STRICTES - À RESPECTER ===
- Tu ne réponds QU'aux questions concernant notre entreprise et nos services
- Pour TOUTE question hors sujet (code, maths, traduction, actualités, recettes...), tu réponds : "Je suis l'assistant de [NOM] et je ne peux vous aider que pour [DOMAINE]. Comment puis-je vous renseigner sur nos services ?"
- Tu ne fais JAMAIS de programmation, traduction, rédaction de texte, ou aide aux devoirs
- Tu ne donnes pas de conseils médicaux, juridiques ou financiers généraux

=== CE QUE TU PEUX FAIRE ===
- Présenter nos services et tarifs
- Aider à formuler une demande de devis
- Donner les horaires et coordonnées
- Proposer une prise de rendez-vous
- Répondre aux questions fréquentes sur notre activité

Tu es [PERSONNALITÉ : professionnel, chaleureux, réactif, rassurant...] et tu cherches toujours à orienter le visiteur vers une prise de contact ou un devis.</pre>
            <button type="button" class="btn btn-secondary btn-sm" style="margin-top: 12px; background: #dcfce7; color: #166534;" onclick="document.querySelector('textarea[name=system_prompt]').value = document.getElementById('template-full').textContent;">Utiliser ce template</button>
        </details>

        <div style="display: flex; gap: 12px;">
            <button type="submit" class="btn btn-primary">
                <?= $editBot ? 'Mettre à jour' : 'Créer' ?>
            </button>
            <?php if ($editBot): ?>
                <a href="demo-chatbots.php" class="btn btn-secondary">Annuler</a>
            <?php endif; ?>
            <a href="../demo.php" target="_blank" class="btn btn-secondary" style="margin-left: auto;">Voir la démo</a>
        </div>
    </form>
</div>

<!-- Exemples de prompts par secteur -->
<div class="card" style="margin-top: 24px;">
    <h2 class="card-title">Exemples par secteur d'activité</h2>
    <p style="color: var(--text-light); margin-bottom: 20px;">Cliquez sur un exemple pour le copier dans le formulaire</p>

    <div class="examples-grid">
        <!-- Exemple Restaurant -->
        <details class="example-card" style="border-left: 4px solid #dc2626;">
            <summary>
                <span class="example-icon">🍽️</span>
                <span class="example-title">Restaurant</span>
            </summary>
            <div class="example-content">
                <div class="example-field">
                    <strong>Message de bienvenue :</strong>
                    <p id="ex-resto-welcome">Bienvenue chez [Nom du Restaurant] ! Je peux vous aider pour réserver une table, consulter notre menu ou répondre à vos questions. Que souhaitez-vous ?</p>
                </div>
                <div class="example-field">
                    <strong>Prompt système :</strong>
                    <pre id="ex-resto-prompt">Tu es l'assistant du restaurant [NOM], cuisine [TYPE] à [VILLE].

=== INFORMATIONS ===
- Adresse : [Adresse]
- Téléphone : [Numéro]
- Horaires : Mar-Sam 12h-14h et 19h-22h, Dim 12h-15h
- Capacité : [X] couverts

=== NOTRE CARTE ===
Entrées : [Liste] - 8 à 15€
Plats : [Liste] - 18 à 28€
Desserts : [Liste] - 8 à 12€
Menu du jour : 16€ (entrée + plat ou plat + dessert)

=== SERVICES ===
- Réservation recommandée le week-end
- Terrasse disponible en été
- Menu enfant 10€
- Options végétariennes disponibles

=== RÈGLES ===
- Tu ne réponds qu'aux questions sur le restaurant
- Pour toute question hors sujet : "Je suis l'assistant de ce restaurant..."

Tu es chaleureux et gourmand.</pre>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="useExample('resto')">Utiliser cet exemple</button>
            </div>
        </details>

        <!-- Exemple Garage -->
        <details class="example-card" style="border-left: 4px solid #3b82f6;">
            <summary>
                <span class="example-icon">🚗</span>
                <span class="example-title">Garage automobile</span>
            </summary>
            <div class="example-content">
                <div class="example-field">
                    <strong>Message de bienvenue :</strong>
                    <p id="ex-garage-welcome">Bienvenue chez [Nom du Garage] ! Entretien, réparation, contrôle technique... Comment puis-je vous aider ?</p>
                </div>
                <div class="example-field">
                    <strong>Prompt système :</strong>
                    <pre id="ex-garage-prompt">Tu es l'assistant du garage [NOM] à [VILLE].

=== INFORMATIONS ===
- Adresse : [Adresse]
- Téléphone : [Numéro]
- Horaires : Lun-Ven 8h-12h et 14h-18h, Sam 8h-12h

=== NOS SERVICES ===
1. Entretien courant
   - Vidange : à partir de 59€
   - Révision complète : à partir de 149€
   - Climatisation : recharge 79€

2. Réparations
   - Freins, embrayage, distribution
   - Diagnostic électronique : 45€
   - Devis gratuit

3. Contrôle technique
   - CT : 79€
   - Contre-visite offerte

=== MARQUES ===
Toutes marques, spécialiste [MARQUES]

=== RÈGLES ===
- Tu réponds uniquement sur nos services auto
- Pour toute question hors sujet : "Je suis l'assistant de ce garage..."

Tu es professionnel et rassurant.</pre>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="useExample('garage')">Utiliser cet exemple</button>
            </div>
        </details>

        <!-- Exemple Coiffeur -->
        <details class="example-card" style="border-left: 4px solid #ec4899;">
            <summary>
                <span class="example-icon">✂️</span>
                <span class="example-title">Salon de coiffure</span>
            </summary>
            <div class="example-content">
                <div class="example-field">
                    <strong>Message de bienvenue :</strong>
                    <p id="ex-coiffeur-welcome">Bienvenue au salon [Nom] ! Coupe, couleur, soins... Je suis là pour vous renseigner ou prendre rendez-vous. Que puis-je faire pour vous ?</p>
                </div>
                <div class="example-field">
                    <strong>Prompt système :</strong>
                    <pre id="ex-coiffeur-prompt">Tu es l'assistant du salon de coiffure [NOM] à [VILLE].

=== INFORMATIONS ===
- Adresse : [Adresse]
- Téléphone : [Numéro]
- Horaires : Mar-Sam 9h-19h

=== NOS PRESTATIONS ===
Femmes :
- Coupe : à partir de 35€
- Brushing : 20€
- Couleur : à partir de 45€
- Mèches/Balayage : à partir de 65€

Hommes :
- Coupe : 22€
- Barbe : 15€
- Coupe + Barbe : 32€

Enfants (-12 ans) : 15€

=== PRODUITS ===
Gamme [MARQUE] disponible à la vente

=== RÈGLES ===
- Tu réponds uniquement sur nos services coiffure
- Pour toute question hors sujet : "Je suis l'assistant de ce salon..."

Tu es accueillant et tendance.</pre>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="useExample('coiffeur')">Utiliser cet exemple</button>
            </div>
        </details>

        <!-- Exemple Avocat -->
        <details class="example-card" style="border-left: 4px solid #6366f1;">
            <summary>
                <span class="example-icon">⚖️</span>
                <span class="example-title">Cabinet d'avocat</span>
            </summary>
            <div class="example-content">
                <div class="example-field">
                    <strong>Message de bienvenue :</strong>
                    <p id="ex-avocat-welcome">Bienvenue au Cabinet [Nom]. Je peux vous renseigner sur nos domaines d'intervention et vous aider à prendre rendez-vous. Comment puis-je vous aider ?</p>
                </div>
                <div class="example-field">
                    <strong>Prompt système :</strong>
                    <pre id="ex-avocat-prompt">Tu es l'assistant du Cabinet d'Avocats [NOM] à [VILLE].

=== INFORMATIONS ===
- Adresse : [Adresse]
- Téléphone : [Numéro]
- Email : [Email]
- RDV sur rendez-vous uniquement

=== DOMAINES D'INTERVENTION ===
- Droit de la famille (divorce, garde, pension)
- Droit du travail (licenciement, prud'hommes)
- Droit immobilier (baux, copropriété)
- Droit des affaires

=== CONSULTATION ===
- Premier rendez-vous : 80€ (30 min)
- Consultation approfondie : sur devis

=== RÈGLES IMPORTANTES ===
- Tu ne donnes JAMAIS de conseil juridique précis
- Tu présentes uniquement nos domaines d'intervention
- Tu proposes toujours un rendez-vous pour analyse du dossier
- Pour toute question hors sujet : "Je suis l'assistant de ce cabinet..."

Tu es professionnel, discret et rassurant.</pre>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" onclick="useExample('avocat')">Utiliser cet exemple</button>
            </div>
        </details>
    </div>
</div>

<style>
    /* Guide de configuration */
    .guide-card details summary::-webkit-details-marker { display: none; }
    .guide-card details summary::after { content: '▶'; margin-left: auto; font-size: 12px; color: #92400e; transition: transform 0.2s; }
    .guide-card details[open] summary::after { transform: rotate(90deg); }
    .guide-steps { display: flex; flex-direction: column; gap: 16px; }
    .guide-step { display: flex; gap: 16px; align-items: flex-start; }
    .step-number {
        width: 32px; height: 32px;
        background: var(--primary);
        color: white;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; flex-shrink: 0;
    }
    .step-content h4 { margin: 0 0 8px 0; color: var(--text); }
    .step-content p { margin: 0; font-size: 14px; color: var(--text-light); }
    .step-content ul { margin: 8px 0 0 0; padding-left: 20px; font-size: 14px; color: var(--text-light); }
    .step-content li { margin-bottom: 4px; }

    .chatbots-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px; }
    .chatbot-card { background: #f8fafc; border-radius: 12px; padding: 16px; }
    .chatbot-card.inactive { opacity: 0.6; }
    .chatbot-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .chatbot-icon { font-size: 28px; }
    .chatbot-name { font-weight: 600; }
    .chatbot-slug { font-size: 11px; background: #e2e8f0; padding: 2px 6px; border-radius: 4px; }
    .chatbot-status { margin-left: auto; font-size: 11px; padding: 4px 8px; border-radius: 12px; background: #fee2e2; color: #991b1b; }
    .chatbot-status.active { background: #dcfce7; color: #166534; }
    .chatbot-preview { font-size: 13px; color: var(--text-light); margin-bottom: 12px; }
    .chatbot-actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-sm { padding: 6px 12px; font-size: 12px; }
    .btn-danger { background: #fee2e2; color: #991b1b; }
    .btn-danger:hover { background: #fecaca; }
    .btn-knowledge { background: #dbeafe; color: #1d4ed8; }
    .btn-knowledge:hover { background: #bfdbfe; }
    .btn-info { background: #d1fae5; color: #047857; }
    .btn-info:hover { background: #a7f3d0; }

    /* Styles pour les exemples */
    .examples-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
    .example-card { background: #f8fafc; border-radius: 12px; padding: 0; }
    .example-card summary { display: flex; align-items: center; gap: 12px; padding: 16px; cursor: pointer; list-style: none; }
    .example-card summary::-webkit-details-marker { display: none; }
    .example-card summary::after { content: '▶'; margin-left: auto; font-size: 10px; color: var(--text-light); transition: transform 0.2s; }
    .example-card[open] summary::after { transform: rotate(90deg); }
    .example-icon { font-size: 24px; }
    .example-title { font-weight: 600; }
    .example-content { padding: 0 16px 16px; }
    .example-field { margin-bottom: 12px; }
    .example-field strong { display: block; font-size: 12px; color: var(--text-light); margin-bottom: 4px; }
    .example-field p { margin: 0; font-size: 13px; background: white; padding: 8px; border-radius: 6px; }
    .example-field pre { margin: 0; font-size: 11px; background: white; padding: 12px; border-radius: 6px; white-space: pre-wrap; max-height: 200px; overflow-y: auto; }
</style>

<script>
const defaultTemplate = `Tu es EXCLUSIVEMENT l'assistant virtuel de [NOM ENTREPRISE], spécialisé dans [SECTEUR].

=== INFORMATIONS ===
- Entreprise : [Nom complet]
- Adresse : [Adresse]
- Téléphone : [Numéro]
- Horaires : [Horaires d'ouverture]

=== NOS PRESTATIONS ===
1. [Prestation 1] - [Description] - À partir de [Prix]
2. [Prestation 2] - [Description] - À partir de [Prix]

=== RÈGLES STRICTES ===
- Tu ne réponds QU'aux questions sur nos services
- Pour toute question hors sujet : "Je suis l'assistant de [X] et je ne peux vous aider que pour [DOMAINE]."
- Tu ne fais JAMAIS de programmation, traduction, devoirs ou rédaction

Tu es [PERSONNALITÉ : professionnel, chaleureux, etc.].`;

const defaultWelcome = `Bonjour ! Je suis l'assistant de [NOM ENTREPRISE]. Comment puis-je vous aider aujourd'hui ?`;

const defaultRedirect = `Je suis l'assistant de [NOM ENTREPRISE] et je suis spécialisé dans [DOMAINE].

Je peux vous aider pour :
• Obtenir des informations sur nos services
• Demander un devis
• Prendre rendez-vous

Comment puis-je vous aider ?`;

const defaultQuickActions = `Demander un devis
En savoir plus
Contact`;

function resetForm() {
    const form = document.querySelector('#form-edit form');
    form.querySelector('input[name="slug"]').value = '';
    form.querySelector('input[name="name"]').value = '';
    form.querySelector('input[name="icon"]').value = '💬';
    form.querySelector('input[name="color"]').value = '#6366f1';
    form.querySelector('input[name="sort_order"]').value = '0';
    form.querySelector('input[name="active"]').checked = true;
    form.querySelector('textarea[name="welcome_message"]').value = defaultWelcome;
    form.querySelector('textarea[name="redirect_message"]').value = defaultRedirect;
    form.querySelector('textarea[name="quick_actions"]').value = defaultQuickActions;
    form.querySelector('textarea[name="system_prompt"]').value = defaultTemplate;
    form.querySelector('input[name="action"]').value = 'add';
    const idInput = form.querySelector('input[name="id"]');
    if (idInput) idInput.remove();

    // Mettre à jour le titre du formulaire
    document.querySelector('#form-edit .card-title').textContent = 'Nouveau Chatbot';
}

function useExample(type) {
    const form = document.querySelector('#form-edit form');
    const welcomeEl = document.getElementById(`ex-${type}-welcome`);
    const promptEl = document.getElementById(`ex-${type}-prompt`);

    if (welcomeEl && promptEl) {
        form.querySelector('textarea[name="welcome_message"]').value = welcomeEl.textContent;
        form.querySelector('textarea[name="system_prompt"]').value = promptEl.textContent;

        // Scroll vers le formulaire
        document.getElementById('form-edit').scrollIntoView({ behavior: 'smooth' });
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
