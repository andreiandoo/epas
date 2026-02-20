/**
 * AmBilet AI Chat Widget
 * Floating chat bubble with AI-powered customer support
 */
const AmbiletChatWidget = (function () {
    'use strict';

    let config = {
        apiUrl: '/api/proxy.php',
        enabled: true,
        welcomeMessage: 'Bună! Sunt asistentul virtual. Cu ce te pot ajuta?',
        placeholder: 'Scrie un mesaj...',
        title: 'Asistent Virtual',
        suggestions: [
            { label: 'Comenzile mele', message: 'Vreau să văd statusul comenzilor mele' },
            { label: 'Biletele mele', message: 'Ce bilete am pentru evenimente viitoare?' },
            { label: 'Rambursare', message: 'Cum pot solicita o rambursare?' },
            { label: 'Caută eveniment', message: 'Ce evenimente sunt disponibile?' },
        ],
    };

    let state = {
        isOpen: false,
        isLoading: false,
        sessionId: null,
        conversationId: null,
        messages: [],
        initialized: false,
    };

    // DOM elements
    let els = {};

    /**
     * Initialize the chat widget
     */
    function init(userConfig = {}) {
        if (state.initialized) return;

        Object.assign(config, userConfig);

        // Generate or restore session ID
        state.sessionId = localStorage.getItem('ambilet_chat_session') || generateId();
        localStorage.setItem('ambilet_chat_session', state.sessionId);

        createDOM();
        attachEvents();
        loadConversation();

        state.initialized = true;
    }

    /**
     * Create all DOM elements
     */
    function createDOM() {
        // Container
        const container = document.createElement('div');
        container.id = 'ambilet-chat';

        // Chat bubble button
        container.innerHTML = `
            <button class="chat-bubble-btn" id="chatBubbleBtn" aria-label="Deschide chat">
                <svg class="chat-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <svg class="chat-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <div class="chat-window" id="chatWindow">
                <div class="chat-header">
                    <div class="chat-header-title">
                        <span class="status-dot"></span>
                        ${escapeHtml(config.title)}
                    </div>
                    <div class="chat-header-actions">
                        <button id="chatNewBtn" title="Conversație nouă">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 5v14M5 12h14"/>
                            </svg>
                        </button>
                        <button id="chatCloseBtn" title="Închide">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <div class="chat-welcome">
                        <h3>${escapeHtml(config.title)}</h3>
                        <p>${escapeHtml(config.welcomeMessage)}</p>
                    </div>
                </div>
                <div class="chat-typing" id="chatTyping">
                    <span class="chat-typing-dot"></span>
                    <span class="chat-typing-dot"></span>
                    <span class="chat-typing-dot"></span>
                </div>
                <div class="chat-suggestions" id="chatSuggestions"></div>
                <div class="chat-input-area">
                    <textarea class="chat-input" id="chatInput" placeholder="${escapeHtml(config.placeholder)}" rows="1" maxlength="1000"></textarea>
                    <button class="chat-send-btn" id="chatSendBtn" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
                <div class="chat-powered">Powered by AI</div>
            </div>
        `;

        document.body.appendChild(container);

        // Cache DOM refs
        els.bubble = document.getElementById('chatBubbleBtn');
        els.window = document.getElementById('chatWindow');
        els.messages = document.getElementById('chatMessages');
        els.typing = document.getElementById('chatTyping');
        els.suggestions = document.getElementById('chatSuggestions');
        els.input = document.getElementById('chatInput');
        els.sendBtn = document.getElementById('chatSendBtn');
        els.closeBtn = document.getElementById('chatCloseBtn');
        els.newBtn = document.getElementById('chatNewBtn');

        renderSuggestions();
    }

    /**
     * Attach event listeners
     */
    function attachEvents() {
        els.bubble.addEventListener('click', toggle);
        els.closeBtn.addEventListener('click', close);
        els.newBtn.addEventListener('click', newConversation);
        els.sendBtn.addEventListener('click', sendMessage);

        els.input.addEventListener('input', function () {
            els.sendBtn.disabled = !this.value.trim();
            autoResize(this);
        });

        els.input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim()) sendMessage();
            }
        });

        // Keyboard: Escape to close
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && state.isOpen) close();
        });

        // Listen for auth events
        document.addEventListener('ambilet:auth:login', loadConversation);
        document.addEventListener('ambilet:auth:logout', function () {
            state.messages = [];
            state.conversationId = null;
            if (els.messages) {
                els.messages.innerHTML = `
                    <div class="chat-welcome">
                        <h3>${escapeHtml(config.title)}</h3>
                        <p>${escapeHtml(config.welcomeMessage)}</p>
                    </div>
                `;
            }
            renderSuggestions();
        });
    }

    /**
     * Toggle chat window
     */
    function toggle() {
        state.isOpen ? close() : open();
    }

    function open() {
        state.isOpen = true;
        els.bubble.classList.add('is-open');
        els.window.classList.add('is-visible');
        els.input.focus();
        scrollToBottom();
    }

    function close() {
        state.isOpen = false;
        els.bubble.classList.remove('is-open');
        els.window.classList.remove('is-visible');
    }

    /**
     * Load existing conversation
     */
    async function loadConversation() {
        try {
            const params = new URLSearchParams({
                action: 'chat.conversation',
                session_id: state.sessionId,
            });

            const response = await apiGet(`?${params}`);

            if (response.success && response.data) {
                const { conversation, messages } = response.data;

                if (conversation) {
                    state.conversationId = conversation.id;
                }

                if (messages && messages.length > 0) {
                    state.messages = messages;
                    renderMessages();
                    hideSuggestions();
                }
            }
        } catch (e) {
            // Silent fail - will start fresh conversation
        }
    }

    /**
     * Send a message
     */
    async function sendMessage() {
        const text = els.input.value.trim();
        if (!text || state.isLoading) return;

        // Add user message to UI
        addMessage('user', text);
        els.input.value = '';
        els.sendBtn.disabled = true;
        autoResize(els.input);
        hideSuggestions();

        // Show typing
        state.isLoading = true;
        showTyping();

        try {
            const response = await apiPost('?action=chat.send', {
                message: text,
                session_id: state.sessionId,
                page_url: window.location.href,
            });

            hideTyping();
            state.isLoading = false;

            if (response.success && response.data) {
                state.conversationId = response.data.conversation_id;
                state.sessionId = response.data.session_id || state.sessionId;
                localStorage.setItem('ambilet_chat_session', state.sessionId);

                const msg = response.data.message;
                addMessage('assistant', msg.content, msg.id);
            } else {
                showError(response.message || 'Eroare la trimiterea mesajului');
            }
        } catch (e) {
            hideTyping();
            state.isLoading = false;
            showError('Nu s-a putut contacta serverul. Încearcă din nou.');
        }
    }

    /**
     * Start a new conversation
     */
    async function newConversation() {
        try {
            await apiPost('?action=chat.new', {
                session_id: state.sessionId,
            });
        } catch (e) {
            // Continue anyway
        }

        state.messages = [];
        state.conversationId = null;
        els.messages.innerHTML = `
            <div class="chat-welcome">
                <h3>${escapeHtml(config.title)}</h3>
                <p>${escapeHtml(config.welcomeMessage)}</p>
            </div>
        `;
        renderSuggestions();
    }

    /**
     * Add message to UI
     */
    function addMessage(role, content, messageId = null) {
        const msg = { role, content, id: messageId };
        state.messages.push(msg);

        const el = createMessageElement(msg);
        els.messages.appendChild(el);
        scrollToBottom();
    }

    /**
     * Create message DOM element
     */
    function createMessageElement(msg) {
        const div = document.createElement('div');
        div.className = `chat-msg chat-msg-${msg.role}`;

        const bubble = document.createElement('div');
        bubble.className = 'chat-msg-bubble';
        bubble.innerHTML = formatContent(msg.content);
        div.appendChild(bubble);

        // Add rating buttons for assistant messages
        if (msg.role === 'assistant' && msg.id) {
            const rating = document.createElement('div');
            rating.className = 'chat-msg-rating';
            rating.innerHTML = `
                <button data-rating="1" data-msg-id="${msg.id}" title="Util">&#128077;</button>
                <button data-rating="-1" data-msg-id="${msg.id}" title="Nu e util">&#128078;</button>
            `;

            rating.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', function () {
                    rateMessage(this.dataset.msgId, parseInt(this.dataset.rating));
                    rating.querySelectorAll('button').forEach(b => b.classList.remove('is-active'));
                    this.classList.add('is-active');
                });
            });

            div.appendChild(rating);
        }

        return div;
    }

    /**
     * Render all messages
     */
    function renderMessages() {
        // Clear welcome but keep it if no messages
        els.messages.innerHTML = '';

        state.messages.forEach(msg => {
            const el = createMessageElement(msg);
            els.messages.appendChild(el);
        });

        scrollToBottom();
    }

    /**
     * Render suggestion buttons
     */
    function renderSuggestions() {
        els.suggestions.innerHTML = '';
        config.suggestions.forEach(s => {
            const btn = document.createElement('button');
            btn.className = 'chat-suggestion-btn';
            btn.textContent = s.label;
            btn.addEventListener('click', () => {
                els.input.value = s.message;
                els.sendBtn.disabled = false;
                sendMessage();
            });
            els.suggestions.appendChild(btn);
        });
        els.suggestions.style.display = 'flex';
    }

    function hideSuggestions() {
        els.suggestions.style.display = 'none';
    }

    /**
     * Rate a message
     */
    async function rateMessage(messageId, rating) {
        try {
            await apiPost(`?action=chat.rate&message_id=${messageId}`, { rating });
        } catch (e) {
            // Silent fail
        }
    }

    /**
     * Show/hide typing indicator
     */
    function showTyping() {
        els.typing.classList.add('is-visible');
        scrollToBottom();
    }

    function hideTyping() {
        els.typing.classList.remove('is-visible');
    }

    /**
     * Show error message
     */
    function showError(text) {
        const div = document.createElement('div');
        div.className = 'chat-error';
        div.textContent = text;
        els.messages.appendChild(div);
        scrollToBottom();

        setTimeout(() => div.remove(), 5000);
    }

    /**
     * Format message content (basic markdown)
     */
    function formatContent(text) {
        if (!text) return '';

        return escapeHtml(text)
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n- (.*?)(?=\n|$)/g, '<li>$1</li>')
            .replace(/(<li>.*?<\/li>)/gs, '<ul>$1</ul>')
            .replace(/<\/ul>\s*<ul>/g, '')
            .replace(/\n\d+\. (.*?)(?=\n|$)/g, '<li>$1</li>')
            .replace(/\n/g, '<br>');
    }

    /**
     * Scroll messages to bottom
     */
    function scrollToBottom() {
        requestAnimationFrame(() => {
            els.messages.scrollTop = els.messages.scrollHeight;
        });
    }

    /**
     * Auto-resize textarea
     */
    function autoResize(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight, 100) + 'px';
    }

    /**
     * Escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Generate unique ID
     */
    function generateId() {
        return 'cs_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 9);
    }

    /**
     * API helpers using the existing proxy
     */
    async function apiGet(url) {
        const headers = { 'Content-Type': 'application/json' };

        // Add auth token if available
        if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isLoggedIn()) {
            const token = AmbiletAuth.getToken();
            if (token) headers['Authorization'] = 'Bearer ' + token;
        }

        const resp = await fetch(config.apiUrl + url, { headers });
        return resp.json();
    }

    async function apiPost(url, data = {}) {
        const headers = { 'Content-Type': 'application/json' };

        if (typeof AmbiletAuth !== 'undefined' && AmbiletAuth.isLoggedIn()) {
            const token = AmbiletAuth.getToken();
            if (token) headers['Authorization'] = 'Bearer ' + token;
        }

        const resp = await fetch(config.apiUrl + url, {
            method: 'POST',
            headers,
            body: JSON.stringify(data),
        });
        return resp.json();
    }

    // Public API
    return { init, open, close, toggle };
})();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        AmbiletChatWidget.init();
    });
} else {
    AmbiletChatWidget.init();
}
