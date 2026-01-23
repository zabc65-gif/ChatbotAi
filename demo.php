<?php
/**
 * Page de démo des chatbots
 * Charge les chatbots dynamiquement depuis la base de données
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/Database.php';
require_once __DIR__ . '/classes/Settings.php';

$db = new Database();
$settings = new Settings($db);

// Charger les chatbots actifs depuis la BDD
$chatbots = $db->fetchAll(
    "SELECT * FROM demo_chatbots WHERE active = 1 ORDER BY sort_order, id"
);

// Préparer les données pour JavaScript
$sectorsJS = [];
foreach ($chatbots as $bot) {
    $sectorsJS[$bot['slug']] = [
        'name' => $bot['name'],
        'icon' => $bot['icon'],
        'color' => $bot['color'],
        'welcome' => $bot['welcome_message'],
        'tips' => [
            ['title' => 'Question type', 'text' => '"Comment puis-je vous aider ?"']
        ],
        'quickActions' => ['Demander un devis', 'En savoir plus', 'Contact']
    ];
}

// Récupérer la limite quotidienne
$dailyLimit = (int)($settings->get('demo_daily_limit') ?: 10);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Testez notre chatbot IA en conditions réelles. Découvrez comment il peut transformer votre relation client.">
    <title>Démo ChatBot IA - Testez en temps réel</title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="favicon.svg">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Widget CSS -->
    <link rel="stylesheet" href="assets/css/widget.css">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary: #6366f1;
            --primary-dark: #4f46e5;
            --btp: #f59e0b;
            --immo: #10b981;
            --ecommerce: #8b5cf6;
            --text: #1e293b;
            --text-light: #64748b;
            --bg: #f8fafc;
            --white: #ffffff;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text);
            line-height: 1.6;
            background: var(--bg);
            min-height: 100vh;
        }

        /* Header */
        .demo-header {
            background: var(--white);
            padding: 16px 24px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .demo-header .logo {
            font-size: 24px;
            font-weight: 800;
            background: linear-gradient(135deg, var(--primary), #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
        }

        .back-link {
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .back-link:hover {
            color: var(--text);
        }

        /* Main Layout */
        .demo-container {
            display: flex;
            min-height: calc(100vh - 65px);
        }

        /* Sidebar */
        .demo-sidebar {
            width: 320px;
            background: var(--white);
            padding: 32px;
            border-right: 1px solid #e2e8f0;
            flex-shrink: 0;
        }

        .sidebar-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }

        .sector-selector {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .sector-btn {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: var(--white);
            cursor: pointer;
            transition: all 0.2s;
            text-align: left;
        }

        .sector-btn:hover {
            border-color: var(--primary);
            background: #f8fafc;
        }

        .sector-btn.active {
            border-color: var(--sector-color, var(--primary));
            background: color-mix(in srgb, var(--sector-color, var(--primary)) 10%, white);
        }

        .sector-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .sector-btn .sector-icon { transition: all 0.2s; }

        .sector-btn.active {
            border-color: var(--sector-color, var(--primary));
            background: color-mix(in srgb, var(--sector-color, var(--primary)) 10%, white);
        }

        .sector-info h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .sector-info p {
            font-size: 13px;
            color: var(--text-light);
        }

        /* Tips */
        .tips-section {
            margin-top: 32px;
            padding-top: 32px;
            border-top: 1px solid #e2e8f0;
        }

        .tips-title {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }

        .tip-card {
            background: #f8fafc;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .tip-card h4 {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tip-card p {
            font-size: 13px;
            color: var(--text-light);
        }

        /* Chat Area */
        .demo-chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding: 32px;
            max-width: 800px;
            margin: 0 auto;
        }

        .chat-intro {
            text-align: center;
            margin-bottom: 32px;
        }

        .chat-intro h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
        }

        .chat-intro p {
            color: var(--text-light);
        }

        /* Embedded Chat */
        .embedded-chat {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 500px;
        }

        .chat-header {
            padding: 20px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
            border-bottom: 1px solid #e2e8f0;
        }

        .chat-header {
            position: relative;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
        }

        .chat-header-avatar {
            width: 48px;
            height: 48px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .chat-header-info {
            color: white;
        }

        .chat-header-name {
            font-size: 18px;
            font-weight: 600;
        }

        .chat-header-status {
            font-size: 14px;
            opacity: 0.9;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .chat-header-status::before {
            content: '';
            width: 8px;
            height: 8px;
            background: #22c55e;
            border-radius: 50%;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 24px;
            display: flex;
            flex-direction: column;
            gap: 16px;
            background: #fafafa;
        }

        .chat-message {
            max-width: 80%;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .chat-message.user {
            align-self: flex-end;
        }

        .chat-message.bot {
            align-self: flex-start;
        }

        .chat-message-content {
            padding: 14px 18px;
            border-radius: 16px;
            line-height: 1.5;
            font-size: 15px;
        }

        .chat-message.user .chat-message-content {
            background: var(--primary);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .chat-message.bot .chat-message-content {
            background: white;
            color: var(--text);
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .chat-typing {
            display: flex;
            gap: 4px;
            padding: 14px 18px;
            background: white;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            align-self: flex-start;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .chat-typing span {
            width: 8px;
            height: 8px;
            background: var(--text-light);
            border-radius: 50%;
            animation: typing 1.4s infinite ease-in-out;
        }

        .chat-typing span:nth-child(2) { animation-delay: 0.2s; }
        .chat-typing span:nth-child(3) { animation-delay: 0.4s; }

        @keyframes typing {
            0%, 100% { transform: translateY(0); opacity: 0.4; }
            50% { transform: translateY(-4px); opacity: 1; }
        }

        .chat-input-area {
            padding: 20px 24px;
            background: white;
            border-top: 1px solid #e2e8f0;
        }

        .chat-input-wrapper {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .chat-input {
            flex: 1;
            border: 2px solid #e2e8f0;
            border-radius: 24px;
            padding: 14px 20px;
            font-size: 15px;
            resize: none;
            max-height: 120px;
            line-height: 1.5;
            transition: border-color 0.2s;
            font-family: inherit;
        }

        .chat-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .chat-send {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: var(--primary);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .chat-send:hover {
            transform: scale(1.05);
        }

        .chat-send:disabled {
            background: #e2e8f0;
            cursor: not-allowed;
            transform: none;
        }

        .chat-send svg {
            width: 22px;
            height: 22px;
            fill: white;
        }

        /* Quick Actions */
        .quick-actions {
            padding: 16px 24px;
            background: white;
            border-top: 1px solid #e2e8f0;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .quick-action {
            padding: 8px 16px;
            background: #f1f5f9;
            border: none;
            border-radius: 100px;
            font-size: 13px;
            color: var(--text);
            cursor: pointer;
            transition: all 0.2s;
            font-family: inherit;
        }

        .quick-action:hover {
            background: #e2e8f0;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .demo-sidebar {
                display: none;
            }

            .demo-chat-area {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="demo-header">
        <a href="index.php" class="logo">ChatBot IA</a>
        <a href="index.php" class="back-link">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                <path d="M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z"/>
            </svg>
            Retour au site
        </a>
    </header>

    <div class="demo-container">
        <!-- Sidebar -->
        <aside class="demo-sidebar">
            <h2 class="sidebar-title">Choisir un secteur</h2>
            <div class="sector-selector">
                <?php foreach ($chatbots as $bot): ?>
                <button class="sector-btn <?= htmlspecialchars($bot['slug']) ?>" data-sector="<?= htmlspecialchars($bot['slug']) ?>" style="--sector-color: <?= htmlspecialchars($bot['color']) ?>">
                    <div class="sector-icon" style="background: <?= htmlspecialchars($bot['color']) ?>20;"><?= $bot['icon'] ?></div>
                    <div class="sector-info">
                        <h3><?= htmlspecialchars($bot['name']) ?></h3>
                        <p>Cliquez pour tester</p>
                    </div>
                </button>
                <?php endforeach; ?>
                <?php if (empty($chatbots)): ?>
                <p style="color: var(--text-light); padding: 16px;">Aucun chatbot de démo disponible pour le moment.</p>
                <?php endif; ?>
            </div>

            <div class="tips-section">
                <h2 class="tips-title">Essayez de demander</h2>
                <div id="tips-container">
                    <!-- Les tips seront injectés dynamiquement -->
                </div>
            </div>
        </aside>

        <!-- Chat Area -->
        <main class="demo-chat-area">
            <div class="chat-intro">
                <h1>Testez le chatbot en temps réel</h1>
                <p>Sélectionnez un secteur et posez vos questions comme un vrai visiteur</p>
            </div>

            <div class="embedded-chat">
                <div class="chat-header" id="chat-header">
                    <div class="chat-header-avatar" id="chat-avatar">🤖</div>
                    <div class="chat-header-info">
                        <div class="chat-header-name" id="chat-name">Assistant IA</div>
                        <div class="chat-header-status">En ligne</div>
                    </div>
                </div>

                <div class="chat-messages" id="chat-messages">
                    <!-- Messages dynamiques -->
                </div>

                <div class="quick-actions" id="quick-actions">
                    <!-- Actions rapides dynamiques -->
                </div>

                <div class="chat-input-area">
                    <div class="chat-input-wrapper">
                        <textarea
                            class="chat-input"
                            placeholder="Écrivez votre message..."
                            rows="1"
                            id="message-input"
                        ></textarea>
                        <button class="chat-send" id="send-btn">
                            <svg viewBox="0 0 24 24">
                                <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Configuration des secteurs (chargée depuis la BDD)
        const sectors = <?= json_encode($sectorsJS, JSON_UNESCAPED_UNICODE) ?>;
        const dailyLimit = <?= $dailyLimit ?>;

        // Fingerprint pour identification utilisateur
        function generateFingerprint() {
            let fp = localStorage.getItem('chatbot_fp');
            if (fp) return fp;
            const components = [
                navigator.userAgent,
                navigator.language,
                screen.width + 'x' + screen.height,
                new Date().getTimezoneOffset(),
                navigator.hardwareConcurrency || 0,
                Math.random().toString(36).substr(2, 9)
            ];
            fp = btoa(components.join('|')).substr(0, 32);
            localStorage.setItem('chatbot_fp', fp);
            return fp;
        }
        const fingerprint = generateFingerprint();

        // État de l'application
        let currentSector = null;
        let sessionId = null;
        let isTyping = false;
        let remainingMessages = null;

        // Éléments DOM
        const chatMessages = document.getElementById('chat-messages');
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        const chatHeader = document.getElementById('chat-header');
        const chatAvatar = document.getElementById('chat-avatar');
        const chatName = document.getElementById('chat-name');
        const quickActions = document.getElementById('quick-actions');
        const tipsContainer = document.getElementById('tips-container');
        const sectorBtns = document.querySelectorAll('.sector-btn');

        // Initialisation
        function init() {
            // Vérifier si des secteurs existent
            const sectorKeys = Object.keys(sectors);

            // Vérifier si un secteur est passé en paramètre URL
            const urlParams = new URLSearchParams(window.location.search);
            const sectorParam = urlParams.get('sector');

            if (sectorParam && sectors[sectorParam]) {
                selectSector(sectorParam);
            } else if (sectorKeys.length > 0) {
                // Sélectionner automatiquement le premier secteur
                selectSector(sectorKeys[0]);
            } else {
                // Afficher un message d'accueil par défaut
                addMessage("Bonjour ! Aucun chatbot de démo n'est configuré pour le moment.", 'bot', false);
            }

            // Event listeners
            sectorBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    selectSector(btn.dataset.sector);
                });
            });

            sendBtn.addEventListener('click', sendMessage);

            messageInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // Auto-resize textarea
            messageInput.addEventListener('input', () => {
                messageInput.style.height = 'auto';
                messageInput.style.height = Math.min(messageInput.scrollHeight, 120) + 'px';
            });
        }

        // Sélectionner un secteur
        async function selectSector(sectorKey) {
            if (currentSector === sectorKey) return;
            if (!sectors[sectorKey]) return;

            currentSector = sectorKey;
            const sector = sectors[sectorKey];

            // Mettre à jour l'UI
            sectorBtns.forEach(btn => {
                btn.classList.toggle('active', btn.dataset.sector === sectorKey);
            });

            // Mettre à jour le header avec couleur dynamique
            chatHeader.className = 'chat-header';
            chatHeader.style.background = `linear-gradient(135deg, ${sector.color}, color-mix(in srgb, ${sector.color} 80%, black))`;
            chatAvatar.textContent = sector.icon;
            chatName.textContent = sector.name;

            // Mettre à jour les tips
            updateTips(sector.tips);

            // Mettre à jour les actions rapides
            updateQuickActions(sector.quickActions);

            // Réinitialiser le chat
            chatMessages.innerHTML = '';
            sessionId = null;

            // Initialiser la session avec le contexte
            try {
                const response = await apiCall('init', { context: sectorKey, fingerprint: fingerprint });
                if (response.success) {
                    sessionId = response.session_id;

                    // Gérer les messages restants / admin
                    if (response.is_admin) {
                        isAdminUser = true;
                        remainingMessages = null;
                    } else if (response.remaining !== undefined) {
                        isAdminUser = false;
                        remainingMessages = response.remaining;
                    }
                    updateRemainingDisplay();

                    addMessage(response.welcome_message || sector.welcome, 'bot', false);
                }
            } catch (error) {
                console.error('Erreur init:', error);
                addMessage(sector.welcome, 'bot', false);
            }
        }

        // Mettre à jour l'affichage des messages restants
        let isAdminUser = false;

        function updateRemainingDisplay() {
            let remainingEl = document.getElementById('remaining-display');
            if (!remainingEl) {
                remainingEl = document.createElement('div');
                remainingEl.id = 'remaining-display';
                remainingEl.style.cssText = 'position: absolute; top: 16px; right: 16px; background: rgba(255,255,255,0.9); padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;';
                document.querySelector('.chat-header').appendChild(remainingEl);
            }

            if (isAdminUser) {
                remainingEl.textContent = '👑 Admin (illimité)';
                remainingEl.style.color = '#8b5cf6';
            } else if (remainingMessages !== null) {
                if (remainingMessages <= 0) {
                    remainingEl.textContent = '⚠️ Limite atteinte';
                    remainingEl.style.color = '#ef4444';
                } else if (remainingMessages <= 3) {
                    remainingEl.textContent = `${remainingMessages} msg restant${remainingMessages > 1 ? 's' : ''}`;
                    remainingEl.style.color = '#f59e0b';
                } else {
                    remainingEl.textContent = `${remainingMessages}/${dailyLimit} messages`;
                    remainingEl.style.color = '#10b981';
                }
            }
        }

        // Mettre à jour les tips
        function updateTips(tips) {
            tipsContainer.innerHTML = tips.map(tip => `
                <div class="tip-card">
                    <h4>💡 ${tip.title}</h4>
                    <p>${tip.text}</p>
                </div>
            `).join('');
        }

        // Mettre à jour les actions rapides
        function updateQuickActions(actions) {
            quickActions.innerHTML = actions.map(action => `
                <button class="quick-action" onclick="sendQuickAction('${action}')">${action}</button>
            `).join('');
        }

        // Envoyer une action rapide
        function sendQuickAction(text) {
            messageInput.value = text;
            sendMessage();
        }

        // Envoyer un message
        async function sendMessage() {
            const message = messageInput.value.trim();
            if (!message || isTyping || !currentSector) return;

            // Vérifier la limite côté client (sauf admins)
            if (!isAdminUser && remainingMessages !== null && remainingMessages <= 0) {
                addMessage("⚠️ Vous avez atteint la limite de " + dailyLimit + " messages par jour pour cette démo.\n\nPour continuer sans limite, contactez-nous pour obtenir votre propre assistant personnalisé !\n\n📧 bruno@myziggi.fr\n📱 06 72 38 64 24", 'bot');
                return;
            }

            // Afficher le message utilisateur
            addMessage(message, 'user');
            messageInput.value = '';
            messageInput.style.height = 'auto';

            // Afficher l'indicateur de frappe
            showTyping();

            try {
                const response = await apiCall('message', {
                    session_id: sessionId,
                    message: message,
                    context: currentSector,
                    fingerprint: fingerprint
                });

                hideTyping();

                if (response.success) {
                    const botMessage = response.message || response.response;
                    addMessage(botMessage, 'bot');

                    // Mettre à jour les messages restants (sauf admins)
                    if (response.is_admin) {
                        isAdminUser = true;
                    } else if (response.remaining !== undefined) {
                        remainingMessages = response.remaining;
                        updateRemainingDisplay();
                    }

                    if (response.session_id) {
                        sessionId = response.session_id;
                    }

                    // Si limite atteinte, désactiver l'input (sauf admins)
                    if (response.limited && !isAdminUser) {
                        disableInput();
                    }
                } else {
                    addMessage(response.error || 'Désolé, une erreur est survenue.', 'bot');
                }
            } catch (error) {
                hideTyping();
                console.error('Erreur:', error);
                addMessage('Désolé, impossible de contacter le serveur.', 'bot');
            }
        }

        // Désactiver l'input quand limite atteinte
        function disableInput() {
            messageInput.disabled = true;
            messageInput.placeholder = 'Limite atteinte pour aujourd\'hui';
            sendBtn.disabled = true;
        }

        // Ajouter un message
        function addMessage(content, type, animate = true) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'chat-message ' + type;

            if (!animate) {
                messageDiv.style.animation = 'none';
            }

            messageDiv.innerHTML = `
                <div class="chat-message-content">
                    ${formatMessage(content)}
                </div>
            `;

            chatMessages.appendChild(messageDiv);
            scrollToBottom();
        }

        // Formater le message
        function formatMessage(content) {
            let formatted = escapeHtml(content);
            formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
            formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
            formatted = formatted.replace(/\n/g, '<br>');
            return formatted;
        }

        // Échapper HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Afficher l'indicateur de frappe
        function showTyping() {
            isTyping = true;
            sendBtn.disabled = true;

            const typingDiv = document.createElement('div');
            typingDiv.className = 'chat-typing';
            typingDiv.id = 'typing-indicator';
            typingDiv.innerHTML = '<span></span><span></span><span></span>';

            chatMessages.appendChild(typingDiv);
            scrollToBottom();
        }

        // Cacher l'indicateur de frappe
        function hideTyping() {
            isTyping = false;
            sendBtn.disabled = false;

            const typingDiv = document.getElementById('typing-indicator');
            if (typingDiv) {
                typingDiv.remove();
            }
        }

        // Scroll vers le bas
        function scrollToBottom() {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Appel API
        async function apiCall(action, data = {}) {
            const response = await fetch('api/chat.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: action,
                    ...data
                })
            });

            return response.json();
        }

        // Démarrage
        init();
    </script>
</body>
</html>
