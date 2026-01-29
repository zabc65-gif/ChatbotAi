/**
 * ChatBot IA - Widget d'intégration Client
 *
 * Méthodes d'intégration:
 *
 * 1. Standard (recommandé):
 *    <script src="https://chatbot.myziggi.pro/widget.js" data-key="VOTRE_CLE_API"></script>
 *
 * 2. Pour WordPress avec cache/minification:
 *    <script>window.ChatbotConfig = { apiKey: 'VOTRE_CLE_API' };</script>
 *    <script src="https://chatbot.myziggi.pro/widget.js"></script>
 *
 * 3. Alternative avec div:
 *    <div id="chatbot-config" data-key="VOTRE_CLE_API"></div>
 *    <script src="https://chatbot.myziggi.pro/widget.js"></script>
 */
(function() {
    'use strict';

    // Récupérer la clé API depuis plusieurs sources possibles
    function getApiKey() {
        // 1. Variable globale (meilleure option pour WordPress avec cache)
        if (window.ChatbotConfig && window.ChatbotConfig.apiKey) {
            return window.ChatbotConfig.apiKey;
        }

        // 2. Div de configuration
        const configDiv = document.getElementById('chatbot-config');
        if (configDiv && configDiv.getAttribute('data-key')) {
            return configDiv.getAttribute('data-key');
        }

        // 3. Attribut data-key sur le script courant
        const currentScript = document.currentScript;
        if (currentScript && currentScript.getAttribute('data-key')) {
            return currentScript.getAttribute('data-key');
        }

        // 4. Chercher dans tous les scripts (fallback)
        const scripts = document.getElementsByTagName('script');
        for (let i = 0; i < scripts.length; i++) {
            const key = scripts[i].getAttribute('data-key');
            if (key) return key;
        }

        // 5. Chercher un script avec l'URL du widget
        for (let i = 0; i < scripts.length; i++) {
            const src = scripts[i].src || '';
            if (src.includes('chatbot.myziggi.pro/widget.js') || src.includes('widget.js')) {
                const key = scripts[i].getAttribute('data-key');
                if (key) return key;
            }
        }

        return null;
    }

    const apiKey = getApiKey();

    if (!apiKey) {
        console.error('ChatBot IA: Clé API manquante. Utilisez une des méthodes suivantes:');
        console.error('1. <script>window.ChatbotConfig = { apiKey: "VOTRE_CLE" };</script> avant le widget');
        console.error('2. <div id="chatbot-config" data-key="VOTRE_CLE"></div> avant le widget');
        console.error('3. Ajoutez data-key="VOTRE_CLE" au script widget');
        return;
    }

    // Configuration
    const API_URL = 'https://chatbot.myziggi.pro/api/chat.php';

    // État du widget
    let isOpen = false;
    let isTyping = false;
    let sessionId = null;
    let config = null;
    let fingerprint = null;
    let teaserDismissed = false;
    let attentionShown = false;

    // Générer un fingerprint pour identifier l'utilisateur
    function generateFingerprint() {
        let fp = localStorage.getItem('chatbot_client_fp');
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
        localStorage.setItem('chatbot_client_fp', fp);
        return fp;
    }

    // Générer un ID de session unique
    function generateSessionId() {
        return 'client_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    // Charger la configuration du chatbot
    async function loadConfig() {
        try {
            const response = await fetch(API_URL + '?action=config&key=' + encodeURIComponent(apiKey));
            const data = await response.json();
            if (data.success) {
                config = data.config;
                return true;
            } else {
                console.error('ChatBot IA: ' + (data.error || 'Erreur de configuration'));
                return false;
            }
        } catch (error) {
            console.error('ChatBot IA: Erreur de connexion', error);
            return false;
        }
    }

    // Charger l'historique de conversation
    async function loadHistory() {
        const savedSession = localStorage.getItem('chatbot_client_session_' + apiKey);
        if (!savedSession) return false;

        sessionId = savedSession;

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'history',
                    session_id: sessionId,
                    api_key: apiKey
                })
            });

            const data = await response.json();
            if (data.success && data.history && data.history.length > 0) {
                data.history.forEach(msg => {
                    addMessage(msg.content, msg.role === 'user' ? 'user' : 'bot', false);
                });
                return true;
            }
        } catch (error) {
            console.error('ChatBot IA: Erreur chargement historique', error);
        }
        return false;
    }

    // Envoyer un message
    async function sendMessage(message) {
        if (isTyping || !message.trim()) return;

        isTyping = true;
        const sendBtn = document.getElementById('chatbot-send');
        if (sendBtn) sendBtn.disabled = true;

        // Masquer les quick actions après le premier message
        const quickActionsEl = document.getElementById('chatbot-quick-actions');
        if (quickActionsEl) quickActionsEl.style.display = 'none';

        // Ajouter le message utilisateur
        addMessage(message, 'user');
        updateInput('');

        // Afficher l'indicateur de frappe
        showTypingIndicator();

        try {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'message',
                    message: message,
                    session_id: sessionId,
                    api_key: apiKey,
                    fingerprint: fingerprint
                })
            });

            const data = await response.json();
            hideTypingIndicator();

            if (data.success) {
                const botMessage = data.message || data.response;
                addMessage(botMessage, 'bot');

                // Afficher la confirmation de RDV si booking détecté
                if (data.booking && data.booking.success) {
                    showBookingConfirmation(data.booking);
                }

                // Mettre à jour le session_id si nécessaire
                if (data.session_id && data.session_id !== sessionId) {
                    sessionId = data.session_id;
                    localStorage.setItem('chatbot_client_session_' + apiKey, sessionId);
                }
            } else {
                addMessage(data.error || 'Une erreur est survenue.', 'bot', false, true);
            }
        } catch (error) {
            hideTypingIndicator();
            addMessage('Erreur de connexion. Veuillez réessayer.', 'bot', false, true);
        }

        isTyping = false;
        if (sendBtn) sendBtn.disabled = false;
    }

    // Ajouter un message à l'interface
    function addMessage(content, sender, animate = true, isError = false) {
        const messagesContainer = document.getElementById('chatbot-messages');
        if (!messagesContainer) return;

        const messageDiv = document.createElement('div');
        messageDiv.className = 'chatbot-message chatbot-message-' + sender;
        if (isError) messageDiv.classList.add('chatbot-message-error');
        if (!animate) messageDiv.style.animation = 'none';

        const bubble = document.createElement('div');
        bubble.className = 'chatbot-bubble';
        bubble.innerHTML = formatMessage(content);

        messageDiv.appendChild(bubble);
        messagesContainer.appendChild(messageDiv);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Formater le message (markdown basique)
    function formatMessage(content) {
        let formatted = escapeHtml(content);

        // Gras **texte**
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Italique *texte*
        formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Sauts de ligne
        formatted = formatted.replace(/\n/g, '<br>');

        return formatted;
    }

    // Afficher l'indicateur de frappe
    function showTypingIndicator() {
        const messagesContainer = document.getElementById('chatbot-messages');
        if (!messagesContainer) return;

        const indicator = document.createElement('div');
        indicator.id = 'chatbot-typing';
        indicator.className = 'chatbot-message chatbot-message-bot';
        indicator.innerHTML = '<div class="chatbot-bubble chatbot-typing-bubble"><span></span><span></span><span></span></div>';
        messagesContainer.appendChild(indicator);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Masquer l'indicateur de frappe
    function hideTypingIndicator() {
        const indicator = document.getElementById('chatbot-typing');
        if (indicator) indicator.remove();
    }

    // Afficher une confirmation de rendez-vous
    function showBookingConfirmation(booking) {
        const messagesContainer = document.getElementById('chatbot-messages');
        if (!messagesContainer) return;

        const card = document.createElement('div');
        card.className = 'chatbot-booking-card';

        const dateStr = booking.date || '';
        const timeStr = booking.time || '';
        const servicStr = booking.service ? '<br>Service : ' + escapeHtml(booking.service) : '';

        card.innerHTML = '<div class="chatbot-booking-card-title">&#x2705; Rendez-vous confirmé</div>' +
            '<div class="chatbot-booking-card-details">' +
            escapeHtml(booking.name || 'Client') +
            '<br>Date : ' + escapeHtml(dateStr) +
            '<br>Heure : ' + escapeHtml(timeStr) +
            servicStr +
            '</div>';

        messagesContainer.appendChild(card);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    // Mettre à jour l'input
    function updateInput(value) {
        const input = document.getElementById('chatbot-input');
        if (input) {
            input.value = value;
            input.style.height = 'auto';
        }
    }

    // Ouvrir/Fermer le widget
    function isMobile() {
        return window.innerWidth <= 480;
    }

    function toggleWidget() {
        isOpen = !isOpen;
        const widget = document.getElementById('chatbot-widget');
        const button = document.getElementById('chatbot-button');
        const container = document.getElementById('chatbot-container');

        if (widget) widget.style.display = isOpen ? 'flex' : 'none';
        if (button) button.classList.toggle('chatbot-button-open', isOpen);

        // Mobile fullscreen : ajouter/retirer la classe et bloquer le scroll
        if (container) container.classList.toggle('chatbot-mobile-open', isOpen);
        if (isMobile()) {
            document.body.style.overflow = isOpen ? 'hidden' : '';
        }

        // Message de bienvenue à la première ouverture
        if (isOpen) {
            const messagesContainer = document.getElementById('chatbot-messages');
            if (messagesContainer && messagesContainer.children.length === 0 && config && config.welcome_message) {
                addMessage(config.welcome_message, 'bot');
            }
            // Focus sur l'input
            const input = document.getElementById('chatbot-input');
            if (input) input.focus();
        }
    }

    // Créer les styles CSS
    function createStyles() {
        const primaryColor = config?.primary_color || '#6366f1';
        const textColor = config?.text_color || '#1e293b';

        const styles = document.createElement('style');
        styles.textContent = `
            #chatbot-container,
            #chatbot-container *,
            #chatbot-container *::before,
            #chatbot-container *::after {
                box-sizing: border-box !important;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
                letter-spacing: normal !important;
                text-transform: none !important;
                text-decoration: none !important;
            }
            #chatbot-container {
                position: fixed !important;
                bottom: 20px !important;
                right: 20px !important;
                z-index: 999999 !important;
                background: transparent !important;
            }
            #chatbot-button {
                width: 60px !important;
                height: 60px !important;
                border-radius: 50% !important;
                background: ${primaryColor} !important;
                border: none !important;
                cursor: pointer !important;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2) !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                transition: transform 0.3s, box-shadow 0.3s !important;
                position: relative !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            #chatbot-button:hover {
                transform: scale(1.05) !important;
                box-shadow: 0 6px 25px rgba(0,0,0,0.25) !important;
            }
            #chatbot-button.chatbot-pulse::before {
                content: '' !important;
                position: absolute !important;
                width: 100% !important;
                height: 100% !important;
                border-radius: 50% !important;
                background: ${primaryColor} !important;
                animation: chatbot-pulse-ring 2s ease-out infinite !important;
                z-index: -1 !important;
            }
            @keyframes chatbot-pulse-ring {
                0% { transform: scale(1); opacity: 0.6; }
                100% { transform: scale(1.5); opacity: 0; }
            }
            #chatbot-badge {
                position: absolute !important;
                top: -2px !important;
                right: -2px !important;
                width: 16px !important;
                height: 16px !important;
                background: #ef4444 !important;
                border-radius: 50% !important;
                border: 2px solid white !important;
                animation: chatbot-badge-bounce 0.5s ease !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            @keyframes chatbot-badge-bounce {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.2); }
            }
            #chatbot-teaser {
                position: absolute !important;
                bottom: 70px !important;
                right: 0 !important;
                background: white !important;
                padding: 14px 18px !important;
                border-radius: 12px !important;
                box-shadow: 0 4px 20px rgba(0,0,0,0.15) !important;
                max-width: 340px !important;
                min-width: 280px !important;
                animation: chatbot-teaser-in 0.4s ease !important;
                cursor: pointer !important;
                margin: 0 !important;
            }
            #chatbot-teaser::after {
                content: '' !important;
                position: absolute !important;
                bottom: -8px !important;
                right: 24px !important;
                width: 0 !important;
                height: 0 !important;
                border-left: 8px solid transparent !important;
                border-right: 8px solid transparent !important;
                border-top: 8px solid white !important;
            }
            @keyframes chatbot-teaser-in {
                from { opacity: 0; transform: translateY(10px) scale(0.95); }
                to { opacity: 1; transform: translateY(0) scale(1); }
            }
            #chatbot-teaser-close {
                position: absolute !important;
                top: 6px !important;
                right: 6px !important;
                width: 20px !important;
                height: 20px !important;
                background: #f1f5f9 !important;
                border: none !important;
                border-radius: 50% !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                font-size: 12px !important;
                color: #64748b !important;
                transition: background 0.2s !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            #chatbot-teaser-close:hover {
                background: #e2e8f0 !important;
            }
            #chatbot-teaser-text {
                font-size: 14px !important;
                color: ${textColor} !important;
                line-height: 1.5 !important;
                padding-right: 16px !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
                padding-left: 0 !important;
                margin: 0 !important;
            }
            #chatbot-teaser-cta {
                margin-top: 8px !important;
                margin-bottom: 0 !important;
                margin-left: 0 !important;
                margin-right: 0 !important;
                font-size: 13px !important;
                color: ${primaryColor} !important;
                font-weight: 500 !important;
                padding: 0 !important;
            }
            #chatbot-button svg {
                width: 28px !important;
                height: 28px !important;
                fill: white !important;
                transition: transform 0.3s !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            #chatbot-button.chatbot-button-open svg {
                transform: rotate(90deg) !important;
            }
            #chatbot-widget {
                display: none;
                flex-direction: column !important;
                position: absolute !important;
                bottom: 70px !important;
                right: 0 !important;
                width: 380px !important;
                max-width: calc(100vw - 40px) !important;
                height: 550px !important;
                max-height: calc(100vh - 120px) !important;
                background: white !important;
                border-radius: 16px !important;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15) !important;
                overflow: hidden !important;
                animation: chatbot-slideIn 0.3s ease !important;
            }
            @keyframes chatbot-slideIn {
                from { opacity: 0; transform: translateY(20px); }
                to { opacity: 1; transform: translateY(0); }
            }
            #chatbot-header {
                background: ${primaryColor} !important;
                color: white !important;
                padding: 16px 20px !important;
                display: flex !important;
                align-items: center !important;
                gap: 12px !important;
            }
            #chatbot-header-icon {
                width: 44px !important;
                height: 44px !important;
                min-width: 44px !important;
                background: rgba(255,255,255,0.2) !important;
                border-radius: 50% !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            #chatbot-header-icon svg {
                width: 26px !important;
                height: 26px !important;
                fill: white !important;
            }
            #chatbot-header-info {
                flex: 1 !important;
            }
            #chatbot-header-title {
                font-weight: 600 !important;
                font-size: 16px !important;
                line-height: 1.4 !important;
                color: white !important;
            }
            #chatbot-header-subtitle {
                font-size: 12px !important;
                opacity: 0.85 !important;
                display: flex !important;
                align-items: center !important;
                gap: 6px !important;
                line-height: 1.4 !important;
                color: white !important;
            }
            #chatbot-header-subtitle::before {
                content: '' !important;
                width: 8px !important;
                height: 8px !important;
                background: #4ade80 !important;
                border-radius: 50% !important;
                display: inline-block !important;
            }
            #chatbot-close {
                background: transparent !important;
                border: none !important;
                color: white !important;
                cursor: pointer !important;
                padding: 8px !important;
                opacity: 0.8 !important;
                transition: opacity 0.2s !important;
            }
            #chatbot-close:hover {
                opacity: 1 !important;
            }
            #chatbot-messages {
                flex: 1 !important;
                padding: 16px !important;
                overflow-y: auto !important;
                background: #f8fafc !important;
            }
            .chatbot-message {
                display: flex !important;
                margin: 0 0 12px 0 !important;
                padding: 0 !important;
                animation: chatbot-fadeIn 0.3s ease !important;
            }
            @keyframes chatbot-fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }
            .chatbot-message-user {
                justify-content: flex-end !important;
            }
            .chatbot-message-bot {
                justify-content: flex-start !important;
            }
            .chatbot-bubble {
                max-width: 85% !important;
                padding: 12px 16px !important;
                border-radius: 16px !important;
                font-size: 14px !important;
                line-height: 1.6 !important;
                word-wrap: break-word !important;
                font-weight: 400 !important;
                letter-spacing: normal !important;
            }
            .chatbot-bubble strong {
                font-weight: 700 !important;
            }
            .chatbot-bubble em {
                font-style: italic !important;
            }
            .chatbot-bubble br {
                line-height: inherit !important;
            }
            .chatbot-message-user .chatbot-bubble {
                background: ${primaryColor} !important;
                color: white !important;
                border-bottom-right-radius: 4px !important;
            }
            .chatbot-message-bot .chatbot-bubble {
                background: white !important;
                color: ${textColor} !important;
                border-bottom-left-radius: 4px !important;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
            }
            .chatbot-message-error .chatbot-bubble {
                background: #fef2f2 !important;
                color: #991b1b !important;
            }
            .chatbot-typing-bubble {
                display: flex !important;
                gap: 5px !important;
                padding: 16px 20px !important;
            }
            .chatbot-typing-bubble span {
                width: 8px !important;
                height: 8px !important;
                background: #94a3b8 !important;
                border-radius: 50% !important;
                animation: chatbot-bounce 1.4s infinite ease-in-out both !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            .chatbot-typing-bubble span:nth-child(1) { animation-delay: -0.32s !important; }
            .chatbot-typing-bubble span:nth-child(2) { animation-delay: -0.16s !important; }
            @keyframes chatbot-bounce {
                0%, 80%, 100% { transform: scale(0); }
                40% { transform: scale(1); }
            }
            #chatbot-quick-actions {
                display: flex !important;
                flex-wrap: nowrap !important;
                gap: 8px !important;
                padding: 10px 16px !important;
                background: #f8fafc !important;
                border-top: 1px solid #e2e8f0 !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch !important;
                scrollbar-width: none !important;
                -ms-overflow-style: none !important;
            }
            #chatbot-quick-actions::-webkit-scrollbar {
                display: none !important;
            }
            .chatbot-quick-action {
                padding: 6px 12px !important;
                background: white !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 16px !important;
                font-size: 12px !important;
                color: ${textColor} !important;
                white-space: nowrap !important;
                flex-shrink: 0 !important;
                cursor: pointer !important;
                transition: all 0.2s !important;
                line-height: 1.4 !important;
            }
            .chatbot-quick-action:hover {
                background: ${primaryColor} !important;
                border-color: ${primaryColor} !important;
                color: white !important;
            }
            #chatbot-input-container {
                padding: 12px 16px !important;
                background: white !important;
                border-top: 1px solid #e2e8f0 !important;
                display: flex !important;
                gap: 10px !important;
                align-items: flex-end !important;
            }
            #chatbot-input {
                flex: 1 !important;
                padding: 12px 16px !important;
                border: 2px solid #e2e8f0 !important;
                border-radius: 24px !important;
                font-size: 16px !important;
                outline: none !important;
                transition: border-color 0.2s !important;
                resize: none !important;
                max-height: 100px !important;
                min-height: 44px !important;
                line-height: 1.4 !important;
                font-family: inherit !important;
                background: white !important;
                color: ${textColor} !important;
            }
            #chatbot-input:focus {
                border-color: ${primaryColor} !important;
            }
            #chatbot-input::placeholder {
                color: #94a3b8 !important;
            }
            #chatbot-send {
                width: 44px !important;
                height: 44px !important;
                min-width: 44px !important;
                border-radius: 50% !important;
                background: ${primaryColor} !important;
                border: none !important;
                cursor: pointer !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                transition: background 0.2s, transform 0.2s !important;
            }
            #chatbot-send:hover:not(:disabled) {
                background: ${primaryColor}dd !important;
                transform: scale(1.05) !important;
            }
            #chatbot-send:disabled {
                background: #94a3b8 !important;
                cursor: not-allowed !important;
            }
            #chatbot-send svg {
                width: 20px !important;
                height: 20px !important;
                fill: white !important;
            }
            #chatbot-powered {
                text-align: center !important;
                padding: 10px !important;
                font-size: 11px !important;
                color: #94a3b8 !important;
                background: white !important;
                border-top: 1px solid #f1f5f9 !important;
                margin: 0 !important;
                line-height: 1.4 !important;
            }
            #chatbot-powered a {
                color: ${primaryColor} !important;
                text-decoration: none !important;
                font-weight: 500 !important;
                background: transparent !important;
            }
            #chatbot-powered a:hover {
                text-decoration: underline !important;
            }
            .chatbot-booking-card {
                background: linear-gradient(135deg, #ecfdf5, #d1fae5) !important;
                border: 1px solid #a7f3d0 !important;
                border-radius: 12px !important;
                padding: 16px !important;
                margin: 8px 0 12px !important;
                animation: chatbot-fadeIn 0.3s ease !important;
            }
            .chatbot-booking-card-title {
                display: flex !important;
                align-items: center !important;
                gap: 8px !important;
                font-size: 14px !important;
                font-weight: 600 !important;
                color: #065f46 !important;
                margin: 0 0 8px 0 !important;
                padding: 0 !important;
            }
            .chatbot-booking-card-details {
                font-size: 13px !important;
                color: #047857 !important;
                line-height: 1.6 !important;
                margin: 0 !important;
                padding: 0 !important;
            }
            @media (max-width: 480px) {
                #chatbot-container {
                    bottom: 10px !important;
                    right: 10px !important;
                }
                #chatbot-widget {
                    position: fixed !important;
                    top: 0 !important;
                    left: 0 !important;
                    right: 0 !important;
                    bottom: 0 !important;
                    width: 100vw !important;
                    max-width: 100vw !important;
                    height: 100vh !important;
                    height: 100dvh !important;
                    max-height: 100vh !important;
                    max-height: 100dvh !important;
                    border-radius: 0 !important;
                    z-index: 9999999 !important;
                }
                #chatbot-container.chatbot-mobile-open #chatbot-button {
                    display: none !important;
                }
                #chatbot-button {
                    width: 56px !important;
                    height: 56px !important;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    // Créer le widget HTML
    function createWidget() {
        const container = document.createElement('div');
        container.id = 'chatbot-container';

        const botName = config?.bot_name || 'Assistant';
        const subtitle = config?.subtitle || 'En ligne';

        container.innerHTML = `
            <div id="chatbot-widget">
                <div id="chatbot-header">
                    <div id="chatbot-header-icon">
                        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/></svg>
                    </div>
                    <div id="chatbot-header-info">
                        <div id="chatbot-header-title">${escapeHtml(botName)}</div>
                        <div id="chatbot-header-subtitle">${escapeHtml(subtitle)}</div>
                    </div>
                    <button id="chatbot-close" aria-label="Fermer">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
                    </button>
                </div>
                <div id="chatbot-messages"></div>
                <div id="chatbot-quick-actions"></div>
                <div id="chatbot-input-container">
                    <textarea id="chatbot-input" placeholder="Écrivez votre message..." rows="1" autocomplete="off"></textarea>
                    <button id="chatbot-send" aria-label="Envoyer">
                        <svg viewBox="0 0 24 24"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                    </button>
                </div>
                <div id="chatbot-powered">
                    Propulsé par <a href="https://chatbot.myziggi.pro" target="_blank">ChatBot IA</a>
                </div>
            </div>
            <div id="chatbot-teaser" style="display: none;">
                <button id="chatbot-teaser-close" aria-label="Fermer">&times;</button>
                <div id="chatbot-teaser-text">👋 Besoin d'aide ? Je suis là pour répondre à vos questions !</div>
                <div id="chatbot-teaser-cta">Cliquez pour discuter →</div>
            </div>
            <button id="chatbot-button" aria-label="Ouvrir le chat">
                <span id="chatbot-badge" style="display: none;"></span>
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/></svg>
            </button>
        `;

        document.body.appendChild(container);

        // Attacher les événements
        document.getElementById('chatbot-button').addEventListener('click', function() {
            hideAttentionElements();
            toggleWidget();
        });
        document.getElementById('chatbot-close').addEventListener('click', toggleWidget);

        document.getElementById('chatbot-send').addEventListener('click', function() {
            const input = document.getElementById('chatbot-input');
            sendMessage(input.value);
        });

        const input = document.getElementById('chatbot-input');
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage(this.value);
            }
        });

        // Auto-resize du textarea
        input.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
        });

        // Afficher les quick actions
        renderQuickActions();

        // Événements teaser
        const teaser = document.getElementById('chatbot-teaser');
        const teaserClose = document.getElementById('chatbot-teaser-close');

        teaser.addEventListener('click', function(e) {
            if (e.target !== teaserClose) {
                hideAttentionElements();
                toggleWidget();
            }
        });

        teaserClose.addEventListener('click', function(e) {
            e.stopPropagation();
            dismissTeaser();
        });

        // Programmer l'apparition des éléments d'attention
        scheduleAttention();
    }

    // Programmer l'affichage des éléments d'attention
    function scheduleAttention() {
        // Vérifier si déjà vu récemment
        const lastDismissed = localStorage.getItem('chatbot_teaser_dismissed_' + apiKey);
        if (lastDismissed) {
            const dismissedTime = parseInt(lastDismissed);
            const hoursSince = (Date.now() - dismissedTime) / (1000 * 60 * 60);
            // Ne pas réafficher avant 24h
            if (hoursSince < 24) {
                teaserDismissed = true;
                return;
            }
        }

        // Afficher après 3 secondes
        setTimeout(() => {
            if (!isOpen && !teaserDismissed && !attentionShown) {
                showAttentionElements();
            }
        }, 3000);
    }

    // Afficher les éléments d'attention
    function showAttentionElements() {
        attentionShown = true;
        const button = document.getElementById('chatbot-button');
        const badge = document.getElementById('chatbot-badge');
        const teaser = document.getElementById('chatbot-teaser');

        // Ajouter l'animation pulse
        if (button) button.classList.add('chatbot-pulse');

        // Afficher le badge
        if (badge) badge.style.display = 'block';

        // Afficher le teaser
        if (teaser) teaser.style.display = 'block';

        // Masquer automatiquement le teaser après 8 secondes
        setTimeout(() => {
            if (teaser && teaser.style.display !== 'none' && !isOpen) {
                teaser.style.display = 'none';
            }
        }, 8000);
    }

    // Masquer les éléments d'attention
    function hideAttentionElements() {
        const button = document.getElementById('chatbot-button');
        const badge = document.getElementById('chatbot-badge');
        const teaser = document.getElementById('chatbot-teaser');

        if (button) button.classList.remove('chatbot-pulse');
        if (badge) badge.style.display = 'none';
        if (teaser) teaser.style.display = 'none';
    }

    // Fermer définitivement le teaser
    function dismissTeaser() {
        teaserDismissed = true;
        localStorage.setItem('chatbot_teaser_dismissed_' + apiKey, Date.now().toString());
        hideAttentionElements();
    }

    // Afficher les boutons de questions rapides
    function renderQuickActions() {
        const container = document.getElementById('chatbot-quick-actions');
        if (!container || !config || !config.quick_actions || config.quick_actions.length === 0) {
            if (container) container.style.display = 'none';
            return;
        }

        container.innerHTML = '';
        config.quick_actions.forEach(action => {
            const button = document.createElement('button');
            button.className = 'chatbot-quick-action';
            button.textContent = action;
            button.addEventListener('click', function() {
                sendMessage(action);
            });
            container.appendChild(button);
        });
    }

    // Échapper le HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialiser le widget
    async function init() {
        fingerprint = generateFingerprint();
        sessionId = generateSessionId();

        const configLoaded = await loadConfig();
        if (!configLoaded) return;

        createStyles();
        createWidget();

        // Charger l'historique si disponible
        const historyLoaded = await loadHistory();

        // Si pas d'historique, préparer une nouvelle session
        if (!historyLoaded) {
            localStorage.setItem('chatbot_client_session_' + apiKey, sessionId);
        }
    }

    // Démarrer quand le DOM est prêt
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
