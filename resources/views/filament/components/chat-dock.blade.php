{{-- Dock de chat (ca pe Facebook): conversațiile cu clienții în ferestre lipite jos-dreapta, pe orice ecran.
     Injectat pe body.end când marketplace-ul are live-chat + operator-cockpit. Expune window.epDock.open(ref). --}}
<style>
    #epd-root {
        --epd-bg: #ffffff; --epd-surface: #f4f6f8; --epd-line: #e1e5ea; --epd-fg: #17202a; --epd-muted: #66717f;
        --epd-in: #eef1f5; --epd-out: #0f9b76; --epd-out-fg: #ffffff; --epd-note-bg: #fff7e0; --epd-note-line: #f0d27a; --epd-note-fg: #7a5300;
        --epd-accent: #0f9b76; --epd-input: #ffffff;
        position: fixed; right: 16px; bottom: 0; z-index: 34;
        display: flex; flex-direction: row-reverse; align-items: flex-end; gap: 10px;
        pointer-events: none; font-family: inherit; transition: bottom .2s ease;
    }
    .dark #epd-root {
        --epd-bg: #171c22; --epd-surface: #1e252d; --epd-line: #2b333d; --epd-fg: #e6eaef; --epd-muted: #8a95a3;
        --epd-in: #232b34; --epd-out: #17775d; --epd-out-fg: #ffffff; --epd-note-bg: rgba(242,181,61,.08); --epd-note-line: #6b5420; --epd-note-fg: #f2c46a;
        --epd-accent: #2fd3a6; --epd-input: #0f1318;
    }
    @media (min-width: 1024px) { html.epc-on #epd-root { right: calc(56px + 16px); } }

    .epd-win {
        pointer-events: auto; width: 320px; max-width: calc(100vw - 32px); background: var(--epd-bg); color: var(--epd-fg);
        border: 1px solid var(--epd-line); border-bottom: 0; border-radius: 10px 10px 0 0;
        box-shadow: 0 -6px 28px rgba(0,0,0,.18); display: flex; flex-direction: column; font-size: 13px;
    }
    .epd-head {
        display: flex; align-items: center; gap: 8px; padding: 8px 8px 8px 12px; cursor: pointer; user-select: none;
        border-top: 3px solid var(--epd-accent); border-radius: 10px 10px 0 0; border-bottom: 1px solid var(--epd-line); min-height: 46px;
    }
    .epd-win.is-min .epd-head { border-bottom: 0; }
    .epd-who { min-width: 0; flex: 1; }
    .epd-who b { display: block; font-weight: 600; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .epd-who small { display: block; color: var(--epd-muted); font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .epd-badge { min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: #f2645f; color: #fff; font-size: 11px; font-weight: 700; display: grid; place-items: center; }
    .epd-hbtn { width: 28px; height: 28px; border: 0; background: transparent; color: var(--epd-muted); border-radius: 6px; cursor: pointer; display: grid; place-items: center; flex: none; }
    .epd-hbtn:hover, .epd-hbtn:focus-visible { background: var(--epd-surface); color: var(--epd-fg); outline: none; }
    .epd-hbtn svg { width: 16px; height: 16px; }
    .epd-win.is-min .epd-body, .epd-win.is-min .epd-foot, .epd-win.is-min .epd-bar, .epd-win.is-min .epd-menu { display: none !important; }

    .epd-menu { position: relative; }
    .epd-menu-list { position: absolute; right: 8px; top: 2px; z-index: 2; min-width: 210px; background: var(--epd-bg); border: 1px solid var(--epd-line); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,.2); padding: 4px; display: grid; }
    .epd-menu-list button, .epd-menu-list a { text-align: left; border: 0; background: transparent; color: var(--epd-fg); font-size: 12.5px; padding: 7px 10px; border-radius: 6px; cursor: pointer; text-decoration: none; }
    .epd-menu-list button:hover, .epd-menu-list a:hover { background: var(--epd-surface); }
    .epd-menu-list .epd-sep { height: 1px; background: var(--epd-line); margin: 4px 2px; }
    .epd-menu-list .epd-lbl { font-size: 11px; color: var(--epd-muted); padding: 4px 10px 2px; }

    .epd-bar { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 12px; background: var(--epd-surface); border-bottom: 1px solid var(--epd-line); font-size: 12px; color: var(--epd-muted); }
    .epd-body { height: 300px; overflow-y: auto; padding: 10px; display: flex; flex-direction: column; gap: 6px; overscroll-behavior: contain; }
    .epd-msg { max-width: 82%; padding: 6px 10px; border-radius: 14px; line-height: 1.4; white-space: pre-wrap; word-wrap: break-word; }
    .epd-msg.in { align-self: flex-start; background: var(--epd-in); border-bottom-left-radius: 4px; }
    .epd-msg.out { align-self: flex-end; background: var(--epd-out); color: var(--epd-out-fg); border-bottom-right-radius: 4px; }
    .epd-msg.note { align-self: stretch; max-width: none; background: var(--epd-note-bg); border: 1px dashed var(--epd-note-line); color: var(--epd-note-fg); border-radius: 8px; font-size: 12px; }
    .epd-msg .epd-meta { display: block; font-size: 10.5px; opacity: .75; margin-top: 2px; }
    .epd-msg.out .epd-meta { text-align: right; }
    .epd-msg img { display: block; max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 4px; cursor: pointer; }
    .epd-msg a { color: inherit; }
    .epd-sys { align-self: center; text-align: center; font-size: 11px; color: var(--epd-muted); max-width: 90%; }
    .epd-foot { border-top: 1px solid var(--epd-line); padding: 8px; display: grid; gap: 6px; }
    .epd-tools { display: flex; align-items: center; gap: 6px; }
    .epd-tools select { flex: 1; min-width: 0; font-size: 12px; padding: 3px 6px; border-radius: 6px; border: 1px solid var(--epd-line); background: var(--epd-input); color: var(--epd-fg); }
    .epd-note-toggle { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--epd-muted); cursor: pointer; white-space: nowrap; }
    .epd-compose { display: flex; gap: 6px; align-items: flex-end; }
    .epd-compose textarea { flex: 1; min-width: 0; resize: none; max-height: 120px; min-height: 36px; font: inherit; font-size: 13px; padding: 8px 10px; border-radius: 16px; border: 1px solid var(--epd-line); background: var(--epd-input); color: var(--epd-fg); }
    .epd-compose textarea:focus { outline: 2px solid var(--epd-accent); outline-offset: -1px; }
    .epd-win.is-note .epd-compose textarea { background: var(--epd-note-bg); border-color: var(--epd-note-line); }
    .epd-send { height: 36px; padding: 0 12px; border-radius: 18px; border: 0; background: var(--epd-accent); color: #fff; font-weight: 600; font-size: 12.5px; cursor: pointer; }
    .dark .epd-send { color: #0c0f13; }
    .epd-send:disabled { opacity: .5; cursor: default; }
    .epd-btn { font-size: 12px; font-weight: 500; padding: 4px 10px; border-radius: 6px; border: 1px solid var(--epd-line); background: var(--epd-bg); color: var(--epd-fg); cursor: pointer; white-space: nowrap; }
    .epd-btn.is-pri { background: var(--epd-accent); border-color: var(--epd-accent); color: #fff; }
    .dark .epd-btn.is-pri { color: #0c0f13; }
    .epd-closed { text-align: center; color: var(--epd-muted); font-size: 12px; padding: 4px; }
    .epd-err { color: #d63c37; font-size: 12px; }
    @media (max-width: 1023px) {
        #epd-root .epd-win:not(.is-min) { width: calc(100vw - 32px); }
        .epd-body { height: 50vh; }
    }
</style>

<div id="epd-root" aria-label="Conversații deschise"></div>

<script>
(function () {
    if (window.epDock) return;
    var root = document.getElementById('epd-root');
    if (!root) return;

    var BASE = '/marketplace/chat-dock/';
    var wins = [];            // [{ref, min, el, lastId, conv, busy}]
    var canned = [];
    var operators = [];
    var saveTimer = null;

    var ICON = {
        min: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 12h12"/></svg>',
        close: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>',
        more: '<svg fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>',
        clip: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5l-8.6 8.6a5 5 0 01-7.1-7.1l8.6-8.6a3.3 3.3 0 014.7 4.7l-8.6 8.6a1.7 1.7 0 01-2.4-2.4l7.9-7.9"/></svg>'
    };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
    function isDesktop() { return window.matchMedia('(min-width: 1024px)').matches; }
    function req(method, url, data) {
        var opt = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() } };
        if (data !== undefined) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(data); }
        return fetch(url, opt).then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { j.__status = r.status; return j; }); });
    }
    function find(ref) { for (var i = 0; i < wins.length; i++) if (wins[i].ref === ref) return wins[i]; return null; }

    function saveState() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            req('POST', BASE + 'state', { windows: wins.map(function (w) { return { ref: w.ref, min: w.min }; }) }).catch(function () {});
        }, 500);
    }

    // Maxim 3 ferestre deschise pe desktop, 1 pe ecrane mici; restul se minimizează.
    function enforceOpenLimit(keep) {
        var max = isDesktop() ? 3 : 1;
        var open = wins.filter(function (w) { return !w.min; });
        for (var i = open.length - 1; i >= 0 && open.length > max; i--) {
            if (open[i] === keep) continue;
            setMin(open[i], true, true);
            open = wins.filter(function (w) { return !w.min; });
        }
    }

    function setMin(w, min, silent) {
        w.min = min;
        w.el.classList.toggle('is-min', min);
        if (!min) { enforceOpenLimit(w); refresh(w, true); scrollDown(w); }
        if (!silent) saveState();
    }

    function closeWin(w) {
        w.el.remove();
        wins = wins.filter(function (x) { return x !== w; });
        saveState();
    }

    function build(ref) {
        var el = document.createElement('div');
        el.className = 'epd-win';
        el.innerHTML =
            '<div class="epd-head" role="button" tabindex="0">' +
                '<div class="epd-who"><b>…</b><small>se încarcă</small></div>' +
                '<span class="epd-badge" hidden></span>' +
                '<button type="button" class="epd-hbtn" data-a="menu" aria-label="Opțiuni">' + ICON.more + '</button>' +
                '<button type="button" class="epd-hbtn" data-a="min" aria-label="Minimizează">' + ICON.min + '</button>' +
                '<button type="button" class="epd-hbtn" data-a="close" aria-label="Închide fereastra">' + ICON.close + '</button>' +
            '</div>' +
            '<div class="epd-menu"><div class="epd-menu-list" hidden></div></div>' +
            '<div class="epd-bar" hidden></div>' +
            '<div class="epd-body" aria-live="polite"></div>' +
            '<div class="epd-foot">' +
                '<div class="epd-tools">' +
                    '<select aria-label="Răspuns rapid" hidden><option value="">Răspuns rapid…</option></select>' +
                    '<label class="epd-note-toggle"><input type="checkbox" data-a="note"> Notă internă</label>' +
                    '<button type="button" class="epd-hbtn" data-a="attach" aria-label="Atașează fișier">' + ICON.clip + '</button>' +
                    '<input type="file" accept="image/*,application/pdf" hidden>' +
                '</div>' +
                '<div class="epd-compose"><textarea rows="1" placeholder="Scrie un mesaj…" aria-label="Mesaj"></textarea><button type="button" class="epd-send">Trimite</button></div>' +
                '<div class="epd-err" hidden></div>' +
            '</div>';

        var w = { ref: ref, min: false, el: el, lastId: 0, conv: null, busy: false, subscribed: false };

        var head = el.querySelector('.epd-head');
        head.addEventListener('click', function (e) {
            var b = e.target.closest('[data-a]');
            if (b && b.getAttribute('data-a') === 'close') { closeWin(w); return; }
            if (b && b.getAttribute('data-a') === 'menu') { if (w.min) setMin(w, false); toggleMenu(w); return; }
            setMin(w, !w.min);
        });
        head.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target === head) setMin(w, !w.min); });

        var ta = el.querySelector('textarea');
        ta.addEventListener('input', function () { ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 120) + 'px'; });
        ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(w); } });
        el.querySelector('.epd-send').addEventListener('click', function () { send(w); });
        el.querySelector('[data-a="note"]').addEventListener('change', function (e) { el.classList.toggle('is-note', e.target.checked); });

        var sel = el.querySelector('.epd-tools select');
        sel.addEventListener('change', function () {
            var c = canned[parseInt(sel.value, 10)];
            if (c) { ta.value = (ta.value ? ta.value + '\n' : '') + c.body; ta.dispatchEvent(new Event('input')); ta.focus(); }
            sel.value = '';
        });

        var file = el.querySelector('input[type="file"]');
        el.querySelector('[data-a="attach"]').addEventListener('click', function () { file.click(); });
        file.addEventListener('change', function () { if (file.files[0]) upload(w, file.files[0]); file.value = ''; });

        el.querySelector('.epd-bar').addEventListener('click', function (e) {
            if (e.target.closest('[data-a="claim"]')) claim(w);
        });
        el.querySelector('.epd-menu-list').addEventListener('click', function (e) {
            var b = e.target.closest('button[data-act]');
            if (!b) return;
            var act = b.getAttribute('data-act');
            toggleMenu(w, false);
            if (act === 'claim') claim(w);
            else if (act === 'resolve') action(w, 'resolve');
            else if (act === 'transfer') action(w, 'transfer', { admin_id: parseInt(b.getAttribute('data-id'), 10) });
        });

        renderCanned(w);
        return w;
    }

    function renderCanned(w) {
        var sel = w.el.querySelector('.epd-tools select');
        if (!canned.length) { sel.hidden = true; return; }
        sel.innerHTML = '<option value="">Răspuns rapid…</option>' + canned.map(function (c, i) { return '<option value="' + i + '">' + esc(c.title) + '</option>'; }).join('');
        sel.hidden = false;
    }

    function toggleMenu(w, force) {
        var list = w.el.querySelector('.epd-menu-list');
        var show = force === undefined ? list.hidden : force;
        if (show) {
            var c = w.conv || {};
            var h = '<a href="' + esc(c.console_url || '/marketplace/chat-console') + '">Deschide în consolă</a>';
            if (!c.closed && !c.mine) h += '<button type="button" data-act="claim">Preiau conversația</button>';
            if (!c.closed && operators.length) {
                h += '<div class="epd-sep"></div><div class="epd-lbl">Transferă către</div>';
                operators.forEach(function (o) { h += '<button type="button" data-act="transfer" data-id="' + o.id + '">' + esc(o.name) + '</button>'; });
            }
            if (!c.closed) h += '<div class="epd-sep"></div><button type="button" data-act="resolve">Marchează rezolvată</button>';
            list.innerHTML = h;
        }
        list.hidden = !show;
    }

    function renderMeta(w) {
        var c = w.conv;
        if (!c) return;
        w.el.querySelector('.epd-who b').textContent = c.name + (c.subject ? ' · ' + c.subject : '');
        var who = c.type + ' · ' + (c.closed ? 'închisă' : (c.mine ? 'a ta' : (c.owner_name ? 'preluată de ' + c.owner_name : 'în coadă')));
        w.el.querySelector('.epd-who small').textContent = who;
        var badge = w.el.querySelector('.epd-badge');
        var unread = w.min ? c.unread : 0;
        badge.hidden = !unread;
        badge.textContent = unread > 99 ? '99+' : String(unread || '');

        var bar = w.el.querySelector('.epd-bar');
        if (!c.closed && !c.owner_id) {
            bar.innerHTML = '<span>Clientul așteaptă în coadă.</span><button type="button" class="epd-btn is-pri" data-a="claim">Preiau</button>';
            bar.hidden = false;
        } else if (!c.closed && !c.mine) {
            bar.innerHTML = '<span>Conversația e la ' + esc(c.owner_name || 'alt operator') + '. Poți lăsa o notă internă.</span>';
            bar.hidden = false;
        } else {
            bar.hidden = true;
        }

        var foot = w.el.querySelector('.epd-foot');
        var closedNote = foot.querySelector('.epd-closed');
        foot.querySelectorAll('.epd-tools, .epd-compose').forEach(function (x) { x.hidden = !!c.closed; });
        if (c.closed && !closedNote) { var p = document.createElement('div'); p.className = 'epd-closed'; p.textContent = 'Conversația e închisă.'; foot.appendChild(p); }
        if (!c.closed && closedNote) closedNote.remove();
    }

    function msgHtml(m) {
        if (m.kind === 'system') return '<div class="epd-sys">' + esc(m.body) + ' · ' + esc(m.time) + '</div>';
        var att = (m.attachments || []).map(function (a) {
            return a.image
                ? '<a href="' + esc(a.url) + '" target="_blank" rel="noopener"><img src="' + esc(a.url) + '" alt="' + esc(a.name) + '"></a>'
                : '<a href="' + esc(a.url) + '" target="_blank" rel="noopener">' + esc(a.name) + '</a>';
        }).join('');
        var meta = (m.kind === 'note' ? 'Notă internă · ' : '') + (m.author && m.kind !== 'in' ? m.author + ' · ' : '') + (m.time || '');
        return '<div class="epd-msg ' + m.kind + '" data-id="' + m.id + '">' + (m.body ? esc(m.body) : '') + att + '<span class="epd-meta">' + esc(meta) + '</span></div>';
    }

    function scrollDown(w) { var b = w.el.querySelector('.epd-body'); b.scrollTop = b.scrollHeight; }

    function refresh(w, markRead) {
        if (w.busy) { w.again = true; return Promise.resolve(); }
        w.busy = true;
        var read = !w.min && document.visibilityState === 'visible' ? 1 : 0;
        return req('GET', BASE + encodeURIComponent(w.ref) + '?after=' + w.lastId + '&read=' + (markRead === false ? 0 : read))
            .then(function (res) {
                if (res.__status === 404) { closeWin(w); return; }
                if (!res || !res.ok) return;
                w.conv = res.conversation;
                operators = res.operators || operators;
                var body = w.el.querySelector('.epd-body');
                var nearBottom = body.scrollHeight - body.scrollTop - body.clientHeight < 60;
                if (w.lastId === 0) body.innerHTML = '';
                (res.messages || []).forEach(function (m) {
                    if (body.querySelector('[data-id="' + m.id + '"]')) return;
                    body.insertAdjacentHTML('beforeend', msgHtml(m));
                    w.lastId = Math.max(w.lastId, m.id);
                });
                renderMeta(w);
                if (nearBottom || (res.messages || []).length) scrollDown(w);
                subscribe(w);
            })
            .catch(function () {})
            .then(function () { w.busy = false; if (w.again) { w.again = false; refresh(w); } });
    }

    function subscribe(w) {
        if (w.subscribed || !w.conv || !window.epRealtime) return;
        w.subscribed = true;
        var t = null;
        var go = function () { if (!find(w.ref)) return; clearTimeout(t); t = setTimeout(function () { refresh(w); }, 200); };
        window.epRealtime.on('chat.conversation.' + w.conv.id, true, 'message', go);
        window.epRealtime.on('chat.conversation.' + w.conv.id, true, 'conversation.updated', go);
    }

    function showErr(w, text) {
        var e = w.el.querySelector('.epd-err');
        e.textContent = text; e.hidden = false;
        setTimeout(function () { e.hidden = true; }, 5000);
    }

    function send(w) {
        var ta = w.el.querySelector('textarea');
        var text = ta.value.trim();
        if (!text) return;
        var btn = w.el.querySelector('.epd-send');
        var internal = w.el.querySelector('[data-a="note"]').checked;
        btn.disabled = true;
        req('POST', BASE + encodeURIComponent(w.ref) + '/reply', { text: text, internal: internal }).then(function (res) {
            btn.disabled = false;
            if (!res.ok) { showErr(w, res.message || 'Mesajul nu a plecat. Încearcă din nou.'); return; }
            ta.value = ''; ta.style.height = '';
            refresh(w);
        }).catch(function () { btn.disabled = false; showErr(w, 'Fără conexiune. Mesajul nu a plecat.'); });
    }

    function upload(w, f) {
        if (!w.conv) return;
        if (f.size > 8 * 1024 * 1024) { showErr(w, 'Fișierul e prea mare (maxim 8 MB).'); return; }
        var reader = new FileReader();
        reader.onload = function () {
            req('POST', w.conv.attach_url, { data: reader.result, name: f.name }).then(function (res) {
                if (!res.ok) { showErr(w, res.message || 'Fișierul nu s-a încărcat.'); return; }
                refresh(w);
            }).catch(function () { showErr(w, 'Fără conexiune. Fișierul nu s-a încărcat.'); });
        };
        reader.readAsDataURL(f);
    }

    function claim(w) {
        req('POST', BASE + encodeURIComponent(w.ref) + '/claim').then(function (res) {
            if (!res.ok) showErr(w, res.message || 'Nu am putut prelua conversația.');
            refresh(w);
        });
    }

    function action(w, name, data) {
        req('POST', BASE + encodeURIComponent(w.ref) + '/' + name, data || {}).then(function (res) {
            if (!res.ok) showErr(w, res.message || 'Acțiunea nu a reușit.');
            refresh(w);
        });
    }

    function open(ref, opts) {
        opts = opts || {};
        var w = find(ref);
        if (!w) {
            w = build(ref);
            wins.unshift(w);
            root.appendChild(w.el);
            if (opts.min) { w.min = true; w.el.classList.add('is-min'); }
            refresh(w, !opts.min);
        }
        if (!opts.min) {
            setMin(w, false, true);
            var ta = w.el.querySelector('textarea');
            if (opts.focus !== false) setTimeout(function () { ta.focus(); }, 50);
        }
        if (!opts.restore) saveState();
        return w;
    }

    // Butonul verde de salvare din formulare stă jos-dreapta; ridicăm dock-ul deasupra lui.
    setInterval(function () {
        var fs = document.getElementById('ep-float-save');
        var visible = fs && getComputedStyle(fs).display !== 'none';
        root.style.bottom = visible ? '76px' : '0px';
    }, 1000);

    // Plasă de siguranță: fără Reverb la 4s, cu Reverb la 30s.
    var lastSafety = 0;
    setInterval(function () {
        if (document.hidden || !wins.length) return;
        var live = window.epRealtime && window.epRealtime.isConnected();
        if (live && Date.now() - lastSafety < 30000) return;
        lastSafety = Date.now();
        wins.forEach(function (w) { refresh(w); });
    }, 4000);

    window.epDock = { open: open };

    req('GET', BASE + 'state').then(function (res) {
        if (!res || !res.ok) return;
        canned = res.canned || [];
        (res.windows || []).slice().reverse().forEach(function (s) { open(s.ref, { min: s.min, restore: true, focus: false }); });
        wins.forEach(renderCanned);
        enforceOpenLimit(null);
    }).catch(function () {});
})();
</script>
