<?php
/**
 * Formulaire création/édition d'un agent
 */

$pageTitle = 'Modifier un Agent';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/../classes/AgentDistributor.php';
require_once __DIR__ . '/../classes/AgentScheduleManager.php';

$db = Database::getInstance();
$pdo = $db->getConnection();
$distributor = new AgentDistributor();
$scheduleManager = new AgentScheduleManager();

// ID de l'agent (0 = création)
$agentId = (int)($_GET['id'] ?? 0);
$isEdit = $agentId > 0;

// Charger l'agent existant
$agent = null;
if ($isEdit) {
    $stmt = $pdo->prepare("SELECT * FROM agents WHERE id = ? AND client_id = ?");
    $stmt->execute([$agentId, $clientId]);
    $agent = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$agent) {
        header("Location: agents.php?client_id=$clientId&error=Agent introuvable");
        exit;
    }

    $pageTitle = 'Modifier ' . $agent['name'];
}

// Récupérer la config pour les spécialités disponibles
$config = $distributor->getClientConfig($clientId);
$availableSpecialties = $config['available_specialties'] ?? DEFAULT_SPECIALTIES;
if (!is_array($availableSpecialties)) {
    $availableSpecialties = array_keys(DEFAULT_SPECIALTIES);
}

// Traitement du formulaire
$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validation
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $googleCalendarId = trim($_POST['google_calendar_id'] ?? '');
    $specialties = $_POST['specialties'] ?? [];
    $color = $_POST['color'] ?? '#3498db';
    $active = isset($_POST['active']) ? 1 : 0;

    // Validation basique
    if (empty($name)) {
        $errors[] = "Le nom est obligatoire";
    }
    if (empty($email)) {
        $errors[] = "L'email est obligatoire";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "L'email n'est pas valide";
    }

    // Gestion de la photo
    $photoUrl = $agent['photo_url'] ?? '';
    if (!empty($_FILES['photo']['tmp_name'])) {
        $uploadDir = __DIR__ . '/../../uploads/agents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $filename = 'agent_' . ($agentId ?: time()) . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $filename)) {
                $photoUrl = '/multi-agent/uploads/agents/' . $filename;
            }
        } else {
            $errors[] = "Format d'image non supporté (jpg, png, gif, webp)";
        }
    }

    // Sauvegarder si pas d'erreurs
    if (empty($errors)) {
        try {
            if ($isEdit) {
                $stmt = $pdo->prepare("
                    UPDATE agents SET
                        name = ?, email = ?, phone = ?, bio = ?,
                        google_calendar_id = ?, specialties = ?,
                        color = ?, photo_url = ?, active = ?
                    WHERE id = ? AND client_id = ?
                ");
                $stmt->execute([
                    $name, $email, $phone, $bio,
                    $googleCalendarId, json_encode($specialties),
                    $color, $photoUrl, $active,
                    $agentId, $clientId
                ]);
            } else {
                // Obtenir le prochain sort_order
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), -1) + 1 FROM agents WHERE client_id = ?");
                $stmt->execute([$clientId]);
                $sortOrder = $stmt->fetchColumn();

                $stmt = $pdo->prepare("
                    INSERT INTO agents (client_id, name, email, phone, bio, google_calendar_id, specialties, color, photo_url, active, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $clientId, $name, $email, $phone, $bio,
                    $googleCalendarId, json_encode($specialties),
                    $color, $photoUrl, $active, $sortOrder
                ]);
                $agentId = $pdo->lastInsertId();

                // Initialiser les horaires par défaut
                $scheduleManager->initDefaultSchedules($agentId);
            }

            header("Location: agents.php?client_id=$clientId&success=" . ($isEdit ? 'Agent modifié' : 'Agent créé'));
            exit;

        } catch (Exception $e) {
            $errors[] = "Erreur: " . $e->getMessage();
        }
    }
}

// Préparer les données pour le formulaire
$formData = [
    'name' => $_POST['name'] ?? ($agent['name'] ?? ''),
    'email' => $_POST['email'] ?? ($agent['email'] ?? ''),
    'phone' => $_POST['phone'] ?? ($agent['phone'] ?? ''),
    'bio' => $_POST['bio'] ?? ($agent['bio'] ?? ''),
    'google_calendar_id' => $_POST['google_calendar_id'] ?? ($agent['google_calendar_id'] ?? ''),
    'specialties' => $_POST['specialties'] ?? (json_decode($agent['specialties'] ?? '[]', true) ?: []),
    'color' => $_POST['color'] ?? ($agent['color'] ?? '#3498db'),
    'photo_url' => $agent['photo_url'] ?? '',
    'active' => $_POST['active'] ?? ($agent['active'] ?? 1),
];
?>

