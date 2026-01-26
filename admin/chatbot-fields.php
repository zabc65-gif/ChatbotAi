<?php
/**
 * Gestion des informations personnalisées par chatbot
 * Permet de renseigner toutes les données métier de chaque chatbot
 * Supporte le chatbot principal (id=main) et les chatbots de démo (id=numérique)
 */

$pageTitle = 'Informations Chatbot';
require_once 'includes/header.php';

$success = '';
$error = '';
$chatbot = null;
$fields = [];
$values = [];
$isMainChatbot = false;

// Récupérer l'ID du chatbot
$rawId = $_GET['id'] ?? '';

// Cas spécial : chatbot principal
if ($rawId === 'main') {
    $isMainChatbot = true;
    $chatbotId = 0; // ID 0 pour le chatbot principal dans la BDD
    $chatbot = [
        'id' => 0,
        'slug' => 'main',
        'name' => 'Chatbot Principal',
        'icon' => '🤖',
        'color' => '#6366f1'
    ];
} else {
    $chatbotId = intval($rawId);
    if (!$chatbotId) {
        // Rediriger vers la liste des chatbots
        header('Location: demo-chatbots.php');
        exit;
    }
}

// Vérifier si les tables existent
try {
    $tableExists = $db->fetchOne("SHOW TABLES LIKE 'chatbot_field_definitions'");
    if (!$tableExists) {
        $error = "Le système de champs n'est pas installé. <a href='update-chatbot-fields.php?key=update_fields_2024'>Cliquez ici pour l'installer</a>.";
    }
} catch (Exception $e) {
    $error = "Erreur de connexion à la base de données.";
}

// Récupérer le chatbot (sauf si c'est le principal, déjà défini)
if (empty($error) && !$isMainChatbot) {
    $chatbot = $db->fetchOne("SELECT * FROM demo_chatbots WHERE id = ?", [$chatbotId]);
    if (!$chatbot) {
        $error = "Chatbot introuvable.";
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error) && $chatbot) {
    try {
        $pdo = $db->getPdo();

        // Préparer les requêtes
        $checkStmt = $pdo->prepare("SELECT id FROM chatbot_field_values WHERE chatbot_id = ? AND field_key = ?");
        $updateStmt = $pdo->prepare("UPDATE chatbot_field_values SET field_value = ? WHERE chatbot_id = ? AND field_key = ?");
        $insertStmt = $pdo->prepare("INSERT INTO chatbot_field_values (chatbot_id, field_key, field_value) VALUES (?, ?, ?)");
        $deleteStmt = $pdo->prepare("DELETE FROM chatbot_field_values WHERE chatbot_id = ? AND field_key = ?");

        foreach ($_POST as $key => $value) {
            if (strpos($key, 'field_') === 0) {
                $fieldKey = substr($key, 6); // Enlever "field_"
                $fieldValue = is_array($value) ? implode(',', $value) : trim($value);

                // Vérifier si la valeur existe déjà
                $checkStmt->execute([$chatbotId, $fieldKey]);
                $existing = $checkStmt->fetch();

                if (empty($fieldValue)) {
                    // Supprimer si vide
                    if ($existing) {
                        $deleteStmt->execute([$chatbotId, $fieldKey]);
                    }
                } elseif ($existing) {
                    // Mettre à jour
                    $updateStmt->execute([$fieldValue, $chatbotId, $fieldKey]);
                } else {
                    // Insérer
                    $insertStmt->execute([$chatbotId, $fieldKey, $fieldValue]);
                }
            }
        }

        // Gérer les checkboxes non cochées
        $allFields = $db->fetchAll(
            "SELECT field_key FROM chatbot_field_definitions WHERE sector = ? OR sector = 'general' ORDER BY sort_order",
            [$chatbot['slug']]
        );

        foreach ($allFields as $field) {
            $postKey = 'field_' . $field['field_key'];
            if (!isset($_POST[$postKey])) {
                // Checkbox non cochée, supprimer la valeur
                $deleteStmt->execute([$chatbotId, $field['field_key']]);
            }
        }

        $success = "Informations enregistrées avec succès !";
    } catch (Exception $e) {
        $error = "Erreur lors de l'enregistrement : " . $e->getMessage();
    }
}

