{{-- Dock de chat (ca pe Facebook): ferestre lipite jos-dreapta, pe orice ecran.
     Două feluri de ferestre: conversații cu clienții (live-chat) și chatul intern al echipei.
     Expune window.epDock.open(ref), window.epDock.openTeam(threadId), window.epDock.isFocused(ref). --}}
<style>
    #epd-root {
        --epd-bg: #ffffff; --epd-surface: #f3f5f8; --epd-line: #e1e5ea; --epd-fg: #17202a; --epd-muted: #66717f;
        --epd-in: #eef1f5; --epd-input: #f3f5f8;
        --epd-note-bg: #fff7e0; --epd-note-line: #f0d27a; --epd-note-fg: #7a5300;
        position: fixed; right: 16px; bottom: 0; z-index: 34;
        display: flex; flex-direction: row-reverse; align-items: flex-end; gap: 10px;
        pointer-events: none; font-family: inherit; transition: bottom .2s ease;
    }
    .dark #epd-root {
        --epd-bg: #171c22; --epd-surface: #1e252d; --epd-line: #2b333d; --epd-fg: #e6eaef; --epd-muted: #8a95a3;
        --epd-in: #232b34; --epd-input: #0f1318;
        --epd-note-bg: rgba(242,181,61,.08); --epd-note-line: #6b5420; --epd-note-fg: #f2c46a;
    }
    @media (min-width: 1024px) { html.epc-on #epd-root { right: calc(56px + 16px); } }

    /* --k = culoarea conversației (client, 1:1, Toată echipa, departament, grup, ajutor) */
    .epd-win {
        --k: #12917a;
        pointer-events: auto; width: 320px; max-width: calc(100vw - 32px); background: var(--epd-bg); color: var(--epd-fg);
        border: 1px solid var(--epd-line); border-bottom: 0; border-radius: 10px 10px 0 0;
        box-shadow: 0 -4px 24px rgba(0,0,0,.16); display: flex; flex-direction: column; font-size: 13px; overflow: hidden;
    }
    .epd-head { display: flex; align-items: center; gap: 8px; padding: 6px 4px 6px 8px; cursor: pointer; user-select: none; border-bottom: 1px solid var(--epd-line); min-height: 42px; transition: background .2s ease; }
    .epd-win.is-min .epd-head { border-bottom: 0; }
    .epd-win.has-unread .epd-head { background: color-mix(in srgb, var(--k) 20%, var(--epd-bg)); }
    .epd-win.has-unread .epd-who b { font-weight: 700; }
    .epd-ava { width: 28px; height: 28px; flex: none; border-radius: 50%; background: var(--k); color: #fff; display: grid; place-items: center; font-size: 11px; font-weight: 700; letter-spacing: .02em; }
    .epd-ava.is-sq { border-radius: 8px; }
    .epd-ava svg { width: 15px; height: 15px; }
    .epd-who { min-width: 0; flex: 1; line-height: 1.25; }
    .epd-who b { display: block; font-weight: 600; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .epd-who small { display: block; color: var(--epd-muted); font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .epd-badge { min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: var(--k); color: #fff; font-size: 11px; font-weight: 700; display: grid; place-items: center; }
    .epd-hbtn { width: 26px; height: 26px; border: 0; background: transparent; color: var(--epd-muted); border-radius: 6px; cursor: pointer; display: grid; place-items: center; flex: none; padding: 0; }
    .epd-hbtn:hover, .epd-hbtn:focus-visible { background: var(--epd-surface); color: var(--epd-fg); outline: none; }
    .epd-hbtn[aria-pressed="true"] { color: var(--k); background: color-mix(in srgb, var(--k) 14%, transparent); }
    .epd-hbtn svg { width: 16px; height: 16px; }
    .epd-win.is-min .epd-body, .epd-win.is-min .epd-foot, .epd-win.is-min .epd-bar, .epd-win.is-min .epd-menu,
    .epd-win.is-min .epd-typing, .epd-win.is-min .epd-ctx, .epd-win.is-min [data-a="info"] { display: none !important; }

    .epd-menu { position: relative; }
    .epd-menu-list { position: absolute; right: 6px; top: 2px; z-index: 3; min-width: 220px; max-height: 320px; overflow-y: auto; background: var(--epd-bg); border: 1px solid var(--epd-line); border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,.2); padding: 4px; display: grid; }
    .epd-menu-list button, .epd-menu-list a { text-align: left; border: 0; background: transparent; color: var(--epd-fg); font-size: 12.5px; padding: 7px 10px; border-radius: 6px; cursor: pointer; text-decoration: none; }
    .epd-menu-list button:hover, .epd-menu-list a:hover { background: var(--epd-surface); }
    .epd-menu-list .epd-sep { height: 1px; background: var(--epd-line); margin: 4px 2px; }
    .epd-menu-list .epd-lbl { font-size: 11px; color: var(--epd-muted); padding: 4px 10px 2px; }
    .epd-menu-list .epd-member { font-size: 12.5px; padding: 3px 10px; color: var(--epd-fg); }

    .epd-bar { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 5px 10px; background: var(--epd-surface); border-bottom: 1px solid var(--epd-line); font-size: 12px; color: var(--epd-muted); }
    .epd-body { height: 300px; overflow-y: auto; padding: 8px 10px; display: flex; flex-direction: column; gap: 5px; overscroll-behavior: contain; }
    .epd-msg { max-width: 82%; padding: 6px 10px; border-radius: 14px; line-height: 1.4; white-space: pre-wrap; word-wrap: break-word; }
    .epd-msg.in { align-self: flex-start; background: var(--epd-in); border-bottom-left-radius: 4px; }
    .epd-msg.out { align-self: flex-end; background: var(--k); color: #fff; border-bottom-right-radius: 4px; }
    .epd-msg.note { align-self: stretch; max-width: none; background: var(--epd-note-bg); border: 1px dashed var(--epd-note-line); color: var(--epd-note-fg); border-radius: 8px; font-size: 12px; }
    .epd-msg.is-mention { box-shadow: 0 0 0 2px var(--k); }
    .epd-msg.is-deleted { font-style: italic; opacity: .65; }
    .epd-msg .epd-author { display: block; font-size: 11px; font-weight: 600; margin-bottom: 1px; opacity: .85; }
    .epd-msg .epd-meta { display: block; font-size: 10.5px; opacity: .72; margin-top: 2px; }
    .epd-msg.out .epd-meta { text-align: right; }
    .epd-msg img { display: block; max-width: 200px; max-height: 200px; border-radius: 8px; margin-top: 4px; cursor: pointer; }
    .epd-msg a { color: inherit; text-decoration: underline; text-underline-offset: 2px; word-break: break-all; }
    .epd-msg .epd-at { font-weight: 700; }
    .epd-msg.in .epd-at { color: var(--k); }
    .epd-att { margin-top: 4px; font-size: 11.5px; display: grid; gap: 2px; }
    .epd-att button { font: inherit; font-size: 11px; border: 0; background: transparent; color: inherit; text-decoration: underline; cursor: pointer; padding: 0; justify-self: start; opacity: .9; }
    .epd-own { align-self: flex-end; display: flex; gap: 8px; font-size: 11px; margin-top: -2px; }
    .epd-own button { border: 0; background: transparent; color: var(--epd-muted); cursor: pointer; padding: 0; font-size: 11px; }
    .epd-own button:hover { color: var(--epd-fg); text-decoration: underline; }
    .epd-edit { align-self: stretch; display: grid; gap: 4px; }
    .epd-edit textarea { font: inherit; font-size: 13px; padding: 6px 8px; border-radius: 8px; border: 1px solid var(--epd-line); background: var(--epd-input); color: var(--epd-fg); resize: vertical; min-height: 50px; }
    .epd-edit div { display: flex; gap: 6px; justify-content: flex-end; }
    .epd-sys { align-self: center; text-align: center; font-size: 11px; color: var(--epd-muted); max-width: 90%; }
    .epd-day { align-self: center; font-size: 10.5px; color: var(--epd-muted); text-transform: uppercase; letter-spacing: .06em; padding: 2px 0; }
    .epd-typing { padding: 0 12px; font-size: 11.5px; color: var(--epd-muted); height: 0; overflow: hidden; transition: height .15s ease; }
    .epd-typing.is-on { height: 18px; }

    /* Compunere: [atașament][răspuns rapid][notă]  [text]  [trimite] */
    .epd-foot { border-top: 1px solid var(--epd-line); padding: 6px; position: relative; display: grid; gap: 4px; }
    .epd-compose { display: flex; gap: 2px; align-items: flex-end; }
    .epd-compose .epd-hbtn { width: 30px; height: 32px; }
    .epd-compose textarea { flex: 1; min-width: 0; resize: none; max-height: 120px; min-height: 32px; font: inherit; font-size: 13px; line-height: 1.35; padding: 6px 10px; margin: 0 2px; border-radius: 16px; border: 1px solid transparent; background: var(--epd-input); color: var(--epd-fg); }
    .epd-compose textarea:focus { outline: none; border-color: var(--k); }
    .epd-win.is-note .epd-compose textarea { background: var(--epd-note-bg); border-color: var(--epd-note-line); }
    .epd-send { width: 32px; height: 32px; flex: none; border-radius: 50%; border: 0; background: var(--k); color: #fff; cursor: pointer; display: grid; place-items: center; padding: 0; }
    .epd-send svg { width: 16px; height: 16px; }
    .epd-send:disabled { opacity: .45; cursor: default; }
    .epd-btn { font-size: 12px; font-weight: 500; padding: 4px 10px; border-radius: 6px; border: 1px solid var(--epd-line); background: var(--epd-bg); color: var(--epd-fg); cursor: pointer; white-space: nowrap; }
    .epd-btn.is-pri { background: var(--k); border-color: var(--k); color: #fff; }
    .epd-closed { text-align: center; color: var(--epd-muted); font-size: 12px; padding: 2px; }
    .epd-err { color: #d63c37; font-size: 12px; padding: 0 4px; }
    .epd-pop { position: absolute; left: 6px; right: 6px; bottom: 100%; margin-bottom: 4px; z-index: 3; background: var(--epd-bg); border: 1px solid var(--epd-line); border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.18); padding: 4px; display: grid; max-height: 200px; overflow-y: auto; }
    .epd-pop button { text-align: left; border: 0; background: transparent; color: var(--epd-fg); font-size: 12.5px; padding: 6px 8px; border-radius: 6px; cursor: pointer; }
    .epd-pop button small { display: block; color: var(--epd-muted); font-size: 11.5px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .epd-pop button:hover, .epd-pop button.is-on { background: var(--epd-surface); }

    /* Context vizitator (ca în consolă) */
    .epd-ctx { height: 300px; overflow-y: auto; padding: 8px 12px 12px; display: grid; gap: 12px; align-content: start; font-size: 12.5px; overscroll-behavior: contain; }
    .epd-ctx h5 { margin: 0 0 4px; font-size: 10.5px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--epd-muted); display: flex; justify-content: space-between; gap: 8px; }
    .epd-ctx h5 span { text-transform: none; letter-spacing: 0; font-weight: 400; }
    .epd-ctx dl { margin: 0; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 10px; }
    .epd-ctx dt { font-size: 10.5px; color: var(--epd-muted); }
    .epd-ctx dd { margin: 0; word-break: break-word; }
    .epd-ctx .is-wide { grid-column: 1 / -1; }
    .epd-ctx a { color: var(--k); }
    .epd-ctx .epd-hist button { display: flex; justify-content: space-between; width: 100%; border: 0; background: transparent; color: var(--epd-fg); font-size: 12px; padding: 5px 6px; border-radius: 6px; cursor: pointer; }
    .epd-ctx .epd-hist button:hover { background: var(--epd-surface); }
    .epd-ctx .epd-tag { font-size: 10.5px; padding: 0 6px; border-radius: 4px; background: var(--epd-surface); color: var(--epd-muted); }
    .epd-ctx .epd-stats { display: flex; flex-wrap: wrap; gap: 2px 10px; color: var(--epd-muted); font-size: 11.5px; margin-bottom: 6px; }
    .epd-ctx input[type="search"] { width: 100%; font-size: 12px; padding: 5px 8px; border-radius: 6px; border: 1px solid var(--epd-line); background: var(--epd-input); color: var(--epd-fg); margin-bottom: 6px; }
    .epd-ctx .epd-ev { padding: 6px 8px; border-radius: 6px; border: 1px solid var(--epd-line); margin-bottom: 5px; }
    .epd-ctx .epd-ev.is-up { border-color: color-mix(in srgb, #2fb67c 45%, transparent); background: color-mix(in srgb, #2fb67c 8%, transparent); }
    .epd-ctx .epd-ev-h { display: flex; justify-content: space-between; gap: 8px; font-weight: 500; }
    .epd-ctx .epd-ev small { color: var(--epd-muted); display: block; font-size: 11px; }
    .epd-ctx .epd-ord { font-size: 11.5px; color: var(--epd-muted); padding-left: 8px; border-left: 2px solid var(--epd-line); margin-top: 3px; }
    .epd-ctx .epd-ord a { font-weight: 600; }
    .epd-ctx .epd-sub { font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--epd-muted); margin: 6px 0 3px; }
    .epd-muted { color: var(--epd-muted); }

    @media (max-width: 1023px) {
        #epd-root .epd-win:not(.is-min) { width: calc(100vw - 32px); }
        .epd-body, .epd-ctx { height: 50vh; }
    }
</style>

<div id="epd-root" aria-label="Conversații deschise" data-me="{{ (int) ($meId ?? 0) }}" data-live-chat="{{ ! empty($liveChat) ? '1' : '0' }}"></div>

<script>
(function () {
    if (window.epDock) return;
    var root = document.getElementById('epd-root');
    if (!root) return;

    var BASE = '/marketplace/chat-dock/';
    var TEAM = '/marketplace/team-chat/';
    var ME = parseInt(root.getAttribute('data-me'), 10) || 0;
    var LIVE_CHAT = root.getAttribute('data-live-chat') === '1';
    var wins = [];
    var canned = [];
    var operators = [];
    var saveTimer = null;

    // Culoarea fiecărui fel de conversație. Pentru 1:1, fiecare coleg are culoarea lui.
    var KIND = { customer: '#12917a', team: '#b7791f', department: '#2f6fd0', group: '#7656d6', help: '#d0453f' };
    var PEOPLE = ['#3a6a8f', '#8a4f9e', '#9b3f62', '#2f7a6a', '#8a5a2b', '#4b5f9e', '#a04848', '#3f7a3f'];
    function kindColor(kind, partnerId) { return kind === 'dm' ? PEOPLE[(partnerId || 0) % PEOPLE.length] : (KIND[kind] || KIND.group); }
    window.epChatColors = { kind: kindColor };

    var ICON = {
        min: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 12h12"/></svg>',
        close: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>',
        more: '<svg fill="currentColor" viewBox="0 0 24 24"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>',
        info: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 11v6M12 7.5v.01"/></svg>',
        clip: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 11.5l-8.6 8.6a5 5 0 01-7.1-7.1l8.6-8.6a3.3 3.3 0 014.7 4.7l-8.6 8.6a1.7 1.7 0 01-2.4-2.4l7.9-7.9"/></svg>',
        bolt: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linejoin="round" d="M13 2L4 14h7l-1 8 9-12h-7z"/></svg>',
        note: '<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 11V8a5 5 0 0110 0v3M6 11h12v9H6z"/></svg>',
        send: '<svg fill="currentColor" viewBox="0 0 24 24"><path d="M3.4 20.4l17.4-7.5a1 1 0 000-1.8L3.4 3.6a1 1 0 00-1.4 1.1L4 11l9 1-9 1-2 6.3a1 1 0 001.4 1.1z"/></svg>',
        chat: '<svg fill="currentColor" viewBox="0 0 24 24"><path d="M4 3.5h16a2 2 0 012 2v10a2 2 0 01-2 2h-9.2L6 21.2V17.5H4a2 2 0 01-2-2v-10a2 2 0 012-2z"/></svg>',
        team: '<svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="7.5" r="3.2"/><circle cx="5" cy="9" r="2.4"/><circle cx="19" cy="9" r="2.4"/><path d="M6.5 20a5.5 5.5 0 0111 0zM0.8 19a4.3 4.3 0 016.1-3.9A7 7 0 005.3 19zM23.2 19a4.3 4.3 0 00-6.1-3.9 7 7 0 011.6 3.9z"/></svg>',
        group: '<svg fill="currentColor" viewBox="0 0 24 24"><circle cx="9" cy="8" r="3.5"/><circle cx="17" cy="9" r="2.8"/><path d="M2 20a7 7 0 0114 0zM15.5 20a8.4 8.4 0 00-2-5.4A5 5 0 0122 20z"/></svg>',
        department: '<svg fill="currentColor" viewBox="0 0 24 24"><path d="M4 21V5a2 2 0 012-2h8a2 2 0 012 2v4h2a2 2 0 012 2v10h-6v-4h-4v4zM8 7v2h2V7zm4 0v2h2V7zM8 11v2h2v-2zm4 0v2h2v-2z"/></svg>',
        help: '<svg fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><circle cx="12" cy="12" r="8.5"/><circle cx="12" cy="12" r="3.5"/><path d="M6 6l3.5 3.5M18 6l-3.5 3.5M6 18l3.5-3.5M18 18l-3.5-3.5"/></svg>'
    };

    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
    function isDesktop() { return window.matchMedia('(min-width: 1024px)').matches; }
    function initials(name) {
        var p = String(name || '').trim().split(/\s+/), out = '';
        for (var i = 0; i < p.length && out.length < 2; i++) if (p[i]) out += p[i].charAt(0).toUpperCase();
        return out || '#';
    }
    function req(method, url, data) {
        var opt = { method: method, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() } };
        if (data instanceof FormData) { opt.body = data; }
        else if (data !== undefined) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(data); }
        return fetch(url, opt).then(function (r) { return r.json().catch(function () { return { ok: false }; }).then(function (j) { j.__status = r.status; return j; }); });
    }
    function find(ref) { for (var i = 0; i < wins.length; i++) if (wins[i].ref === ref) return wins[i]; return null; }
    function isTeam(ref) { return ref.indexOf('team:') === 0; }

    // Text de mesaj: escapat, linkuri în tab nou, @mențiuni evidențiate.
    var URL_RE = /(https?:\/\/[^\s<>"']+)/g;
    function richText(raw, mentions) {
        return String(raw || '').split(URL_RE).map(function (part, i) {
            if (i % 2 === 1) {
                var trail = '';
                var m = part.match(/[.,;:!?)\]]+$/);
                if (m) { trail = m[0]; part = part.slice(0, -trail.length); }
                return '<a href="' + esc(part) + '" target="_blank" rel="noopener noreferrer">' + esc(part) + '</a>' + esc(trail);
            }
            var e = esc(part);
            return mentions ? e.replace(/(^|\s)@([A-Za-zĂÂÎȘȚăâîșțÀ-ž][\wĂÂÎȘȚăâîșțÀ-ž.-]*)/g, '$1<span class="epd-at">@$2</span>') : e;
        }).join('');
    }

    function saveState() {
        clearTimeout(saveTimer);
        saveTimer = setTimeout(function () {
            req('POST', BASE + 'state', { windows: wins.map(function (w) { return { ref: w.ref, min: w.min }; }) }).catch(function () {});
        }, 500);
    }

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
        if (!min) { w.unread = 0; enforceOpenLimit(w); refresh(w); scrollDown(w); }
        renderBadge(w);
        if (!silent) saveState();
    }

    function closeWin(w) {
        w.el.remove();
        wins = wins.filter(function (x) { return x !== w; });
        saveState();
    }

    // ================================================================ construcție
    function build(ref) {
        var team = isTeam(ref);
        var el = document.createElement('div');
        el.className = 'epd-win' + (team ? ' is-team' : '');
        el.innerHTML =
            '<div class="epd-head" role="button" tabindex="0">' +
                '<span class="epd-ava' + (team ? '' : ' is-sq') + '">' + (team ? '' : ICON.chat) + '</span>' +
                '<div class="epd-who"><b>…</b><small>se încarcă</small></div>' +
                '<span class="epd-badge" hidden></span>' +
                (team ? '' : '<button type="button" class="epd-hbtn" data-a="info" aria-pressed="false" title="Context vizitator și istoric" aria-label="Context vizitator și istoric">' + ICON.info + '</button>') +
                '<button type="button" class="epd-hbtn" data-a="menu" title="Opțiuni" aria-label="Opțiuni">' + ICON.more + '</button>' +
                '<button type="button" class="epd-hbtn" data-a="min" title="Minimizează" aria-label="Minimizează">' + ICON.min + '</button>' +
                '<button type="button" class="epd-hbtn" data-a="close" title="Închide fereastra" aria-label="Închide fereastra">' + ICON.close + '</button>' +
            '</div>' +
            '<div class="epd-menu"><div class="epd-menu-list" hidden></div></div>' +
            '<div class="epd-bar" hidden></div>' +
            '<div class="epd-body" aria-live="polite"></div>' +
            '<div class="epd-ctx" hidden></div>' +
            '<div class="epd-typing"></div>' +
            '<div class="epd-foot">' +
                '<div class="epd-compose">' +
                    '<button type="button" class="epd-hbtn" data-a="attach" title="' + (team ? 'Atașează imagine sau PDF' : 'Trimite o imagine') + '" aria-label="Atașează fișier">' + ICON.clip + '</button>' +
                    (team ? '' :
                        '<button type="button" class="epd-hbtn" data-a="canned" title="Răspunsuri rapide" aria-label="Răspunsuri rapide" hidden>' + ICON.bolt + '</button>' +
                        '<button type="button" class="epd-hbtn" data-a="note" aria-pressed="false" title="Notă internă (clientul nu o vede)" aria-label="Notă internă">' + ICON.note + '</button>') +
                    '<textarea rows="1" placeholder="' + (team ? 'Scrie un mesaj… (@ pentru coleg)' : 'Scrie un mesaj…') + '" aria-label="Mesaj"></textarea>' +
                    '<button type="button" class="epd-send" title="Trimite (Enter)" aria-label="Trimite">' + ICON.send + '</button>' +
                    '<input type="file" accept="' + (team ? 'image/*,application/pdf' : 'image/*') + '" hidden>' +
                '</div>' +
                '<div class="epd-err" role="alert" hidden></div>' +
            '</div>';

        var w = { ref: ref, team: team, threadId: team ? parseInt(ref.slice(5), 10) : 0, min: false, el: el, lastId: 0, conv: null, thread: null, busy: false, subscribed: false, unread: 0, typingTimer: null, lastTypingSent: 0, lastDate: null, messages: [], ctxOpen: false, ctxSearch: '' };

        var head = el.querySelector('.epd-head');
        head.addEventListener('click', function (e) {
            var b = e.target.closest('[data-a]');
            var a = b && b.getAttribute('data-a');
            if (a === 'close') { closeWin(w); return; }
            if (a === 'menu') { if (w.min) setMin(w, false); toggleMenu(w); return; }
            if (a === 'info') { toggleContext(w); return; }
            setMin(w, !w.min);
        });
        head.addEventListener('keydown', function (e) { if (e.key === 'Enter' && e.target === head) setMin(w, !w.min); });

        var ta = el.querySelector('textarea');
        ta.addEventListener('input', function () {
            ta.style.height = 'auto'; ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
            if (team) { sendTyping(w); suggest(w); }
        });
        ta.addEventListener('keydown', function (e) {
            if (handlePopKeys(w, e)) return;
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); send(w); }
        });

        el.querySelector('.epd-foot').addEventListener('click', function (e) {
            var b = e.target.closest('[data-a]');
            var a = b && b.getAttribute('data-a');
            if (a === 'attach') el.querySelector('input[type="file"]').click();
            else if (a === 'note') { var on = b.getAttribute('aria-pressed') !== 'true'; b.setAttribute('aria-pressed', on ? 'true' : 'false'); el.classList.toggle('is-note', on); ta.focus(); }
            else if (a === 'canned') toggleCanned(w);
        });
        el.querySelector('.epd-send').addEventListener('click', function () { send(w); });

        var file = el.querySelector('input[type="file"]');
        file.addEventListener('change', function () { if (file.files[0]) upload(w, file.files[0]); file.value = ''; });

        el.querySelector('.epd-bar').addEventListener('click', function (e) {
            if (e.target.closest('[data-a="claim"]')) claim(w);
        });
        el.querySelector('.epd-menu-list').addEventListener('click', function (e) {
            var b = e.target.closest('[data-act]');
            if (!b) return;
            var act = b.getAttribute('data-act');
            toggleMenu(w, false);
            if (act === 'claim') claim(w);
            else if (act === 'resolve') action(w, 'resolve');
            else if (act === 'transfer') action(w, 'transfer', { admin_id: parseInt(b.getAttribute('data-id'), 10) });
            else if (act === 'help') askHelp(w, parseInt(b.getAttribute('data-id'), 10));
            else if (act === 'add') teamAction(w, 'members', { member_ids: [parseInt(b.getAttribute('data-id'), 10)] });
            else if (act === 'mute') teamAction(w, 'mute', { minutes: parseInt(b.getAttribute('data-min'), 10) || null });
            else if (act === 'leave') teamAction(w, 'leave').then(function (res) { if (res && res.ok) closeWin(w); });
            else if (act === 'open-chat') { var r = b.getAttribute('data-ref'); if (r) open(r); }
        });

        el.querySelector('.epd-body').addEventListener('click', function (e) { if (team) onTeamBodyClick(w, e); });
        el.querySelector('.epd-ctx').addEventListener('click', function (e) {
            var h = e.target.closest('[data-open-ref]');
            if (h) open(h.getAttribute('data-open-ref'));
        });

        if (!team) {
            el.style.setProperty('--k', KIND.customer);
            renderCanned(w);
        }
        return w;
    }

    function renderCanned(w) {
        if (w.team) return;
        w.el.querySelector('[data-a="canned"]').hidden = !canned.length;
    }

    function toggleCanned(w) {
        var foot = w.el.querySelector('.epd-foot');
        var old = foot.querySelector('.epd-pop');
        if (old) { old.remove(); return; }
        var box = document.createElement('div');
        box.className = 'epd-pop';
        box.setAttribute('data-pop', 'canned');
        box.innerHTML = canned.map(function (c, i) { return '<button type="button" class="' + (i === 0 ? 'is-on' : '') + '" data-canned="' + i + '">' + esc(c.title) + '<small>' + esc(c.body) + '</small></button>'; }).join('');
        box.addEventListener('mousedown', function (e) { e.preventDefault(); });
        box.addEventListener('click', function (e) { var b = e.target.closest('[data-canned]'); if (b) insertCanned(w, parseInt(b.getAttribute('data-canned'), 10)); });
        foot.appendChild(box);
        w.el.querySelector('textarea').focus();
    }
    function insertCanned(w, i) {
        var c = canned[i], ta = w.el.querySelector('textarea');
        if (c) { ta.value = (ta.value ? ta.value + '\n' : '') + c.body; ta.dispatchEvent(new Event('input')); }
        var box = w.el.querySelector('.epd-pop'); if (box) box.remove();
        ta.focus();
    }

    function toggleMenu(w, force) {
        var list = w.el.querySelector('.epd-menu-list');
        var show = force === undefined ? list.hidden : force;
        if (show) list.innerHTML = w.team ? teamMenu(w) : customerMenu(w);
        list.hidden = !show;
    }

    function customerMenu(w) {
        var c = w.conv || {};
        var h = '<a href="' + esc(c.console_url || '/marketplace/chat-console') + '">Deschide în consolă</a>';
        if (!c.closed && !c.mine) h += '<button type="button" data-act="claim">Preiau conversația</button>';
        if (!c.closed && operators.length) {
            h += '<div class="epd-sep"></div><div class="epd-lbl">Transferă către</div>';
            operators.forEach(function (o) { h += '<button type="button" data-act="transfer" data-id="' + o.id + '">' + esc(o.name) + '</button>'; });
        }
        var colleagues = window.epTeam ? window.epTeam.colleagues() : [];
        if (colleagues.length) {
            h += '<div class="epd-sep"></div><div class="epd-lbl">Cere ajutor de la</div>';
            colleagues.forEach(function (o) { h += '<button type="button" data-act="help" data-id="' + o.id + '">' + esc(o.name) + '</button>'; });
        }
        if (!c.closed) h += '<div class="epd-sep"></div><button type="button" data-act="resolve">Marchează rezolvată</button>';
        return h;
    }

    function teamMenu(w) {
        var t = w.thread || {};
        var h = '';
        if (t.chat_ref && LIVE_CHAT) h += '<button type="button" data-act="open-chat" data-ref="' + esc(t.chat_ref) + '">Deschide chatul clientului</button><div class="epd-sep"></div>';
        var st = window.epTeam && window.epTeam.thread(w.threadId);
        h += '<div class="epd-lbl">Notificări</div>';
        if (st && st.muted) h += '<button type="button" data-act="mute" data-min="0">Pornește notificările</button>';
        else h += '<button type="button" data-act="mute" data-min="60">Oprește 1 oră</button><button type="button" data-act="mute" data-min="480">Oprește 8 ore</button><button type="button" data-act="mute" data-min="10080">Oprește o săptămână</button>';
        if (t.type !== 'dm') {
            h += '<div class="epd-sep"></div><div class="epd-lbl">Membri (' + (t.members || []).length + ')</div>';
            (t.members || []).forEach(function (m) { h += '<div class="epd-member">' + esc(m.name) + (m.id === ME ? ' (tu)' : '') + '</div>'; });
            if (!t.is_system) {
                var ids = (t.members || []).map(function (m) { return m.id; });
                var others = (window.epTeam ? window.epTeam.colleagues() : []).filter(function (c) { return ids.indexOf(c.id) === -1; });
                if (others.length) {
                    h += '<div class="epd-sep"></div><div class="epd-lbl">Adaugă în conversație</div>';
                    others.forEach(function (c) { h += '<button type="button" data-act="add" data-id="' + c.id + '">+ ' + esc(c.name) + '</button>'; });
                }
                h += '<div class="epd-sep"></div><button type="button" data-act="leave">Părăsește conversația</button>';
            }
        }
        return h;
    }

    function unreadOf(w) {
        if (w.team) {
            var st = window.epTeam && window.epTeam.thread(w.threadId);
            return Math.max(w.unread || 0, st ? st.unread : 0);
        }
        return (w.conv && w.conv.unread) || 0;
    }
    function renderBadge(w) {
        var n = w.min ? unreadOf(w) : 0;
        var badge = w.el.querySelector('.epd-badge');
        badge.hidden = !n;
        badge.textContent = n > 99 ? '99+' : String(n || '');
        w.el.classList.toggle('has-unread', !!n);
    }

    // ================================================================ context vizitator
    function toggleContext(w, force) {
        w.ctxOpen = force === undefined ? !w.ctxOpen : force;
        w.el.querySelector('[data-a="info"]').setAttribute('aria-pressed', w.ctxOpen ? 'true' : 'false');
        w.el.querySelector('.epd-ctx').hidden = !w.ctxOpen;
        w.el.querySelector('.epd-body').hidden = w.ctxOpen;
        if (w.ctxOpen) loadContext(w);
    }

    function loadContext(w) {
        var box = w.el.querySelector('.epd-ctx');
        if (!box.innerHTML) box.innerHTML = '<p class="epd-muted">Se încarcă…</p>';
        req('GET', BASE + encodeURIComponent(w.ref) + '/context' + (w.ctxSearch ? '?search=' + encodeURIComponent(w.ctxSearch) : '')).then(function (res) {
            if (!res || !res.ok) { box.innerHTML = '<p class="epd-muted">Nu am putut încărca contextul.</p>'; return; }
            renderContext(w, res);
        }).catch(function () { box.innerHTML = '<p class="epd-muted">Fără conexiune.</p>'; });
    }

    function row(label, value, wide, html) {
        if (!value) value = '—';
        return '<div' + (wide ? ' class="is-wide"' : '') + '><dt>' + esc(label) + '</dt><dd>' + (html ? value : esc(value)) + '</dd></div>';
    }

    function renderContext(w, d) {
        var v = d.visitor || {};
        var h = '<section><h5>Context vizitator</h5><dl>'
            + row('Nume', v.name) + row('Tip', v.type)
            + (v.email ? row('Email', v.email, true) : '')
            + (v.event ? row('Eveniment', v.event_url ? '<a href="' + esc(v.event_url) + '" target="_blank" rel="noopener">' + esc(v.event) + '</a>' : esc(v.event), true, true) : '')
            + row('Operator', v.operator)
            + (v.page ? row('Pagină', '<a href="' + esc(v.page) + '" target="_blank" rel="noopener noreferrer">' + esc(v.page) + '</a>', true, true) : '')
            + row('IP', v.ip) + row('Dispozitiv', v.device) + row('Browser', v.browser) + row('Rezoluție', v.screen)
            + '</dl></section>';

        var hist = d.history || [];
        if (hist.length) {
            h += '<section class="epd-hist"><h5>Istoric conversații (' + hist.length + ')</h5>';
            hist.forEach(function (x) { h += '<button type="button" data-open-ref="' + esc(x.ref) + '"><span>' + esc(x.date) + '</span><span class="epd-tag">' + esc(x.status) + '</span></button>'; });
            h += '</section>';
        }

        var p = d.profile;
        if (p) {
            var cust = p.type === 'customer', s = p.stats || {};
            h += '<section><h5>' + (cust ? 'Istoric client' : 'Istoric organizator')
                + (cust ? '' : '<span>' + esc((s.events || 0) + ' evenimente · ' + (s.tickets_sold || 0) + ' bilete vândute') + '</span>') + '</h5>';
            if (cust) {
                h += '<div class="epd-stats"><span>' + esc((s.orders || 0) + ' comenzi') + '</span><span>' + esc((s.tickets || 0) + ' bilete') + '</span>'
                    + (s.ltv ? '<span>LTV: <b>' + esc(s.ltv) + '</b></span>' : '')
                    + (s.since ? '<span>Client din ' + esc(s.since) + ' · ' + esc(s.since_days) + ' zile</span>' : '') + '</div>'
                    + '<input type="search" class="epd-ctx-search" placeholder="Caută: eveniment, nr. comandă sau nr. bilet…" aria-label="Caută în istoricul clientului" value="' + esc(w.ctxSearch) + '">';
            }
            var ev = function (e, up) {
                var o = '<div class="epd-ev' + (up ? ' is-up' : '') + '"><div class="epd-ev-h">'
                    + (e.url ? '<a href="' + esc(e.url) + '" target="_blank" rel="noopener">' + esc(e.title) + '</a>' : '<span>' + esc(e.title) + '</span>')
                    + '<span class="epd-tag">' + esc(e.count) + '</span></div>'
                    + (e.date ? '<small>' + esc(e.date) + '</small>' : '');
                (e.orders || []).forEach(function (ord) {
                    o += '<div class="epd-ord">' + (ord.url ? '<a href="' + esc(ord.url) + '" target="_blank" rel="noopener" title="Status: ' + esc(ord.status) + '">' + esc(ord.number) + '</a>' : esc(ord.number))
                        + ' · ' + esc(ord.status) + ' · ' + esc(ord.detail) + '</div>';
                });
                return o + '</div>';
            };
            if ((p.upcoming || []).length) { h += '<div class="epd-sub">Urmează</div>'; p.upcoming.forEach(function (e) { h += ev(e, true); }); }
            if ((p.past || []).length) { h += '<div class="epd-sub">Trecute</div>'; p.past.forEach(function (e) { h += ev(e, false); }); }
            if (!(p.upcoming || []).length && !(p.past || []).length) {
                h += '<p class="epd-muted">' + (p.searching ? 'Niciun rezultat pentru căutare.' : (cust ? 'Nicio comandă găsită.' : 'Niciun eveniment găsit.')) + '</p>';
            }
            h += '</section>';
        }

        var box = w.el.querySelector('.epd-ctx');
        box.innerHTML = h;
        var search = box.querySelector('.epd-ctx-search');
        if (search) {
            var t = null;
            search.addEventListener('input', function () {
                clearTimeout(t);
                t = setTimeout(function () {
                    w.ctxSearch = search.value.trim();
                    loadContext(w);
                    setTimeout(function () { var s2 = box.querySelector('.epd-ctx-search'); if (s2) { s2.focus(); s2.setSelectionRange(s2.value.length, s2.value.length); } }, 60);
                }, 450);
            });
        }
    }

    // ================================================================ antet
    function renderCustomerMeta(w) {
        var c = w.conv;
        if (!c) return;
        w.el.querySelector('.epd-who b').textContent = c.name + (c.subject ? ' · ' + c.subject : '');
        w.el.querySelector('.epd-who small').textContent = c.type + ' · ' + (c.closed ? 'închisă' : (c.mine ? 'a ta' : (c.owner_name ? 'preluată de ' + c.owner_name : 'în coadă')));
        renderBadge(w);

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
        setClosed(w, !!c.closed, 'Conversația e închisă.');
    }

    function setClosed(w, closed, text) {
        var foot = w.el.querySelector('.epd-foot');
        var note = foot.querySelector('.epd-closed');
        foot.querySelector('.epd-compose').hidden = closed;
        if (closed && !note) { var p = document.createElement('div'); p.className = 'epd-closed'; p.textContent = text; foot.appendChild(p); }
        if (!closed && note) note.remove();
    }

    function renderTeamMeta(w) {
        var t = w.thread;
        if (!t) return;
        var color = kindColor(t.kind, t.partner_id);
        w.el.style.setProperty('--k', color);
        var ava = w.el.querySelector('.epd-ava');
        ava.classList.toggle('is-sq', t.kind !== 'dm');
        ava.innerHTML = t.kind === 'dm' ? esc(initials(t.name)) : (ICON[t.kind] || ICON.group);
        w.el.querySelector('.epd-who b').textContent = t.name;
        var sub = t.kind === 'dm' ? 'mesaj direct'
            : t.kind === 'help' ? 'cerere de ajutor · ' + t.members.length + ' colegi'
            : t.kind === 'team' ? 'toată echipa · ' + t.members.length + ' membri'
            : t.kind === 'department' ? 'departament · ' + t.members.length + ' membri'
            : 'grup · ' + t.members.length + ' membri';
        var st = window.epTeam && window.epTeam.thread(w.threadId);
        if (st && st.muted) sub += ' · fără notificări';
        w.el.querySelector('.epd-who small').textContent = sub;
        renderBadge(w);
    }

    // ================================================================ mesaje
    function customerMsgHtml(m) {
        if (m.kind === 'system') return '<div class="epd-sys">' + esc(m.body) + ' · ' + esc(m.time) + '</div>';
        var att = (m.attachments || []).map(function (a) {
            return a.image
                ? '<a href="' + esc(a.url) + '" target="_blank" rel="noopener"><img src="' + esc(a.url) + '" alt="' + esc(a.name) + '"></a>'
                : '<a href="' + esc(a.url) + '" target="_blank" rel="noopener">' + esc(a.name) + '</a>';
        }).join('');
        var meta = (m.kind === 'note' ? 'Notă internă · ' : '') + (m.author && m.kind !== 'in' ? m.author + ' · ' : '') + (m.time || '');
        return '<div class="epd-msg ' + m.kind + '" data-id="' + m.id + '">' + (m.body ? richText(m.body, false) : '') + att + '<span class="epd-meta">' + esc(meta) + '</span></div>';
    }

    function teamMsgHtml(w, m) {
        var out = '';
        if (m.date && m.date !== w.lastDate) { w.lastDate = m.date; out += '<div class="epd-day">' + esc(m.date) + '</div>'; }
        if (m.system) return out + '<div class="epd-sys" data-id="' + m.id + '">' + esc(m.body) + ' · ' + esc(m.time) + '</div>';

        var cls = 'epd-msg ' + (m.mine ? 'out' : 'in') + (m.mentions_me && !m.mine ? ' is-mention' : '') + (m.deleted ? ' is-deleted' : '');
        var html = '<div class="' + cls + '" data-id="' + m.id + '">';
        if (!m.mine && w.thread && w.thread.type !== 'dm') html += '<span class="epd-author">' + esc(m.author) + '</span>';
        if (m.deleted) html += 'Mesaj șters';
        else if (m.body) html += richText(m.body, true);
        (m.attachments || []).forEach(function (a) {
            html += '<div class="epd-att">';
            if (a.purged) html += '<span>' + esc(a.name) + ' · fișier expirat</span>';
            else {
                html += a.image
                    ? '<a href="' + esc(a.url) + '" target="_blank" rel="noopener"><img src="' + esc(a.url) + '" alt="' + esc(a.name) + '"></a>'
                    : '<a href="' + esc(a.url) + '" target="_blank" rel="noopener">' + esc(a.name) + '</a>';
                html += '<span>' + (a.keep ? 'păstrat până pe ' : 'se șterge pe ') + esc(a.expires) + '</span>';
                html += '<button type="button" data-keep="' + a.id + '" data-val="' + (a.keep ? '0' : '1') + '">' + (a.keep ? 'Nu mai păstra' : 'Păstrează un an') + '</button>';
            }
            html += '</div>';
        });
        html += '<span class="epd-meta">' + esc(m.time) + (m.edited && !m.deleted ? ' · editat' : '') + '</span></div>';
        if (m.mine && !m.deleted) {
            html += '<div class="epd-own" data-own="' + m.id + '">' + (m.editable ? '<button type="button" data-edit="' + m.id + '">Editează</button>' : '') + '<button type="button" data-del="' + m.id + '">Șterge</button></div>';
        }
        return out + html;
    }

    function onTeamBodyClick(w, e) {
        var keep = e.target.closest('[data-keep]');
        if (keep) {
            req('POST', TEAM + 'attachments/' + keep.getAttribute('data-keep') + '/keep', { keep: keep.getAttribute('data-val') === '1' })
                .then(function () { reload(w); });
            return;
        }
        var del = e.target.closest('[data-del]');
        if (del) {
            if (del.getAttribute('data-confirm') !== '1') { del.setAttribute('data-confirm', '1'); del.textContent = 'Sigur ștergi?'; return; }
            req('POST', TEAM + 'messages/' + del.getAttribute('data-del') + '/delete').then(function (res) {
                if (!res.ok) showErr(w, res.message || 'Nu am putut șterge mesajul.');
                reload(w);
            });
            return;
        }
        var ed = e.target.closest('[data-edit]');
        if (ed) { startEdit(w, parseInt(ed.getAttribute('data-edit'), 10)); return; }
        var save = e.target.closest('[data-edit-save]');
        if (save) {
            var box = save.closest('.epd-edit');
            req('POST', TEAM + 'messages/' + save.getAttribute('data-edit-save') + '/edit', { body: box.querySelector('textarea').value }).then(function (res) {
                if (!res.ok) showErr(w, res.message || 'Nu am putut salva.');
                reload(w);
            });
            return;
        }
        if (e.target.closest('[data-edit-cancel]')) reload(w);
    }

    function startEdit(w, id) {
        var msg = w.messages.filter(function (m) { return m.id === id; })[0];
        var bubble = w.el.querySelector('.epd-msg[data-id="' + id + '"]');
        if (!msg || !bubble) return;
        var own = w.el.querySelector('[data-own="' + id + '"]');
        if (own) own.remove();
        var box = document.createElement('div');
        box.className = 'epd-edit';
        box.innerHTML = '<textarea aria-label="Editează mesajul"></textarea><div><button type="button" class="epd-btn" data-edit-cancel="1">Renunță</button><button type="button" class="epd-btn is-pri" data-edit-save="' + id + '">Salvează</button></div>';
        box.querySelector('textarea').value = msg.body || '';
        bubble.replaceWith(box);
        box.querySelector('textarea').focus();
    }

    function sendTyping(w) {
        if (Date.now() - w.lastTypingSent < 3000) return;
        w.lastTypingSent = Date.now();
        req('POST', TEAM + 'threads/' + w.threadId + '/typing').catch(function () {});
    }

    function showTyping(w, name) {
        var el = w.el.querySelector('.epd-typing');
        el.textContent = name + ' scrie…';
        el.classList.add('is-on');
        clearTimeout(w.typingTimer);
        w.typingTimer = setTimeout(function () { el.classList.remove('is-on'); }, 4500);
    }

    // @mențiuni: sugestii din membrii conversației
    function suggest(w) {
        var ta = w.el.querySelector('textarea');
        var before = ta.value.slice(0, ta.selectionStart);
        var m = before.match(/(^|\s)@([^\s@]*)$/);
        var foot = w.el.querySelector('.epd-foot');
        var old = foot.querySelector('.epd-pop[data-pop="mention"]');
        if (!m || !w.thread) { if (old) old.remove(); return; }
        var q = m[2].toLowerCase();
        var list = (w.thread.members || []).filter(function (x) { return x.id !== ME && x.name.toLowerCase().indexOf(q) !== -1; }).slice(0, 6);
        if (w.thread.type !== 'dm' && 'toti'.indexOf(q) === 0) list.push({ id: 0, name: 'toti' });
        if (!list.length) { if (old) old.remove(); return; }
        var box = old || document.createElement('div');
        box.className = 'epd-pop';
        box.setAttribute('data-pop', 'mention');
        box.innerHTML = list.map(function (x, i) { return '<button type="button" class="' + (i === 0 ? 'is-on' : '') + '" data-name="' + esc(x.name.split(' ')[0]) + '">' + esc(x.id ? x.name : '@toti (toată conversația)') + '</button>'; }).join('');
        if (!old) {
            foot.appendChild(box);
            box.addEventListener('mousedown', function (e) { e.preventDefault(); });
            box.addEventListener('click', function (e) { var b = e.target.closest('[data-name]'); if (b) insertMention(w, b.getAttribute('data-name')); });
        }
    }
    function insertMention(w, first) {
        var ta = w.el.querySelector('textarea');
        var pos = ta.selectionStart;
        var before = ta.value.slice(0, pos).replace(/@([^\s@]*)$/, '@' + first + ' ');
        ta.value = before + ta.value.slice(pos);
        ta.selectionStart = ta.selectionEnd = before.length;
        var box = w.el.querySelector('.epd-pop'); if (box) box.remove();
        ta.focus();
    }
    function handlePopKeys(w, e) {
        var box = w.el.querySelector('.epd-pop');
        if (!box) return false;
        var items = box.querySelectorAll('button');
        var cur = box.querySelector('.is-on');
        var idx = Array.prototype.indexOf.call(items, cur);
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (cur) cur.classList.remove('is-on');
            idx = (idx + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length;
            items[idx].classList.add('is-on');
            return true;
        }
        if (e.key === 'Enter' || e.key === 'Tab') {
            e.preventDefault();
            if (cur && cur.hasAttribute('data-name')) insertMention(w, cur.getAttribute('data-name'));
            else if (cur && cur.hasAttribute('data-canned')) insertCanned(w, parseInt(cur.getAttribute('data-canned'), 10));
            return true;
        }
        if (e.key === 'Escape') { box.remove(); return true; }
        return false;
    }

    // ================================================================ încărcare
    function scrollDown(w) { var b = w.el.querySelector('.epd-body'); b.scrollTop = b.scrollHeight; }

    function reload(w) { w.lastId = 0; w.lastDate = null; refresh(w); }

    function refresh(w) {
        if (w.busy) { w.again = true; return Promise.resolve(); }
        w.busy = true;
        var read = !w.min && !w.ctxOpen && document.visibilityState === 'visible' ? 1 : 0;
        var url = w.team
            ? TEAM + 'threads/' + w.threadId + '?after=' + w.lastId + '&read=' + read
            : BASE + encodeURIComponent(w.ref) + '?after=' + w.lastId + '&read=' + read;
        return req('GET', url)
            .then(function (res) {
                if (res.__status === 404 || res.__status === 403) { closeWin(w); return; }
                if (!res || !res.ok) return;
                var body = w.el.querySelector('.epd-body');
                var nearBottom = body.scrollHeight - body.scrollTop - body.clientHeight < 60;
                var full = w.lastId === 0;
                if (full) { body.innerHTML = ''; w.messages = []; w.lastDate = null; }
                var fresh = res.messages || [];
                if (w.team) {
                    w.thread = res.thread;
                    fresh.forEach(function (m) {
                        w.messages.push(m);
                        body.insertAdjacentHTML('beforeend', teamMsgHtml(w, m));
                        w.lastId = Math.max(w.lastId, m.id);
                    });
                    renderTeamMeta(w);
                    if (read && fresh.length && window.epTeam) window.epTeam.reload();
                } else {
                    w.conv = res.conversation;
                    operators = res.operators || operators;
                    fresh.forEach(function (m) {
                        if (body.querySelector('[data-id="' + m.id + '"]')) return;
                        body.insertAdjacentHTML('beforeend', customerMsgHtml(m));
                        w.lastId = Math.max(w.lastId, m.id);
                    });
                    renderCustomerMeta(w);
                    subscribeCustomer(w);
                }
                if (full || nearBottom || fresh.length) scrollDown(w);
            })
            .catch(function () {})
            .then(function () { w.busy = false; if (w.again) { w.again = false; refresh(w); } });
    }

    function subscribeCustomer(w) {
        if (w.subscribed || !w.conv || !window.epRealtime) return;
        w.subscribed = true;
        var t = null;
        var go = function () { if (!find(w.ref)) return; clearTimeout(t); t = setTimeout(function () { refresh(w); }, 200); };
        window.epRealtime.on('chat.conversation.' + w.conv.id, true, 'message', go);
        window.epRealtime.on('chat.conversation.' + w.conv.id, true, 'conversation.updated', go);
    }

    // Evenimentele echipei vin prin bara de atenție (canalul team-user.{me}).
    window.addEventListener('ep:team-message', function (e) {
        var d = e.detail || {};
        var w = find('team:' + d.thread_id);
        if (!w) return;
        if (d.action === 'new') {
            if (w.min && d.author_id !== ME) { w.unread++; renderBadge(w); }
            refresh(w);
        } else {
            reload(w);
        }
    });
    window.addEventListener('ep:team-typing', function (e) {
        var d = e.detail || {};
        var w = find('team:' + d.thread_id);
        if (w && d.admin_id !== ME) showTyping(w, d.name || 'Un coleg');
    });
    // Numărul de necitite vine de pe server, deci rămâne și după ce schimbi pagina.
    window.addEventListener('ep:team-threads', function () {
        wins.forEach(function (w) { if (w.team) { if (!w.min) w.unread = 0; renderBadge(w); } });
    });

    // ================================================================ acțiuni
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
        btn.disabled = true;
        var done = function (res) {
            btn.disabled = false;
            if (!res.ok) { showErr(w, res.message || 'Mesajul nu a plecat. Încearcă din nou.'); return; }
            ta.value = ''; ta.style.height = '';
            refresh(w);
        };
        var fail = function () { btn.disabled = false; showErr(w, 'Fără conexiune. Mesajul nu a plecat.'); };
        if (w.team) {
            var fd = new FormData(); fd.append('body', text);
            req('POST', TEAM + 'threads/' + w.threadId + '/messages', fd).then(done).catch(fail);
        } else {
            var internal = w.el.querySelector('[data-a="note"]').getAttribute('aria-pressed') === 'true';
            req('POST', BASE + encodeURIComponent(w.ref) + '/reply', { text: text, internal: internal }).then(done).catch(fail);
        }
    }

    function upload(w, f) {
        if (w.team) {
            if (f.size > 10 * 1024 * 1024) { showErr(w, 'Fișierul e prea mare (maxim 10 MB).'); return; }
            var ta = w.el.querySelector('textarea');
            var fd = new FormData(); fd.append('file', f); fd.append('body', ta.value.trim());
            req('POST', TEAM + 'threads/' + w.threadId + '/messages', fd).then(function (res) {
                if (!res.ok) { showErr(w, res.message || 'Fișierul nu s-a încărcat.'); return; }
                ta.value = ''; ta.style.height = '';
                refresh(w);
            }).catch(function () { showErr(w, 'Fără conexiune. Fișierul nu s-a încărcat.'); });
            return;
        }
        if (!w.conv) return;
        if (f.type.indexOf('image/') !== 0) { showErr(w, 'Clienților li se pot trimite doar imagini.'); return; }
        if (f.size > 3 * 1024 * 1024) { showErr(w, 'Imaginea e prea mare (maxim 3 MB).'); return; }
        var reader = new FileReader();
        reader.onload = function () {
            req('POST', w.conv.attach_url, { data: reader.result, name: f.name }).then(function (res) {
                if (!res.ok) { showErr(w, res.message || 'Imaginea nu s-a încărcat.'); return; }
                refresh(w);
            }).catch(function () { showErr(w, 'Fără conexiune. Imaginea nu s-a încărcat.'); });
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

    function teamAction(w, name, data) {
        return req('POST', TEAM + 'threads/' + w.threadId + '/' + name, data || {}).then(function (res) {
            if (!res.ok) showErr(w, res.message || 'Acțiunea nu a reușit.');
            if (window.epTeam) window.epTeam.reload().then(function () { renderTeamMeta(w); });
            if (name !== 'leave') refresh(w);
            return res;
        });
    }

    function askHelp(w, adminId) {
        if (!w.conv || !adminId) return;
        req('POST', TEAM + 'help', { conversation_ref: w.conv.ref, member_ids: [adminId] }).then(function (res) {
            if (!res.ok) { showErr(w, res.message || 'Nu am putut trimite cererea.'); return; }
            if (window.epTeam) window.epTeam.reload();
            openTeam(res.thread_id);
        });
    }

    // ================================================================ API
    function open(ref, opts) {
        opts = opts || {};
        if (!isTeam(ref) && !LIVE_CHAT) return null;
        var w = find(ref);
        if (!w) {
            w = build(ref);
            wins.unshift(w);
            root.appendChild(w.el);
            if (opts.min) { w.min = true; w.el.classList.add('is-min'); }
            refresh(w);
        }
        if (!opts.min) {
            setMin(w, false, true);
            if (opts.focus !== false) setTimeout(function () { w.el.querySelector('textarea').focus(); }, 50);
        }
        if (!opts.restore) saveState();
        return w;
    }
    function openTeam(id) { return open('team:' + id); }

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

    window.epDock = {
        open: open,
        openTeam: openTeam,
        isFocused: function (ref) { var w = find(ref); return !!w && !w.min && document.hasFocus(); }
    };

    req('GET', BASE + 'state').then(function (res) {
        if (!res || !res.ok) return;
        canned = res.canned || [];
        (res.windows || []).slice().reverse().forEach(function (s) { open(s.ref, { min: s.min, restore: true, focus: false }); });
        wins.forEach(renderCanned);
        enforceOpenLimit(null);
    }).catch(function () {});
})();
</script>