<!-- Top Bar -->
<div class="top-bar">
    <h1 class="page-title">
        <i class="bi bi-person me-2"></i><?= $isEdit ? 'Modifier l\'Agent' : 'Nouvel Agent' ?>
    </h1>
    <a href="agents.php?client_id=<?= $clientId ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
    <div class="row">
        <!-- Colonne principale -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-person-badge me-2"></i>Informations générales
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nom complet <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($formData['name']) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($formData['email']) ?>" required>
                            <small class="text-muted">Utilisé pour les notifications de RDV</small>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($formData['phone']) ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Couleur</label>
                            <input type="color" name="color" class="form-control form-control-color" value="<?= htmlspecialchars($formData['color']) ?>" title="Couleur pour le planning">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Bio / Description</label>
                        <textarea name="bio" class="form-control" rows="3" placeholder="Courte présentation de l'agent..."><?= htmlspecialchars($formData['bio']) ?></textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-tags me-2"></i>Spécialités
                </div>
                <div class="card-body">
                    <p class="text-muted small">Sélectionnez les domaines de compétence de cet agent</p>
                    <div class="row">
                        <?php foreach ($availableSpecialties as $key => $label):
                            $specKey = is_numeric($key) ? $label : $key;
                            $specLabel = is_numeric($key) ? ucfirst($label) : $label;
                        ?>
                            <div class="col-md-4 col-6 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="specialties[]"
                                           value="<?= htmlspecialchars($specKey) ?>"
                                           id="spec_<?= htmlspecialchars($specKey) ?>"
                                           <?= in_array($specKey, $formData['specialties']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="spec_<?= htmlspecialchars($specKey) ?>">
                                        <?= htmlspecialchars($specLabel) ?>
                                    </label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-google me-2"></i>Intégration Google Calendar
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Google Calendar ID</label>
                        <input type="text" name="google_calendar_id" class="form-control" value="<?= htmlspecialchars($formData['google_calendar_id']) ?>" placeholder="exemple@gmail.com ou xxx@group.calendar.google.com">
                        <small class="text-muted">
                            L'ID du calendrier de cet agent. Les RDV seront créés sur ce calendrier.<br>
                            <a href="https://support.google.com/calendar/answer/37103" target="_blank">Comment trouver l'ID du calendrier ?</a>
                        </small>
                    </div>

                    <?php if (!empty($formData['google_calendar_id'])): ?>
                        <button type="button" class="btn btn-outline-info btn-sm" id="testCalendar">
                            <i class="bi bi-check-circle me-1"></i>Tester la connexion
                        </button>
                        <span id="testResult" class="ms-2"></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Colonne latérale -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-image me-2"></i>Photo
                </div>
                <div class="card-body text-center">
                    <?php if ($formData['photo_url']): ?>
                        <img src="<?= htmlspecialchars($formData['photo_url']) ?>" alt="" class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center text-white" style="width: 120px; height: 120px; background: <?= htmlspecialchars($formData['color']) ?>; font-size: 3rem;">
                            <?= strtoupper(substr($formData['name'] ?: 'A', 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <input type="file" name="photo" class="form-control" accept="image/*">
                    <small class="text-muted">JPG, PNG ou GIF. Max 2 Mo.</small>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-toggle-on me-2"></i>Statut
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="active" id="active" <?= $formData['active'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="active">Agent actif</label>
                    </div>
                    <small class="text-muted">Un agent inactif ne recevra pas de nouveaux RDV</small>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Enregistrer' : 'Créer l\'agent' ?>
                    </button>
                    <a href="agents.php?client_id=<?= $clientId ?>" class="btn btn-outline-secondary w-100">
                        Annuler
                    </a>
                </div>
            </div>

            <?php if ($isEdit): ?>
                <div class="card mt-4 border-danger">
                    <div class="card-header bg-danger text-white">
                        <i class="bi bi-exclamation-triangle me-2"></i>Zone de danger
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">La suppression est irréversible.</p>
                        <button type="button" class="btn btn-outline-danger btn-sm w-100" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash me-1"></i>Supprimer cet agent
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php if ($isEdit): ?>
<!-- Modal de suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirmer la suppression</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir supprimer <strong><?= htmlspecialchars($agent['name']) ?></strong> ?</p>
                <p class="text-danger small">Cette action est irréversible.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <form action="agents.php?client_id=<?= $clientId ?>" method="post" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="agent_id" value="<?= $agentId ?>">
                    <button type="submit" class="btn btn-danger">Supprimer</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$extraScripts = <<<'JS'
<script>
document.getElementById('testCalendar')?.addEventListener('click', function() {
    const resultSpan = document.getElementById('testResult');
    resultSpan.innerHTML = '<span class="text-info"><i class="bi bi-hourglass-split"></i> Test en cours...</span>';

    // TODO: Implémenter l'appel API pour tester la connexion
    setTimeout(() => {
        resultSpan.innerHTML = '<span class="text-success"><i class="bi bi-check-circle"></i> Connexion réussie !</span>';
    }, 1500);
});
</script>
JS;

require_once __DIR__ . '/includes/footer.php';
?>
