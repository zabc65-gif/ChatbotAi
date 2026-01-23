<?php
/**
 * Gestion des textes de la landing page
 */

$pageTitle = 'Textes du Site';
require_once 'includes/header.php';

$success = '';
$error = '';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Hero Section
        $settings->set('hero_title', $_POST['hero_title'] ?? '', 'text', 'landing', 'Titre Hero');
        $settings->set('hero_subtitle', $_POST['hero_subtitle'] ?? '', 'text', 'landing', 'Sous-titre Hero');
        $settings->set('hero_cta', $_POST['hero_cta'] ?? '', 'string', 'landing', 'Bouton CTA Hero');

        // Section Avantages
        $settings->set('features_title', $_POST['features_title'] ?? '', 'string', 'landing', 'Titre Avantages');
        $settings->set('feature_1_title', $_POST['feature_1_title'] ?? '', 'string', 'landing', 'Avantage 1 titre');
        $settings->set('feature_1_desc', $_POST['feature_1_desc'] ?? '', 'text', 'landing', 'Avantage 1 description');
        $settings->set('feature_2_title', $_POST['feature_2_title'] ?? '', 'string', 'landing', 'Avantage 2 titre');
        $settings->set('feature_2_desc', $_POST['feature_2_desc'] ?? '', 'text', 'landing', 'Avantage 2 description');
        $settings->set('feature_3_title', $_POST['feature_3_title'] ?? '', 'string', 'landing', 'Avantage 3 titre');
        $settings->set('feature_3_desc', $_POST['feature_3_desc'] ?? '', 'text', 'landing', 'Avantage 3 description');

        // Section Secteurs
        $settings->set('sectors_title', $_POST['sectors_title'] ?? '', 'string', 'landing', 'Titre Secteurs');
        $settings->set('sector_btp_title', $_POST['sector_btp_title'] ?? '', 'string', 'landing', 'BTP titre');
        $settings->set('sector_btp_desc', $_POST['sector_btp_desc'] ?? '', 'text', 'landing', 'BTP description');
        $settings->set('sector_immo_title', $_POST['sector_immo_title'] ?? '', 'string', 'landing', 'Immobilier titre');
        $settings->set('sector_immo_desc', $_POST['sector_immo_desc'] ?? '', 'text', 'landing', 'Immobilier description');
        $settings->set('sector_ecom_title', $_POST['sector_ecom_title'] ?? '', 'string', 'landing', 'E-commerce titre');
        $settings->set('sector_ecom_desc', $_POST['sector_ecom_desc'] ?? '', 'text', 'landing', 'E-commerce description');

        // Section Tarifs
        $settings->set('pricing_title', $_POST['pricing_title'] ?? '', 'string', 'landing', 'Titre Tarifs');
        $settings->set('price_basic', $_POST['price_basic'] ?? '', 'string', 'landing', 'Prix Essentiel');
        $settings->set('price_pro', $_POST['price_pro'] ?? '', 'string', 'landing', 'Prix Pro');
        $settings->set('price_enterprise', $_POST['price_enterprise'] ?? '', 'string', 'landing', 'Prix Entreprise');

        $success = 'Textes sauvegardés avec succès !';
    } catch (Exception $e) {
        $error = 'Erreur lors de la sauvegarde : ' . $e->getMessage();
    }
}

// Récupérer les valeurs actuelles
$landingSettings = $settings->getGroup('landing');

