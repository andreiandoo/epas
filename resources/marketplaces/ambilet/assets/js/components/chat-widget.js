/**
 * AmBilet Live Chat widget (live-chat microservice).
 *
 * Self-contained floating widget. Renders only when the microservice is active
 * (bootstrap.active). Entry flow:
 *   - logged-in visitor  -> straight to the message box (identity from the
 *     Sanctum bearer, so the operator sees their orders/tickets/history);
 *   - anonymous visitor  -> a choice screen: "log in" (redirect) or "continue
 *     without an account" -> name + email (validated) -> message box.
 * Ownership across polling is proven by a per-conversation session_token kept in
 * localStorage. Transport: polling. Anti-bot: hidden honeypot + time-trap.
 *
 * Depends on: window.AMBILET (apiUrl), AmbiletAuth (optional).
 */
(function () {
    'use strict';

    var PROXY = (window.AMBILET && window.AMBILET.apiUrl) || '/api/proxy.php';
    var LOGIN_URL = '/autentificare';
    var LS_REF = 'ambilet_chat_ref';
    var LS_TOKEN = 'ambilet_chat_token';

    var cfg = null;          // bootstrap config
    var ref = null;          // conversation reference
    var sessionToken = null; // ownership token
    var guestName = null;    // captured in pre-chat (anonymous)
    var guestEmail = null;
    var lastMessageId = 0;
    var pollTimer = null;
    var openedAt = 0;
    var panelOpen = false;
    var els = {};

    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // Idle auto-close: after IDLE_WARN_MS of no messages on an active chat, show a
    // visible countdown; when it reaches zero the conversation is closed.
    var IDLE_WARN_MS = 180000;   // 3 min quiet → start warning
    var AUTO_CLOSE_MS = 60000;   // then 60s countdown (4 min total inactivity)
    var lastActivityTs = 0;
    var idleTimer = null;
    var currentStatus = null;
    var currentOperator = null;
    var rated = false;

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
        try { ctx.screen = (window.screen && screen.width) ? (screen.width + 'x' + screen.height) : null; } catch (e) {}
        var m = document.querySelector('meta[name="ambilet:event-id"]') ||
                document.querySelector('[data-event-id]');
        if (m) {
            var eid = m.getAttribute('content') || m.getAttribute('data-event-id');
            if (eid && /^\d+$/.test(eid)) ctx.event_id = parseInt(eid, 10);
        }
        return ctx;
    }

    function fmtTime(iso) {
        if (!iso) return '';
        try {
            var d = new Date(iso);
            var h = ('0' + d.getHours()).slice(-2), m = ('0' + d.getMinutes()).slice(-2);
            return h + ':' + m;
        } catch (e) { return ''; }
    }
    function fmtDate(iso) {
        if (!iso) return '';
        try {
            var d = new Date(iso);
            return ('0' + d.getDate()).slice(-2) + '.' + ('0' + (d.getMonth() + 1)).slice(-2) + '.' + d.getFullYear() + ' ' + fmtTime(iso);
        } catch (e) { return ''; }
    }

    // ---------- Styles ----------

    function injectStyles() {
        if (document.getElementById('amb-chat-styles')) return;
        var css = ''
        + '.amb-chat-bubble{position:fixed;bottom:20px;right:20px;z-index:99998;width:56px;height:56px;border-radius:50%;background:#e11d48;color:#fff;border:none;cursor:pointer;box-shadow:0 6px 20px rgba(0,0,0,.25);display:flex;align-items:center;justify-content:center;transition:transform .15s}'
        + '.amb-chat-bubble:hover{transform:scale(1.05)}'
        + '.amb-chat-bubble svg{width:26px;height:26px}'
        + '.amb-chat-panel{position:fixed;bottom:20px;right:20px;z-index:99999;width:370px;max-width:calc(100vw - 32px);height:540px;max-height:calc(100vh - 40px);background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(0,0,0,.28);display:none;flex-direction:column;overflow:hidden;font-family:inherit}'
        + '.amb-chat-panel.open{display:flex}'
        + '.amb-chat-head{background:#e11d48;color:#fff;padding:13px 16px;display:flex;align-items:center;gap:8px}'
        + '.amb-chat-head .amb-head-main{flex:1;min-width:0}'
        + '.amb-chat-head h4{margin:0;font-size:15px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
        + '.amb-chat-state{font-size:11px;opacity:.9;margin-top:2px}'
        + '.amb-chat-head .amb-x{background:none;border:none;color:#fff;cursor:pointer;font-size:22px;line-height:1;opacity:.9}'
        + '.amb-chat-body{flex:1;overflow-y:auto;padding:14px;background:#f8fafc;display:flex;flex-direction:column;gap:8px}'
        + '.amb-intro{font-size:14px;color:#334155;line-height:1.45}'
        + '.amb-benefit{display:flex;gap:8px;align-items:flex-start;font-size:13px;color:#475569;margin-top:8px}'
        + '.amb-benefit svg{width:16px;height:16px;color:#16a34a;flex-shrink:0;margin-top:1px}'
        + '.amb-msg{max-width:82%;padding:8px 11px;border-radius:12px;font-size:14px;line-height:1.35;white-space:pre-wrap;word-break:break-word}'
        + '.amb-msg.me{align-self:flex-end;background:#e11d48;color:#fff;border-bottom-right-radius:4px}'
        + '.amb-msg.operator{align-self:flex-start;background:#fff;border:1px solid #e5e7eb;color:#111;border-bottom-left-radius:4px}'
        + '.amb-msg.system{align-self:center;background:transparent;color:#94a3b8;font-size:12px;text-align:center}'
        + '.amb-msg-time{font-size:10px;opacity:.6;margin-top:3px;text-align:right}'
        + '.amb-op-name{font-size:11px;font-weight:600;color:#64748b;align-self:flex-start;margin:2px 0 -4px 4px}'
        + '.amb-chat-foot{border-top:1px solid #e5e7eb;padding:10px;background:#fff}'
        + '.amb-chat-foot textarea,.amb-chat-foot input{width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:9px;padding:9px 10px;font-size:14px;font-family:inherit;margin-bottom:6px}'
        + '.amb-chat-foot textarea{resize:none}'
        + '.amb-chat-foot input.amb-invalid{border-color:#dc2626}'
        + '.amb-btn{display:block;width:100%;box-sizing:border-box;border:none;border-radius:9px;padding:10px;font-size:14px;font-weight:600;cursor:pointer;text-align:center;text-decoration:none}'
        + '.amb-btn-primary{background:#e11d48;color:#fff}'
        + '.amb-btn-ghost{background:#f1f5f9;color:#0f172a;margin-top:8px}'
        + '.amb-btn:disabled{opacity:.5;cursor:default}'
        + '.amb-hp{position:absolute!important;left:-9999px!important;width:1px;height:1px;opacity:0}'
        + '.amb-org-badge{display:inline-block;font-size:9px;font-weight:700;background:rgba(255,255,255,.25);padding:1px 5px;border-radius:4px;margin-left:6px;vertical-align:middle}'
        + '.amb-field-err{font-size:11px;color:#fca5a5;margin:-2px 0 6px}'
        + '.amb-chat-notice{padding:8px 12px;background:#fff7ed;border-top:1px solid #fed7aa;color:#9a3412;font-size:12px;text-align:center}'
        + '.amb-rating{text-align:center;padding:4px}'
        + '.amb-rating-q{font-size:14px;font-weight:600;color:#0f172a;margin-bottom:2px}'
        + '.amb-rating-sub{font-size:12px;color:#64748b;margin-bottom:8px}'
        + '.amb-stars{display:flex;justify-content:center;gap:4px;margin-bottom:8px}'
        + '.amb-star{font-size:30px;line-height:1;cursor:pointer;color:#d1d5db;transition:color .1s,transform .1s;background:none;border:none;padding:0}'
        + '.amb-star:hover{transform:scale(1.12)}'
        + '.amb-star.on{color:#f59e0b}'
        + '.amb-rating-hint{font-size:11px;color:#94a3b8;min-height:14px}'
        + '.amb-rating-thanks{font-size:14px;color:#16a34a;font-weight:600;padding:10px}';
        var s = document.createElement('style');
        s.id = 'amb-chat-styles';
        s.textContent = css;
        document.head.appendChild(s);
    }

    // ---------- UI shell ----------

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
          + '  <div class="amb-head-main">'
          + '    <h4 data-title></h4>'
          + '    <div class="amb-chat-state" data-state></div>'
          + '  </div>'
          + '  <button class="amb-x" aria-label="Închide">&times;</button>'
          + '</div>'
          + '<div class="amb-chat-body" data-body></div>'
          + '<div class="amb-chat-notice" data-notice style="display:none"></div>'
          + '<div class="amb-chat-foot" data-foot></div>';
        document.body.appendChild(panel);
        panel.querySelector('.amb-x').addEventListener('click', togglePanel);
        els.panel = panel;
        els.title = panel.querySelector('[data-title]');
        els.state = panel.querySelector('[data-state]');
        els.body = panel.querySelector('[data-body]');
        els.notice = panel.querySelector('[data-notice]');
        els.foot = panel.querySelector('[data-foot]');
        setHeaderDefault();
    }

    function setHeaderDefault() {
        els.title.style.whiteSpace = '';
        var org = isOrganizer() ? '<span class="amb-org-badge">ORGANIZATOR</span>' : '';
        els.title.innerHTML = 'Chat AmBilet' + org;
    }
    function setHeaderConnected(operator) {
        els.title.style.whiteSpace = '';
        els.title.textContent = operator ? ('Conectat cu ' + operator) : 'Conectat cu un operator';
    }
    function setState(text) { if (els.state) els.state.textContent = text || ''; }

    function applyStatus(status, queuePosition, operator) {
        var wasActive = currentStatus === 'active';
        currentStatus = status;
        if (operator) currentOperator = operator;
        if (status === 'active') {
            setHeaderConnected(currentOperator);
            setState('');
            // Fresh idle window when the operator connects — do NOT count the
            // time the visitor spent waiting in the queue toward auto-close.
            if (!wasActive) lastActivityTs = Date.now();
            startIdleWatch();
        } else if (status === 'queued') {
            // Hide "Chat AmBilet" and show the searching message prominently.
            els.title.style.whiteSpace = 'normal';
            els.title.textContent = 'Caut un operator liber. Ești pe poziția ' + (queuePosition || 1) + ' în așteptare...';
            setState('');
            stopIdleWatch();
            hideNotice();
        } else {
            setHeaderDefault();
            stopIdleWatch();
            hideNotice();
            if (status === 'offline_message') setState('Suntem offline — îți răspundem pe email.');
            else if (status === 'resolved' || status === 'closed') setState('Conversație încheiată');
            else setState('');
        }
    }

    // ---------- Messages ----------

    function renderStartDivider(iso) {
        if (!iso) return;
        var d = document.createElement('div');
        d.className = 'amb-msg system';
        d.textContent = 'Conversație începută ' + fmtDate(iso);
        els.body.appendChild(d);
    }

    function renderMessage(m) {
        var cls = m.from === 'operator' ? 'operator' : (m.from === 'system' ? 'system' : 'me');
        var div = document.createElement('div');
        div.className = 'amb-msg ' + cls;
        if (cls === 'system') {
            div.textContent = (m.body || '') + (m.created_at ? ' · ' + fmtTime(m.created_at) : '');
        } else {
            var txt = document.createElement('div');
            txt.textContent = m.body || '';
            div.appendChild(txt);
            if (m.created_at) {
                var t = document.createElement('div');
                t.className = 'amb-msg-time';
                t.textContent = fmtTime(m.created_at);
                div.appendChild(t);
            }
        }
        if (cls === 'operator' && m.author) {
            var nm = document.createElement('div');
            nm.className = 'amb-op-name';
            nm.textContent = m.author;
            els.body.appendChild(nm);
        }
        els.body.appendChild(div);
        if (m.id && m.id > lastMessageId) lastMessageId = m.id;
        els.body.scrollTop = els.body.scrollHeight;
        bumpActivity();
    }

    // ---------- Idle auto-close ----------

    function bumpActivity() {
        lastActivityTs = Date.now();
        hideNotice();
    }
    function hideNotice() {
        if (els.notice) { els.notice.style.display = 'none'; els.notice.textContent = ''; }
    }
    function showNotice(text) {
        if (els.notice) { els.notice.textContent = text; els.notice.style.display = 'block'; }
    }
    function startIdleWatch() {
        stopIdleWatch();
        idleTimer = setInterval(function () {
            if (currentStatus !== 'active' || !ref) { hideNotice(); return; }
            var idle = Date.now() - lastActivityTs;
            if (idle < IDLE_WARN_MS) { hideNotice(); return; }
            var remaining = Math.ceil((AUTO_CLOSE_MS - (idle - IDLE_WARN_MS)) / 1000);
            if (remaining <= 0) {
                hideNotice();
                closeConversation();
                return;
            }
            var mm = Math.floor(remaining / 60), ss = remaining % 60;
            var t = (mm > 0 ? (mm + ':' + (ss < 10 ? '0' : '') + ss) : (ss + 's'));
            showNotice('Conversația se va închide automat în ' + t + ' dacă nu mai scrii nimic.');
        }, 1000);
    }
    function stopIdleWatch() { if (idleTimer) { clearInterval(idleTimer); idleTimer = null; } }

    function closeConversation() {
        stopIdleWatch();
        if (!ref) return;
        api('chat.close', { method: 'POST', params: { ref: ref }, body: { session_token: sessionToken } })
            .then(function () { poll(); });
    }

    // ---------- Entry screens ----------

    function greetingText() {
        return (cfg && cfg.availability === 'offline')
            ? (cfg.offline_message || 'Suntem offline. Lasă-ne un mesaj și revenim pe email.')
            : (cfg && cfg.greeting) || 'Bună! Cu ce te putem ajuta?';
    }

    function showGreeting() {
        els.body.innerHTML = '';
        var g = document.createElement('div');
        g.className = 'amb-msg operator';
        g.textContent = greetingText();
        els.body.appendChild(g);
    }

    // Screen 1 (anonymous): choose to log in or continue as guest.
    function renderChoice() {
        setHeaderDefault();
        applyStatus(cfg && cfg.availability === 'offline' ? 'offline_message' : (cfg && cfg.availability === 'queue' ? 'queued' : ''), null, null);

        els.body.innerHTML = '';
        var intro = document.createElement('div');
        intro.className = 'amb-intro';
        intro.innerHTML = greetingText()
            + '<div class="amb-benefit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>'
            + '<span>Autentifică-te ca să vedem rapid comenzile, biletele și istoricul tău.</span></div>';
        els.body.appendChild(intro);

        els.foot.innerHTML = '';
        var loginBtn = document.createElement('button');
        loginBtn.type = 'button';
        loginBtn.className = 'amb-btn amb-btn-primary';
        loginBtn.textContent = 'Autentifică-te';
        loginBtn.addEventListener('click', renderLogin);
        els.foot.appendChild(loginBtn);

        var guestBtn = document.createElement('button');
        guestBtn.type = 'button';
        guestBtn.className = 'amb-btn amb-btn-ghost';
        guestBtn.textContent = 'Continuă fără cont';
        guestBtn.addEventListener('click', renderPrechat);
        els.foot.appendChild(guestBtn);
    }

    // Screen 1b (anonymous): inline login — stays on the page, so after logging
    // in we drop straight back into the chat instead of navigating away.
    function renderLogin() {
        els.body.innerHTML = '';
        var intro = document.createElement('div');
        intro.className = 'amb-intro';
        intro.textContent = 'Autentifică-te ca să continuăm cu contul tău.';
        els.body.appendChild(intro);

        els.foot.innerHTML = '';
        var email = document.createElement('input');
        email.type = 'email'; email.placeholder = 'Email'; email.setAttribute('data-email', '');
        var pass = document.createElement('input');
        pass.type = 'password'; pass.placeholder = 'Parolă'; pass.setAttribute('data-pass', '');
        var err = document.createElement('div');
        err.className = 'amb-field-err'; err.style.display = 'none';

        var go = document.createElement('button');
        go.type = 'button';
        go.className = 'amb-btn amb-btn-primary';
        go.textContent = 'Autentifică-te';

        function doLogin() {
            var e = (email.value || '').trim();
            var p = pass.value || '';
            email.classList.remove('amb-invalid'); pass.classList.remove('amb-invalid'); err.style.display = 'none';
            if (!EMAIL_RE.test(e)) { email.classList.add('amb-invalid'); err.textContent = 'Email invalid.'; err.style.display = 'block'; return; }
            if (!p) { pass.classList.add('amb-invalid'); err.textContent = 'Introdu parola.'; err.style.display = 'block'; return; }
            if (!(window.AmbiletAuth && AmbiletAuth.login)) { err.textContent = 'Autentificarea nu e disponibilă aici.'; err.style.display = 'block'; return; }
            go.disabled = true; go.textContent = 'Se conectează...';
            Promise.resolve(AmbiletAuth.login(e, p, false)).then(function (res) {
                go.disabled = false; go.textContent = 'Autentifică-te';
                if (res && res.success) {
                    renderCompose(); // now isLogged() is true → identified chat, no page change
                } else {
                    err.textContent = (res && res.message) || 'Email sau parolă incorecte.'; err.style.display = 'block';
                }
            }).catch(function () {
                go.disabled = false; go.textContent = 'Autentifică-te';
                err.textContent = 'Eroare la conectare.'; err.style.display = 'block';
            });
        }
        go.addEventListener('click', doLogin);
        pass.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); doLogin(); } });

        var back = document.createElement('button');
        back.type = 'button';
        back.className = 'amb-btn amb-btn-ghost';
        back.textContent = 'Înapoi';
        back.addEventListener('click', renderChoice);

        els.foot.appendChild(email);
        els.foot.appendChild(pass);
        els.foot.appendChild(err);
        els.foot.appendChild(go);
        els.foot.appendChild(back);
    }

    // Screen 2 (anonymous): capture + validate name & email, then the message box.
    function renderPrechat() {
        els.body.innerHTML = '';
        var intro = document.createElement('div');
        intro.className = 'amb-intro';
        intro.textContent = 'Lasă-ne câteva date ca să putem continua conversația pe email dacă e nevoie și să-ți trimitem transcriptul conversației.';
        els.body.appendChild(intro);

        els.foot.innerHTML = '';
        var name = document.createElement('input');
        name.type = 'text'; name.placeholder = 'Numele tău'; name.setAttribute('data-name', '');
        var email = document.createElement('input');
        email.type = 'email'; email.placeholder = 'Emailul tău'; email.setAttribute('data-email', '');
        var err = document.createElement('div');
        err.className = 'amb-field-err'; err.style.display = 'none';

        var go = document.createElement('button');
        go.type = 'button';
        go.className = 'amb-btn amb-btn-primary';
        go.textContent = 'Continuă';
        go.addEventListener('click', function () {
            var n = (name.value || '').trim();
            var e = (email.value || '').trim();
            name.classList.remove('amb-invalid'); email.classList.remove('amb-invalid'); err.style.display = 'none';
            if (!n) { name.classList.add('amb-invalid'); err.textContent = 'Introdu numele.'; err.style.display = 'block'; return; }
            if (!EMAIL_RE.test(e)) { email.classList.add('amb-invalid'); err.textContent = 'Adresa de email nu este validă.'; err.style.display = 'block'; return; }
            guestName = n; guestEmail = e;
            renderCompose();
        });

        var back = document.createElement('button');
        back.type = 'button';
        back.className = 'amb-btn amb-btn-ghost';
        back.textContent = 'Înapoi';
        back.addEventListener('click', renderChoice);

        els.foot.appendChild(name);
        els.foot.appendChild(email);
        els.foot.appendChild(err);
        els.foot.appendChild(go);
        els.foot.appendChild(back);
    }

    // Screen 3: the message box (logged-in visitors land here directly).
    function renderCompose() {
        showGreeting();
        applyStatus(cfg && cfg.availability === 'offline' ? 'offline_message' : (cfg && cfg.availability === 'queue' ? 'queued' : ''), null, null);

        var foot = els.foot;
        foot.innerHTML = '';
        var honeypot = (cfg && cfg.honeypot_field) || 'company_website';

        var ta = document.createElement('textarea');
        ta.rows = 2;
        ta.placeholder = 'Scrie un mesaj...';
        ta.setAttribute('data-input', '');
        foot.appendChild(ta);

        var hp = document.createElement('input');
        hp.className = 'amb-hp'; hp.type = 'text'; hp.name = honeypot;
        hp.setAttribute('tabindex', '-1'); hp.setAttribute('autocomplete', 'off'); hp.setAttribute('data-hp', '');
        foot.appendChild(hp);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'amb-btn amb-btn-primary';
        btn.textContent = 'Trimite';
        btn.addEventListener('click', function () { submit(foot, btn); });
        foot.appendChild(btn);

        ta.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); submit(foot, btn); }
        });
        ta.focus();
    }

    function submit(foot, btn) {
        var ta = foot.querySelector('[data-input]');
        var body = (ta && ta.value || '').trim();
        if (!body) return;
        var hp = foot.querySelector('[data-hp]');

        if (!ref) {
            var payload = {
                message: body,
                context: pageContext(),
                elapsed_seconds: Math.round((Date.now() - openedAt) / 1000)
            };
            if (payload.context && payload.context.event_id) payload.event_id = payload.context.event_id;
            if (hp) payload[hp.name] = hp.value;
            if (!isLogged()) {
                if (!guestName || !EMAIL_RE.test(guestEmail || '')) { renderPrechat(); return; }
                payload.guest_name = guestName;
                payload.guest_email = guestEmail;
            }
            btn.disabled = true;
            api('chat.open', { method: 'POST', body: payload }).then(function (res) {
                btn.disabled = false;
                if (!res || !res.success) { alert((res && res.message) || 'Eroare la pornirea conversației.'); return; }
                var d = res.data;
                ref = d.reference; sessionToken = d.session_token;
                try { localStorage.setItem(LS_REF, ref); localStorage.setItem(LS_TOKEN, sessionToken); } catch (e) {}
                if (ta) ta.value = ''; // clear the just-sent first message from the box
                els.body.innerHTML = '';
                renderStartDivider(d.started_at);
                (d.messages || []).forEach(renderMessage);
                applyStatus(d.status, d.queue_position, d.operator);
                startPolling();
            });
            return;
        }

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

    // ---------- Polling ----------

    function poll() {
        if (!ref || !sessionToken) return;
        api('chat.show', { params: { ref: ref, session_token: sessionToken, after: lastMessageId } }).then(function (res) {
            if (!res || !res.success) return;
            var d = res.data;
            (d.messages || []).forEach(renderMessage);
            applyStatus(d.status, d.queue_position, d.operator);
            if (d.status === 'resolved' || d.status === 'closed') {
                stopPolling();
                renderEnded(!!d.rated);
            }
        });
    }
    function startPolling() {
        stopPolling();
        var interval = (cfg && cfg.poll_interval_ms) || 2500;
        pollTimer = setInterval(poll, interval);
    }
    function stopPolling() { if (pollTimer) { clearInterval(pollTimer); pollTimer = null; } }

    var RATING_HINTS = ['', 'Foarte slab', 'Slab', 'Ok', 'Bun', 'Excelent'];

    function renderEnded(alreadyRated) {
        stopIdleWatch();
        hideNotice();
        setState('Conversație încheiată');
        els.foot.innerHTML = '';
        if (!alreadyRated && !rated) {
            buildRatingBox();
        } else {
            var done = document.createElement('div');
            done.className = 'amb-rating-thanks';
            done.textContent = 'Conversație încheiată. Mulțumim!';
            els.foot.appendChild(done);
        }
        var nb = document.createElement('button');
        nb.type = 'button';
        nb.className = 'amb-btn amb-btn-ghost';
        nb.textContent = 'Începe o conversație nouă';
        nb.addEventListener('click', function () { clearConversation(); els.body.innerHTML = ''; entry(); });
        els.foot.appendChild(nb);
    }

    function buildRatingBox() {
        var opTxt = currentOperator ? (' cu ' + currentOperator) : '';
        var wrap = document.createElement('div');
        wrap.className = 'amb-rating';
        wrap.setAttribute('data-rating', '');
        wrap.innerHTML =
            '<div class="amb-rating-q">Cum a fost conversația' + opTxt + '?</div>'
          + '<div class="amb-rating-sub">Apasă pe stele pentru a evalua' + (currentOperator ? ' operatorul' : '') + '.</div>'
          + '<div class="amb-stars" data-stars></div>'
          + '<div class="amb-rating-hint" data-hint>&nbsp;</div>';
        els.foot.appendChild(wrap);

        var starsEl = wrap.querySelector('[data-stars]');
        var hintEl = wrap.querySelector('[data-hint]');
        var selected = 0;
        var stars = [];

        function paint(upto) {
            for (var k = 0; k < 5; k++) stars[k].classList.toggle('on', k < upto);
        }
        for (var i = 1; i <= 5; i++) {
            (function (score) {
                var star = document.createElement('button');
                star.type = 'button';
                star.className = 'amb-star';
                star.textContent = '★';
                star.setAttribute('aria-label', score + ' stele');
                star.addEventListener('mouseenter', function () { paint(score); hintEl.textContent = RATING_HINTS[score]; });
                star.addEventListener('click', function () {
                    selected = score;
                    paint(score);
                    submitRating(score, wrap);
                });
                stars.push(star);
                starsEl.appendChild(star);
            })(i);
        }
        starsEl.addEventListener('mouseleave', function () { paint(selected); hintEl.textContent = selected ? RATING_HINTS[selected] : ' '; });
    }

    function submitRating(score, wrap) {
        rated = true;
        api('chat.rating', { method: 'POST', params: { ref: ref }, body: { rating: score, session_token: sessionToken } })
            .then(function () {
                wrap.innerHTML = '<div class="amb-rating-thanks">Mulțumim pentru feedback! ★ ' + score + '/5</div>';
            })
            .catch(function () {
                wrap.innerHTML = '<div class="amb-rating-thanks">Mulțumim pentru feedback!</div>';
            });
    }

    // ---------- Resume / open ----------

    function resumeOrCompose() {
        try { ref = localStorage.getItem(LS_REF); sessionToken = localStorage.getItem(LS_TOKEN); } catch (e) {}
        if (ref && sessionToken) {
            els.body.innerHTML = '';
            lastMessageId = 0;
            api('chat.show', { params: { ref: ref, session_token: sessionToken, after: 0 } }).then(function (res) {
                if (!res || !res.success) { clearConversation(); entry(); return; }
                var d = res.data;
                renderStartDivider(d.started_at);
                (d.messages || []).forEach(renderMessage);
                applyStatus(d.status, d.queue_position, d.operator);
                if (d.status === 'resolved' || d.status === 'closed') { renderEnded(!!d.rated); }
                else { renderComposeExisting(); startPolling(); }
            });
        } else {
            entry();
        }
    }

    // Compose box for an already-open conversation (skip greeting).
    function renderComposeExisting() {
        var foot = els.foot;
        foot.innerHTML = '';
        var ta = document.createElement('textarea');
        ta.rows = 2; ta.placeholder = 'Scrie un mesaj...'; ta.setAttribute('data-input', '');
        foot.appendChild(ta);
        var btn = document.createElement('button');
        btn.type = 'button'; btn.className = 'amb-btn amb-btn-primary'; btn.textContent = 'Trimite';
        btn.addEventListener('click', function () { submit(foot, btn); });
        foot.appendChild(btn);
        ta.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && !ev.shiftKey) { ev.preventDefault(); submit(foot, btn); }
        });
    }

    function clearConversation() {
        ref = null; sessionToken = null; lastMessageId = 0;
        lastActivityTs = 0; rated = false; currentStatus = null; currentOperator = null;
        stopIdleWatch(); hideNotice();
        try { localStorage.removeItem(LS_REF); localStorage.removeItem(LS_TOKEN); } catch (e) {}
    }

    function entry() {
        if (isLogged()) {
            renderCompose();
        } else if (guestName && EMAIL_RE.test(guestEmail || '')) {
            // Already gave name+email earlier this session → skip the choice and
            // drop straight into a fresh chat with the details reused.
            renderCompose();
        } else {
            renderChoice();
        }
    }

    function togglePanel() {
        panelOpen = !panelOpen;
        els.panel.classList.toggle('open', panelOpen);
        if (panelOpen) {
            openedAt = Date.now();
            resumeOrCompose();
        } else {
            stopPolling();
            stopIdleWatch();
        }
    }

    // ---------- Boot ----------

    function boot() {
        api('chat.bootstrap').then(function (res) {
            if (!res || !res.success || !res.data || !res.data.active) return;
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
