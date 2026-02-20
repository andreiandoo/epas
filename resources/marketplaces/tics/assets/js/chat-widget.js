/**
 * TICS AI Chat Widget
 * Adapted from AmBilet widget for TICS marketplace
 */
const TicsChatWidget = (function () {
    'use strict';

    let config = {
        apiUrl: '/api/proxy.php',
        enabled: true,
        welcomeMessage: 'Buna! Sunt asistentul virtual TICS. Cu ce te pot ajuta?',
        placeholder: 'Scrie un mesaj...',
        title: 'Asistent TICS',
        suggestions: [
            { label: 'Cauta eveniment', message: 'Ce evenimente sunt disponibile?' },
            { label: 'Rambursare', message: 'Cum pot solicita o rambursare?' },
            { label: 'Ajutor', message: 'Am nevoie de ajutor cu o comanda' },
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

    let els = {};

    function init(userConfig = {}) {
        if (state.initialized) return;
        Object.assign(config, userConfig);

        state.sessionId = localStorage.getItem('tics_chat_session') || generateId();
        localStorage.setItem('tics_chat_session', state.sessionId);

        createDOM();
        attachEvents();
        loadConversation();
        state.initialized = true;
    }

    function createDOM() {
        const container = document.createElement('div');
        container.id = 'tics-chat';
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
                        ${esc(config.title)}
                    </div>
                    <div class="chat-header-actions">
                        <button id="chatNewBtn" title="Conversatie noua">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
                        </button>
                        <button id="chatCloseBtn" title="Inchide">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="chat-messages" id="chatMessages">
                    <div class="chat-welcome">
                        <h3>${esc(config.title)}</h3>
                        <p>${esc(config.welcomeMessage)}</p>
                    </div>
                </div>
                <div class="chat-typing" id="chatTyping">
                    <span class="chat-typing-dot"></span>
                    <span class="chat-typing-dot"></span>
                    <span class="chat-typing-dot"></span>
                </div>
                <div class="chat-suggestions" id="chatSuggestions"></div>
                <div class="chat-input-area">
                    <textarea class="chat-input" id="chatInput" placeholder="${esc(config.placeholder)}" rows="1" maxlength="1000"></textarea>
                    <button class="chat-send-btn" id="chatSendBtn" disabled>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
                <div class="chat-powered">Powered by AI</div>
            </div>
        `;
        document.body.appendChild(container);

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
    }

    function toggle() { state.isOpen ? close() : open(); }

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

    async function loadConversation() {
        try {
            const resp = await apiGet('chat.conversation', { session_id: state.sessionId });
            if (resp.success && resp.data) {
                if (resp.data.conversation) state.conversationId = resp.data.conversation.id;
                if (resp.data.messages && resp.data.messages.length > 0) {
                    state.messages = resp.data.messages;
                    renderMessages();
                    hideSuggestions();
                }
            }
        } catch (e) { /* silent */ }
    }

    async function sendMessage() {
        const text = els.input.value.trim();
        if (!text || state.isLoading) return;

        addMessage('user', text);
        els.input.value = '';
        els.sendBtn.disabled = true;
        autoResize(els.input);
        hideSuggestions();

        state.isLoading = true;
        showTyping();

        try {
            const resp = await apiPost('chat.send', {
                message: text,
                session_id: state.sessionId,
                page_url: window.location.href,
            });

            hideTyping();
            state.isLoading = false;

            if (resp.success && resp.data) {
                state.conversationId = resp.data.conversation_id;
                state.sessionId = resp.data.session_id || state.sessionId;
                localStorage.setItem('tics_chat_session', state.sessionId);
                addMessage('assistant', resp.data.message.content, resp.data.message.id);
            } else {
                showError(resp.message || 'Eroare la trimiterea mesajului');
            }
        } catch (e) {
            hideTyping();
            state.isLoading = false;
            showError('Nu s-a putut contacta serverul.');
        }
    }

    async function newConversation() {
        try { await apiPost('chat.new', { session_id: state.sessionId }); } catch (e) { }
        state.messages = [];
        state.conversationId = null;
        els.messages.innerHTML = `<div class="chat-welcome"><h3>${esc(config.title)}</h3><p>${esc(config.welcomeMessage)}</p></div>`;
        renderSuggestions();
    }

    function addMessage(role, content, messageId = null) {
        state.messages.push({ role, content, id: messageId });
        els.messages.appendChild(createMsgEl({ role, content, id: messageId }));
        scrollToBottom();
    }

    function createMsgEl(msg) {
        const div = document.createElement('div');
        div.className = `chat-msg chat-msg-${msg.role}`;
        const bubble = document.createElement('div');
        bubble.className = 'chat-msg-bubble';
        bubble.innerHTML = formatContent(msg.content);
        div.appendChild(bubble);

        if (msg.role === 'assistant' && msg.id) {
            const rating = document.createElement('div');
            rating.className = 'chat-msg-rating';
            rating.innerHTML = `<button data-r="1" data-id="${msg.id}">&#128077;</button><button data-r="-1" data-id="${msg.id}">&#128078;</button>`;
            rating.querySelectorAll('button').forEach(btn => {
                btn.addEventListener('click', function () {
                    rateMessage(this.dataset.id, parseInt(this.dataset.r));
                    rating.querySelectorAll('button').forEach(b => b.classList.remove('is-active'));
                    this.classList.add('is-active');
                });
            });
            div.appendChild(rating);
        }
        return div;
    }

    function renderMessages() {
        els.messages.innerHTML = '';
        state.messages.forEach(msg => els.messages.appendChild(createMsgEl(msg)));
        scrollToBottom();
    }

    function renderSuggestions() {
        els.suggestions.innerHTML = '';
        config.suggestions.forEach(s => {
            const btn = document.createElement('button');
            btn.className = 'chat-suggestion-btn';
            btn.textContent = s.label;
            btn.addEventListener('click', () => { els.input.value = s.message; els.sendBtn.disabled = false; sendMessage(); });
            els.suggestions.appendChild(btn);
        });
        els.suggestions.style.display = 'flex';
    }

    function hideSuggestions() { els.suggestions.style.display = 'none'; }

    async function rateMessage(id, rating) {
        try { await apiPost('chat.rate', { rating }, { message_id: id }); } catch (e) { }
    }

    function showTyping() { els.typing.classList.add('is-visible'); scrollToBottom(); }
    function hideTyping() { els.typing.classList.remove('is-visible'); }

    function showError(text) {
        const div = document.createElement('div');
        div.className = 'chat-error';
        div.textContent = text;
        els.messages.appendChild(div);
        scrollToBottom();
        setTimeout(() => div.remove(), 5000);
    }

    function formatContent(text) {
        if (!text) return '';
        return esc(text)
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.*?)\*/g, '<em>$1</em>')
            .replace(/\n/g, '<br>');
    }

    function scrollToBottom() { requestAnimationFrame(() => { els.messages.scrollTop = els.messages.scrollHeight; }); }
    function autoResize(el) { el.style.height = 'auto'; el.style.height = Math.min(el.scrollHeight, 100) + 'px'; }
    function esc(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
    function generateId() { return 'cs_' + Date.now().toString(36) + Math.random().toString(36).substr(2, 9); }

    // TICS uses endpoint= pattern instead of action=
    async function apiGet(action, params = {}) {
        const qs = new URLSearchParams({ endpoint: action, ...params });
        const resp = await fetch(config.apiUrl + '?' + qs);
        return resp.json();
    }

    async function apiPost(action, data = {}, extraParams = {}) {
        const qs = new URLSearchParams({ endpoint: action, ...extraParams });
        const resp = await fetch(config.apiUrl + '?' + qs, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data),
        });
        return resp.json();
    }

    return { init, open, close, toggle };
})();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => TicsChatWidget.init());
} else {
    TicsChatWidget.init();
}