// Valeurs par défaut
$defaults = [
    'hero_title' => "Boostez votre relation client avec l'IA",
    'hero_subtitle' => "Un chatbot intelligent qui répond à vos clients 24h/24, qualifie vos leads et libère votre temps pour ce qui compte vraiment.",
    'hero_cta' => "Essayer gratuitement",
    'features_title' => "Pourquoi choisir notre solution ?",
    'feature_1_title' => "Installation en 5 minutes",
    'feature_1_desc' => "Un simple copier-coller de code et votre chatbot est opérationnel sur votre site.",
    'feature_2_title' => "IA dernière génération",
    'feature_2_desc' => "Propulsé par les meilleurs modèles d'IA pour des réponses pertinentes et naturelles.",
    'feature_3_title' => "100% personnalisable",
    'feature_3_desc' => "Adaptez l'apparence et le comportement à votre marque et votre secteur.",
    'sectors_title' => "Adapté à votre secteur",
    'sector_btp_title' => "Artisans & BTP",
    'sector_btp_desc' => "Qualifiez vos demandes de devis et planifiez vos interventions automatiquement.",
    'sector_immo_title' => "Agences Immobilières",
    'sector_immo_desc' => "Pré-qualifiez vos acquéreurs et automatisez la prise de rendez-vous visites.",
    'sector_ecom_title' => "E-commerce",
    'sector_ecom_desc' => "Assistez vos clients dans leurs achats et réduisez l'abandon de panier.",
    'pricing_title' => "Tarifs simples et transparents",
    'price_basic' => "29",
    'price_pro' => "79",
    'price_enterprise' => "199"
];

function getValue($settings, $key, $defaults) {
    return htmlspecialchars($settings[$key]['value'] ?? $defaults[$key] ?? '');
}
?>

