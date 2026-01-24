<?php
/**
 * Configuration du Chatbot
 */

$pageTitle = 'Configuration Chatbot';
require_once 'includes/header.php';

$success = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $settings->set('chatbot_name', $_POST['chatbot_name'] ?? '', 'string', 'chatbot', 'Nom du chatbot');
        $settings->set('chatbot_welcome_message', $_POST['chatbot_welcome_message'] ?? '', 'text', 'chatbot', 'Message de bienvenue');
        $settings->set('chatbot_system_prompt', $_POST['chatbot_system_prompt'] ?? '', 'text', 'chatbot', 'Prompt système');
        $settings->set('chatbot_placeholder', $_POST['chatbot_placeholder'] ?? '', 'string', 'chatbot', 'Placeholder');
        $settings->set('chatbot_primary_color', $_POST['chatbot_primary_color'] ?? '#6366f1', 'string', 'chatbot', 'Couleur principale');

        $success = 'Configuration sauvegardée avec succès !';
    } catch (Exception $e) {
        $error = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
    }
}

// Récupérer les valeurs actuelles
$chatbotSettings = $settings->getGroup('chatbot');
?>

<div class="page-header">
    <h1 class="page-title">Configuration du Chatbot</h1>
    <p class="page-subtitle">Personnalisez le comportement et l'apparence de votre assistant IA</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <div class="grid-2">
        <!-- Apparence -->
        <div class="card">
            <h2 class="card-title">Apparence</h2>

            <div class="form-group">
                <label class="form-label">Nom du chatbot</label>
                <input type="text" name="chatbot_name" class="form-input"
                       value="<?= htmlspecialchars($chatbotSettings['chatbot_name']['value'] ?? 'Assistant IA') ?>">
                <p class="form-hint">Le nom affiché dans l'en-tête du widget</p>
            </div>

            <div class="form-group">
                <label class="form-label">Placeholder du champ de saisie</label>
                <input type="text" name="chatbot_placeholder" class="form-input"
                       value="<?= htmlspecialchars($chatbotSettings['chatbot_placeholder']['value'] ?? 'Écrivez votre message...') ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Couleur principale</label>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <input type="color" name="chatbot_primary_color"
                           value="<?= htmlspecialchars($chatbotSettings['chatbot_primary_color']['value'] ?? '#6366f1') ?>"
                           style="width: 60px; height: 40px; border: none; cursor: pointer;">
                    <input type="text" class="form-input" style="width: 120px;"
                           value="<?= htmlspecialchars($chatbotSettings['chatbot_primary_color']['value'] ?? '#6366f1') ?>"
                           onchange="this.previousElementSibling.value = this.value"
                           oninput="this.previousElementSibling.value = this.value">
                </div>
            </div>
        </div>

        <!-- Messages -->
        <div class="card">
            <h2 class="card-title">Messages</h2>

            <div class="form-group">
                <label class="form-label">Message de bienvenue</label>
                <textarea name="chatbot_welcome_message" class="form-textarea" rows="3"><?= htmlspecialchars($chatbotSettings['chatbot_welcome_message']['value'] ?? 'Bonjour ! Je suis votre assistant virtuel. Comment puis-je vous aider ?') ?></textarea>
                <p class="form-hint">Le premier message affiché quand un visiteur ouvre le chat</p>
            </div>
        </div>
    </div>

    <!-- Comportement IA -->
    <div class="card">
        <h2 class="card-title">Comportement de l'IA (Prompt Système)</h2>

        <div class="form-group">
            <label class="form-label">Prompt système</label>
            <textarea name="chatbot_system_prompt" class="form-textarea" rows="8"><?= htmlspecialchars($chatbotSettings['chatbot_system_prompt']['value'] ?? SYSTEM_MESSAGE) ?></textarea>
            <p class="form-hint">
                Ce texte définit la personnalité et le comportement de l'IA. Il est envoyé au début de chaque conversation.
                Soyez précis sur le rôle, le ton et les limites de l'assistant.
            </p>
        </div>

        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-top: 20px;">
            <h4 style="font-size: 14px; font-weight: 600; margin-bottom: 12px;">Exemples de prompts par secteur</h4>

            <details style="margin-bottom: 12px;">
                <summary style="cursor: pointer; font-weight: 500; color: var(--primary);">Artisan / BTP</summary>
                <pre style="background: white; padding: 12px; border-radius: 8px; margin-top: 8px; font-size: 13px; white-space: pre-wrap;">Tu es un assistant virtuel pour un artisan du bâtiment. Tu aides les visiteurs à :
- Demander un devis pour des travaux
- Obtenir des informations sur les services
- Prendre rendez-vous pour une visite technique
Tu es professionnel, rassurant et tu mets en avant la qualité du travail artisanal.</pre>
            </details>

            <details style="margin-bottom: 12px;">
                <summary style="cursor: pointer; font-weight: 500; color: var(--primary);">Agence Immobilière</summary>
                <pre style="background: white; padding: 12px; border-radius: 8px; margin-top: 8px; font-size: 13px; white-space: pre-wrap;">Tu es un assistant pour une agence immobilière. Tu aides les visiteurs à :
- Rechercher un bien (achat ou location)
- Estimer la valeur d'un bien
- Prendre rendez-vous pour une visite
Tu es accueillant, à l'écoute et tu cherches à comprendre les besoins du client.</pre>
            </details>

            <details>
                <summary style="cursor: pointer; font-weight: 500; color: var(--primary);">E-commerce</summary>
                <pre style="background: white; padding: 12px; border-radius: 8px; margin-top: 8px; font-size: 13px; white-space: pre-wrap;">Tu es un assistant pour un site e-commerce. Tu aides les clients à :
- Trouver des produits correspondant à leurs besoins
- Suivre leurs commandes
- Gérer les retours et remboursements
Tu es serviable, réactif et tu cherches à maximiser la satisfaction client.</pre>
            </details>
        </div>
    </div>

    <div style="display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap;">
        <button type="submit" class="btn btn-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
            </svg>
            Sauvegarder
        </button>
        <a href="chatbot-knowledge.php?id=main" class="btn btn-secondary" style="background: #dbeafe; color: #1d4ed8;">
            📚 Apprentissage
        </a>
        <a href="../demo.php" target="_blank" class="btn btn-secondary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
            </svg>
            Prévisualiser
        </a>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>
