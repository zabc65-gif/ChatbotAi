<?php
/**
 * Header commun administration
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../classes/Database.php';
require_once __DIR__ . '/../../classes/Auth.php';
require_once __DIR__ . '/../../classes/Settings.php';
require_once __DIR__ . '/../../classes/CSRF.php';

// Headers de sécurité
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data:;");

$db = new Database();
$auth = new Auth($db);
$settings = new Settings($db);

// Vérifier l'authentification
$auth->requireLogin('login.php');

$currentUser = $auth->getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Administration' ?> - ChatBot IA</title>
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --text: #1e293b;
            --text-light: #64748b;
            --bg: #f1f5f9;
            --white: #ffffff;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
            --sidebar-width: 260px;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            min-height: 100vh;
            display: flex;
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--text);
            color: white;
            padding: 24px;
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            overflow-y: auto;
        }
        .sidebar-logo {
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .sidebar-subtitle {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
            margin-bottom: 32px;
        }
        .sidebar-nav {
            list-style: none;
        }
        .sidebar-nav li {
            margin-bottom: 4px;
        }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .sidebar-nav a:hover,
        .sidebar-nav a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        .sidebar-nav a.active {
            background: var(--primary);
        }
        .sidebar-nav svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
        .sidebar-section {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.4);
            margin: 24px 0 12px;
            padding-left: 16px;
        }
        .sidebar-footer {
            position: absolute;
            bottom: 24px;
            left: 24px;
            right: 24px;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255,255,255,0.05);
            border-radius: 10px;
            margin-bottom: 12px;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        .user-name {
            font-weight: 500;
            font-size: 14px;
        }
        .user-role {
            font-size: 12px;
            color: rgba(255,255,255,0.5);
        }
        .logout-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: rgba(255,255,255,0.1);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 500;
            transition: background 0.2s;
        }
        .logout-btn:hover {
            background: rgba(239, 68, 68, 0.3);
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 32px;
            min-height: 100vh;
        }
        .page-header {
            margin-bottom: 32px;
        }
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
        }
        .page-subtitle {
            color: var(--text-light);
            margin-top: 4px;
        }

        /* Cards */
        .card {
            background: var(--white);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        .card-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        .form-label {
            display: block;
            font-weight: 500;
            color: var(--text);
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-hint {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
        }
        .form-input,
        .form-textarea,
        .form-select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.2s;
        }
        .form-input:focus,
        .form-textarea:focus,
        .form-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* Buttons */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s;
            font-family: inherit;
        }
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: var(--text);
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
        .btn-success {
            background: var(--success);
            color: white;
        }

        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-size: 14px;
        }
        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: var(--white);
            padding: 24px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
        }
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--primary);
        }
        .stat-label {
            color: var(--text-light);
            font-size: 14px;
            margin-top: 4px;
        }

        /* Grid */
        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        /* Mobile Menu Button */
        .mobile-menu-btn {
            display: none;
            position: fixed;
            top: 16px;
            left: 16px;
            z-index: 1001;
            width: 44px;
            height: 44px;
            background: var(--primary);
            border: none;
            border-radius: 10px;
            cursor: pointer;
            align-items: center;
            justify-content: center;
        }
        .mobile-menu-btn svg {
            width: 24px;
            height: 24px;
            fill: white;
        }
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .mobile-menu-btn {
                display: flex;
            }
            .sidebar {
                transform: translateX(-100%);
                z-index: 1000;
                transition: transform 0.3s ease;
            }
            .sidebar.open {
                transform: translateX(0);
            }
            .sidebar-overlay.open {
                display: block;
            }
            .main-content {
                margin-left: 0;
                padding-top: 80px;
            }
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Button -->
    <button class="mobile-menu-btn" onclick="toggleMobileMenu()" aria-label="Menu">
        <svg viewBox="0 0 24 24"><path d="M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z"/></svg>
    </button>
    <div class="sidebar-overlay" onclick="toggleMobileMenu()"></div>

    <aside class="sidebar">
        <div class="sidebar-logo">ChatBot IA</div>
        <div class="sidebar-subtitle">Administration</div>

        <nav>
            <ul class="sidebar-nav">
                <li>
                    <a href="index.php" class="<?= $currentPage === 'index' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                        Dashboard
                    </a>
                </li>

                <div class="sidebar-section">Chatbot</div>
                <li>
                    <a href="chatbot-settings.php" class="<?= $currentPage === 'chatbot-settings' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
                        Chatbot Principal
                    </a>
                </li>
                <li>
                    <a href="demo-chatbots.php" class="<?= $currentPage === 'demo-chatbots' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M4 6H2v14c0 1.1.9 2 2 2h14v-2H4V6zm16-4H8c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H8V4h12v12z"/></svg>
                        Chatbots Démo
                    </a>
                </li>
                <li>
                    <a href="conversations.php" class="<?= $currentPage === 'conversations' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M21 6h-2v9H6v2c0 .55.45 1 1 1h11l4 4V7c0-.55-.45-1-1-1zm-4 6V3c0-.55-.45-1-1-1H3c-.55 0-1 .45-1 1v14l4-4h10c.55 0 1-.45 1-1z"/></svg>
                        Conversations
                    </a>
                </li>

                <div class="sidebar-section">Site Web</div>
                <li>
                    <a href="site-settings.php" class="<?= $currentPage === 'site-settings' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                        Paramètres Site
                    </a>
                </li>
                <li>
                    <a href="landing-texts.php" class="<?= $currentPage === 'landing-texts' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                        Textes Landing Page
                    </a>
                </li>

                <li>
                    <a href="appointments.php" class="<?= $currentPage === 'appointments' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M19 3h-1V1h-2v2H8V1H6v2H5c-1.11 0-1.99.9-1.99 2L3 19c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H5V8h14v11zM9 10H7v2h2v-2zm4 0h-2v2h2v-2zm4 0h-2v2h2v-2z"/></svg>
                        Rendez-vous
                    </a>
                </li>

                <div class="sidebar-section">Clients</div>
                <li>
                    <a href="clients.php" class="<?= $currentPage === 'clients' ? 'active' : '' ?>">
                        <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                        Mes Clients
                    </a>
                </li>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($currentUser['username'], 0, 2)) ?></div>
                <div>
                    <div class="user-name"><?= htmlspecialchars($currentUser['username']) ?></div>
                    <div class="user-role"><?= ucfirst($currentUser['role']) ?></div>
                </div>
            </div>
            <a href="logout.php" class="logout-btn">Déconnexion</a>
        </div>
    </aside>

    <main class="main-content">