<div class="page-header">
    <h1 class="page-title">Textes du Site</h1>
    <p class="page-subtitle">Modifiez les textes de votre landing page</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" action="">
    <!-- Hero Section -->
    <div class="card">
        <h2 class="card-title">Section Hero (En-tête)</h2>

        <div class="form-group">
            <label class="form-label">Titre principal</label>
            <input type="text" name="hero_title" class="form-input"
                   value="<?= getValue($landingSettings, 'hero_title', $defaults) ?>">
        </div>

        <div class="form-group">
            <label class="form-label">Sous-titre</label>
            <textarea name="hero_subtitle" class="form-textarea" rows="2"><?= getValue($landingSettings, 'hero_subtitle', $defaults) ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Texte du bouton CTA</label>
            <input type="text" name="hero_cta" class="form-input" style="max-width: 300px;"
                   value="<?= getValue($landingSettings, 'hero_cta', $defaults) ?>">
        </div>
    </div>

    <!-- Features Section -->
    <div class="card">
        <h2 class="card-title">Section Avantages</h2>

        <div class="form-group">
            <label class="form-label">Titre de section</label>
            <input type="text" name="features_title" class="form-input"
                   value="<?= getValue($landingSettings, 'features_title', $defaults) ?>">
        </div>

        <div class="grid-3" style="gap: 24px; margin-top: 20px;">
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 12px;">Avantage 1</h4>
                <div class="form-group">
                    <label class="form-label">Titre</label>
                    <input type="text" name="feature_1_title" class="form-input"
                           value="<?= getValue($landingSettings, 'feature_1_title', $defaults) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="feature_1_desc" class="form-textarea" rows="2"><?= getValue($landingSettings, 'feature_1_desc', $defaults) ?></textarea>
                </div>
            </div>

            <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 12px;">Avantage 2</h4>
                <div class="form-group">
                    <label class="form-label">Titre</label>
                    <input type="text" name="feature_2_title" class="form-input"
                           value="<?= getValue($landingSettings, 'feature_2_title', $defaults) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="feature_2_desc" class="form-textarea" rows="2"><?= getValue($landingSettings, 'feature_2_desc', $defaults) ?></textarea>
                </div>
            </div>

            <div style="background: #f8fafc; padding: 20px; border-radius: 12px;">
                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 12px;">Avantage 3</h4>
                <div class="form-group">
                    <label class="form-label">Titre</label>
                    <input type="text" name="feature_3_title" class="form-input"
                           value="<?= getValue($landingSettings, 'feature_3_title', $defaults) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="feature_3_desc" class="form-textarea" rows="2"><?= getValue($landingSettings, 'feature_3_desc', $defaults) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Sectors Section -->
    <div class="card">
        <h2 class="card-title">Section Secteurs d'activité</h2>

        <div class="form-group">
            <label class="form-label">Titre de section</label>
            <input type="text" name="sectors_title" class="form-input"
                   value="<?= getValue($landingSettings, 'sectors_title', $defaults) ?>">
        </div>

        <div class="grid-3" style="gap: 24px; margin-top: 20px;">
            <div style="background: #fef3c7; padding: 20px; border-radius: 12px;">
                <h4 style="font-size: 14px; color: #92400e; margin-bottom: 12px;">Artisans & BTP</h4>
                <div class="form-group">
                    <label class="form-label">Titre</label>
                    <input type="text" name="sector_btp_title" class="form-input"
                           value="<?= getValue($landingSettings, 'sector_btp_title', $defaults) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="sector_btp_desc" class="form-textarea" rows="2"><?= getValue($landingSettings, 'sector_btp_desc', $defaults) ?></textarea>
                </div>
            </div>

            <div style="background: #dbeafe; padding: 20px; border-radius: 12px;">
                <h4 style="font-size: 14px; color: #1e40af; margin-bottom: 12px;">Immobilier</h4>
                <div class="form-group">
                    <label class="form-label">Titre</label>
                    <input type="text" name="sector_immo_title" class="form-input"
                           value="<?= getValue($landingSettings, 'sector_immo_title', $defaults) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="sector_immo_desc" class="form-textarea" rows="2"><?= getValue($landingSettings, 'sector_immo_desc', $defaults) ?></textarea>
                </div>
            </div>

            <div style="background: #dcfce7; padding: 20px; border-radius: 12px;">
                <h4 style="font-size: 14px; color: #166534; margin-bottom: 12px;">E-commerce</h4>
                <div class="form-group">
                    <label class="form-label">Titre</label>
                    <input type="text" name="sector_ecom_title" class="form-input"
                           value="<?= getValue($landingSettings, 'sector_ecom_title', $defaults) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="sector_ecom_desc" class="form-textarea" rows="2"><?= getValue($landingSettings, 'sector_ecom_desc', $defaults) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Pricing Section -->
    <div class="card">
        <h2 class="card-title">Section Tarifs</h2>

        <div class="form-group">
            <label class="form-label">Titre de section</label>
            <input type="text" name="pricing_title" class="form-input"
                   value="<?= getValue($landingSettings, 'pricing_title', $defaults) ?>">
        </div>

        <div class="grid-3" style="gap: 24px; margin-top: 20px;">
            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; text-align: center;">
                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 12px;">Offre Essentiel</h4>
                <div class="form-group">
                    <label class="form-label">Prix (€/mois)</label>
                    <input type="text" name="price_basic" class="form-input" style="text-align: center; font-size: 24px; font-weight: 700;"
                           value="<?= getValue($landingSettings, 'price_basic', $defaults) ?>">
                </div>
            </div>

            <div style="background: #eef2ff; padding: 20px; border-radius: 12px; text-align: center; border: 2px solid var(--primary);">
                <h4 style="font-size: 14px; color: var(--primary); margin-bottom: 12px;">Offre Pro (Populaire)</h4>
                <div class="form-group">
                    <label class="form-label">Prix (€/mois)</label>
                    <input type="text" name="price_pro" class="form-input" style="text-align: center; font-size: 24px; font-weight: 700;"
                           value="<?= getValue($landingSettings, 'price_pro', $defaults) ?>">
                </div>
            </div>

            <div style="background: #f8fafc; padding: 20px; border-radius: 12px; text-align: center;">
                <h4 style="font-size: 14px; color: var(--text-light); margin-bottom: 12px;">Offre Entreprise</h4>
                <div class="form-group">
                    <label class="form-label">Prix (€/mois)</label>
                    <input type="text" name="price_enterprise" class="form-input" style="text-align: center; font-size: 24px; font-weight: 700;"
                           value="<?= getValue($landingSettings, 'price_enterprise', $defaults) ?>">
                </div>
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
        <a href="../index.php" target="_blank" class="btn btn-secondary">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5z"/>
            </svg>
            Voir le site
        </a>
    </div>
</form>

<style>
    .grid-3 {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
    }
    @media (max-width: 1024px) {
        .grid-3 {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php require_once 'includes/footer.php'; ?>
