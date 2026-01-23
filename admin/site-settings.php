<?php
/**
 * Paramètres généraux du site
 */

$pageTitle = 'Paramètres Site';
require_once 'includes/header.php';

$success = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Informations générales
        $settings->set('site_name', $_POST['site_name'] ?? '', 'string', 'site', 'Nom du site');
        $settings->set('site_description', $_POST['site_description'] ?? '', 'text', 'site', 'Description du site');
        $settings->set('contact_email', $_POST['contact_email'] ?? '', 'string', 'site', 'Email de contact');
        $settings->set('contact_phone', $_POST['contact_phone'] ?? '', 'string', 'site', 'Téléphone');

        // Entreprise
        $settings->set('company_name', $_POST['company_name'] ?? '', 'string', 'site', 'Raison sociale');
        $settings->set('company_address', $_POST['company_address'] ?? '', 'text', 'site', 'Adresse');
        $settings->set('company_siret', $_POST['company_siret'] ?? '', 'string', 'site', 'SIRET');

        // Anti-abus
        $settings->set('abuse_filter_enabled', isset($_POST['abuse_filter_enabled']) ? '1' : '0', 'boolean', 'security', 'Filtre anti-abus activé');
        $settings->set('max_messages_per_session', $_POST['max_messages_per_session'] ?? '50', 'integer', 'security', 'Messages max par session');
        $settings->set('rate_limit_enabled', isset($_POST['rate_limit_enabled']) ? '1' : '0', 'boolean', 'security', 'Rate limiting activé');

        $success = 'Paramètres sauvegardés avec succès !';
    } catch (Exception $e) {
        $error = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
    }
}

// Récupérer les valeurs actuelles
$siteSettings = $settings->getGroup('site');
$securitySettings = $settings->getGroup('security');

// Valeurs par défaut
$defaults = [
    'site_name' => 'ChatBot IA',
    'site_description' => 'Solution de chatbot IA pour professionnels',
    'contact_email' => 'bruno@myziggi.fr',
    'contact_phone' => '06 72 38 64 24',
    'company_name' => 'Myziggi SASU',
    'company_address' => '34 route du lac, 65350 LASLADES',
    'company_siret' => '913 721 239 00014',
    'abuse_filter_enabled' => '1',
    'max_messages_per_session' => '50',
    'rate_limit_enabled' => '1'
];

function getVal($settings, $key, $defaults) {
    return htmlspecialchars($settings[$key]['value'] ?? $defaults[$key] ?? '');
}
function isChecked($settings, $key, $defaults) {
    $value = $settings[$key]['value'] ?? $defaults[$key] ?? '0';
    return $value === '1' || $value === 'true';
}
?>

<div class="page-header">
    <h1 class="page-title">Paramètres du Site</h1>
    <p class="page-subtitle">Configuration générale et sécurité</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <div class="grid-2">
        <!-- Informations générales -->
        <div class="card">
            <h2 class="card-title">Informations générales</h2>

            <div class="form-group">
                <label class="form-label">Nom du site</label>
                <input type="text" name="site_name" class="form-input"
                       value="<?= getVal($siteSettings, 'site_name', $defaults) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="site_description" class="form-textarea" rows="2"><?= getVal($siteSettings, 'site_description', $defaults) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Email de contact</label>
                <input type="email" name="contact_email" class="form-input"
                       value="<?= getVal($siteSettings, 'contact_email', $defaults) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Téléphone</label>
                <input type="text" name="contact_phone" class="form-input"
                       value="<?= getVal($siteSettings, 'contact_phone', $defaults) ?>">
            </div>
        </div>

        <!-- Informations entreprise -->
        <div class="card">
            <h2 class="card-title">Informations entreprise</h2>

            <div class="form-group">
                <label class="form-label">Raison sociale</label>
                <input type="text" name="company_name" class="form-input"
                       value="<?= getVal($siteSettings, 'company_name', $defaults) ?>">
            </div>

            <div class="form-group">
                <label class="form-label">Adresse</label>
                <textarea name="company_address" class="form-textarea" rows="2"><?= getVal($siteSettings, 'company_address', $defaults) ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">SIRET</label>
                <input type="text" name="company_siret" class="form-input"
                       value="<?= getVal($siteSettings, 'company_siret', $defaults) ?>">
            </div>
        </div>
    </div>

    <!-- Sécurité et Anti-abus -->
    <div class="card">
        <h2 class="card-title">Sécurité & Anti-abus</h2>

        <div style="background: #fef3c7; padding: 16px; border-radius: 12px; margin-bottom: 20px;">
            <p style="color: #92400e; font-size: 14px; margin: 0;">
                <strong>Protection anti-abus</strong> : Le système détecte automatiquement les tentatives d'utilisation du chatbot comme un assistant général (code, traduction, devoirs, etc.) et redirige poliment l'utilisateur vers votre activité.
            </p>
        </div>

        <div class="grid-2" style="gap: 24px;">
            <div>
                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                        <input type="checkbox" name="abuse_filter_enabled"
                               <?= isChecked($securitySettings, 'abuse_filter_enabled', $defaults) ? 'checked' : '' ?>
                               style="width: 20px; height: 20px;">
                        <span class="form-label" style="margin-bottom: 0;">Activer le filtre anti-abus</span>
                    </label>
                    <p class="form-hint">Bloque automatiquement les demandes hors contexte (code, traduction, devoirs...)</p>
                </div>

                <div class="form-group">
                    <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                        <input type="checkbox" name="rate_limit_enabled"
                               <?= isChecked($securitySettings, 'rate_limit_enabled', $defaults) ? 'checked' : '' ?>
                               style="width: 20px; height: 20px;">
                        <span class="form-label" style="margin-bottom: 0;">Activer la limite de requêtes</span>
                    </label>
                    <p class="form-hint">Limite le nombre de requêtes par IP (<?= RATE_LIMIT_PER_MINUTE ?> req/min)</p>
                </div>
            </div>

            <div>
                <div class="form-group">
                    <label class="form-label">Messages max par session</label>
                    <input type="number" name="max_messages_per_session" class="form-input" min="10" max="200"
                           value="<?= getVal($securitySettings, 'max_messages_per_session', $defaults) ?>">
                    <p class="form-hint">Après cette limite, l'utilisateur doit démarrer une nouvelle session</p>
                </div>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 20px; border-radius: 12px; margin-top: 20px;">
            <h4 style="font-size: 14px; margin-bottom: 12px;">Types de requêtes bloquées :</h4>
            <div style="display: flex; flex-wrap: wrap; gap: 8px;">
                <span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Programmation/Code</span>
                <span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Traduction</span>
                <span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Devoirs scolaires</span>
                <span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Rédaction de textes</span>
                <span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Questions générales</span>
                <span style="background: #fee2e2; color: #991b1b; padding: 4px 12px; border-radius: 20px; font-size: 12px;">Contenu sensible</span>
            </div>
        </div>
    </div>

    <div style="display: flex; gap: 12px; margin-top: 24px;">
        <button type="submit" class="btn btn-primary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
            </svg>
            Sauvegarder
        </button>
    </div>
</form>

<?php require_once 'includes/footer.php'; ?>
