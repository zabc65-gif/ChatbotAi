/**
 * Widget Chatbot Multi-Agent V2
 *
 * Ajoute la possibilité de sélectionner un agent
 * Compatible avec le système de booking multi-agent
 */

(function() {
    'use strict';

    // Configuration par défaut
    const DEFAULT_CONFIG = {
        apiUrl: '/multi-agent/api/chat.php',
        agentsUrl: '/multi-agent/api/agents.php',
        botName: 'Assistant',
        welcomeMessage: 'Bonjour ! Comment puis-je vous aider ?',
        primaryColor: '#3498db',
        position: 'right',
        multiAgent: {
            enabled: true,
            allowVisitorChoice: false,
            showPhotos: true,
            showBios: true
        }
    };

    // État du widget
    let state = {
        isOpen: false,
        isLoading: false,
        sessionId: null,
        apiKey: null,
        config: { ...DEFAULT_CONFIG },
        messages: [],
        selectedAgent: null,
        agents: [],
        lastUserMessageEl: null
    };

    /**
     * Récupérer la clé API depuis différentes sources
     */
    function getApiKey() {
        // 1. Depuis window.ChatbotConfig
        if (window.ChatbotConfig && window.ChatbotConfig.apiKey) {
            return window.ChatbotConfig.apiKey;
        }

        // 2. Depuis un élément data
        const configEl = document.getElementById('chatbot-config');
        if (configEl && configEl.dataset.key) {
            return configEl.dataset.key;
        }

        // 3. Depuis le script lui-même
        const scripts = document.querySelectorAll('script[src*="widget-v2.js"]');
        for (const script of scripts) {
            if (script.dataset.key) {
                return script.dataset.key;
            }
        }

        return null;
    }

    /**
     * Générer un ID de session unique
     */
    function generateSessionId() {
        return 'ma_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }

    /**
     * Charger la configuration depuis l'API
     */
    async function loadConfig() {
        if (!state.apiKey) {
            console.error('ChatbotV2: API key non trouvée');
            return;
        }

        try {
            const response = await fetch(`${state.config.apiUrl}?action=config&key=${state.apiKey}`);
            const data = await response.json();

            if (data.success && data.config) {
                state.config = { ...state.config, ...data.config };

                // Mettre à jour le thème
                updateTheme();

                // Afficher le message de bienvenue
                if (state.config.welcome_message) {
                    addMessage('assistant', state.config.welcome_message);
                }

                // Charger les agents si le choix visiteur est activé
                if (state.config.multi_agent?.allow_visitor_choice) {
                    await loadAgents();
                }

                // Afficher les quick actions
                if (state.config.quick_actions && state.config.quick_actions.length > 0) {
                    renderQuickActions();
                }
            }
        } catch (error) {
            console.error('ChatbotV2: Erreur chargement config', error);
        }
    }

    /**
     * Charger la liste des agents
     */
    async function loadAgents() {
        try {
            const response = await fetch(`${state.config.agentsUrl}?key=${state.apiKey}&action=list`);
            const data = await response.json();

            if (data.success && data.agents) {
                state.agents = data.agents;
                renderAgentSelector();
            }
        } catch (error) {
            console.error('ChatbotV2: Erreur chargement agents', error);
        }
    }

    /**
     * Envoyer un message
     */
    async function sendMessage(text) {
        if (!text.trim() || state.isLoading) return;

        // Ajouter le message utilisateur
        addMessage('user', text);

        // Afficher l'indicateur de chargement
        state.isLoading = true;
        showTypingIndicator();

        try {
            const formData = new FormData();
            formData.append('action', 'message');
            formData.append('message', text);
            formData.append('session_id', state.sessionId);
            formData.append('api_key', state.apiKey);

            if (state.selectedAgent) {
                formData.append('preferred_agent_id', state.selectedAgent.id);
            }

            const response = await fetch(state.config.apiUrl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            hideTypingIndicator();

            if (data.success) {
                addMessage('assistant', data.message);

                // Si un booking a été créé, afficher les infos
                if (data.booking && data.booking.success) {
                    renderBookingConfirmation(data.booking);
                }
            } else {
                addMessage('assistant', 'Désolé, une erreur est survenue. Veuillez réessayer.');
            }

        } catch (error) {
            console.error('ChatbotV2: Erreur envoi message', error);
            hideTypingIndicator();
            addMessage('assistant', 'Erreur de connexion. Veuillez réessayer.');
        } finally {
            state.isLoading = false;
        }
    }

    /**
     * Ajouter un message à la conversation
     */
    function addMessage(role, content) {
        state.messages.push({ role, content, timestamp: new Date() });
        const messageEl = renderMessage(role, content);

        // Pour les messages utilisateur, on scrolle en bas
        // Pour les messages bot, on scrolle vers le message utilisateur précédent
        if (role === 'user') {
            state.lastUserMessageEl = messageEl;
            scrollToBottom();
        } else if (state.lastUserMessageEl) {
            scrollToUserMessage();
        } else {
            scrollToBottom();
        }
    }

    /**
     * Afficher un message dans le chat
     */
    function renderMessage(role, content) {
        const messagesContainer = document.getElementById('chatbot-v2-messages');
        if (!messagesContainer) return null;

        const messageEl = document.createElement('div');
        messageEl.className = `chatbot-v2-message chatbot-v2-message-${role}`;

        // Formater le contenu (Markdown basique)
        const formattedContent = formatMessage(content);

        messageEl.innerHTML = `
            <div class="chatbot-v2-message-content">
                ${formattedContent}
            </div>
        `;

        messagesContainer.appendChild(messageEl);
        return messageEl;
    }

    /**
     * Formater un message (Markdown basique + détection booking)
     */
    function formatMessage(content) {
        // Supprimer les marqueurs de booking
        content = content.replace(/\[BOOKING_REQUEST\][\s\S]*?\[\/BOOKING_REQUEST\]/g, '');

        // Markdown basique
        content = content
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');

        return content;
    }

    /**
     * Afficher la confirmation de booking
     */
    function renderBookingConfirmation(booking) {
        const messagesContainer = document.getElementById('chatbot-v2-messages');
        if (!messagesContainer) return;

        const agent = booking.agent || {};

        const bookingEl = document.createElement('div');
        bookingEl.className = 'chatbot-v2-booking-card';
        bookingEl.innerHTML = `
            <div class="chatbot-v2-booking-header">
                <span class="chatbot-v2-booking-icon">📅</span>
                Rendez-vous confirmé
            </div>
            <div class="chatbot-v2-booking-body">
                ${agent.name ? `
                    <div class="chatbot-v2-booking-agent">
                        ${agent.photo_url ? `<img src="${agent.photo_url}" alt="" class="chatbot-v2-agent-photo-small">` : ''}
                        <span>Avec <strong>${agent.name}</strong></span>
                    </div>
                ` : ''}
                <div class="chatbot-v2-booking-detail">
                    <span class="chatbot-v2-booking-label">Date :</span>
                    <span>${booking.booking?.date || ''}</span>
                </div>
                <div class="chatbot-v2-booking-detail">
                    <span class="chatbot-v2-booking-label">Heure :</span>
                    <span>${booking.booking?.time || ''}</span>
                </div>
                ${booking.booking?.service ? `
                    <div class="chatbot-v2-booking-detail">
                        <span class="chatbot-v2-booking-label">Service :</span>
                        <span>${booking.booking.service}</span>
                    </div>
                ` : ''}
            </div>
            <div class="chatbot-v2-booking-footer">
                Vous recevrez une confirmation par email.
            </div>
        `;

        messagesContainer.appendChild(bookingEl);
        scrollToBottom();
    }

    /**
     * Afficher le sélecteur d'agents
     */
    function renderAgentSelector() {
        const container = document.getElementById('chatbot-v2-agent-selector');
        if (!container || state.agents.length === 0) return;

        container.innerHTML = `
            <div class="chatbot-v2-agent-selector-title">Choisissez votre conseiller :</div>
            <div class="chatbot-v2-agents-list">
                ${state.agents.map(agent => `
                    <div class="chatbot-v2-agent-item ${state.selectedAgent?.id === agent.id ? 'selected' : ''}"
                         data-agent-id="${agent.id}">
                        ${state.config.multi_agent?.showPhotos && agent.photo ? `
                            <img src="${agent.photo}" alt="" class="chatbot-v2-agent-photo">
                        ` : `
                            <div class="chatbot-v2-agent-avatar" style="background: ${agent.color || '#3498db'}">
                                ${agent.name.charAt(0).toUpperCase()}
                            </div>
                        `}
                        <div class="chatbot-v2-agent-info">
                            <div class="chatbot-v2-agent-name">${agent.name}</div>
                            ${state.config.multi_agent?.showBios && agent.bio ? `
                                <div class="chatbot-v2-agent-bio">${agent.bio}</div>
                            ` : ''}
                            ${agent.specialties && agent.specialties.length > 0 ? `
                                <div class="chatbot-v2-agent-specialties">
                                    ${agent.specialties.slice(0, 2).map(s => `<span class="chatbot-v2-specialty-tag">${s}</span>`).join('')}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                `).join('')}
            </div>
        `;

        // Event listeners pour la sélection
        container.querySelectorAll('.chatbot-v2-agent-item').forEach(item => {
            item.addEventListener('click', () => {
                const agentId = parseInt(item.dataset.agentId);
                selectAgent(agentId);
            });
        });

        container.style.display = 'block';
    }

    /**
     * Sélectionner un agent
     */
    function selectAgent(agentId) {
        state.selectedAgent = state.agents.find(a => a.id === agentId) || null;

        // Mettre à jour l'UI
        document.querySelectorAll('.chatbot-v2-agent-item').forEach(item => {
            item.classList.toggle('selected', parseInt(item.dataset.agentId) === agentId);
        });

        // Message de confirmation
        if (state.selectedAgent) {
            addMessage('assistant', `Parfait ! Vous serez en contact avec ${state.selectedAgent.name}. Comment puis-je vous aider ?`);
        }
    }

    /**
     * Afficher les quick actions
     */
    function renderQuickActions() {
        const container = document.getElementById('chatbot-v2-quick-actions');
        if (!container || !state.config.quick_actions) return;

        container.innerHTML = state.config.quick_actions.map(action => `
            <button class="chatbot-v2-quick-action">${action}</button>
        `).join('');

        container.querySelectorAll('.chatbot-v2-quick-action').forEach(btn => {
            btn.addEventListener('click', () => {
                sendMessage(btn.textContent);
                container.style.display = 'none';
            });
        });

        container.style.display = 'flex';
    }

    /**
     * Afficher l'indicateur de saisie
     */
    function showTypingIndicator() {
        const messagesContainer = document.getElementById('chatbot-v2-messages');
        if (!messagesContainer) return;

        const indicator = document.createElement('div');
        indicator.id = 'chatbot-v2-typing';
        indicator.className = 'chatbot-v2-typing';
        indicator.innerHTML = `
            <div class="chatbot-v2-typing-dot"></div>
            <div class="chatbot-v2-typing-dot"></div>
            <div class="chatbot-v2-typing-dot"></div>
        `;
        messagesContainer.appendChild(indicator);
        scrollToBottom();
    }

    /**
     * Masquer l'indicateur de saisie
     */
    function hideTypingIndicator() {
        const indicator = document.getElementById('chatbot-v2-typing');
        if (indicator) indicator.remove();
    }

    /**
     * Scroller vers le bas
     */
    function scrollToBottom() {
        const messagesContainer = document.getElementById('chatbot-v2-messages');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    /**
     * Scroll pour montrer le message utilisateur en haut
     */
    function scrollToUserMessage() {
        if (state.lastUserMessageEl) {
            state.lastUserMessageEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            scrollToBottom();
        }
    }

    /**
     * Mettre à jour le thème
     */
    function updateTheme() {
        const root = document.documentElement;
        root.style.setProperty('--chatbot-v2-primary', state.config.primary_color || state.config.primaryColor);
    }

    /**
     * Ouvrir/Fermer le widget
     */
    function toggleWidget() {
        state.isOpen = !state.isOpen;
        const widget = document.getElementById('chatbot-v2-widget');
        const button = document.getElementById('chatbot-v2-button');

        if (widget) widget.classList.toggle('open', state.isOpen);
        if (button) button.classList.toggle('open', state.isOpen);
    }

    /**
     * Créer le DOM du widget
     */
    function createWidget() {
        const container = document.createElement('div');
        container.id = 'chatbot-v2-container';
        container.innerHTML = `
            <!-- Bouton flottant -->
            <button id="chatbot-v2-button" class="chatbot-v2-button">
                <span class="chatbot-v2-button-icon">💬</span>
                <span class="chatbot-v2-button-close">✕</span>
            </button>

            <!-- Widget -->
            <div id="chatbot-v2-widget" class="chatbot-v2-widget">
                <!-- Header -->
                <div class="chatbot-v2-header">
                    <div class="chatbot-v2-header-info">
                        <span class="chatbot-v2-header-icon">${state.config.icon || '🤖'}</span>
                        <span class="chatbot-v2-header-name">${state.config.bot_name || state.config.botName}</span>
                    </div>
                    <button class="chatbot-v2-header-close" onclick="window.ChatbotV2.toggle()">✕</button>
                </div>

                <!-- Sélecteur d'agents (si activé) -->
                <div id="chatbot-v2-agent-selector" class="chatbot-v2-agent-selector" style="display: none;"></div>

                <!-- Messages -->
                <div id="chatbot-v2-messages" class="chatbot-v2-messages"></div>

                <!-- Quick actions -->
                <div id="chatbot-v2-quick-actions" class="chatbot-v2-quick-actions" style="display: none;"></div>

                <!-- Input -->
                <div class="chatbot-v2-input-container">
                    <input type="text" id="chatbot-v2-input" class="chatbot-v2-input"
                           placeholder="Écrivez votre message..." autocomplete="off">
                    <button id="chatbot-v2-send" class="chatbot-v2-send">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(container);

        // Event listeners
        document.getElementById('chatbot-v2-button').addEventListener('click', toggleWidget);

        const input = document.getElementById('chatbot-v2-input');
        const sendBtn = document.getElementById('chatbot-v2-send');

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                const text = input.value.trim();
                if (text) {
                    sendMessage(text);
                    input.value = '';
                }
            }
        });

        sendBtn.addEventListener('click', () => {
            const text = input.value.trim();
            if (text) {
                sendMessage(text);
                input.value = '';
            }
        });
    }

    /**
     * Injecter les styles
     */
    function injectStyles() {
        const styles = document.createElement('style');
        styles.textContent = `
            :root {
                --chatbot-v2-primary: #3498db;
            }

            #chatbot-v2-container {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                font-size: 14px;
                line-height: 1.5;
            }

            .chatbot-v2-button {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 60px;
                height: 60px;
                border-radius: 50%;
                background: var(--chatbot-v2-primary);
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 20px rgba(0,0,0,0.2);
                z-index: 9999;
                transition: transform 0.3s, box-shadow 0.3s;
            }

            .chatbot-v2-button:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 25px rgba(0,0,0,0.25);
            }

            .chatbot-v2-button-icon,
            .chatbot-v2-button-close {
                font-size: 24px;
                color: white;
                transition: opacity 0.3s, transform 0.3s;
            }

            .chatbot-v2-button-close {
                position: absolute;
                opacity: 0;
                transform: rotate(-90deg);
            }

            .chatbot-v2-button.open .chatbot-v2-button-icon {
                opacity: 0;
                transform: rotate(90deg);
            }

            .chatbot-v2-button.open .chatbot-v2-button-close {
                opacity: 1;
                transform: rotate(0);
            }

            .chatbot-v2-widget {
                position: fixed;
                bottom: 100px;
                right: 20px;
                width: 380px;
                height: 600px;
                max-height: calc(100vh - 120px);
                background: white;
                border-radius: 16px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.15);
                display: flex;
                flex-direction: column;
                z-index: 9998;
                opacity: 0;
                transform: translateY(20px) scale(0.95);
                pointer-events: none;
                transition: opacity 0.3s, transform 0.3s;
            }

            .chatbot-v2-widget.open {
                opacity: 1;
                transform: translateY(0) scale(1);
                pointer-events: all;
            }

            .chatbot-v2-header {
                background: var(--chatbot-v2-primary);
                color: white;
                padding: 16px;
                border-radius: 16px 16px 0 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .chatbot-v2-header-info {
                display: flex;
                align-items: center;
                gap: 10px;
            }

            .chatbot-v2-header-icon {
                font-size: 24px;
            }

            .chatbot-v2-header-name {
                font-weight: 600;
                font-size: 16px;
            }

            .chatbot-v2-header-close {
                background: none;
                border: none;
                color: white;
                font-size: 20px;
                cursor: pointer;
                opacity: 0.8;
            }

            .chatbot-v2-header-close:hover {
                opacity: 1;
            }

            .chatbot-v2-agent-selector {
                padding: 12px;
                border-bottom: 1px solid #eee;
                max-height: 200px;
                overflow-y: auto;
            }

            .chatbot-v2-agent-selector-title {
                font-size: 12px;
                color: #666;
                margin-bottom: 10px;
            }

            .chatbot-v2-agents-list {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .chatbot-v2-agent-item {
                display: flex;
                align-items: center;
                gap: 10px;
                padding: 10px;
                border-radius: 10px;
                cursor: pointer;
                transition: background 0.2s;
                border: 2px solid transparent;
            }

            .chatbot-v2-agent-item:hover {
                background: #f5f5f5;
            }

            .chatbot-v2-agent-item.selected {
                background: #e8f4fc;
                border-color: var(--chatbot-v2-primary);
            }

            .chatbot-v2-agent-photo,
            .chatbot-v2-agent-avatar {
                width: 45px;
                height: 45px;
                border-radius: 50%;
                flex-shrink: 0;
            }

            .chatbot-v2-agent-photo {
                object-fit: cover;
            }

            .chatbot-v2-agent-avatar {
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 600;
                font-size: 18px;
            }

            .chatbot-v2-agent-info {
                flex: 1;
                min-width: 0;
            }

            .chatbot-v2-agent-name {
                font-weight: 600;
                color: #333;
            }

            .chatbot-v2-agent-bio {
                font-size: 12px;
                color: #666;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .chatbot-v2-agent-specialties {
                display: flex;
                gap: 4px;
                margin-top: 4px;
            }

            .chatbot-v2-specialty-tag {
                font-size: 10px;
                padding: 2px 6px;
                background: #e8f4fc;
                color: var(--chatbot-v2-primary);
                border-radius: 10px;
            }

            .chatbot-v2-messages {
                flex: 1;
                overflow-y: auto;
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }

            .chatbot-v2-message {
                max-width: 85%;
                animation: chatbot-v2-fadeIn 0.3s ease;
            }

            @keyframes chatbot-v2-fadeIn {
                from { opacity: 0; transform: translateY(10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .chatbot-v2-message-user {
                align-self: flex-end;
            }

            .chatbot-v2-message-assistant {
                align-self: flex-start;
            }

            .chatbot-v2-message-content {
                padding: 12px 16px;
                border-radius: 18px;
            }

            .chatbot-v2-message-user .chatbot-v2-message-content {
                background: var(--chatbot-v2-primary);
                color: white;
                border-bottom-right-radius: 4px;
            }

            .chatbot-v2-message-assistant .chatbot-v2-message-content {
                background: #f0f0f0;
                color: #333;
                border-bottom-left-radius: 4px;
            }

            .chatbot-v2-typing {
                display: flex;
                gap: 4px;
                padding: 12px 16px;
                background: #f0f0f0;
                border-radius: 18px;
                width: fit-content;
            }

            .chatbot-v2-typing-dot {
                width: 8px;
                height: 8px;
                background: #999;
                border-radius: 50%;
                animation: chatbot-v2-typing 1.4s infinite;
            }

            .chatbot-v2-typing-dot:nth-child(2) { animation-delay: 0.2s; }
            .chatbot-v2-typing-dot:nth-child(3) { animation-delay: 0.4s; }

            @keyframes chatbot-v2-typing {
                0%, 60%, 100% { transform: translateY(0); opacity: 0.6; }
                30% { transform: translateY(-5px); opacity: 1; }
            }

            .chatbot-v2-booking-card {
                background: #f8fff8;
                border: 1px solid #27ae60;
                border-radius: 12px;
                overflow: hidden;
                margin-top: 8px;
                animation: chatbot-v2-fadeIn 0.3s ease;
            }

            .chatbot-v2-booking-header {
                background: #27ae60;
                color: white;
                padding: 10px 14px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .chatbot-v2-booking-body {
                padding: 14px;
            }

            .chatbot-v2-booking-agent {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 12px;
                padding-bottom: 12px;
                border-bottom: 1px solid #e0e0e0;
            }

            .chatbot-v2-agent-photo-small {
                width: 35px;
                height: 35px;
                border-radius: 50%;
                object-fit: cover;
            }

            .chatbot-v2-booking-detail {
                display: flex;
                justify-content: space-between;
                padding: 4px 0;
            }

            .chatbot-v2-booking-label {
                color: #666;
            }

            .chatbot-v2-booking-footer {
                background: #f0f0f0;
                padding: 10px 14px;
                font-size: 12px;
                color: #666;
                text-align: center;
            }

            .chatbot-v2-quick-actions {
                padding: 10px 16px;
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                border-top: 1px solid #eee;
            }

            .chatbot-v2-quick-action {
                padding: 8px 14px;
                background: #f5f5f5;
                border: 1px solid #ddd;
                border-radius: 20px;
                cursor: pointer;
                font-size: 13px;
                transition: background 0.2s, border-color 0.2s;
            }

            .chatbot-v2-quick-action:hover {
                background: #e8f4fc;
                border-color: var(--chatbot-v2-primary);
            }

            .chatbot-v2-input-container {
                display: flex;
                padding: 12px;
                border-top: 1px solid #eee;
                gap: 8px;
            }

            .chatbot-v2-input {
                flex: 1;
                padding: 12px 16px;
                border: 1px solid #ddd;
                border-radius: 24px;
                font-size: 14px;
                outline: none;
                transition: border-color 0.2s;
            }

            .chatbot-v2-input:focus {
                border-color: var(--chatbot-v2-primary);
            }

            .chatbot-v2-send {
                width: 44px;
                height: 44px;
                border-radius: 50%;
                background: var(--chatbot-v2-primary);
                border: none;
                color: white;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: transform 0.2s;
            }

            .chatbot-v2-send:hover {
                transform: scale(1.05);
            }

            /* Mobile responsive */
            @media (max-width: 480px) {
                .chatbot-v2-widget {
                    width: 100%;
                    height: 100%;
                    max-height: 100%;
                    bottom: 0;
                    right: 0;
                    border-radius: 0;
                }

                .chatbot-v2-header {
                    border-radius: 0;
                }

                .chatbot-v2-button.open {
                    display: none;
                }
            }
        `;
        document.head.appendChild(styles);
    }

    /**
     * Initialisation
     */
    function init() {
        state.apiKey = getApiKey();
        state.sessionId = localStorage.getItem('chatbot_v2_session') || generateSessionId();
        localStorage.setItem('chatbot_v2_session', state.sessionId);

        injectStyles();
        createWidget();
        loadConfig();
    }

    // API publique
    window.ChatbotV2 = {
        init,
        toggle: toggleWidget,
        sendMessage,
        selectAgent,
        getState: () => ({ ...state })
    };

    // Auto-init au chargement
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