// Récupérer les définitions de champs pour ce secteur
if (empty($error) && $chatbot) {
    // Récupérer les champs spécifiques au secteur + les champs généraux
    $fields = $db->fetchAll(
        "SELECT * FROM chatbot_field_definitions
         WHERE sector = ? OR sector = 'general'
         ORDER BY field_group, sort_order",
        [$chatbot['slug']]
    );

    // Récupérer les valeurs existantes
    $valuesRaw = $db->fetchAll(
        "SELECT field_key, field_value FROM chatbot_field_values WHERE chatbot_id = ?",
        [$chatbotId]
    );

    foreach ($valuesRaw as $v) {
        $values[$v['field_key']] = $v['field_value'];
    }
}

// Grouper les champs par groupe
$fieldGroups = [];
$groupLabels = [
    'agence' => ['label' => 'Informations Agence', 'icon' => '🏢', 'color' => '#3b82f6'],
    'entreprise' => ['label' => 'Informations Entreprise', 'icon' => '🏢', 'color' => '#3b82f6'],
    'boutique' => ['label' => 'Informations Boutique', 'icon' => '🏪', 'color' => '#8b5cf6'],
    'mandats' => ['label' => 'Types de Mandats', 'icon' => '📝', 'color' => '#10b981'],
    'honoraires' => ['label' => 'Honoraires & Tarifs', 'icon' => '💰', 'color' => '#f59e0b'],
    'services' => ['label' => 'Services Inclus', 'icon' => '✨', 'color' => '#ec4899'],
    'zone' => ['label' => 'Zone d\'Intervention', 'icon' => '📍', 'color' => '#6366f1'],
    'documents' => ['label' => 'Documents & Formalités', 'icon' => '📄', 'color' => '#64748b'],
    'processus' => ['label' => 'Processus & Étapes', 'icon' => '📋', 'color' => '#0ea5e9'],
    'metier' => ['label' => 'Métier & Spécialités', 'icon' => '🔧', 'color' => '#f59e0b'],
    'prestations' => ['label' => 'Prestations', 'icon' => '🛠️', 'color' => '#10b981'],
    'livraison' => ['label' => 'Livraison', 'icon' => '🚚', 'color' => '#3b82f6'],
    'retours' => ['label' => 'Retours & Échanges', 'icon' => '↩️', 'color' => '#ef4444'],
    'paiement' => ['label' => 'Paiement', 'icon' => '💳', 'color' => '#10b981'],
    'produits' => ['label' => 'Produits', 'icon' => '📦', 'color' => '#8b5cf6'],
    'general' => ['label' => 'Informations Générales', 'icon' => 'ℹ️', 'color' => '#64748b'],
];

foreach ($fields as $field) {
    $group = $field['field_group'] ?: 'general';
    if (!isset($fieldGroups[$group])) {
        $fieldGroups[$group] = [];
    }
    $fieldGroups[$group][] = $field;
}
?>

<div class="page-header">
    <div style="display: flex; align-items: center; gap: 16px;">
        <a href="<?= $isMainChatbot ? 'chatbot-settings.php' : 'demo-chatbots.php' ?>" class="btn btn-secondary" style="padding: 8px 12px;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/></svg>
        </a>
        <div>
            <h1 class="page-title" style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 32px;"><?= htmlspecialchars($chatbot['icon'] ?? '💬') ?></span>
                Informations - <?= htmlspecialchars($chatbot['name'] ?? 'Chatbot') ?>
            </h1>
            <p class="page-subtitle">Renseignez les informations métier qui seront utilisées par l'IA</p>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<?php if ($chatbot && !empty($fields)): ?>

<!-- Guide -->
<div class="card" style="background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%); border-left: 4px solid #3b82f6; margin-bottom: 24px;">
    <div style="display: flex; align-items: flex-start; gap: 16px;">
        <span style="font-size: 32px;">💡</span>
        <div>
            <h3 style="margin: 0 0 8px 0; color: #1e40af;">Comment ça fonctionne ?</h3>
            <p style="margin: 0; color: #1e40af; font-size: 14px;">
                Les informations que vous renseignez ici seront <strong>automatiquement injectées</strong> dans le chatbot.
                L'IA utilisera ces données pour répondre précisément aux questions des visiteurs.
                Plus vous êtes précis, plus les réponses seront pertinentes !
            </p>
        </div>
    </div>
</div>

