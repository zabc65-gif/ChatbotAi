<?php
/**
 * Gestion des connaissances et FAQ d'un chatbot
 * Système d'apprentissage personnalisé
 */

$pageTitle = 'Base de Connaissances';
require_once 'includes/header.php';

$success = '';
$error = '';
$editItem = null;

// Vérifier que la table existe
try {
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'chatbot_knowledge'");
    if (!$tableExists) {
        $error = "La table n'existe pas encore. <a href='update-knowledge-system.php?key=install_knowledge_2024'>Cliquez ici pour l'installer</a>.";
    }
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données.";
}

// Récupérer l'ID du chatbot (main = chatbot principal, sinon ID numérique)
$chatbotIdParam = $_GET['id'] ?? '';
$isMainChatbot = ($chatbotIdParam === 'main');
$chatbotId = $isMainChatbot ? null : (intval($chatbotIdParam) ?: 0);
$chatbot = null;

if ($isMainChatbot && empty($error)) {
    // Chatbot principal - créer un faux objet chatbot pour l'affichage
    $chatbot = [
        'id' => null,
        'name' => 'Chatbot Principal',
        'icon' => '🤖',
        'slug' => 'main'
    ];
} elseif ($chatbotId && empty($error)) {
    $chatbot = $db->fetchOne("SELECT * FROM demo_chatbots WHERE id = ?", [$chatbotId]);
    if (!$chatbot) {
        $error = "Chatbot introuvable.";
    }
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $chatbot && empty($error)) {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add':
            case 'edit':
                $itemId = $_POST['item_id'] ?? null;
                $type = $_POST['type'] ?? 'faq';
                $question = trim($_POST['question'] ?? '');
                $answer = trim($_POST['answer'] ?? '');
                $keywords = trim($_POST['keywords'] ?? '');
                $active = isset($_POST['active']) ? 1 : 0;
                $sortOrder = intval($_POST['sort_order'] ?? 0);

                if (empty($answer)) {
                    throw new Exception('La réponse/information est obligatoire.');
                }

                if ($type === 'faq' && empty($question)) {
                    throw new Exception('La question est obligatoire pour une FAQ.');
                }

                if ($action === 'edit' && $itemId) {
                    if ($isMainChatbot) {
                        $sql = "UPDATE chatbot_knowledge SET type=?, question=?, answer=?, keywords=?, active=?, sort_order=? WHERE id=? AND chatbot_id IS NULL";
                        $db->query($sql, [$type, $question, $answer, $keywords, $active, $sortOrder, $itemId]);
                    } else {
                        $sql = "UPDATE chatbot_knowledge SET type=?, question=?, answer=?, keywords=?, active=?, sort_order=? WHERE id=? AND chatbot_id=?";
                        $db->query($sql, [$type, $question, $answer, $keywords, $active, $sortOrder, $itemId, $chatbotId]);
                    }
                    $success = "Connaissance mise à jour !";
                } else {
                    $sql = "INSERT INTO chatbot_knowledge (chatbot_id, type, question, answer, keywords, active, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $db->query($sql, [$chatbotId, $type, $question, $answer, $keywords, $active, $sortOrder]);
                    $success = "Connaissance ajoutée !";
                }
                break;

            case 'delete':
                $itemId = $_POST['item_id'] ?? null;
                if ($itemId) {
                    if ($isMainChatbot) {
                        $db->query("DELETE FROM chatbot_knowledge WHERE id = ? AND chatbot_id IS NULL", [$itemId]);
                    } else {
                        $db->query("DELETE FROM chatbot_knowledge WHERE id = ? AND chatbot_id = ?", [$itemId, $chatbotId]);
                    }
                    $success = "Connaissance supprimée !";
                }
                break;

            case 'toggle':
                $itemId = $_POST['item_id'] ?? null;
                if ($itemId) {
                    if ($isMainChatbot) {
                        $db->query("UPDATE chatbot_knowledge SET active = NOT active WHERE id = ? AND chatbot_id IS NULL", [$itemId]);
                    } else {
                        $db->query("UPDATE chatbot_knowledge SET active = NOT active WHERE id = ? AND chatbot_id = ?", [$itemId, $chatbotId]);
                    }
                    $success = "Statut mis à jour !";
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Récupérer l'élément à éditer
if (isset($_GET['edit']) && $chatbot && empty($error)) {
    if ($isMainChatbot) {
        $editItem = $db->fetchOne("SELECT * FROM chatbot_knowledge WHERE id = ? AND chatbot_id IS NULL", [$_GET['edit']]);
    } else {
        $editItem = $db->fetchOne("SELECT * FROM chatbot_knowledge WHERE id = ? AND chatbot_id = ?", [$_GET['edit'], $chatbotId]);
    }
}

// Récupérer toutes les connaissances du chatbot
$knowledgeItems = [];
if ($chatbot && empty($error)) {
    if ($isMainChatbot) {
        $knowledgeItems = $db->fetchAll(
            "SELECT * FROM chatbot_knowledge WHERE chatbot_id IS NULL ORDER BY type ASC, sort_order ASC, id ASC"
        );
    } else {
        $knowledgeItems = $db->fetchAll(
            "SELECT * FROM chatbot_knowledge WHERE chatbot_id = ? ORDER BY type ASC, sort_order ASC, id ASC",
            [$chatbotId]
        );
    }
}

// Liste des chatbots pour sélection
$allChatbots = [];
if (empty($error) || strpos($error, 'table') === false) {
    try {
        $allChatbots = $db->fetchAll("SELECT id, name, icon FROM demo_chatbots ORDER BY name ASC");
    } catch (Exception $e) {
        // Silently fail
    }
}
?>

<div class="page-header">
    <h1 class="page-title">
        <?php if ($chatbot): ?>
            <?= htmlspecialchars($chatbot['icon']) ?> Base de Connaissances - <?= htmlspecialchars($chatbot['name']) ?>
        <?php else: ?>
            Base de Connaissances
        <?php endif; ?>
    </h1>
    <p class="page-subtitle">Ajoutez des FAQ et informations pour enrichir les réponses du chatbot</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<!-- Sélection du chatbot -->
<?php if (!$chatbot): ?>
    <div class="card">
        <h2 class="card-title">Sélectionner un chatbot</h2>

        <!-- Chatbot principal -->
        <div style="margin-bottom: 24px;">
            <h3 style="font-size: 14px; color: var(--text-light); margin-bottom: 12px;">Chatbot Principal</h3>
            <a href="?id=main" class="chatbot-select-card main-chatbot">
                <span class="chatbot-icon">🤖</span>
                <span class="chatbot-name">Chatbot Principal</span>
                <span style="font-size: 12px; color: var(--text-light); margin-left: auto;">Configuration Chatbot</span>
            </a>
        </div>

        <!-- Chatbots de démo -->
        <h3 style="font-size: 14px; color: var(--text-light); margin-bottom: 12px;">Chatbots de Démonstration</h3>
        <?php if (empty($allChatbots)): ?>
            <p style="color: var(--text-light);">Aucun chatbot de démo configuré. <a href="demo-chatbots.php">Créez d'abord un chatbot</a>.</p>
        <?php else: ?>
            <div class="chatbots-select-grid">
                <?php foreach ($allChatbots as $bot): ?>
                    <a href="?id=<?= $bot['id'] ?>" class="chatbot-select-card">
                        <span class="chatbot-icon"><?= htmlspecialchars($bot['icon']) ?></span>
                        <span class="chatbot-name"><?= htmlspecialchars($bot['name']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php else: ?>

    <!-- Navigation -->
    <?php $currentId = $isMainChatbot ? 'main' : $chatbotId; ?>
    <div style="margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap;">
        <?php if ($isMainChatbot): ?>
            <a href="chatbot-settings.php" class="btn btn-secondary">Retour aux paramètres</a>
        <?php else: ?>
            <a href="demo-chatbots.php" class="btn btn-secondary">Retour aux chatbots</a>
        <?php endif; ?>
        <a href="?id=<?= $currentId ?>" class="btn btn-secondary">Actualiser</a>
        <select onchange="if(this.value) window.location.href='?id='+this.value" class="form-input" style="width: auto;">
            <option value="">Changer de chatbot...</option>
            <option value="main" <?= $isMainChatbot ? 'selected' : '' ?>>🤖 Chatbot Principal</option>
            <?php foreach ($allChatbots as $bot): ?>
                <option value="<?= $bot['id'] ?>" <?= (!$isMainChatbot && $bot['id'] == $chatbotId) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($bot['icon'] . ' ' . $bot['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Guide rapide -->
    <div class="card" style="background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%); border-left: 4px solid #3b82f6;">
        <h2 class="card-title" style="color: #1d4ed8;">Comment fonctionne l'apprentissage ?</h2>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
            <div>
                <h4 style="margin: 0 0 8px 0; color: #1e40af;">FAQ</h4>
                <p style="margin: 0; font-size: 14px; color: #1e40af;">Questions fréquentes avec leurs réponses exactes. Le chatbot les utilisera quand la question correspond.</p>
            </div>
            <div>
                <h4 style="margin: 0 0 8px 0; color: #1e40af;">Informations</h4>
                <p style="margin: 0; font-size: 14px; color: #1e40af;">Données générales sur l'entreprise (tarifs, horaires, services...) injectées dans le contexte.</p>
            </div>
            <div>
                <h4 style="margin: 0 0 8px 0; color: #1e40af;">Mots-clés</h4>
                <p style="margin: 0; font-size: 14px; color: #1e40af;">Ajoutez des mots-clés pour améliorer la détection (ex: "prix, tarif, cout" pour une question sur les prix).</p>
            </div>
        </div>
    </div>

    <!-- Statistiques -->
    <div class="grid-3" style="margin-bottom: 24px;">
        <?php
        $countByType = ['faq' => 0, 'info' => 0, 'response' => 0];
        foreach ($knowledgeItems as $item) {
            $countByType[$item['type']]++;
        }
        ?>
        <div class="stat-card">
            <div class="stat-value"><?= $countByType['faq'] ?></div>
            <div class="stat-label">FAQ</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $countByType['info'] ?></div>
            <div class="stat-label">Informations</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $countByType['response'] ?></div>
            <div class="stat-label">Réponses</div>
        </div>
    </div>

    <!-- Liste des connaissances -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 class="card-title" style="margin-bottom: 0; padding-bottom: 0; border: none;">
                Connaissances (<?= count($knowledgeItems) ?>)
            </h2>
            <a href="#form-knowledge" class="btn btn-primary" onclick="resetKnowledgeForm()">
                + Ajouter
            </a>
        </div>

        <?php if (empty($knowledgeItems)): ?>
            <div style="text-align: center; padding: 40px; color: var(--text-light);">
                <p style="font-size: 48px; margin: 0;">📚</p>
                <p>Aucune connaissance ajoutée pour ce chatbot.</p>
                <p>Ajoutez des FAQ et informations pour enrichir ses réponses !</p>
            </div>
        <?php else: ?>
            <div class="knowledge-list">
                <?php foreach ($knowledgeItems as $item): ?>
                    <div class="knowledge-item <?= $item['active'] ? '' : 'inactive' ?>" data-type="<?= $item['type'] ?>">
                        <div class="knowledge-type type-<?= $item['type'] ?>">
                            <?php
                            $typeLabels = ['faq' => 'FAQ', 'info' => 'Info', 'response' => 'Réponse'];
                            echo $typeLabels[$item['type']] ?? $item['type'];
                            ?>
                        </div>
                        <div class="knowledge-content">
                            <?php if ($item['type'] === 'faq' && $item['question']): ?>
                                <div class="knowledge-question">Q: <?= htmlspecialchars($item['question']) ?></div>
                            <?php endif; ?>
                            <div class="knowledge-answer">
                                <?= nl2br(htmlspecialchars(mb_substr($item['answer'], 0, 200))) ?>
                                <?= mb_strlen($item['answer']) > 200 ? '...' : '' ?>
                            </div>
                            <?php if ($item['keywords']): ?>
                                <div class="knowledge-keywords">
                                    <?php foreach (explode(',', $item['keywords']) as $kw): ?>
                                        <span class="keyword-tag"><?= htmlspecialchars(trim($kw)) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="knowledge-actions">
                            <a href="?id=<?= $currentId ?>&edit=<?= $item['id'] ?>#form-knowledge" class="btn btn-secondary btn-sm">Modifier</a>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-secondary btn-sm">
                                    <?= $item['active'] ? 'Désactiver' : 'Activer' ?>
                                </button>
                            </form>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('Supprimer cette connaissance ?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">Supprimer</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Formulaire d'ajout/édition -->
    <div class="card" id="form-knowledge">
        <h2 class="card-title"><?= $editItem ? 'Modifier la connaissance' : 'Ajouter une connaissance' ?></h2>

        <form method="POST">
            <input type="hidden" name="action" value="<?= $editItem ? 'edit' : 'add' ?>">
            <?php if ($editItem): ?>
                <input type="hidden" name="item_id" value="<?= $editItem['id'] ?>">
            <?php endif; ?>

            <div class="grid-2" style="gap: 24px;">
                <div>
                    <div class="form-group">
                        <label class="form-label">Type de connaissance *</label>
                        <select name="type" class="form-input" id="knowledge-type" onchange="toggleQuestionField()">
                            <option value="faq" <?= ($editItem['type'] ?? 'faq') === 'faq' ? 'selected' : '' ?>>FAQ (Question/Réponse)</option>
                            <option value="info" <?= ($editItem['type'] ?? '') === 'info' ? 'selected' : '' ?>>Information générale</option>
                            <option value="response" <?= ($editItem['type'] ?? '') === 'response' ? 'selected' : '' ?>>Réponse personnalisée</option>
                        </select>
                    </div>

                    <div class="form-group" id="question-group">
                        <label class="form-label">Question</label>
                        <input type="text" name="question" class="form-input"
                               value="<?= htmlspecialchars($editItem['question'] ?? '') ?>"
                               placeholder="Ex: Quels sont vos horaires d'ouverture ?">
                        <p class="form-hint">La question que les visiteurs pourraient poser</p>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mots-clés (optionnel)</label>
                        <input type="text" name="keywords" class="form-input"
                               value="<?= htmlspecialchars($editItem['keywords'] ?? '') ?>"
                               placeholder="Ex: horaires, ouverture, heures, quand">
                        <p class="form-hint">Séparés par des virgules, pour améliorer la détection</p>
                    </div>

                    <div style="display: flex; gap: 12px;">
                        <div class="form-group" style="width: 100px;">
                            <label class="form-label">Ordre</label>
                            <input type="number" name="sort_order" class="form-input" min="0"
                                   value="<?= htmlspecialchars($editItem['sort_order'] ?? '0') ?>">
                        </div>
                        <div class="form-group" style="flex: 1; display: flex; align-items: flex-end;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; padding: 10px;">
                                <input type="checkbox" name="active" value="1"
                                       <?= ($editItem['active'] ?? true) ? 'checked' : '' ?>
                                       style="width: 18px; height: 18px;">
                                <span>Actif</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="form-group">
                        <label class="form-label">Réponse / Information *</label>
                        <textarea name="answer" class="form-textarea" rows="10" required
                                  placeholder="Ex: Nous sommes ouverts du lundi au vendredi de 9h à 18h, et le samedi de 9h à 12h."><?= htmlspecialchars($editItem['answer'] ?? '') ?></textarea>
                        <p class="form-hint">Le contenu que le chatbot utilisera dans sa réponse</p>
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">
                    <?= $editItem ? 'Mettre à jour' : 'Ajouter' ?>
                </button>
                <?php if ($editItem): ?>
                    <a href="?id=<?= $currentId ?>" class="btn btn-secondary">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Exemples de FAQ -->
    <div class="card" style="margin-top: 24px;">
        <h2 class="card-title">Exemples de FAQ courantes</h2>
        <p style="color: var(--text-light); margin-bottom: 16px;">Cliquez pour pré-remplir le formulaire</p>

        <div class="faq-examples-grid">
            <button type="button" class="faq-example" onclick="fillExample('Quels sont vos horaires d\'ouverture ?', 'Nous sommes ouverts du lundi au vendredi de 9h à 18h, et le samedi matin de 9h à 12h. Fermé le dimanche et les jours fériés.', 'horaires, ouverture, heures, ouvert, fermé')">
                <span class="faq-icon">🕐</span>
                <span>Horaires</span>
            </button>
            <button type="button" class="faq-example" onclick="fillExample('Quels sont vos tarifs ?', 'Nos tarifs varient selon la prestation. Voici nos prix de base :\n- [Service 1] : à partir de XX€\n- [Service 2] : à partir de XX€\n\nContactez-nous pour un devis personnalisé.', 'tarifs, prix, cout, combien, devis')">
                <span class="faq-icon">💰</span>
                <span>Tarifs</span>
            </button>
            <button type="button" class="faq-example" onclick="fillExample('Comment vous contacter ?', 'Vous pouvez nous contacter :\n- Par téléphone : [numéro]\n- Par email : [email]\n- En personne : [adresse]\n\nNous répondons généralement sous 24h.', 'contact, téléphone, email, adresse, joindre')">
                <span class="faq-icon">📞</span>
                <span>Contact</span>
            </button>
            <button type="button" class="faq-example" onclick="fillExample('Où êtes-vous situés ?', 'Nous sommes situés au [adresse complète].\n\nAccès : [indications parking, transports en commun...]\n\nVoir sur Google Maps : [lien]', 'adresse, situé, localisation, où, trouver, accès')">
                <span class="faq-icon">📍</span>
                <span>Localisation</span>
            </button>
            <button type="button" class="faq-example" onclick="fillExample('Comment prendre rendez-vous ?', 'Pour prendre rendez-vous :\n- Par téléphone au [numéro]\n- Via notre site web : [lien]\n- Sur place aux heures d\'ouverture\n\nNous vous recommandons de réserver à l\'avance.', 'rendez-vous, rdv, réserver, réservation, prendre')">
                <span class="faq-icon">📅</span>
                <span>Rendez-vous</span>
            </button>
            <button type="button" class="faq-example" onclick="fillExample('Quels modes de paiement acceptez-vous ?', 'Nous acceptons les modes de paiement suivants :\n- Carte bancaire (Visa, Mastercard)\n- Espèces\n- Chèque\n- Virement bancaire\n\n[Facilités de paiement si applicable]', 'paiement, payer, carte, espèces, chèque, virement')">
                <span class="faq-icon">💳</span>
                <span>Paiement</span>
            </button>
        </div>
    </div>

<?php endif; ?>

<style>
    .chatbots-select-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 16px;
    }
    .chatbot-select-card {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 16px;
        background: #f8fafc;
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s;
    }
    .chatbot-select-card:hover {
        background: var(--primary);
        color: white;
        transform: translateY(-2px);
    }
    .chatbot-select-card.main-chatbot {
        background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
        border: 2px solid #3b82f6;
    }
    .chatbot-select-card.main-chatbot:hover {
        background: #3b82f6;
    }
    .chatbot-select-card .chatbot-icon {
        font-size: 28px;
    }
    .chatbot-select-card .chatbot-name {
        font-weight: 600;
    }

    .grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 16px;
    }
    .stat-card {
        background: #f8fafc;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
    }
    .stat-value {
        font-size: 36px;
        font-weight: 700;
        color: var(--primary);
    }
    .stat-label {
        color: var(--text-light);
        font-size: 14px;
    }

    .knowledge-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .knowledge-item {
        display: flex;
        gap: 16px;
        padding: 16px;
        background: #f8fafc;
        border-radius: 12px;
        align-items: flex-start;
    }
    .knowledge-item.inactive {
        opacity: 0.5;
    }
    .knowledge-type {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }
    .type-faq { background: #dbeafe; color: #1e40af; }
    .type-info { background: #dcfce7; color: #166534; }
    .type-response { background: #fef3c7; color: #92400e; }

    .knowledge-content {
        flex: 1;
        min-width: 0;
    }
    .knowledge-question {
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text);
    }
    .knowledge-answer {
        font-size: 14px;
        color: var(--text-light);
        line-height: 1.5;
    }
    .knowledge-keywords {
        margin-top: 8px;
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
    }
    .keyword-tag {
        background: #e2e8f0;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        color: var(--text-light);
    }
    .knowledge-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-sm { padding: 6px 12px; font-size: 12px; }
    .btn-danger { background: #fee2e2; color: #991b1b; }
    .btn-danger:hover { background: #fecaca; }

    .faq-examples-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 12px;
    }
    .faq-example {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        padding: 16px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .faq-example:hover {
        background: #eff6ff;
        border-color: var(--primary);
    }
    .faq-icon { font-size: 24px; }

    @media (max-width: 768px) {
        .grid-3 { grid-template-columns: 1fr; }
        .knowledge-item { flex-direction: column; }
        .knowledge-actions { width: 100%; }
    }
</style>

<script>
function toggleQuestionField() {
    const type = document.getElementById('knowledge-type').value;
    const questionGroup = document.getElementById('question-group');
    if (type === 'faq') {
        questionGroup.style.display = 'block';
    } else {
        questionGroup.style.display = 'none';
    }
}

function resetKnowledgeForm() {
    const form = document.querySelector('#form-knowledge form');
    form.querySelector('select[name="type"]').value = 'faq';
    form.querySelector('input[name="question"]').value = '';
    form.querySelector('textarea[name="answer"]').value = '';
    form.querySelector('input[name="keywords"]').value = '';
    form.querySelector('input[name="sort_order"]').value = '0';
    form.querySelector('input[name="active"]').checked = true;
    form.querySelector('input[name="action"]').value = 'add';
    const itemIdInput = form.querySelector('input[name="item_id"]');
    if (itemIdInput) itemIdInput.remove();
    toggleQuestionField();
}

function fillExample(question, answer, keywords) {
    const form = document.querySelector('#form-knowledge form');
    form.querySelector('select[name="type"]').value = 'faq';
    form.querySelector('input[name="question"]').value = question;
    form.querySelector('textarea[name="answer"]').value = answer;
    form.querySelector('input[name="keywords"]').value = keywords;
    toggleQuestionField();
    document.getElementById('form-knowledge').scrollIntoView({ behavior: 'smooth' });
}

// Initialiser l'état du champ question
toggleQuestionField();
</script>

<?php require_once 'includes/footer.php'; ?>
