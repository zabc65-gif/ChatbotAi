<?php
/**
 * Page d'accueil - ChatBot IA
 * Charge dynamiquement les chatbots depuis la base de données
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';

// Charger les chatbots à afficher sur le site (max 3)
$homepageChatbots = [];
try {
    $db = new Database();
    $homepageChatbots = $db->fetchAll(
        "SELECT slug, name, icon, color, welcome_message
         FROM demo_chatbots
         WHERE active = 1 AND show_on_site = 1
         ORDER BY sort_order ASC, id ASC
         LIMIT 3"
    );
} catch (Exception $e) {
    // Silently fail - use empty array
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Chatbot IA intelligent pour votre site web. Boostez vos conversions avec un assistant virtuel disponible 24h/24.">
    <title>ChatBot IA - Assistant Virtuel Intelligent pour votre Site Web</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Widget CSS -->
    <link rel="stylesheet" href="assets/css/widget.css">

    <style>
        /* Reset et base */
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --secondary: #f1f5f9;
            --text: #1e293b;
            --text-light: #64748b;
            --white: #ffffff;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text);
            line-height: 1.6;
            background: var(--white);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            z-index: 1000;
            padding: 16px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .navbar .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .nav-links {
            display: flex;
            gap: 32px;
            list-style: none;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text);
            font-weight: 500;
            transition: color 0.2s;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--gradient);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: var(--secondary);
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 120px 0 80px;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
        }

        .hero .container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .hero-content h1 {
            font-size: 56px;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
        }

        .hero-content h1 span {
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-content p {
            font-size: 20px;
            color: var(--text-light);
            margin-bottom: 32px;
            max-width: 500px;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
        }

        .hero-stats {
            display: flex;
            gap: 40px;
            margin-top: 48px;
            padding-top: 32px;
            border-top: 1px solid var(--secondary);
        }

        .stat {
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--primary);
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-light);
        }

        /* Demo Preview */
        .hero-demo {
            position: relative;
        }

        .demo-mockup {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0, 0, 0, 0.15);
            padding: 20px;
            position: relative;
        }

        .demo-browser {
            display: flex;
            gap: 8px;
            margin-bottom: 16px;
        }

        .demo-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #e2e8f0;
        }

        .demo-dot:nth-child(1) { background: #ef4444; }
        .demo-dot:nth-child(2) { background: #fbbf24; }
        .demo-dot:nth-child(3) { background: #22c55e; }

        .demo-content {
            background: #f8fafc;
            border-radius: 16px;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--text-light);
        }

        .demo-badge {
            position: absolute;
            bottom: -20px;
            right: -20px;
            background: var(--gradient);
            color: white;
            padding: 12px 20px;
            border-radius: 100px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 10px 30px rgba(99, 102, 241, 0.4);
        }

        /* Features Section */
        .features {
            padding: 100px 0;
        }

        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 60px;
        }

        .section-header h2 {
            font-size: 40px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .section-header p {
            font-size: 18px;
            color: var(--text-light);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .feature-card {
            background: var(--secondary);
            padding: 32px;
            border-radius: 20px;
            transition: all 0.3s;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .feature-icon {
            width: 56px;
            height: 56px;
            background: var(--gradient);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .feature-icon svg {
            width: 28px;
            height: 28px;
            fill: white;
        }

        .feature-card h3 {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .feature-card p {
            color: var(--text-light);
            font-size: 15px;
        }

        /* Booking Features Section */
        .booking-features {
            padding: 100px 0;
            background: linear-gradient(180deg, #f0f9ff 0%, #ffffff 100%);
        }

        .booking-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
            align-items: center;
        }

        .booking-demo {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
        }

        .booking-chat {
            background: #f8fafc;
            border-radius: 16px;
            padding: 20px;
        }

        .booking-message {
            margin-bottom: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            max-width: 85%;
            font-size: 14px;
        }

        .booking-message.bot {
            background: var(--primary);
            color: white;
            border-bottom-left-radius: 4px;
        }

        .booking-message.user {
            background: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .booking-benefits {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .booking-benefit {
            display: flex;
            gap: 20px;
            align-items: flex-start;
        }

        .booking-benefit-icon {
            width: 56px;
            height: 56px;
            background: var(--gradient);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .booking-benefit h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .booking-benefit p {
            color: var(--text-light);
            font-size: 15px;
            line-height: 1.5;
        }

        /* Sectors Section */
        .sectors {
            padding: 100px 0;
            background: #f8fafc;
        }

        .sectors-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
        }

        .sector-card {
            background: white;
            padding: 40px 32px;
            border-radius: 20px;
            text-align: center;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .sector-card:hover {
            border-color: var(--sector-color, var(--primary));
            transform: translateY(-8px);
        }

        .sector-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            background: color-mix(in srgb, var(--sector-color, var(--primary)) 15%, white);
        }

        .sector-card h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .sector-card p {
            color: var(--text-light);
            margin-bottom: 24px;
        }

        /* Pricing Section */
        .pricing {
            padding: 100px 0;
        }

        .pricing-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 32px;
            max-width: 1000px;
            margin: 0 auto;
        }

        .pricing-card {
            background: white;
            border: 2px solid var(--secondary);
            border-radius: 24px;
            padding: 40px;
            position: relative;
            transition: all 0.3s;
        }

        .pricing-card:hover {
            border-color: var(--primary);
        }

        .pricing-card.popular {
            border-color: var(--primary);
            transform: scale(1.05);
        }

        .pricing-card.popular::before {
            content: 'Populaire';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--gradient);
            color: white;
            padding: 4px 16px;
            border-radius: 100px;
            font-size: 12px;
            font-weight: 600;
        }

        .pricing-card h3 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .pricing-card .price {
            font-size: 48px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .pricing-card .price span {
            font-size: 18px;
            font-weight: 500;
            color: var(--text-light);
        }

        .pricing-card .description {
            color: var(--text-light);
            margin-bottom: 24px;
        }

        .pricing-features {
            list-style: none;
            margin-bottom: 32px;
        }

        .pricing-features li {
            padding: 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pricing-features li::before {
            content: '✓';
            color: var(--primary);
            font-weight: 700;
        }

        .pricing-card .btn {
            width: 100%;
            justify-content: center;
        }

        /* CTA Section */
        .cta {
            padding: 100px 0;
            background: var(--gradient);
            text-align: center;
            color: white;
        }

        .cta h2 {
            font-size: 40px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .cta p {
            font-size: 18px;
            opacity: 0.9;
            margin-bottom: 32px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .cta .btn-white {
            background: white;
            color: var(--primary);
        }

        /* Footer */
        .footer {
            padding: 60px 0 30px;
            background: var(--text);
            color: white;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }

        .footer-brand p {
            color: rgba(255,255,255,0.7);
            margin-top: 16px;
        }

        .footer h4 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .footer ul {
            list-style: none;
        }

        .footer ul li {
            margin-bottom: 12px;
        }

        .footer ul a {
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: color 0.2s;
        }

        .footer ul a:hover {
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255,255,255,0.1);
            padding-top: 30px;
            text-align: center;
            color: rgba(255,255,255,0.5);
            font-size: 14px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .hero .container {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .hero-content p {
                margin-left: auto;
                margin-right: auto;
            }

            .hero-buttons {
                justify-content: center;
            }

            .hero-stats {
                justify-content: center;
            }

            .hero-demo {
                display: none;
            }

            .features-grid,
            .sectors-grid,
            .pricing-cards {
                grid-template-columns: 1fr;
            }

            .booking-layout {
                grid-template-columns: 1fr;
            }

            .booking-demo {
                order: 2;
            }

            .footer-content {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }

            .hero-content h1 {
                font-size: 36px;
            }

            .section-header h2 {
                font-size: 28px;
            }

            .footer-content {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="container">
            <div class="logo">ChatBot IA</div>
            <ul class="nav-links">
                <li><a href="#features">Fonctionnalités</a></li>
                <li><a href="#booking">Rendez-vous</a></li>
                <li><a href="#sectors">Secteurs</a></li>
                <li><a href="#pricing">Tarifs</a></li>
                <li><a href="demo.php">Démo</a></li>
            </ul>
            <a href="#contact" class="btn btn-primary">Nous contacter</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="hero-content">
                <h1>
                    Boostez vos conversions avec un
                    <span>Assistant IA</span>
                </h1>
                <p>
                    Un chatbot intelligent disponible 24h/24 pour répondre à vos clients,
                    qualifier vos leads et augmenter vos ventes. Simple à installer, puissant en résultats.
                </p>
                <div class="hero-buttons">
                    <a href="demo.php" class="btn btn-primary">
                        Essayer la démo
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 4l-1.41 1.41L16.17 11H4v2h12.17l-5.58 5.59L12 20l8-8z"/>
                        </svg>
                    </a>
                    <a href="#pricing" class="btn btn-secondary">Voir les tarifs</a>
                </div>
                <div class="hero-stats">
                    <div class="stat">
                        <div class="stat-value">24/7</div>
                        <div class="stat-label">Disponibilité</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">&lt;2s</div>
                        <div class="stat-label">Temps de réponse</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value">+40%</div>
                        <div class="stat-label">Conversions</div>
                    </div>
                </div>
            </div>
            <div class="hero-demo">
                <div class="demo-mockup">
                    <div class="demo-browser">
                        <div class="demo-dot"></div>
                        <div class="demo-dot"></div>
                        <div class="demo-dot"></div>
                    </div>
                    <div class="demo-content">
                        Testez le chat en bas à droite
                    </div>
                    <div class="demo-badge">IA en temps réel</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="container">
            <div class="section-header">
                <h2>Tout ce dont vous avez besoin</h2>
                <p>Un assistant IA complet et facile à intégrer sur n'importe quel site web.</p>
            </div>
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24"><path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/></svg>
                    </div>
                    <h3>Disponible 24h/24</h3>
                    <p>Votre assistant ne dort jamais. Il répond instantanément à vos visiteurs, même la nuit et les week-ends.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24"><path d="M9 21c0 .55.45 1 1 1h4c.55 0 1-.45 1-1v-1H9v1zm3-19C8.14 2 5 5.14 5 9c0 2.38 1.19 4.47 3 5.74V17c0 .55.45 1 1 1h6c.55 0 1-.45 1-1v-2.26c1.81-1.27 3-3.36 3-5.74 0-3.86-3.14-7-7-7zm2.85 11.1l-.85.6V16h-4v-2.3l-.85-.6C7.8 12.16 7 10.63 7 9c0-2.76 2.24-5 5-5s5 2.24 5 5c0 1.63-.8 3.16-2.15 4.1z"/></svg>
                    </div>
                    <h3>IA Intelligente</h3>
                    <p>Propulsé par les meilleurs modèles d'IA (Llama, Gemini), il comprend le contexte et répond de manière pertinente.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.31.06-.63.06-.94 0-.31-.02-.63-.06-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.04.31-.06.63-.06.94s.02.63.06.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                    </div>
                    <h3>Personnalisable</h3>
                    <p>Adaptez le comportement, le ton et l'apparence du chatbot à votre marque et votre secteur d'activité.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                    </div>
                    <h3>Installation Simple</h3>
                    <p>Une seule ligne de code à ajouter sur votre site. Compatible avec tous les CMS et hébergements.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24"><path d="M11.99 2C6.47 2 2 6.48 2 12s4.47 10 9.99 10C17.52 22 22 17.52 22 12S17.52 2 11.99 2zm6.93 6h-2.95c-.32-1.25-.78-2.45-1.38-3.56 1.84.63 3.37 1.91 4.33 3.56zM12 4.04c.83 1.2 1.48 2.53 1.91 3.96h-3.82c.43-1.43 1.08-2.76 1.91-3.96zM4.26 14C4.1 13.36 4 12.69 4 12s.1-1.36.26-2h3.38c-.08.66-.14 1.32-.14 2 0 .68.06 1.34.14 2H4.26zm.82 2h2.95c.32 1.25.78 2.45 1.38 3.56-1.84-.63-3.37-1.9-4.33-3.56zm2.95-8H5.08c.96-1.66 2.49-2.93 4.33-3.56C8.81 5.55 8.35 6.75 8.03 8zM12 19.96c-.83-1.2-1.48-2.53-1.91-3.96h3.82c-.43 1.43-1.08 2.76-1.91 3.96zM14.34 14H9.66c-.09-.66-.16-1.32-.16-2 0-.68.07-1.35.16-2h4.68c.09.65.16 1.32.16 2 0 .68-.07 1.34-.16 2zm.25 5.56c.6-1.11 1.06-2.31 1.38-3.56h2.95c-.96 1.65-2.49 2.93-4.33 3.56zM16.36 14c.08-.66.14-1.32.14-2 0-.68-.06-1.34-.14-2h3.38c.16.64.26 1.31.26 2s-.1 1.36-.26 2h-3.38z"/></svg>
                    </div>
                    <h3>Multilingue</h3>
                    <p>Support natif du français avec une excellente compréhension des expressions et du contexte culturel.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                    </div>
                    <h3>Statistiques</h3>
                    <p>Tableau de bord complet pour suivre les conversations, les questions fréquentes et les performances.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Booking Features Section -->
    <section class="booking-features" id="booking">
        <div class="container">
            <div class="section-header">
                <h2>Prise de rendez-vous automatisée</h2>
                <p>Votre chatbot gère les RDV 24h/24 et synchronise votre agenda en temps réel.</p>
            </div>
            <div class="booking-layout">
                <div class="booking-demo">
                    <div class="booking-chat">
                        <div class="booking-message bot">Bonjour ! Je peux vous aider à prendre rendez-vous. Quel service vous intéresse ?</div>
                        <div class="booking-message user">Je voudrais un devis pour une rénovation</div>
                        <div class="booking-message bot">Parfait ! Pouvez-vous me donner votre nom et un créneau qui vous arrange ?</div>
                        <div class="booking-message user">Bruno Martin, mardi 15h si possible</div>
                        <div class="booking-message bot">C'est noté ! Je confirme votre RDV mardi à 15h. Vous recevrez un email de confirmation.</div>
                    </div>
                </div>
                <div class="booking-benefits">
                    <div class="booking-benefit">
                        <div class="booking-benefit-icon">💬</div>
                        <div>
                            <h3>Conversation naturelle</h3>
                            <p>Le chatbot collecte les informations de manière fluide et confirme le récapitulatif avec le visiteur avant validation.</p>
                        </div>
                    </div>
                    <div class="booking-benefit">
                        <div class="booking-benefit-icon">📅</div>
                        <div>
                            <h3>Synchronisation Google Calendar</h3>
                            <p>Les rendez-vous sont ajoutés instantanément à votre agenda. Plus aucun risque de double-booking.</p>
                        </div>
                    </div>
                    <div class="booking-benefit">
                        <div class="booking-benefit-icon">✉️</div>
                        <div>
                            <h3>Double notification email</h3>
                            <p>Vous recevez une alerte à chaque nouveau RDV et le visiteur reçoit sa confirmation automatiquement.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Sectors Section -->
    <section class="sectors" id="sectors">
        <div class="container">
            <div class="section-header">
                <h2>Adapté à votre secteur</h2>
                <p>Des solutions spécialisées pour répondre aux besoins spécifiques de votre métier.</p>
            </div>
            <?php if (!empty($homepageChatbots)): ?>
            <div class="sectors-grid">
                <?php foreach ($homepageChatbots as $bot): ?>
                <div class="sector-card" style="--sector-color: <?= htmlspecialchars($bot['color']) ?>">
                    <div class="sector-icon" style="background: <?= htmlspecialchars($bot['color']) ?>20;"><?= htmlspecialchars($bot['icon']) ?></div>
                    <h3><?= htmlspecialchars($bot['name']) ?></h3>
                    <p><?= htmlspecialchars(mb_substr($bot['welcome_message'] ?? 'Découvrez notre assistant IA spécialisé.', 0, 120)) ?></p>
                    <a href="demo?sector=<?= htmlspecialchars($bot['slug']) ?>" class="btn btn-primary" style="background: <?= htmlspecialchars($bot['color']) ?>;">Voir la démo</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="sectors-grid">
                <div class="sector-card">
                    <div class="sector-icon" style="background: #fef3c7;">🏗️</div>
                    <h3>Bâtiment & Artisans</h3>
                    <p>Qualifiez vos demandes de devis, planifiez les visites techniques et répondez aux questions sur vos services.</p>
                    <a href="demo" class="btn btn-primary">Voir la démo</a>
                </div>
                <div class="sector-card">
                    <div class="sector-icon" style="background: #d1fae5;">🏠</div>
                    <h3>Agences Immobilières</h3>
                    <p>Accompagnez vos clients dans leur recherche, proposez des biens adaptés et planifiez les visites.</p>
                    <a href="demo" class="btn btn-primary">Voir la démo</a>
                </div>
                <div class="sector-card">
                    <div class="sector-icon" style="background: #ede9fe;">🛒</div>
                    <h3>E-Commerce</h3>
                    <p>Aidez vos clients à trouver le bon produit, gérez le SAV et boostez vos ventes avec des recommandations.</p>
                    <a href="demo" class="btn btn-primary">Voir la démo</a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Pricing Section -->
    <section class="pricing" id="pricing">
        <div class="container">
            <div class="section-header">
                <h2>Tarifs simples et transparents</h2>
                <p>Choisissez la formule adaptée à vos besoins. Sans engagement.</p>
            </div>
            <div class="pricing-cards">
                <div class="pricing-card">
                    <h3>Starter</h3>
                    <div class="price">Gratuit</div>
                    <p class="description">Pour découvrir et tester</p>
                    <ul class="pricing-features">
                        <li>1 000 messages/mois</li>
                        <li>1 site web</li>
                        <li>Widget personnalisable</li>
                        <li>Support email</li>
                    </ul>
                    <a href="#" class="btn btn-secondary">Commencer</a>
                </div>
                <div class="pricing-card popular">
                    <h3>Pro</h3>
                    <div class="price">49€ <span>/mois</span></div>
                    <p class="description">Pour les PME</p>
                    <ul class="pricing-features">
                        <li>10 000 messages/mois</li>
                        <li>3 sites web</li>
                        <li>Personnalisation avancée</li>
                        <li>Statistiques détaillées</li>
                        <li>Support prioritaire</li>
                    </ul>
                    <a href="#" class="btn btn-primary">Choisir Pro</a>
                </div>
                <div class="pricing-card">
                    <h3>Enterprise</h3>
                    <div class="price">Sur mesure</div>
                    <p class="description">Pour les grandes entreprises</p>
                    <ul class="pricing-features">
                        <li>Messages illimités</li>
                        <li>Sites illimités</li>
                        <li>API dédiée</li>
                        <li>Formation incluse</li>
                        <li>Account manager dédié</li>
                    </ul>
                    <a href="#contact" class="btn btn-secondary">Nous contacter</a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta" id="contact">
        <div class="container">
            <h2>Prêt à booster vos conversions ?</h2>
            <p>Rejoignez les entreprises qui font confiance à notre solution pour automatiser leur relation client.</p>
            <a href="demo.php" class="btn btn-white">Essayer gratuitement</a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-brand">
                    <div class="logo" style="color: white; -webkit-text-fill-color: white;">ChatBot IA</div>
                    <p>L'assistant intelligent qui transforme vos visiteurs en clients.</p>
                </div>
                <div>
                    <h4>Produit</h4>
                    <ul>
                        <li><a href="#features">Fonctionnalités</a></li>
                        <li><a href="#booking">Prise de RDV</a></li>
                        <li><a href="#pricing">Tarifs</a></li>
                        <li><a href="demo.php">Démo</a></li>
                    </ul>
                </div>
                <div>
                    <h4>Secteurs</h4>
                    <ul>
                        <?php if (!empty($homepageChatbots)): ?>
                            <?php foreach ($homepageChatbots as $bot): ?>
                            <li><a href="demo?sector=<?= htmlspecialchars($bot['slug']) ?>"><?= htmlspecialchars($bot['name']) ?></a></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li><a href="demo">Voir la démo</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div>
                    <h4>Légal</h4>
                    <ul>
                        <li><a href="mentions-legales.php">Mentions légales</a></li>
                        <li><a href="cgu.php">CGU</a></li>
                        <li><a href="cgv.php">CGV</a></li>
                    </ul>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> ChatBot IA - Myziggi SASU. Tous droits réservés.</p>
            </div>
        </div>
    </footer>

    <!-- Widget Chatbot -->
    <script src="assets/js/widget.js"></script>
    <script>
        // Initialisation du chatbot démo
        const chatbot = new ChatbotWidget({
            apiUrl: 'api/chat.php',
            botName: 'Assistant IA',
            welcomeMessage: 'Bonjour ! Je suis votre assistant virtuel. Posez-moi vos questions sur notre solution de chatbot IA, je suis là pour vous aider !'
        });
    </script>
</body>
</html>
