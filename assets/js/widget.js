/**
 * Widget Chatbot IA
 * Script JavaScript pour le widget de chat interactif
 */

class ChatbotWidget {
    constructor(options = {}) {
        this.options = {
            apiUrl: options.apiUrl || '/api/chat.php',
            context: options.context || null, // 'btp', 'immo', 'ecommerce'
            theme: options.theme || null,
            botName: options.botName || 'Assistant IA',
            welcomeMessage: options.welcomeMessage || null,
            placeholder: options.placeholder || 'Écrivez votre message...',
            position: options.position || 'right', // 'right' ou 'left'
            showRemainingMessages: options.showRemainingMessages !== false, // Afficher le compteur
            showFace: options.showFace !== false, // Afficher le visage animé (true par défaut)
            showHat: options.showHat !== false, // Afficher le chapeau (true par défaut)
            faceColor: options.faceColor || '#6366f1', // Couleur du visage animé
            hatColor: options.hatColor || '#1e293b', // Couleur du chapeau
            ...options
        };

        this.sessionId = null;
        this.isOpen = false;
        this.isTyping = false;
        this.remainingMessages = null;
        this.dailyLimit = null;
        this.fingerprint = this.generateFingerprint();
        this.lastUserMessageEl = null;

        this.init();
    }

    /**
     * Génère un fingerprint simple pour identifier l'utilisateur
     */
    generateFingerprint() {
        // Récupérer depuis localStorage si existant
        let fp = localStorage.getItem('chatbot_fp');
        if (fp) return fp;

        // Générer un nouveau fingerprint
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

    /**
     * Initialise le widget
     */
    init() {
        this.injectDynamicStyles();
        this.createWidget();
        this.bindEvents();
        this.initSession();
    }

    /**
     * Injecte les styles dynamiques pour les couleurs personnalisées
     */
    injectDynamicStyles() {
        const faceColor = this.options.faceColor;
        const hatColor = this.options.hatColor;

        const styleId = 'chatbot-dynamic-styles';
        let styleEl = document.getElementById(styleId);

        if (!styleEl) {
            styleEl = document.createElement('style');
            styleEl.id = styleId;
            document.head.appendChild(styleEl);
        }

        styleEl.textContent = `
            .chatbot-widget .chatbot-avatar {
                background: ${faceColor} !important;
            }
            .chatbot-widget .chatbot-toggle-face {
                background: ${faceColor} !important;
            }
            .chatbot-widget .chatbot-hat,
            .chatbot-widget .chatbot-hat::before {
                background: ${hatColor} !important;
            }
            .chatbot-widget .toggle-hat,
            .chatbot-widget .toggle-hat::before {
                background: ${hatColor} !important;
            }
        `;
    }

    /**
     * Crée la structure HTML du widget
     */
    createWidget() {
        // Container principal
        this.container = document.createElement('div');
        this.container.className = 'chatbot-widget';
        if (this.options.theme) {
            this.container.setAttribute('data-theme', this.options.theme);
        }

        // Bouton toggle
        this.toggleBtn = document.createElement('button');
        this.toggleBtn.className = 'chatbot-toggle';
        this.toggleBtn.setAttribute('aria-label', 'Ouvrir le chat');

        // Contenu du toggle : visage animé (avec ou sans chapeau) ou juste l'icône SVG
        const toggleHatHtml = this.options.showHat ? '<div class="toggle-hat"></div>' : '';
        const toggleFaceHtml = this.options.showFace
            ? `<div class="chatbot-toggle-face">
                ${toggleHatHtml}
                <div class="toggle-eye"></div>
                <div class="toggle-eye"></div>
                <div class="toggle-mouth"></div>
            </div>`
            : '';

        this.toggleBtn.innerHTML = `
            ${toggleFaceHtml}
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm0 14H5.17L4 17.17V4h16v12z"/>
                <path d="M7 9h10v2H7zm0-3h10v2H7zm0 6h7v2H7z"/>
            </svg>
        `;

        // Fenêtre de chat
        this.window = document.createElement('div');
        this.window.className = 'chatbot-window';

        // Contenu de l'avatar : visage animé (avec ou sans chapeau) ou icône SVG
        const headerHatHtml = this.options.showHat ? '<div class="chatbot-hat"></div>' : '';
        const headerAvatarContent = this.options.showFace
            ? `${headerHatHtml}
               <div class="chatbot-face">
                   <div class="chatbot-eye"></div>
                   <div class="chatbot-eye"></div>
               </div>
               <div class="chatbot-mouth"></div>`
            : `<svg viewBox="0 0 24 24" fill="white" width="26" height="26">
                   <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/>
               </svg>`;

        this.window.innerHTML = `
            <div class="chatbot-header">
                <div class="chatbot-avatar">
                    ${headerAvatarContent}
                </div>
                <div class="chatbot-info">
                    <div class="chatbot-name">${this.escapeHtml(this.options.botName)}</div>
                    <div class="chatbot-status">
                        <span class="chatbot-online">En ligne</span>
                        <span class="chatbot-remaining" id="chatbot-remaining" style="display: none;"></span>
                    </div>
                </div>
                <button class="chatbot-close" aria-label="Fermer">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
            </div>
            <div class="chatbot-messages" id="chatbot-messages"></div>
            <div class="chatbot-input-area">
                <div class="chatbot-input-wrapper">
                    <textarea
                        class="chatbot-input"
                        placeholder="${this.escapeHtml(this.options.placeholder)}"
                        rows="1"
                        id="chatbot-input"
                    ></textarea>
                    <button class="chatbot-send" id="chatbot-send" aria-label="Envoyer">
                        <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div class="chatbot-powered">
                Propulsé par <a href="#" target="_blank">ChatBot IA</a>
            </div>
        `;

        // Overlay mobile (fond sombre)
        this.overlay = document.createElement('div');
        this.overlay.className = 'chatbot-overlay';

        // Ajout au DOM
        this.container.appendChild(this.overlay);
        this.container.appendChild(this.toggleBtn);
        this.container.appendChild(this.window);
        document.body.appendChild(this.container);

        // Références aux éléments
        this.messagesContainer = this.window.querySelector('#chatbot-messages');
        this.input = this.window.querySelector('#chatbot-input');
        this.sendBtn = this.window.querySelector('#chatbot-send');
        this.closeBtn = this.window.querySelector('.chatbot-close');
    }

    /**
     * Attache les événements
     */
    bindEvents() {
        // Toggle ouverture/fermeture
        this.toggleBtn.addEventListener('click', () => this.toggle());
        this.closeBtn.addEventListener('click', () => this.close());
        this.overlay.addEventListener('click', () => this.close());

        // Envoi de message
        this.sendBtn.addEventListener('click', () => this.sendMessage());

        // Touche Entrée pour envoyer
        this.input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Auto-resize du textarea
        this.input.addEventListener('input', () => {
            this.input.style.height = 'auto';
            this.input.style.height = Math.min(this.input.scrollHeight, 100) + 'px';
        });
    }

    /**
     * Initialise la session avec l'API
     */
    async initSession() {
        // Vérifier si une session existe en localStorage
        const savedSession = localStorage.getItem('chatbot_session');
        const savedDate = localStorage.getItem('chatbot_session_date');
        const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD

        // Si session existante mais jour différent → réinitialiser
        if (savedSession && savedDate && savedDate !== today) {
            localStorage.removeItem('chatbot_session');
            localStorage.removeItem('chatbot_session_date');
            // Continuer pour créer une nouvelle session
        } else if (savedSession) {
            // Même jour (ou pas de date stockée) → réutiliser la session
            this.sessionId = savedSession;
            // Mettre à jour la date si elle n'existait pas
            if (!savedDate) {
                localStorage.setItem('chatbot_session_date', today);
            }
            await this.loadHistory();
            // Recharger les infos de limite
            await this.refreshLimitInfo();
            return;
        }

        try {
            const response = await this.apiCall('init', {
                context: this.options.context,
                fingerprint: this.fingerprint
            });

            if (response.success) {
                this.sessionId = response.session_id;
                localStorage.setItem('chatbot_session', this.sessionId);
                localStorage.setItem('chatbot_session_date', new Date().toISOString().split('T')[0]);

                // Stocker les infos de limite
                if (response.remaining !== undefined) {
                    this.remainingMessages = response.remaining;
                    this.dailyLimit = response.daily_limit || 10;
                    this.updateRemainingDisplay();
                }

                // Afficher le message de bienvenue
                const welcomeMsg = this.options.welcomeMessage || response.welcome_message;
                if (welcomeMsg) {
                    this.addMessage(welcomeMsg, 'bot');
                }
            }
        } catch (error) {
            console.error('Erreur initialisation chatbot:', error);
        }
    }

    /**
     * Rafraîchit les infos de limite d'utilisation
     */
    async refreshLimitInfo() {
        if (!this.options.context) return;

        try {
            const response = await this.apiCall('init', {
                context: this.options.context,
                fingerprint: this.fingerprint
            });

            if (response.success && response.remaining !== undefined) {
                this.remainingMessages = response.remaining;
                this.dailyLimit = response.daily_limit || 10;
                this.updateRemainingDisplay();
            }
        } catch (error) {
            // Silently fail
        }
    }

    /**
     * Met à jour l'affichage du compteur de messages restants
     */
    updateRemainingDisplay() {
        if (!this.options.showRemainingMessages || !this.options.context) return;

        const remainingEl = document.getElementById('chatbot-remaining');
        if (!remainingEl) return;

        if (this.remainingMessages !== null) {
            remainingEl.style.display = 'inline';
            if (this.remainingMessages <= 0) {
                remainingEl.textContent = '• Limite atteinte';
                remainingEl.style.color = '#ef4444';
            } else if (this.remainingMessages <= 3) {
                remainingEl.textContent = `• ${this.remainingMessages} msg restant${this.remainingMessages > 1 ? 's' : ''}`;
                remainingEl.style.color = '#f59e0b';
            } else {
                remainingEl.textContent = `• ${this.remainingMessages}/${this.dailyLimit} msg`;
                remainingEl.style.color = '#10b981';
            }
        }
    }

    /**
     * Charge l'historique de la conversation
     */
    async loadHistory() {
        try {
            const response = await this.apiCall('history', {
                session_id: this.sessionId
            });

            if (response.success && response.history) {
                response.history.forEach(msg => {
                    this.addMessage(msg.content, msg.role === 'user' ? 'user' : 'bot', false);
                });
            }

            // Si pas d'historique, afficher message de bienvenue
            if (!response.history || response.history.length === 0) {
                const welcomeMsg = this.options.welcomeMessage || "Bonjour ! Comment puis-je vous aider ?";
                this.addMessage(welcomeMsg, 'bot');
            }
        } catch (error) {
            console.error('Erreur chargement historique:', error);
        }
    }

    /**
     * Ouvre/ferme le widget
     */
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    /**
     * Ouvre le widget
     */
    open() {
        this.isOpen = true;
        this.window.classList.add('open');
        this.toggleBtn.classList.add('active');
        this.overlay.classList.add('visible');
        this.input.focus();
    }

    /**
     * Ferme le widget
     */
    close() {
        this.isOpen = false;
        this.window.classList.remove('open');
        this.toggleBtn.classList.remove('active');
        this.overlay.classList.remove('visible');
    }

    /**
     * Envoie un message
     */
    async sendMessage() {
        const message = this.input.value.trim();

        if (!message || this.isTyping) return;

        // Vérifier la limite côté client (optimiste)
        if (this.options.context && this.remainingMessages !== null && this.remainingMessages <= 0) {
            this.addMessage("⚠️ Vous avez atteint la limite de messages pour aujourd'hui. Revenez demain ou contactez-nous pour obtenir votre propre assistant !", 'bot');
            return;
        }

        // Afficher le message utilisateur
        this.addMessage(message, 'user');

        // Vider l'input
        this.input.value = '';
        this.input.style.height = 'auto';

        // Afficher l'indicateur de frappe
        this.showTyping();

        try {
            const response = await this.apiCall('message', {
                session_id: this.sessionId,
                message: message,
                context: this.options.context,
                fingerprint: this.fingerprint
            });

            this.hideTyping();

            if (response.success) {
                // Gérer les différents types de réponse
                const botMessage = response.message || response.response;
                this.addMessage(botMessage, 'bot');

                // Mettre à jour le compteur de messages restants
                if (response.remaining !== undefined) {
                    this.remainingMessages = response.remaining;
                    this.updateRemainingDisplay();
                }

                // Mettre à jour le session_id si nécessaire
                if (response.session_id && response.session_id !== this.sessionId) {
                    this.sessionId = response.session_id;
                    localStorage.setItem('chatbot_session', this.sessionId);
                }

                // Si limite atteinte, désactiver l'input
                if (response.limited) {
                    this.disableInput();
                }
            } else {
                this.addMessage(response.error || 'Désolé, une erreur est survenue.', 'bot');
            }
        } catch (error) {
            this.hideTyping();
            console.error('Erreur envoi message:', error);
            this.addMessage('Désolé, impossible de contacter le serveur.', 'bot');
        }
    }

    /**
     * Désactive l'input (limite atteinte)
     */
    disableInput() {
        this.input.disabled = true;
        this.input.placeholder = 'Limite atteinte pour aujourd\'hui';
        this.sendBtn.disabled = true;
    }

    /**
     * Ajoute un message à la conversation
     */
    addMessage(content, type, animate = true) {
        const messageDiv = document.createElement('div');
        messageDiv.className = `chatbot-message ${type}`;

        if (!animate) {
            messageDiv.style.animation = 'none';
        }

        messageDiv.innerHTML = `
            <div class="chatbot-message-content">
                ${this.formatMessage(content)}
            </div>
        `;

        this.messagesContainer.appendChild(messageDiv);

        // Pour les messages utilisateur, on scrolle en bas et on sauvegarde l'élément
        // Pour les messages bot, on scrolle vers le message utilisateur précédent
        if (type === 'user') {
            this.lastUserMessageEl = messageDiv;
            this.scrollToBottom();
        } else if (this.lastUserMessageEl) {
            this.scrollToUserMessage();
        } else {
            this.scrollToBottom();
        }
    }

    /**
     * Affiche l'indicateur de frappe
     */
    showTyping() {
        this.isTyping = true;
        this.sendBtn.disabled = true;
        this.container.classList.add('typing');

        const typingDiv = document.createElement('div');
        typingDiv.className = 'chatbot-typing';
        typingDiv.id = 'chatbot-typing';
        typingDiv.innerHTML = '<span></span><span></span><span></span>';

        this.messagesContainer.appendChild(typingDiv);
        // Garder le message utilisateur visible pendant la frappe
        this.scrollToUserMessage();
    }

    /**
     * Cache l'indicateur de frappe
     */
    hideTyping() {
        this.isTyping = false;
        this.sendBtn.disabled = false;
        this.container.classList.remove('typing');

        const typingDiv = document.getElementById('chatbot-typing');
        if (typingDiv) {
            typingDiv.remove();
        }
    }

    /**
     * Scroll vers le bas des messages
     */
    scrollToBottom() {
        this.messagesContainer.scrollTop = this.messagesContainer.scrollHeight;
    }

    /**
     * Scroll pour montrer le message utilisateur en haut de la vue
     */
    scrollToUserMessage() {
        if (this.lastUserMessageEl) {
            this.lastUserMessageEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            this.scrollToBottom();
        }
    }

    /**
     * Formate le message (markdown basique)
     */
    formatMessage(content) {
        // Traiter les demandes de rendez-vous [BOOKING_REQUEST]...[/BOOKING_REQUEST]
        const bookingMatch = content.match(/\[BOOKING_REQUEST\]([\s\S]*?)\[\/BOOKING_REQUEST\]/);
        if (bookingMatch) {
            try {
                const booking = JSON.parse(bookingMatch[1]);
                const bookingHtml = `
                    <div class="chatbot-booking-card">
                        <div class="chatbot-booking-header">📅 Rendez-vous confirmé</div>
                        <div class="chatbot-booking-details">
                            <div><strong>Nom :</strong> ${this.escapeHtml(booking.name || '')}</div>
                            <div><strong>Date :</strong> ${this.escapeHtml(booking.date || '')} à ${this.escapeHtml(booking.time || '')}</div>
                            ${booking.service ? `<div><strong>Service :</strong> ${this.escapeHtml(booking.service)}</div>` : ''}
                            ${booking.email ? `<div><strong>Email :</strong> ${this.escapeHtml(booking.email)}</div>` : ''}
                            ${booking.phone ? `<div><strong>Tél :</strong> ${this.escapeHtml(booking.phone)}</div>` : ''}
                        </div>
                        <div class="chatbot-booking-footer">Vous recevrez une confirmation par email.</div>
                    </div>
                `;
                // Remplacer le bloc technique par la carte formatée
                content = content.replace(/\[BOOKING_REQUEST\][\s\S]*?\[\/BOOKING_REQUEST\]/, '');
                // Traiter le reste du message normalement
                let formatted = this.escapeHtml(content.trim());
                formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
                formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');
                formatted = formatted.replace(/\n/g, '<br>');
                return (formatted ? formatted + '<br><br>' : '') + bookingHtml;
            } catch (e) {
                // Si erreur de parsing, continuer normalement
            }
        }

        // Échapper le HTML d'abord
        let formatted = this.escapeHtml(content);

        // Gras **texte**
        formatted = formatted.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

        // Italique *texte*
        formatted = formatted.replace(/\*(.*?)\*/g, '<em>$1</em>');

        // Sauts de ligne
        formatted = formatted.replace(/\n/g, '<br>');

        return formatted;
    }

    /**
     * Échappe les caractères HTML
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Appel à l'API
     */
    async apiCall(action, data = {}) {
        const response = await fetch(this.options.apiUrl, {
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

    /**
     * Réinitialise la conversation
     */
    async reset() {
        if (this.sessionId) {
            await this.apiCall('clear', { session_id: this.sessionId });
        }

        localStorage.removeItem('chatbot_session');
        localStorage.removeItem('chatbot_session_date');
        this.sessionId = null;
        this.messagesContainer.innerHTML = '';

        await this.initSession();
    }

    /**
     * Détruit le widget
     */
    destroy() {
        if (this.container) {
            this.container.remove();
        }
    }
}

// Export pour utilisation en module
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChatbotWidget;
}

// Disponible globalement
window.ChatbotWidget = ChatbotWidget;
