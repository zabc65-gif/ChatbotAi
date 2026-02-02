<?php
/**
 * Header Admin Multi-Agent V2
 */

// Inclure la configuration
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../../classes/Auth.php';
require_once __DIR__ . '/../../../classes/Database.php';

// Vérifier l'authentification
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    header('Location: /admin/login.php');
    exit;
}

// Récupérer l'utilisateur connecté
$user = $auth->getCurrentUser();

// Page courante pour la navigation
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Client ID (pour filtrer les agents)
// TODO: Pour une vraie implémentation multi-tenant, récupérer depuis la session
$clientId = $_GET['client_id'] ?? 1;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Multi-Agent Admin - <?= htmlspecialchars($pageTitle ?? 'Gestion des Agents') ?></title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <!-- FullCalendar CSS (pour le planning) -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= MULTI_AGENT_URL ?>/assets/css/admin.css" rel="stylesheet">

    <style>
        :root {
            --sidebar-width: 260px;
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(135deg, var(--secondary-color) 0%, #1a252f 100%);
            color: white;
            padding: 0;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h4 {
            margin: 0;
            font-weight: 600;
        }

        .sidebar-header small {
            color: rgba(255,255,255,0.6);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-section {
            padding: 0.5rem 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            color: rgba(255,255,255,0.4);
            letter-spacing: 1px;
        }

        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.2s;
            border-left: 3px solid transparent;
        }

        .sidebar-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .sidebar-nav .nav-link.active {
            background: rgba(52, 152, 219, 0.2);
            color: var(--primary-color);
            border-left-color: var(--primary-color);
        }

        .sidebar-nav .nav-link i {
            font-size: 1.1rem;
            width: 24px;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        /* Top Bar */
        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .page-title {
            margin: 0;
            font-weight: 600;
            color: var(--secondary-color);
        }

        /* Cards */
        .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-radius: 10px;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #f0f0f0;
            font-weight: 600;
        }

        /* Agent Cards */
        .agent-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .agent-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .agent-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #f0f0f0;
        }

        .agent-photo-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #2980b9);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: 600;
        }

        .specialty-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            background: #e8f4fc;
            color: #2980b9;
        }

        /* Stats */
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            .sidebar-header h4,
            .sidebar-header small,
            .nav-section,
            .sidebar-nav .nav-link span {
                display: none;
            }
            .sidebar-nav .nav-link {
                justify-content: center;
                padding: 1rem;
            }
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="bi bi-people-fill me-2"></i>Multi-Agent</h4>
            <small>Gestion des commerciaux</small>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">Principal</div>

            <a href="agents.php?client_id=<?= $clientId ?>" class="nav-link <?= $currentPage === 'agents' ? 'active' : '' ?>">
                <i class="bi bi-people"></i>
                <span>Agents</span>
            </a>

            <a href="agent-schedule.php?client_id=<?= $clientId ?>" class="nav-link <?= $currentPage === 'agent-schedule' ? 'active' : '' ?>">
                <i class="bi bi-calendar3"></i>
                <span>Planning</span>
            </a>

            <a href="appointments.php?client_id=<?= $clientId ?>" class="nav-link <?= $currentPage === 'appointments' ? 'active' : '' ?>">
                <i class="bi bi-calendar-check"></i>
                <span>Rendez-vous</span>
            </a>

            <div class="nav-section mt-4">Configuration</div>

            <a href="config.php?client_id=<?= $clientId ?>" class="nav-link <?= $currentPage === 'config' ? 'active' : '' ?>">
                <i class="bi bi-gear"></i>
                <span>Paramètres</span>
            </a>

            <a href="specialties.php?client_id=<?= $clientId ?>" class="nav-link <?= $currentPage === 'specialties' ? 'active' : '' ?>">
                <i class="bi bi-tags"></i>
                <span>Spécialités</span>
            </a>

            <div class="nav-section mt-4">Liens</div>

            <a href="/admin/" class="nav-link">
                <i class="bi bi-arrow-left"></i>
                <span>Retour Admin</span>
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