<!-- Navigation des groupes -->
<div class="card" style="margin-bottom: 24px; padding: 16px;">
    <div style="display: flex; flex-wrap: wrap; gap: 8px;">
        <?php foreach ($fieldGroups as $groupKey => $groupFields): ?>
            <?php $groupInfo = $groupLabels[$groupKey] ?? ['label' => ucfirst($groupKey), 'icon' => '📁', 'color' => '#64748b']; ?>
            <a href="#group-<?= $groupKey ?>" class="group-nav-btn" style="--group-color: <?= $groupInfo['color'] ?>;">
                <span><?= $groupInfo['icon'] ?></span>
                <?= $groupInfo['label'] ?>
                <span class="field-count"><?= count($groupFields) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<form method="POST" id="fields-form">
    <?php foreach ($fieldGroups as $groupKey => $groupFields): ?>
        <?php $groupInfo = $groupLabels[$groupKey] ?? ['label' => ucfirst($groupKey), 'icon' => '📁', 'color' => '#64748b']; ?>

        <div class="card field-group-card" id="group-<?= $groupKey ?>" style="--group-color: <?= $groupInfo['color'] ?>;">
            <div class="field-group-header">
                <span class="field-group-icon"><?= $groupInfo['icon'] ?></span>
                <h2 class="field-group-title"><?= $groupInfo['label'] ?></h2>
                <span class="field-group-count"><?= count($groupFields) ?> champs</span>
            </div>

            <div class="fields-grid">
                <?php foreach ($groupFields as $field): ?>
                    <?php
                    $fieldKey = $field['field_key'];
                    $fieldValue = $values[$fieldKey] ?? '';
                    $inputName = 'field_' . $fieldKey;
                    $isRequired = $field['required'] ? 'required' : '';
                    $hasValue = !empty($fieldValue);
                    ?>

                    <div class="field-item <?= $hasValue ? 'has-value' : '' ?> <?= $field['field_type'] === 'checkbox' ? 'field-checkbox' : '' ?>">
                        <?php if ($field['field_type'] === 'checkbox'): ?>
                            <label class="checkbox-label">
                                <input type="checkbox"
                                       name="<?= $inputName ?>"
                                       value="1"
                                       <?= $fieldValue ? 'checked' : '' ?>>
                                <span class="checkbox-text"><?= htmlspecialchars($field['field_label']) ?></span>
                                <?php if ($field['field_hint']): ?>
                                    <span class="field-hint"><?= htmlspecialchars($field['field_hint']) ?></span>
                                <?php endif; ?>
                            </label>
                        <?php else: ?>
                            <label class="form-label">
                                <?= htmlspecialchars($field['field_label']) ?>
                                <?php if ($field['required']): ?>
                                    <span class="required">*</span>
                                <?php endif; ?>
                            </label>

                            <?php if ($field['field_type'] === 'textarea'): ?>
                                <textarea name="<?= $inputName ?>"
                                          class="form-textarea"
                                          rows="3"
                                          placeholder="<?= htmlspecialchars($field['field_placeholder'] ?? '') ?>"
                                          <?= $isRequired ?>><?= htmlspecialchars($fieldValue) ?></textarea>
                            <?php elseif ($field['field_type'] === 'select'): ?>
                                <select name="<?= $inputName ?>" class="form-input" <?= $isRequired ?>>
                                    <option value="">-- Sélectionner --</option>
                                    <?php
                                    $options = json_decode($field['field_options'], true) ?: [];
                                    foreach ($options as $optValue => $optLabel):
                                    ?>
                                        <option value="<?= htmlspecialchars($optValue) ?>" <?= $fieldValue === $optValue ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($optLabel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <input type="<?= $field['field_type'] ?>"
                                       name="<?= $inputName ?>"
                                       class="form-input"
                                       value="<?= htmlspecialchars($fieldValue) ?>"
                                       placeholder="<?= htmlspecialchars($field['field_placeholder'] ?? '') ?>"
                                       <?= $isRequired ?>>
                            <?php endif; ?>

                            <?php if ($field['field_hint']): ?>
                                <p class="field-hint"><?= htmlspecialchars($field['field_hint']) ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <!-- Boutons d'action -->
    <div class="action-bar">
        <div class="action-bar-content">
            <div class="action-bar-info">
                <span class="filled-count" id="filled-count">0</span> / <?= count($fields) ?> champs renseignés
            </div>
            <div class="action-bar-buttons">
                <a href="<?= $isMainChatbot ? 'chatbot-settings.php' : 'demo-chatbots.php' ?>" class="btn btn-secondary">Annuler</a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
                    </svg>
                    Enregistrer les informations
                </button>
            </div>
        </div>
    </div>
</form>

<?php elseif (empty($fields) && $chatbot): ?>
    <div class="card" style="text-align: center; padding: 60px;">
        <span style="font-size: 64px; display: block; margin-bottom: 20px;">📝</span>
        <h2>Aucun champ défini pour ce secteur</h2>
        <p style="color: var(--text-light);">
            Les champs personnalisés pour le secteur "<?= htmlspecialchars($chatbot['slug']) ?>" n'ont pas encore été définis.
        </p>
    </div>
<?php endif; ?>

<style>
/* Navigation des groupes */
.group-nav-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    background: #f1f5f9;
    border-radius: 20px;
    text-decoration: none;
    color: var(--text);
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
}
.group-nav-btn:hover {
    background: var(--group-color);
    color: white;
}
.group-nav-btn .field-count {
    background: rgba(0,0,0,0.1);
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
}

