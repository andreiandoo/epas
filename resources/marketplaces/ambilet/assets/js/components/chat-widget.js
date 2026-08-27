/**
 * AmBilet Live Chat widget (live-chat microservice).
 *
 * Self-contained floating widget. Renders only when the microservice is active
 * for the marketplace (bootstrap.active). Works for logged-in customers /
 * organizers (identity derived server-side from the Sanctum bearer forwarded by
 * the proxy) and for anonymous guests (pre-chat name + email). Ownership across
 * polling is proven by a per-conversation session_token kept in localStorage.
 *
 * Transport: polling (F1). Anti-bot: hidden honeypot field + time-trap elapsed
 * timer sent on open.
 *
 * Depends on: window.AMBILET (apiUrl), AmbiletAuth (optional, for bearer + type).
 */
(function () {
    'use strict';

    var PROXY = (window.AMBILET && window.AMBILET.apiUrl) || '/api/proxy.php';
    var LS_REF = 'ambilet_chat_ref';
    var LS_TOKEN = 'ambilet_chat_token';

    var cfg = null;          // bootstrap config
    var ref = null;          // conversation reference
    var sessionToken = null; // ownership token
    var lastMessageId = 0;
    var pollTimer = null;
    var openedAt = 0;
    var panelOpen = false;
    var els = {};

    function token() {
        try { return (window.AmbiletAuth && AmbiletAuth.getToken) ? AmbiletAuth.getToken() : null; }
        catch (e) { return null; }
    }
    function isLogged() {
        try { return !!(window.AmbiletAuth && AmbiletAuth.isLoggedIn && AmbiletAuth.isLoggedIn()); }
        catch (e) { return false; }
    }
    function isOrganizer() {
        try { return !!(window.AmbiletAuth && AmbiletAuth.isOrganizer && AmbiletAuth.isOrganizer()); }
        catch (e) { return false; }
    }

    function api(action, opts) {
        opts = opts || {};
        var method = opts.method || 'GET';
        var params = opts.params || {};
        var qs = '?action=' + encodeURIComponent(action);
        Object.keys(params).forEach(function (k) {
            if (params[k] !== undefined && params[k] !== null) qs += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
        });
        var headers = { 'Accept': 'application/json' };
        if (method !== 'GET') headers['Content-Type'] = 'application/json';
        var t = token();
        if (t) headers['Authorization'] = 'Bearer ' + t;
        return fetch(PROXY + qs, {
            method: method,
            headers: headers,
            body: opts.body ? JSON.stringify(opts.body) : undefined
        }).then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    function pageContext() {
        var ctx = { url: location.href, title: document.title };
        // Best-effort event id from a meta tag or data attribute.
        var m = document.querySelector('meta[name="ambilet:event-id"]') ||
                document.querySelector('[data-event-id]');
        if (m) {
            var eid = m.getAttribute('content') || m.getAttribute('data-event-id');
            if (eid && /^\d+$/.test(eid)) ctx.event_id = parseInt(eid, 10);
        }
        return ctx;
    }

    // ---------- Rendering ----------

    function injectStyles() {
        if (document.getElementById('amb-chat-styles')) return;
        var css = ''
        + '.amb-chat-bubble{position:fixed;bottom:20px;right:20px;z-index:99998;width:56px;height:56px;border-radius:50%;background:#e11d48;color:#fff;border:none;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transition:transform .15s}'
        + '.amb-chat-bubble:hover{transform:scale(1.05)}'
        + '.amb-chat-bubble svg{width:26px;height:26px}'
        + '.amb-chat-badge{position:absolute;top:-4px;right:-4px;background:#111;color:#fff;font-size:11px;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px}'
        + '.amb-chat-panel{position:fixed;bottom:20px;right:20px;z-index:99999;width:360px;max-width:calc(100vw - 32px);height:520px;max-height:calc(100vh - 40px);background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.28);display:none;flex-direction:column;overflow:hidden;font-family:inherit}'
        + '.amb-chat-panel.open{display:flex}'
        + '.amb-chat-head{background:#e11d48;color:#fff;padding:14px 16px;display:flex;align-items:center;justify-content:between;gap:8px}'
        + '.amb-chat-head h4{margin:0;font-size:15px;font-weight:600;flex:1}'
        + '.amb-chat-head .amb-x{background:none;border:none;color:#fff;cursor:pointer;font-size:20px;line-height:1;opacity:.9}'
        + '.amb-chat-state{font-size:11px;opacity:.9;margin-top:2px}'
        + '.amb-chat-body{flex:1;overflow-y:auto;padding:14px;background:#f8fafc;display:flex;flex-direction:column;gap:8px}'
        + '.amb-msg{max-width:82%;padding:8px 11px;border-radius:12px;font-size:14px;line-height:1.35;white-space:pre-wrap;word-break:break-word}'
        + '.amb-msg.me{align-self:flex-end;background:#e11d48;color:#fff;border-bottom-right-radius:4px}'
        + '.amb-msg.operator{align-self:flex-start;background:#fff;border:1px solid #e5e7eb;color:#111;border-bottom-left-radius:4px}'
        + '.amb-msg.system{align-self:center;background:transparent;color:#94a3b8;font-size:12px}'
        + '.amb-chat-foot{border-top:1px solid #e5e7eb;padding:10px;background:#fff}'
        + '.amb-chat-foot textarea,.amb-chat-foot input{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:9px;padding:8px 10px;font-size:14px;font-family:inherit;margin-bottom:6px}'
        + '.amb-chat-foot textarea{resize:none}'
        + '.amb-chat-send{width:100%;background:#e11d48;color:#fff;border:none;border-radius:9px;padding:9px;font-size:14px;font-weight:600;cursor:pointer}'
        + '.amb-chat-send:disabled{opacity:.5;cursor:default}'
        + '.amb-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;opacity:0}'
        + '.amb-org-badge{display:inline-block;font-size:9px;font-weight:700;background:rgba(255,255,255,.25);padding:1px 5px;border-radius:4px;margin-left:6px;vertical-align:middle}';
        var s = document.createElement('style');
        s.id = 'amb-chat-styles';
        s.textContent = css;
        document.head.appendChild(s);
    }

    function buildUI() {
        var bubble = document.createElement('button');
        bubble.className = 'amb-chat-bubble';
        bubble.setAttribute('aria-label', 'Deschide chat');
        bubble.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>';
        bubble.addEventListener('click', togglePanel);
        document.body.appendChild(bubble);
        els.bubble = bubble;

        var panel = document.createElement('div');
        panel.className = 'amb-chat-panel';
        panel.innerHTML =
            '<div class="amb-chat-head">'
          + '  <div style="flex:1">'
          + '    <h4>Chat AmBilet' + (isOrganizer() ? '<span class="amb-org-badge">ORGANIZATOR</span>' : '') + '</h4>'
          + '    <div class="amb-chat-state" data-state></div>'
          + '  </div>'
          + '  <button class="amb-x" aria-label="Închide">&times;</button>'
          + '</div>'
          + '<div class="amb-chat-body" data-body></div>'
          + '<div class="amb-chat-foot" data-foot></div>';
        document.body.appendChild(panel);
        panel.querySelector('.amb-x').addEventListener('click', togglePanel);
        els.panel = panel;
        els.state = panel.querySelector('[data-state]');
        els.body = panel.querySelector('[data-body]');
        els.foot = panel.querySelector('[data-foot]');
    }

    function setState(text) { if (els.state) els.state.textContent = text || ''; }

    function renderMessage(m) {
        var div = document.createElement('div');
        var cls = m.from === 'operator' ? 'operator' : (m.from === 'system' ? 'system' : 'me');
        div.className = 'amb-msg ' + cls;
        div.textContent = m.body || '';
        els.body.appendChild(div);
        if (m.id && m.id > lastMessageId) lastMessageId = m.id;
        els.body.scrollTop = els.body.scrollHeight;
    }

    function renderComposer(mode) {
        // mode: 'prechat' (guest) | 'chat' | 'offline'
        var foot = els.foot;
        foot.innerHTML = '';
        var honeypot = (cfg && cfg.honeypot_field) || 'company_website';

        if (mode === 'prechat' || mode === 'offline') {
            var name = document.createElement('input');
            name.type = 'text'; name.placeholder = 'Numele tău'; name.setAttribute('data-name', '');
            var email = document.createElement('input');
            email.type = 'email'; email.placeholder = 'Emailul tău'; email.setAttribute('data-email', '');
            foot.appendChild(name); foot.appendChild(email);
        }

        var ta = document.createElement('textarea');
        ta.rows = 2;
        ta.placeholder = mode === 'offline' ? 'Scrie mesajul tău...' : 'Scrie un mesaj...';
        ta.setAttribute('data-input', '');
        foot.appendChild(ta);

        // Honeypot (hidden).
        var hp = document.createElement('input');
        hp.className = 'amb-hp'; hp.type = 'text'; hp.name = honeypot;
        hp.setAttribute('tabindex', '-1'); hp.setAttribute('autocomplete', 'off'); hp.setAttribute('data-hp', '');
        foot.appendChild(hp);

        var btn = document.createElement('button');
        btn.className = 'amb-chat-send';
        btn.textContent = mode === 'offline' ? 'Trimite mesajul' : (mode === 'prechat' ? 'Începe conversația' : 'Trimite');
        btn.addEventListener('click', function () { submit(mode, foot, btn); });
        foot.appendChild(btn);

        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); submit(mode, foot, btn); }
        });
    }

    function submit(mode, foot, btn) {
        var ta = foot.querySelector('[data-input]');
        var body = (ta && ta.value || '').trim();
        if (!body) return;
        var hp = foot.querySelector('[data-hp]');

        if (!ref) {
            // Opening a new conversation.
            var payload = {
                message: body,
                context: pageContext(),
                elapsed_seconds: Math.round((Date.now() - openedAt) / 1000)
            };
            if (payload.context && payload.context.event_id) payload.event_id = payload.context.event_id;
            if (hp) payload[hp.name] = hp.value;
            if (mode === 'prechat' || mode === 'offline') {
                var nameEl = foot.querySelector('[data-name]');
                var emailEl = foot.querySelector('[data-email]');
                payload.guest_name = nameEl ? nameEl.value.trim() : '';
                payload.guest_email = emailEl ? emailEl.value.trim() : '';
                if (!payload.guest_name || !payload.guest_email) { alert('Completează numele și emailul.'); return; }
            }
            btn.disabled = true;
            api('chat.open', { method: 'POST', body: payload }).then(function (res) {
                btn.disabled = false;
                if (!res || !res.success) { alert((res && res.message) || 'Eroare la pornirea conversației.'); return; }
                var d = res.data;
                ref = d.reference; sessionToken = d.session_token;
                try { localStorage.setItem(LS_REF, ref); localStorage.setItem(LS_TOKEN, sessionToken); } catch (e) {}
                els.body.innerHTML = '';
                (d.messages || []).forEach(renderMessage);
                applyStatus(d.status, d.queue_position);
                renderComposer(d.status === 'offline_message' ? 'offline_done' : 'chat');
                startPolling();
            });
            return;
        }

        // Existing conversation → post a message.
        btn.disabled = true;
        api('chat.message', {
            method: 'POST', params: { ref: ref },
            body: { message: body, session_token: sessionToken }
        }).then(function (res) {
            btn.disabled = false;
            if (!res || !res.success) { alert((res && res.message) || 'Mesajul nu a putut fi trimis.'); return; }
            if (ta) ta.value = '';
            if (res.data && res.data.message) renderMessage(res.data.message);
        });
    }

    function applyStatus(status, queuePosition) {
        if (status === 'offline_message') setState('Suntem offline — îți răspundem pe email.');
        else if (status === 'queued') setState('În așteptare' + (queuePosition ? ' · poziția ' + queuePosition : '') + '...');
        else if (status === 'active') setState('Conectat cu un operator');
        else if (status === 'resolved' || status === 'closed') setState('Conversație încheiată');
        else setState('');
    }

    function poll() {
        if (!ref || !sessionToken) return;
        api('chat.show', { params: { ref: ref, session_token: sessionToken, after: lastMessageId } }).then(function (res) {
            if (!res || !res.success) return;
            var d = res.data;
            (d.messages || []).forEach(renderMessage);
            applyStatus(d.status, d.queue_position);
            if (d.status === 'resolved' || d.status === 'closed') {
                stopPolling();
                offerRating();
            }
        });
    }

    function startPolling() {
        stopPolling();
        var interval = (cfg && cfg.poll_interval_ms) || 4000;
        pollTimer = setInterval(poll, interval);
    }
    function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

    function offerRating() {
        if (els.foot.querySelector('[data-rating]')) return;
        var wrap = document.createElement('div');
        wrap.setAttribute('data-rating', '');
        wrap.style.textAlign = 'center';
        wrap.innerHTML = '<div style="font-size:13px;color:#475569;margin-bottom:6px">Cum a fost conversația?</div>';
        for (var i = 1; i <= 5; i++) {
            (function (score) {
                var star = document.createElement('button');
                star.textContent = '★';
                star.style.cssText = 'background:none;border:none;font-size:22px;color:#f59e0b;cursor:pointer';
                star.addEventListener('click', function () {
                    api('chat.rating', { method: 'POST', params: { ref: ref }, body: { rating: score, session_token: sessionToken } })
                        .then(function () { wrap.innerHTML = '<div style="font-size:13px;color:#16a34a">Mulțumim pentru feedback!</div>'; });
                });
                wrap.appendChild(star);
            })(i);
        }
        els.foot.innerHTML = '';
        els.foot.appendChild(wrap);
    }

    // ---------- Resume / open ----------

    function resumeOrCompose() {
        try { ref = localStorage.getItem(LS_REF); sessionToken = localStorage.getItem(LS_TOKEN); } catch (e) {}
        if (ref && sessionToken) {
            els.body.innerHTML = '';
            lastMessageId = 0;
            api('chat.show', { params: { ref: ref, session_token: sessionToken, after: 0 } }).then(function (res) {
                if (!res || !res.success) { clearConversation(); freshCompose(); return; }
                var d = res.data;
                (d.messages || []).forEach(renderMessage);
                applyStatus(d.status, d.queue_position);
                if (d.status === 'resolved' || d.status === 'closed') { offerRating(); }
                else { renderComposer('chat'); startPolling(); }
            });
        } else {
            freshCompose();
        }
    }

    function clearConversation() {
        ref = null; sessionToken = null; lastMessageId = 0;
        try { localStorage.removeItem(LS_REF); localStorage.removeItem(LS_TOKEN); } catch (e) {}
    }

    function freshCompose() {
        els.body.innerHTML = '';
        var greeting = document.createElement('div');
        greeting.className = 'amb-msg operator';
        greeting.textContent = (cfg && cfg.availability === 'offline')
            ? (cfg.offline_message || 'Suntem offline. Lasă-ne un mesaj.')
            : (cfg && cfg.greeting) || 'Bună! Cu ce te putem ajuta?';
        els.body.appendChild(greeting);
        applyStatus(cfg && cfg.availability === 'offline' ? 'offline_message' : (cfg && cfg.availability === 'queue' ? 'queued' : ''), null);

        var mode;
        if (cfg && cfg.availability === 'offline') mode = 'offline';
        else if (isLogged()) mode = 'chat';
        else mode = 'prechat';
        renderComposer(mode);
    }

    function togglePanel() {
        panelOpen = !panelOpen;
        els.panel.classList.toggle('open', panelOpen);
        if (panelOpen) {
            openedAt = Date.now();
            resumeOrCompose();
        } else {
            stopPolling();
        }
    }

    // ---------- Boot ----------

    function boot() {
        api('chat.bootstrap').then(function (res) {
            if (!res || !res.success || !res.data || !res.data.active) return; // inactive → no widget
            cfg = res.data;
            injectStyles();
            buildUI();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
