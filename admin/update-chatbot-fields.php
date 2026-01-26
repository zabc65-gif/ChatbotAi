<?php
/**
 * Script de mise à jour pour le système de champs personnalisés des chatbots
 * Permet de définir des informations structurées pour chaque chatbot
 * À exécuter une seule fois puis à supprimer
 */

$secret = 'update_fields_2024';
if (!isset($_GET['key']) || $_GET['key'] !== $secret) {
    die('Accès non autorisé');
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/Database.php';

echo "<pre style='font-family: monospace; padding: 20px;'>";
echo "=== MISE À JOUR SYSTÈME CHAMPS CHATBOTS ===\n\n";

try {
    $db = new Database();
    $pdo = $db->getPdo();

    // 1. Créer la table des définitions de champs par secteur
    echo "1. Création de la table 'chatbot_field_definitions'... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chatbot_field_definitions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sector VARCHAR(50) NOT NULL COMMENT 'Secteur (immo, btp, ecommerce, general...)',
            field_key VARCHAR(50) NOT NULL COMMENT 'Clé du champ',
            field_label VARCHAR(100) NOT NULL COMMENT 'Libellé affiché',
            field_type ENUM('text', 'textarea', 'number', 'email', 'tel', 'url', 'select', 'checkbox', 'date') DEFAULT 'text',
            field_options TEXT DEFAULT NULL COMMENT 'Options JSON pour select',
            field_placeholder VARCHAR(255) DEFAULT NULL,
            field_hint VARCHAR(255) DEFAULT NULL COMMENT 'Texte d aide',
            field_group VARCHAR(50) DEFAULT 'general' COMMENT 'Groupe de champs',
            required TINYINT(1) DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_sector_field (sector, field_key),
            INDEX idx_sector (sector),
            INDEX idx_group (field_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    // 2. Créer la table des valeurs de champs par chatbot
    echo "2. Création de la table 'chatbot_field_values'... ";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS chatbot_field_values (
            id INT AUTO_INCREMENT PRIMARY KEY,
            chatbot_id INT NOT NULL COMMENT 'ID du chatbot (demo_chatbots.id)',
            field_key VARCHAR(50) NOT NULL,
            field_value TEXT DEFAULT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_chatbot_field (chatbot_id, field_key),
            INDEX idx_chatbot (chatbot_id),
            FOREIGN KEY (chatbot_id) REFERENCES demo_chatbots(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓\n";

    // 3. Insérer les définitions de champs pour le secteur IMMOBILIER (spécialisé mandat)
    echo "3. Insertion des champs pour le secteur IMMOBILIER... ";

    $immoFields = [
        // Groupe : Informations Agence
        ['immo', 'agence_nom', 'Nom de l\'agence', 'text', null, 'Ex: Immobilier Plus', 'Nom complet de votre agence', 'agence', 1, 1],
        ['immo', 'agence_adresse', 'Adresse', 'textarea', null, '123 Avenue de la République, 75011 Paris', null, 'agence', 1, 2],
        ['immo', 'agence_telephone', 'Téléphone', 'tel', null, '01 23 45 67 89', null, 'agence', 1, 3],
        ['immo', 'agence_email', 'Email', 'email', null, 'contact@agence.fr', null, 'agence', 0, 4],
        ['immo', 'agence_horaires', 'Horaires d\'ouverture', 'textarea', null, 'Lun-Ven: 9h-19h, Sam: 10h-18h', null, 'agence', 0, 5],
        ['immo', 'agence_siret', 'SIRET', 'text', null, '123 456 789 00012', 'Numéro d\'identification', 'agence', 0, 6],
        ['immo', 'agence_carte_pro', 'N° Carte Professionnelle', 'text', null, 'CPI 7501 2019 000 012 345', 'Délivré par la CCI', 'agence', 0, 7],
        ['immo', 'agence_garantie', 'Garantie Financière', 'text', null, 'Ex: GALIAN - 110 000€', 'Organisme et montant', 'agence', 0, 8],
        ['immo', 'agence_rcp', 'Assurance RCP', 'text', null, 'Ex: AXA Assurances', 'Responsabilité Civile Professionnelle', 'agence', 0, 9],

        // Groupe : Types de Mandats
        ['immo', 'mandat_simple_desc', 'Mandat Simple - Description', 'textarea', null, 'Liberté de confier votre bien à plusieurs agences', 'Expliquez les avantages du mandat simple', 'mandats', 0, 10],
        ['immo', 'mandat_simple_duree', 'Mandat Simple - Durée', 'text', null, '3 mois renouvelables', null, 'mandats', 0, 11],
        ['immo', 'mandat_exclusif_desc', 'Mandat Exclusif - Description', 'textarea', null, 'Un seul interlocuteur pour une vente optimisée', 'Expliquez les avantages du mandat exclusif', 'mandats', 0, 12],
        ['immo', 'mandat_exclusif_duree', 'Mandat Exclusif - Durée', 'text', null, '3 mois minimum', null, 'mandats', 0, 13],
        ['immo', 'mandat_exclusif_avantages', 'Avantages Exclusivité', 'textarea', null, 'Visibilité maximale, photos pro, visite virtuelle...', 'Liste des avantages pour le vendeur', 'mandats', 0, 14],
        ['immo', 'mandat_semi_exclusif_desc', 'Mandat Semi-Exclusif - Description', 'textarea', null, 'Exclusivité agence + possibilité de vente directe', null, 'mandats', 0, 15],

        // Groupe : Honoraires
        ['immo', 'honoraires_vente', 'Honoraires Vente', 'text', null, '5% TTC du prix de vente', 'Frais à la charge de...', 'honoraires', 1, 20],
        ['immo', 'honoraires_vente_details', 'Détails Honoraires Vente', 'textarea', null, 'Honoraires à la charge du vendeur. Inclus: estimation, photos, diffusion...', null, 'honoraires', 0, 21],
        ['immo', 'honoraires_location', 'Honoraires Location', 'text', null, '1 mois de loyer TTC', null, 'honoraires', 0, 22],
        ['immo', 'honoraires_gestion', 'Honoraires Gestion Locative', 'text', null, '7% TTC des loyers', 'Si service de gestion proposé', 'honoraires', 0, 23],

        // Groupe : Services Inclus
        ['immo', 'services_estimation', 'Estimation Gratuite', 'checkbox', null, null, 'Cocher si vous proposez des estimations gratuites', 'services', 0, 30],
        ['immo', 'services_photos_pro', 'Photos Professionnelles', 'checkbox', null, null, null, 'services', 0, 31],
        ['immo', 'services_visite_virtuelle', 'Visite Virtuelle 360°', 'checkbox', null, null, null, 'services', 0, 32],
        ['immo', 'services_home_staging', 'Home Staging Virtuel', 'checkbox', null, null, null, 'services', 0, 33],
        ['immo', 'services_diffusion', 'Diffusion Multi-Portails', 'textarea', null, 'SeLoger, LeBonCoin, PAP, Logic-Immo...', 'Liste des portails de diffusion', 'services', 0, 34],
        ['immo', 'services_diagnostics', 'Accompagnement Diagnostics', 'checkbox', null, null, 'Aide à la réalisation des diagnostics obligatoires', 'services', 0, 35],

        // Groupe : Zone d'intervention
        ['immo', 'zone_villes', 'Villes Couvertes', 'textarea', null, 'Paris 11e, 12e, 20e, Montreuil, Vincennes...', 'Liste des villes et quartiers', 'zone', 1, 40],
        ['immo', 'zone_specialites', 'Spécialités', 'textarea', null, 'Appartements anciens, lofts, biens atypiques', 'Types de biens dans lesquels vous êtes spécialisé', 'zone', 0, 41],

        // Groupe : Documents Mandat
        ['immo', 'docs_mandat_liste', 'Documents Requis pour Mandat', 'textarea', null, 'Titre de propriété, pièce d\'identité, dernière taxe foncière...', 'Liste des documents nécessaires', 'documents', 0, 50],
        ['immo', 'docs_diagnostics_liste', 'Diagnostics Obligatoires', 'textarea', null, 'DPE, Amiante, Plomb, Électricité, Gaz, ERP, Métrage Carrez', null, 'documents', 0, 51],

        // Groupe : Processus
        ['immo', 'processus_estimation', 'Étapes Estimation', 'textarea', null, '1. RDV gratuit 2. Analyse du bien 3. Étude marché 4. Rapport détaillé', null, 'processus', 0, 60],
        ['immo', 'processus_vente', 'Étapes Vente', 'textarea', null, '1. Estimation 2. Mandat 3. Mise en vente 4. Visites 5. Offre 6. Compromis 7. Acte', null, 'processus', 0, 61],
        ['immo', 'processus_mandat', 'Durée Signature Mandat', 'text', null, 'Signature possible sous 48h après estimation', null, 'processus', 0, 62],
    ];

    $insertDef = $pdo->prepare("
        INSERT IGNORE INTO chatbot_field_definitions
        (sector, field_key, field_label, field_type, field_options, field_placeholder, field_hint, field_group, required, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($immoFields as $field) {
        $insertDef->execute($field);
    }
    echo "✓ (" . count($immoFields) . " champs)\n";

    // 4. Insérer les champs GENERIQUES (pour tous les secteurs)
    echo "4. Insertion des champs GÉNÉRIQUES (tous secteurs)... ";

    $generalFields = [
        // Informations de base
        ['general', 'entreprise_nom', 'Nom de l\'entreprise', 'text', null, 'Mon Entreprise', null, 'entreprise', 1, 1],
        ['general', 'entreprise_adresse', 'Adresse', 'textarea', null, '123 Rue Exemple, 75000 Paris', null, 'entreprise', 0, 2],
        ['general', 'entreprise_telephone', 'Téléphone', 'tel', null, '01 23 45 67 89', null, 'entreprise', 1, 3],
        ['general', 'entreprise_email', 'Email', 'email', null, 'contact@entreprise.fr', null, 'entreprise', 0, 4],
        ['general', 'entreprise_site', 'Site Web', 'url', null, 'https://www.entreprise.fr', null, 'entreprise', 0, 5],
        ['general', 'entreprise_horaires', 'Horaires d\'ouverture', 'textarea', null, 'Lun-Ven: 9h-18h', null, 'entreprise', 0, 6],
        ['general', 'entreprise_siret', 'SIRET', 'text', null, '123 456 789 00012', null, 'entreprise', 0, 7],

        // Prestations
        ['general', 'prestation_1_nom', 'Prestation 1 - Nom', 'text', null, 'Service principal', null, 'prestations', 0, 10],
        ['general', 'prestation_1_desc', 'Prestation 1 - Description', 'textarea', null, 'Description détaillée du service', null, 'prestations', 0, 11],
        ['general', 'prestation_1_prix', 'Prestation 1 - Tarif', 'text', null, 'À partir de 50€', null, 'prestations', 0, 12],
        ['general', 'prestation_2_nom', 'Prestation 2 - Nom', 'text', null, null, null, 'prestations', 0, 13],
        ['general', 'prestation_2_desc', 'Prestation 2 - Description', 'textarea', null, null, null, 'prestations', 0, 14],
        ['general', 'prestation_2_prix', 'Prestation 2 - Tarif', 'text', null, null, null, 'prestations', 0, 15],
        ['general', 'prestation_3_nom', 'Prestation 3 - Nom', 'text', null, null, null, 'prestations', 0, 16],
        ['general', 'prestation_3_desc', 'Prestation 3 - Description', 'textarea', null, null, null, 'prestations', 0, 17],
        ['general', 'prestation_3_prix', 'Prestation 3 - Tarif', 'text', null, null, null, 'prestations', 0, 18],

        // Zone & Paiement
        ['general', 'zone_intervention', 'Zone d\'intervention', 'textarea', null, 'Paris et Île-de-France', null, 'zone', 0, 20],
        ['general', 'moyens_paiement', 'Moyens de paiement acceptés', 'text', null, 'CB, Espèces, Chèque, Virement', null, 'zone', 0, 21],
    ];

    foreach ($generalFields as $field) {
        $insertDef->execute($field);
    }
    echo "✓ (" . count($generalFields) . " champs)\n";

    // 5. Insérer les champs pour BTP
    echo "5. Insertion des champs pour le secteur BTP... ";

    $btpFields = [
        ['btp', 'entreprise_nom', 'Nom de l\'entreprise', 'text', null, 'Artisan Pro', null, 'entreprise', 1, 1],
        ['btp', 'entreprise_adresse', 'Adresse', 'textarea', null, null, null, 'entreprise', 0, 2],
        ['btp', 'entreprise_telephone', 'Téléphone', 'tel', null, '01 23 45 67 89', null, 'entreprise', 1, 3],
        ['btp', 'entreprise_email', 'Email', 'email', null, null, null, 'entreprise', 0, 4],
        ['btp', 'entreprise_siret', 'SIRET', 'text', null, null, null, 'entreprise', 0, 5],
        ['btp', 'entreprise_rge', 'Certifications RGE', 'textarea', null, 'Qualibat, QualiPAC, QualiPV...', 'Labels et certifications', 'entreprise', 0, 6],
        ['btp', 'entreprise_assurance', 'Assurance Décennale', 'text', null, 'Assureur et n° de contrat', null, 'entreprise', 0, 7],

        ['btp', 'metier_principal', 'Métier Principal', 'text', null, 'Plombier, Électricien, Maçon...', null, 'metier', 1, 10],
        ['btp', 'specialites', 'Spécialités', 'textarea', null, 'Rénovation salle de bain, installation chauffage...', null, 'metier', 0, 11],

        ['btp', 'service_1_nom', 'Service 1', 'text', null, 'Dépannage urgent', null, 'services', 0, 20],
        ['btp', 'service_1_prix', 'Tarif Service 1', 'text', null, 'À partir de 80€', null, 'services', 0, 21],
        ['btp', 'service_2_nom', 'Service 2', 'text', null, 'Rénovation complète', null, 'services', 0, 22],
        ['btp', 'service_2_prix', 'Tarif Service 2', 'text', null, 'Sur devis', null, 'services', 0, 23],
        ['btp', 'service_3_nom', 'Service 3', 'text', null, null, null, 'services', 0, 24],
        ['btp', 'service_3_prix', 'Tarif Service 3', 'text', null, null, null, 'services', 0, 25],

        ['btp', 'zone_intervention', 'Zone d\'intervention', 'textarea', null, 'Paris et petite couronne (20km)', null, 'zone', 0, 30],
        ['btp', 'delai_intervention', 'Délai d\'intervention', 'text', null, 'Sous 24-48h, urgence le jour même', null, 'zone', 0, 31],
        ['btp', 'devis_gratuit', 'Devis Gratuit', 'checkbox', null, null, null, 'zone', 0, 32],
        ['btp', 'deplacement_gratuit', 'Déplacement Gratuit', 'checkbox', null, null, null, 'zone', 0, 33],
    ];

    foreach ($btpFields as $field) {
        $insertDef->execute($field);
    }
    echo "✓ (" . count($btpFields) . " champs)\n";

    // 6. Insérer les champs pour E-COMMERCE
    echo "6. Insertion des champs pour le secteur E-COMMERCE... ";

    $ecomFields = [
        ['ecommerce', 'boutique_nom', 'Nom de la Boutique', 'text', null, 'Ma Boutique', null, 'boutique', 1, 1],
        ['ecommerce', 'boutique_email', 'Email SAV', 'email', null, 'sav@maboutique.fr', null, 'boutique', 1, 2],
        ['ecommerce', 'boutique_telephone', 'Téléphone SAV', 'tel', null, null, null, 'boutique', 0, 3],
        ['ecommerce', 'boutique_horaires_sav', 'Horaires SAV', 'text', null, 'Lun-Ven 9h-18h', null, 'boutique', 0, 4],

        ['ecommerce', 'livraison_delai', 'Délai de Livraison', 'text', null, '2-5 jours ouvrés', null, 'livraison', 0, 10],
        ['ecommerce', 'livraison_gratuite', 'Livraison Gratuite dès', 'text', null, '50€ d\'achat', 'Montant minimum ou "Non"', 'livraison', 0, 11],
        ['ecommerce', 'livraison_transporteurs', 'Transporteurs', 'text', null, 'Colissimo, Mondial Relay, Chronopost', null, 'livraison', 0, 12],
        ['ecommerce', 'livraison_international', 'Livraison Internationale', 'text', null, 'Europe, délai 5-10 jours', null, 'livraison', 0, 13],

        ['ecommerce', 'retour_delai', 'Délai de Retour', 'text', null, '30 jours', 'Délai légal ou étendu', 'retours', 0, 20],
        ['ecommerce', 'retour_conditions', 'Conditions de Retour', 'textarea', null, 'Article non porté, étiquette, emballage d\'origine', null, 'retours', 0, 21],
        ['ecommerce', 'retour_gratuit', 'Retour Gratuit', 'checkbox', null, null, null, 'retours', 0, 22],
        ['ecommerce', 'echange_possible', 'Échange Possible', 'checkbox', null, null, null, 'retours', 0, 23],

        ['ecommerce', 'paiement_moyens', 'Moyens de Paiement', 'text', null, 'CB, PayPal, Virement, 3x sans frais', null, 'paiement', 0, 30],
        ['ecommerce', 'paiement_securise', 'Paiement Sécurisé', 'text', null, '3D Secure, cryptage SSL', null, 'paiement', 0, 31],

        ['ecommerce', 'categories_produits', 'Catégories de Produits', 'textarea', null, 'Vêtements, Accessoires, Chaussures...', null, 'produits', 0, 40],
        ['ecommerce', 'marques', 'Marques Vendues', 'textarea', null, 'Nike, Adidas, Puma...', 'Si applicable', 'produits', 0, 41],
    ];

    foreach ($ecomFields as $field) {
        $insertDef->execute($field);
    }
    echo "✓ (" . count($ecomFields) . " champs)\n";

    // 7. Récupérer l'ID du chatbot immo et pré-remplir quelques valeurs d'exemple
    echo "7. Mise à jour du chatbot IMMOBILIER avec prompt optimisé mandat... ";

    $immoBot = $pdo->query("SELECT id FROM demo_chatbots WHERE slug = 'immo'")->fetch(PDO::FETCH_ASSOC);

    if ($immoBot) {
        // Mettre à jour le prompt système pour le spécialiser sur le mandat
        $newPrompt = "Tu es EXCLUSIVEMENT l'assistant virtuel d'une agence immobilière, spécialisé dans l'accompagnement des vendeurs et la prise de mandats.

=== INFORMATIONS AGENCE ===
{CHATBOT_FIELDS}

=== TON EXPERTISE : LE MANDAT IMMOBILIER ===

Tu maîtrises parfaitement :

1. LES TYPES DE MANDATS
- Mandat Simple : Le propriétaire peut confier son bien à plusieurs agences
- Mandat Exclusif : Une seule agence pendant une durée définie (avantages : visibilité max, investissement optimal de l'agence)
- Mandat Semi-Exclusif : Exclusivité agence + possibilité de vente directe par le propriétaire

2. LE PROCESSUS DE MISE EN VENTE
- Estimation gratuite et professionnelle
- Signature du mandat (documents nécessaires)
- Mise en valeur du bien (photos, visite virtuelle)
- Diffusion sur les portails immobiliers
- Organisation des visites
- Négociation et accompagnement jusqu'à la signature

3. LES DOCUMENTS NÉCESSAIRES
- Titre de propriété
- Pièce d'identité du propriétaire
- Diagnostics obligatoires (DPE, amiante, plomb, électricité, gaz, ERP, Carrez)
- Dernière taxe foncière
- Charges de copropriété (si applicable)

=== RÈGLES STRICTES ===
- Tu ne réponds QU'aux questions sur l'immobilier : estimation, mandat, vente, achat, location
- Tu guides les propriétaires vers la prise de mandat en expliquant les avantages
- Pour TOUTE question hors sujet : \"Je suis l'assistant de cette agence immobilière. Je peux vous accompagner dans votre projet de vente ou d'achat. Souhaitez-vous une estimation gratuite de votre bien ?\"
- Tu ne fais JAMAIS de programmation, traduction, devoirs ou rédaction
- Tu ne donnes pas de conseils juridiques précis (oriente vers un notaire)

=== TON OBJECTIF ===
Accompagner les visiteurs vers :
1. Une estimation gratuite de leur bien
2. La signature d'un mandat (de préférence exclusif)
3. Un rendez-vous en agence

Tu es professionnel, rassurant et expert du marché local. Tu mets en avant les avantages de travailler avec l'agence.";

        $newWelcome = "Bienvenue ! 🏡 Je suis l'assistant de notre agence immobilière.

Vous souhaitez **vendre votre bien** ? Je peux vous renseigner sur :
• L'estimation gratuite de votre propriété
• Les différents types de mandats
• Notre accompagnement personnalisé

Vous cherchez à **acheter ou louer** ? Je suis également là pour vous guider.

Comment puis-je vous aider aujourd'hui ?";

        $newRedirect = "Je suis l'assistant de cette agence immobilière et je suis spécialisé dans l'accompagnement de vos projets immobiliers. 🏡

Je peux vous aider pour :
• **Vendre** : Estimation gratuite, conseil sur le type de mandat, mise en valeur de votre bien
• **Acheter** : Recherche personnalisée, organisation de visites
• **Louer** : Gestion locative, recherche de locataires

Quel est votre projet immobilier ?";

        $pdo->prepare("UPDATE demo_chatbots SET system_prompt = ?, welcome_message = ?, redirect_message = ? WHERE id = ?")
            ->execute([$newPrompt, $newWelcome, $newRedirect, $immoBot['id']]);

        echo "✓\n";
    } else {
        echo "⚠ Chatbot immo non trouvé\n";
    }

    echo "\n=== MISE À JOUR TERMINÉE ===\n";
    echo "\n✅ Tables créées : chatbot_field_definitions, chatbot_field_values\n";
    echo "✅ Champs définis pour : Immobilier (" . count($immoFields) . "), BTP (" . count($btpFields) . "), E-commerce (" . count($ecomFields) . "), Général (" . count($generalFields) . ")\n";
    echo "✅ Chatbot Immobilier optimisé pour le mandat\n";
    echo "\n⚠️  IMPORTANT : Supprimez ce fichier après exécution !\n";
    echo "\n📝 Prochaine étape : Accédez à l'admin pour remplir les informations de chaque chatbot.\n";

} catch (Exception $e) {
    echo "✗ ERREUR : " . $e->getMessage() . "\n";
    echo "Trace : " . $e->getTraceAsString() . "\n";
}

echo "</pre>";