/* Carte de groupe */
.field-group-card {
    margin-bottom: 24px;
    border-left: 4px solid var(--group-color);
}
.field-group-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e2e8f0;
}
.field-group-icon {
    font-size: 28px;
}
.field-group-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    flex: 1;
}
.field-group-count {
    font-size: 12px;
    color: var(--text-light);
    background: #f1f5f9;
    padding: 4px 10px;
    border-radius: 12px;
}

/* Grille des champs */
.fields-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}
.field-item {
    position: relative;
}
.field-item.has-value .form-label {
    color: var(--primary);
}
.field-item.has-value .form-input,
.field-item.has-value .form-textarea {
    border-color: #10b981;
    background: #f0fdf4;
}

/* Checkbox */
.field-checkbox {
    grid-column: span 1;
}
.checkbox-label {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    cursor: pointer;
    padding: 12px;
    background: #f8fafc;
    border-radius: 8px;
    transition: background 0.2s;
}
.checkbox-label:hover {
    background: #f1f5f9;
}
.checkbox-label input[type="checkbox"] {
    width: 20px;
    height: 20px;
    margin-top: 2px;
    cursor: pointer;
}
.checkbox-label input[type="checkbox"]:checked + .checkbox-text {
    color: var(--primary);
    font-weight: 500;
}
.checkbox-text {
    flex: 1;
}

/* Labels et hints */
.form-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    font-size: 14px;
}
.form-label .required {
    color: #ef4444;
}
.field-hint {
    font-size: 12px;
    color: var(--text-light);
    margin-top: 4px;
}

/* Inputs */
.form-input, .form-textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}
.form-input:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
}
.form-textarea {
    resize: vertical;
    min-height: 80px;
}

/* Barre d'action fixe */
.action-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    border-top: 1px solid #e2e8f0;
    padding: 16px 24px;
    z-index: 100;
    box-shadow: 0 -4px 12px rgba(0,0,0,0.1);
}
.action-bar-content {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.action-bar-info {
    font-size: 14px;
    color: var(--text-light);
}
.filled-count {
    font-weight: 700;
    color: var(--primary);
    font-size: 18px;
}
.action-bar-buttons {
    display: flex;
    gap: 12px;
}
.btn-lg {
    padding: 12px 24px;
    font-size: 15px;
}

/* Espace pour la barre d'action */
form {
    padding-bottom: 100px;
}

/* Responsive */
@media (max-width: 768px) {
    .fields-grid {
        grid-template-columns: 1fr;
    }
    .action-bar-content {
        flex-direction: column;
        gap: 12px;
    }
    .action-bar-buttons {
        width: 100%;
    }
    .action-bar-buttons .btn {
        flex: 1;
    }
}
</style>

<script>
// Compter les champs remplis
function updateFilledCount() {
    const inputs = document.querySelectorAll('#fields-form input:not([type="checkbox"]), #fields-form textarea, #fields-form select');
    const checkboxes = document.querySelectorAll('#fields-form input[type="checkbox"]');

    let filled = 0;

    inputs.forEach(input => {
        if (input.value.trim()) {
            filled++;
            input.closest('.field-item')?.classList.add('has-value');
        } else {
            input.closest('.field-item')?.classList.remove('has-value');
        }
    });

    checkboxes.forEach(cb => {
        if (cb.checked) filled++;
    });

    document.getElementById('filled-count').textContent = filled;
}

// Écouter les changements
document.querySelectorAll('#fields-form input, #fields-form textarea, #fields-form select').forEach(el => {
    el.addEventListener('input', updateFilledCount);
    el.addEventListener('change', updateFilledCount);
});

// Initialiser le compteur
updateFilledCount();

// Smooth scroll pour la navigation
document.querySelectorAll('.group-nav-btn').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
