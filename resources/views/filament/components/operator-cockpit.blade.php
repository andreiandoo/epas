{{-- Bara de atenție a operatorilor (microserviciul operator-cockpit). Injectată pe body.end în panoul marketplace. --}}
<style>
    #epc-root {
        --epc-chat: #2fd3a6; --epc-ticket: #f2645f; --epc-todo: #f2b53d; --epc-artist: #a98bfa; --epc-event: #4ea8f5;
        --epc-rail: #ffffff; --epc-drawer: #ffffff; --epc-line: #e3e7ec; --epc-fg: #17202a; --epc-muted: #66717f;
        --epc-hover: #f2f4f7; --epc-seg: #eef1f5; --epc-seg-on: #ffffff; --epc-btn: #17202a; --epc-btn-fg: #ffffff;
        --epc-late: #d63c37; --epc-rail-w: 56px;
        font-family: inherit;
    }
    .dark #epc-root {
        --epc-rail: #0c0f13; --epc-drawer: #141920; --epc-line: #2b333d; --epc-fg: #e6eaef; --epc-muted: #8a95a3;
        --epc-hover: #1e252d; --epc-seg: #0e1217; --epc-seg-on: #1e252d; --epc-btn: #dfe5ec; --epc-btn-fg: #0f1318;
        --epc-late: #f2645f;
    }
    @media (min-width: 1024px) {
        html.epc-on body { padding-right: var(--epc-rail-w, 56px); }
        html.epc-on #ep-float-save { right: calc(3rem + 56px); }
    }

    .epc-rail {
        position: fixed; top: 0; right: 0; bottom: 0; width: var(--epc-rail-w); z-index: 36;
        background: var(--epc-rail); border-left: 1px solid var(--epc-line);
        display: none; flex-direction: column; align-items: center; gap: 10px; padding: 14px 0;
    }
    @media (min-width: 1024px) { .epc-rail { display: flex; } }
    .epc-rail-lbl { font-size: 9px; letter-spacing: .1em; text-transform: uppercase; color: var(--epc-muted); font-weight: 600; }
    .epc-tile {
        position: relative; width: 38px; height: 38px; border-radius: 9px; border: 0; cursor: pointer;
        display: grid; place-items: center; font-weight: 700; font-size: 13px; line-height: 1;
        color: #0c0f13; background: var(--col); transition: transform .15s ease, box-shadow .15s ease;
    }
    .epc-tile:hover, .epc-tile:focus-visible { transform: translateX(-3px); outline: none; box-shadow: 0 0 0 2px var(--epc-rail), 0 0 0 4px var(--col); }
    .epc-tile.is-empty { background: transparent; color: var(--col); box-shadow: inset 0 0 0 1.5px color-mix(in srgb, var(--col) 45%, transparent); opacity: .75; }
    .epc-tile { --cut: var(--col); }
    .epc-tile.is-empty { --cut: var(--epc-rail); }
    .epc-tile svg { width: 21px; height: 21px; display: block; }
    /* Contorul: etichetă cu fundal, ridicată ca un <sup> în colțul iconiței */
    .epc-tile .epc-count {
        position: absolute; top: -8px; right: -10px; min-width: 19px; height: 19px; padding: 0 5px;
        border-radius: 10px; font-size: 10.5px; font-weight: 700; line-height: 15px; text-align: center;
        background: var(--epc-fg); color: var(--epc-rail); border: 2px solid var(--epc-rail);
        font-variant-numeric: tabular-nums; pointer-events: none;
    }
    .epc-tile .epc-count.is-new { background: #f2645f; color: #fff; }
    .epc-tile.is-hot::after { content: ""; position: absolute; inset: -5px; border-radius: 12px; border: 2px solid var(--col); animation: epc-pulse 1.6s ease-out infinite; pointer-events: none; }
    @keyframes epc-pulse { from { opacity: .9; transform: scale(.92); } to { opacity: 0; transform: scale(1.18); } }
    .epc-spacer { flex: 1; }
    .epc-icon-btn { width: 36px; height: 36px; border-radius: 8px; display: grid; place-items: center; background: transparent; color: var(--epc-muted); border: 1px solid var(--epc-line); cursor: pointer; }
    .epc-icon-btn:hover, .epc-icon-btn:focus-visible { color: var(--epc-fg); outline: none; border-color: var(--epc-muted); }
    .epc-icon-btn svg { width: 18px; height: 18px; }

    .epc-fab {
        position: fixed; right: 16px; bottom: 84px; z-index: 36; height: 44px; min-width: 44px; padding: 0 14px;
        border-radius: 22px; border: 0; cursor: pointer; display: flex; align-items: center; gap: 8px;
        background: var(--epc-btn); color: var(--epc-btn-fg); font-weight: 700; font-size: 14px;
        box-shadow: 0 6px 20px rgba(0,0,0,.25);
    }
    .epc-fab .epc-fab-dot { width: 10px; height: 10px; border-radius: 50%; background: var(--epc-late); }
    @media (min-width: 1024px) { .epc-fab { display: none; } }

    .epc-drawer {
        position: fixed; top: 0; bottom: 0; right: 0; width: min(400px, 100vw); z-index: 35;
        background: var(--epc-drawer); border-left: 1px solid var(--epc-line); color: var(--epc-fg);
        box-shadow: -24px 0 48px rgba(0,0,0,.25);
        transform: translateX(105%); transition: transform .26s cubic-bezier(.2,.8,.2,1);
        display: flex; flex-direction: column; visibility: hidden;
    }
    @media (min-width: 1024px) { .epc-drawer { right: var(--epc-rail-w); transform: translateX(calc(100% + var(--epc-rail-w))); } }
    #epc-root.is-open .epc-drawer { transform: translateX(0); visibility: visible; }
    .epc-head { padding: 14px 16px 10px; border-bottom: 1px solid var(--epc-line); display: grid; gap: 10px; background: var(--epc-rail); }
    .epc-head-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .epc-title { font-weight: 600; font-size: 15px; }
    .epc-head-actions { display: flex; gap: 6px; }
    .epc-pill { border: 1px solid var(--epc-line); background: transparent; color: var(--epc-muted); font-size: 12px; font-weight: 500; padding: 4px 9px; border-radius: 6px; cursor: pointer; }
    .epc-pill[aria-pressed="true"] { color: var(--epc-fg); border-color: var(--epc-fg); }
    .epc-pill:focus-visible { outline: 2px solid var(--epc-event); outline-offset: 1px; }
    .epc-seg { display: flex; gap: 4px; background: var(--epc-seg); padding: 3px; border-radius: 7px; }
    .epc-seg button { flex: 1; border: 0; background: transparent; color: var(--epc-muted); font-size: 12px; font-weight: 500; padding: 5px 6px; border-radius: 5px; cursor: pointer; white-space: nowrap; }
    .epc-seg button.is-on { background: var(--epc-seg-on); color: var(--epc-fg); box-shadow: 0 1px 2px rgba(0,0,0,.08); }
    .epc-body { overflow-y: auto; padding: 0 0 16px; flex: 1; overscroll-behavior: contain; }
    .epc-grp { position: sticky; top: 0; margin-top: 0; background: var(--epc-drawer); padding: 10px 16px 6px; display: flex; align-items: center; gap: 8px; font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--epc-muted); z-index: 1; }
    .epc-grp .epc-sq { width: 10px; height: 10px; border-radius: 2px; flex: none; }
    .epc-grp .epc-n { margin-left: auto; font-variant-numeric: tabular-nums; }
    .epc-sub { padding: 8px 18px 4px; font-size: 11.5px; font-weight: 600; color: var(--epc-fg); display: flex; align-items: center; gap: 6px; }
    .epc-sub small { color: var(--epc-muted); font-weight: 400; }
    .epc-item { margin: 0 8px; padding: 9px 10px; border-radius: 7px; display: grid; grid-template-columns: minmax(0,1fr) auto; gap: 2px 10px; }
    .epc-item:hover { background: var(--epc-hover); }
    .epc-item-t { color: var(--epc-fg); font-weight: 500; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; }
    .epc-item-t:hover { text-decoration: underline; }
    .epc-item-m { color: var(--epc-muted); font-size: 12px; grid-column: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .epc-item-a { grid-row: 1 / span 2; grid-column: 2; display: flex; gap: 6px; align-items: center; }
    .epc-chip { font-size: 11px; padding: 1px 7px; border-radius: 999px; border: 1px solid var(--epc-line); color: var(--epc-muted); white-space: nowrap; }
    .epc-chip.is-late { color: var(--epc-late); border-color: color-mix(in srgb, var(--epc-late) 45%, transparent); }
    .epc-btn { font-size: 12px; font-weight: 500; padding: 4px 9px; border-radius: 6px; border: 1px solid var(--epc-line); background: transparent; color: var(--epc-fg); cursor: pointer; white-space: nowrap; }
    .epc-btn.is-pri { background: var(--epc-btn); color: var(--epc-btn-fg); border-color: var(--epc-btn); }
    .epc-btn:disabled { opacity: .5; cursor: default; }
    .epc-btn:focus-visible { outline: 2px solid var(--epc-event); outline-offset: 1px; }
    .epc-snooze { border: 0; background: transparent; color: var(--epc-muted); cursor: pointer; padding: 2px; display: grid; place-items: center; border-radius: 5px; }
    .epc-snooze:hover, .epc-snooze:focus-visible { color: var(--epc-fg); outline: none; background: var(--epc-hover); }
    .epc-snooze svg { width: 15px; height: 15px; }
    .epc-tabs { display: flex; gap: 16px; }
    .epc-tab { border: 0; background: transparent; padding: 0 0 4px; font-size: 14px; font-weight: 600; color: var(--epc-muted); cursor: pointer; border-bottom: 2px solid transparent; display: inline-flex; align-items: center; gap: 6px; }
    .epc-tab.is-on { color: var(--epc-fg); border-bottom-color: var(--epc-fg); }
    .epc-tab:focus-visible { outline: 2px solid var(--epc-event); outline-offset: 2px; }
    .epc-tab .epc-tab-n { font-size: 11px; font-weight: 700; min-width: 18px; height: 18px; padding: 0 5px; border-radius: 9px; background: var(--epc-seg); color: var(--epc-fg); display: inline-grid; place-items: center; }
    .epc-jbtn { position: relative; }
    .epc-jbtn .epc-jn { position: absolute; top: -6px; right: -7px; min-width: 17px; height: 17px; padding: 0 4px; border-radius: 9px; background: var(--epc-fg); color: var(--epc-rail); font-size: 10px; font-weight: 700; display: grid; place-items: center; border: 2px solid var(--epc-rail); }
    .epc-jday { position: sticky; top: 0; background: var(--epc-drawer); padding: 10px 16px 6px; font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--epc-muted); z-index: 1; }
    .epc-jrow { margin: 0 8px; padding: 8px 10px; border-radius: 7px; display: grid; grid-template-columns: 10px minmax(0,1fr) auto; gap: 2px 10px; align-items: start; }
    .epc-jrow:hover { background: var(--epc-hover); }
    .epc-jrow .epc-jdot { width: 8px; height: 8px; border-radius: 50%; margin-top: 6px; }
    .epc-jrow .epc-jt { font-size: 13px; font-weight: 500; color: var(--epc-fg); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-decoration: none; }
    .epc-jrow a.epc-jt:hover { text-decoration: underline; }
    .epc-jrow .epc-jm { grid-column: 2; font-size: 12px; color: var(--epc-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .epc-jrow .epc-jtime { grid-row: 1; grid-column: 3; font-size: 11.5px; color: var(--epc-muted); font-variant-numeric: tabular-nums; white-space: nowrap; }
    .epc-jrow.is-att .epc-jt { font-weight: 700; }
    .epc-jrow .epc-jread { grid-column: 3; grid-row: 2; justify-self: end; }
    .epc-team { display: grid; gap: 8px; justify-items: center; }
    .epc-ava { position: relative; width: 34px; height: 34px; border-radius: 50%; border: 0; cursor: pointer; display: grid; place-items: center; font-size: 12px; font-weight: 700; color: #fff; background: var(--ava, #6b4f8f); }
    .epc-ava.is-group { border-radius: 9px; }
    .epc-ava:focus-visible, .epc-ava:hover { outline: none; box-shadow: 0 0 0 2px var(--epc-rail), 0 0 0 4px var(--ava, #6b4f8f); }
    .epc-ava .epc-count { position: absolute; top: -7px; right: -9px; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 9px; font-size: 10px; font-weight: 700; line-height: 14px; text-align: center; background: #ec7cc0; color: #0c0f13; border: 2px solid var(--epc-rail); }
    .epc-ava .epc-count.is-mention { background: #f2645f; color: #fff; }
    .epc-plus { width: 34px; height: 34px; border-radius: 9px; border: 1px dashed var(--epc-line); background: transparent; color: var(--epc-muted); cursor: pointer; font-size: 18px; line-height: 1; }
    .epc-plus:hover, .epc-plus:focus-visible { color: var(--epc-fg); border-color: var(--epc-muted); outline: none; }
    .epc-sep { width: 28px; height: 1px; background: var(--epc-line); margin: 2px 0; }
    .epc-tbar { display: flex; gap: 6px; padding: 10px 12px 6px; }
    .epc-tbar input { flex: 1; min-width: 0; font-size: 12.5px; padding: 5px 9px; border-radius: 6px; border: 1px solid var(--epc-line); background: transparent; color: var(--epc-fg); }
    .epc-trow { margin: 0 8px; padding: 8px 10px; border-radius: 7px; display: grid; grid-template-columns: 34px minmax(0,1fr) auto; gap: 1px 10px; align-items: center; cursor: pointer; border: 0; background: transparent; width: calc(100% - 16px); text-align: left; color: var(--epc-fg); font: inherit; }
    .epc-trow:hover, .epc-trow:focus-visible { background: var(--epc-hover); outline: none; }
    .epc-trow .epc-ava { grid-row: 1 / span 2; cursor: inherit; }
    .epc-trow .epc-tn { font-size: 13px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .epc-trow .epc-tl { grid-column: 2; font-size: 12px; color: var(--epc-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .epc-trow .epc-tu { grid-row: 1 / span 2; grid-column: 3; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px; background: #ec7cc0; color: #0c0f13; font-size: 11px; font-weight: 700; display: grid; place-items: center; }
    .epc-trow .epc-tu.is-mention { background: #f2645f; color: #fff; }
    .epc-trow.is-unread .epc-tl { color: var(--epc-fg); }
    .epc-form { margin: 6px 12px 10px; padding: 10px; border: 1px solid var(--epc-line); border-radius: 8px; display: grid; gap: 8px; font-size: 12.5px; }
    .epc-form input[type="text"], .epc-form select { font-size: 12.5px; padding: 6px 8px; border-radius: 6px; border: 1px solid var(--epc-line); background: transparent; color: var(--epc-fg); }
    .epc-form .epc-checks { max-height: 160px; overflow-y: auto; display: grid; gap: 4px; }
    .epc-form label { display: flex; gap: 6px; align-items: center; }
    .epc-form .epc-row { display: flex; gap: 6px; justify-content: flex-end; }
    .epc-set { padding: 12px 16px; display: grid; gap: 14px; font-size: 13px; }
    .epc-set h4 { margin: 0; font-size: 11px; font-weight: 600; letter-spacing: .07em; text-transform: uppercase; color: var(--epc-muted); }
    .epc-set .epc-opt { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .epc-set .epc-opt small { display: block; color: var(--epc-muted); font-size: 12px; }
    .epc-set .epc-opts { display: grid; gap: 10px; }
    .epc-set .epc-dnd { display: flex; flex-wrap: wrap; gap: 6px; }
    .epc-set .epc-dnd-state { font-size: 12.5px; color: var(--epc-fg); }
    .epc-empty { padding: 28px 16px; color: var(--epc-muted); font-size: 13px; }
    .epc-msg { margin: 8px 12px 0; padding: 8px 10px; border-radius: 7px; font-size: 12.5px; background: color-mix(in srgb, var(--epc-late) 14%, transparent); color: var(--epc-fg); }
    .epc-foot { border-top: 1px solid var(--epc-line); padding: 10px 16px; display: flex; justify-content: space-between; align-items: center; gap: 8px; font-size: 12px; color: var(--epc-muted); }
    .epc-foot a { color: var(--epc-fg); text-decoration: none; font-weight: 500; }
    .epc-foot a:hover { text-decoration: underline; }
    @media (prefers-reduced-motion: reduce) {
        .epc-tile.is-hot::after { animation: none; }
        .epc-drawer { transition: none; }
    }
</style>

<div id="epc-root" data-me="{{ (int) ($meId ?? 0) }}" data-poll-ms="{{ (int) config('operator_cockpit.poll_interval_ms', 15000) }}" data-client-id="{{ (int) ($clientId ?? 0) }}">
    <nav class="epc-rail" aria-label="Bara de atenție">
        <span class="epc-rail-lbl">Acțiuni</span>
        <div class="epc-tiles" style="display:grid;gap:10px"></div>
        <div class="epc-sep"></div>
        <span class="epc-rail-lbl">Echipa</span>
        <div class="epc-team"></div>
        <button type="button" class="epc-plus" title="Conversație nouă cu echipa" aria-label="Conversație nouă cu echipa">+</button>
        <div class="epc-spacer"></div>
        <button type="button" class="epc-icon-btn epc-setbtn" title="Sunete și Nu deranja" aria-label="Sunete și Nu deranja">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0"/></svg>
        </button>
        <button type="button" class="epc-icon-btn epc-jbtn" title="Jurnalul zilei" aria-label="Jurnalul zilei">
            <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h10"/></svg>
            <span class="epc-jn" hidden></span>
        </button>
    </nav>

    <button type="button" class="epc-fab" aria-label="Deschide bara de atenție" hidden>
        <span class="epc-fab-n">0</span><span class="epc-fab-dot" hidden></span>
    </button>

    <aside class="epc-drawer" aria-label="De rezolvat" aria-hidden="true">
        <div class="epc-head">
            <div class="epc-head-row">
                <div class="epc-tabs" role="tablist">
                    <button type="button" class="epc-tab is-on" role="tab" data-tab="todo" aria-selected="true">De rezolvat</button>
                    <button type="button" class="epc-tab" role="tab" data-tab="journal" aria-selected="false">Jurnal <span class="epc-tab-n" hidden></span></button>
                    <button type="button" class="epc-tab" role="tab" data-tab="team" aria-selected="false">Echipa <span class="epc-tab-n epc-team-n" hidden></span></button>
                </div>
                <div class="epc-head-actions">
                    <button type="button" class="epc-pill epc-pin" aria-pressed="false">Fixează</button>
                    <button type="button" class="epc-pill epc-close" aria-label="Închide">Închide</button>
                </div>
            </div>
            <div class="epc-seg" role="group" aria-label="Filtru" data-for="todo">
                <button type="button" class="is-on" data-f="all">Toate</button>
                <button type="button" data-f="mine">Ale mele</button>
                <button type="button" data-f="pool">Nealocate</button>
                <button type="button" data-f="others" hidden>Ale altora</button>
            </div>
        </div>
        <div class="epc-msg" role="status" hidden></div>
        <div class="epc-body"></div>
        <div class="epc-body epc-jbody" hidden></div>
        <div class="epc-body epc-tbody" hidden></div>
        <div class="epc-body epc-sbody" hidden></div>
        <div class="epc-foot">
            <a href="/marketplace/notifications">Toate notificările (3 zile)</a>
            <button type="button" class="epc-pill epc-sound">Sunete</button>
        </div>
    </aside>
</div>

<script>
(function () {
    if (window.__epcInit) return;
    window.__epcInit = true;

    var CATS = ['chat', 'ticket', 'todo', 'artist', 'event'];
    var LABEL = { chat: 'Chat clienți', ticket: 'Tichete suport', todo: 'ToDos', artist: 'Conturi artist în review', event: 'Evenimente de revizuit' };
    var COLOR = { chat: 'var(--epc-chat)', ticket: 'var(--epc-ticket)', todo: 'var(--epc-todo)', artist: 'var(--epc-artist)', event: 'var(--epc-event)' };
    // Iconițe pline per categorie; detaliile interioare se „decupează” cu var(--cut).
    var ICONS = {
        chat: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 3.5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-9.2L6 21.2V17.5H4a2 2 0 0 1-2-2v-10a2 2 0 0 1 2-2z"/><path stroke="var(--cut)" stroke-width="1.8" stroke-linecap="round" d="M7 9h10M7 12.5h6"/></svg>',
        ticket: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M4 5h16a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4V7a2 2 0 0 1 2-2z"/><path stroke="var(--cut)" stroke-width="1.6" stroke-dasharray="1.6 1.8" d="M15 6.5v11"/></svg>',
        todo: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 2.5h6a1.5 1.5 0 0 1 1.5 1.5H18a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h1.5A1.5 1.5 0 0 1 9 2.5z"/><path fill="none" stroke="var(--cut)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M8 13.5l2.7 2.7L16.5 10"/></svg>',
        artist: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M20 2.5v13.2a3.3 3.3 0 1 1-2-3V7.4l-8 1.6v8.7a3.3 3.3 0 1 1-2-3V5.3z"/></svg>',
        event: '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M7 2h2v2h6V2h2v2h2a2 2 0 0 1 2 2v13a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2z"/><path stroke="var(--cut)" stroke-width="1.6" d="M3 9h18"/><path fill="var(--cut)" d="M7 12h3v3H7zM14 12h3v3h-3z"/></svg>'
    };
    var LS_PIN = 'epc_pinned', LS_SEEN = 'epc_seen', LS_KNOWN = 'epc_known';

    var root = document.getElementById('epc-root');
    if (!root) return;
    document.documentElement.classList.add('epc-on');

    var tiles = root.querySelector('.epc-tiles');
    var body = root.querySelector('.epc-body');
    var drawer = root.querySelector('.epc-drawer');
    var rail = root.querySelector('.epc-rail');
    var pinBtn = root.querySelector('.epc-pin');
    var soundBtn = root.querySelector('.epc-sound');
    var tbody = root.querySelector('.epc-tbody');
    var sbody = root.querySelector('.epc-sbody');
    var meId = parseInt(root.getAttribute('data-me'), 10) || 0;
    var team = { threads: [], colleagues: [], loaded: false, form: null, filter: '' };
    var msg = root.querySelector('.epc-msg');
    var fab = root.querySelector('.epc-fab');
    var pollMs = parseInt(root.getAttribute('data-poll-ms'), 10) || 15000;

    var state = { items: {}, filter: 'all', super: false, journal: [], journalUnread: 0, tab: 'todo' };
    var jbody = root.querySelector('.epc-jbody');
    var LS_JSEEN = 'epc_journal_seen';
    var JCOLOR = { success: '#2fb67c', warning: '#f2b53d', danger: '#f2645f', info: '#4ea8f5', primary: '#a98bfa' };
    var closeTimer = null, pollTimer = null, msgTimer = null, signalTimer = null, lastPoll = 0;
    var clientId = parseInt(root.getAttribute('data-client-id'), 10) || 0;
    // Cu Reverb conectat, polling-ul devine doar plasă de siguranță.
    var SAFETY_MS = 60000;

    function lsGet(k, d) { try { var v = localStorage.getItem(k); return v === null ? d : v; } catch (e) { return d; } }
    function lsSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
    function lsJson(k) { try { return JSON.parse(localStorage.getItem(k) || '[]'); } catch (e) { return []; } }
    function csrf() { var m = document.querySelector('meta[name="csrf-token"]'); return m ? m.getAttribute('content') : ''; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    // ---------- sunete: cinci tipuri distincte, sintetizate (fără fișiere audio) ----------
    // action = element nou în bară; chat_new = client nou în coadă; chat_mine = mesaj pe chatul meu;
    // dm = mesaj intern 1:1 sau @mențiune; group = mesaj în grup.
    var audioCtx = null;
    var prefs = { sounds: { action: true, chat: true, dm: true, group: true }, dnd_until: null };
    function note(f, at, dur, type, vol) {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (!AC || !audioCtx) return;
        if (audioCtx.state === 'suspended') audioCtx.resume().catch(function () {});
        var t0 = audioCtx.currentTime + at, o = audioCtx.createOscillator(), g = audioCtx.createGain();
        o.type = type || 'sine'; o.frequency.setValueAtTime(f, t0);
        g.gain.setValueAtTime(0.0001, t0); g.gain.linearRampToValueAtTime(vol || 0.12, t0 + 0.012); g.gain.exponentialRampToValueAtTime(0.001, t0 + dur);
        o.connect(g); g.connect(audioCtx.destination); o.start(t0); o.stop(t0 + dur + 0.02);
    }
    function dndActive() { return !!prefs.dnd_until && new Date(prefs.dnd_until).getTime() > Date.now(); }
    var SOUND_PREF = { action: 'action', chat_new: 'chat', chat_mine: 'chat', dm: 'dm', group: 'group' };
    var lastPlayed = {};
    window.epSound = {
        play: function (kind) {
            if (!audioCtx) return;
            // „Nu deranja” lasă să treacă doar mesajele clienților de pe chat-urile mele.
            if (dndActive() && kind !== 'chat_mine') return;
            if (prefs.sounds[SOUND_PREF[kind]] === false) return;
            if (lastPlayed[kind] && Date.now() - lastPlayed[kind] < 1200) return;
            lastPlayed[kind] = Date.now();
            if (kind === 'action') { note(784, 0, 0.16, 'triangle', 0.14); note(587, 0.12, 0.22, 'triangle', 0.14); }
            else if (kind === 'chat_new') { note(660, 0, 0.18); note(880, 0.09, 0.18); }
            else if (kind === 'chat_mine') { note(520, 0, 0.14); }
            else if (kind === 'dm') { note(1180, 0, 0.07, 'sine', 0.1); note(1400, 0.09, 0.09, 'sine', 0.1); }
            else if (kind === 'group') { note(392, 0, 0.28, 'sine', 0.13); }
        },
        preview: function (kind) { var keep = prefs; prefs = { sounds: { action: true, chat: true, dm: true, group: true }, dnd_until: null }; delete lastPlayed[kind]; window.epSound.play(kind); prefs = keep; }
    };
    function playNew() { window.epSound.play('action'); }
    document.addEventListener('click', function unlock() {
        var AC = window.AudioContext || window.webkitAudioContext;
        if (AC && !audioCtx) { try { audioCtx = new AC(); } catch (e) {} }
        document.removeEventListener('click', unlock);
    });
    soundBtn.addEventListener('click', function () { open(); setTab('settings'); });

    // ---------- deschidere / închidere ----------
    function isPinned() { return lsGet(LS_PIN, '0') === '1'; }
    function open() {
        clearTimeout(closeTimer);
        if (root.classList.contains('is-open')) return;
        root.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        markSeen();
    }
    function close() {
        clearTimeout(closeTimer);
        root.classList.remove('is-open');
        drawer.setAttribute('aria-hidden', 'true');
    }
    function scheduleClose() {
        if (isPinned()) return;
        clearTimeout(closeTimer);
        closeTimer = setTimeout(close, 280);
    }
    function renderPin() {
        var p = isPinned();
        pinBtn.setAttribute('aria-pressed', p ? 'true' : 'false');
        pinBtn.textContent = p ? 'Fixat' : 'Fixează';
    }
    pinBtn.addEventListener('click', function () { lsSet(LS_PIN, isPinned() ? '0' : '1'); renderPin(); });
    root.querySelector('.epc-close').addEventListener('click', function () { lsSet(LS_PIN, '0'); renderPin(); close(); });
    tiles.addEventListener('mouseenter', open);
    [rail, drawer].forEach(function (el) {
        el.addEventListener('mouseleave', scheduleClose);
        el.addEventListener('mouseenter', function () { clearTimeout(closeTimer); });
    });
    fab.addEventListener('click', function () { root.classList.contains('is-open') ? close() : open(); });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && root.classList.contains('is-open')) { lsSet(LS_PIN, '0'); renderPin(); close(); }
    });

    function setTab(tab) {
        state.tab = tab;
        root.querySelectorAll('.epc-tab').forEach(function (t) {
            var on = t.getAttribute('data-tab') === tab;
            t.classList.toggle('is-on', on);
            t.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        root.querySelector('.epc-seg').hidden = tab !== 'todo';
        body.hidden = tab !== 'todo';
        jbody.hidden = tab !== 'journal';
        tbody.hidden = tab !== 'team';
        sbody.hidden = tab !== 'settings';
        if (tab === 'journal') markJournalSeen();
        if (tab === 'team') renderTeamTab();
        if (tab === 'settings') renderSettings();
    }
    root.querySelectorAll('.epc-tab').forEach(function (t) {
        t.addEventListener('click', function () { setTab(t.getAttribute('data-tab')); });
    });
    root.querySelector('.epc-jbtn').addEventListener('click', function () { open(); setTab('journal'); });

    function maxJournalId() { return state.journal.reduce(function (m, j) { return Math.max(m, j.id); }, 0); }
    function markJournalSeen() {
        lsSet(LS_JSEEN, String(maxJournalId()));
        renderJournalBadge();
    }
    function renderJournalBadge() {
        var seen = parseInt(lsGet(LS_JSEEN, '0'), 10) || 0;
        var fresh = state.journal.filter(function (j) { return j.id > seen; }).length;
        var n = Math.max(fresh, state.journalUnread);
        var badge = root.querySelector('.epc-jn');
        badge.hidden = !n;
        badge.textContent = n > 99 ? '99+' : String(n);
        var tabN = root.querySelector('.epc-tab-n');
        tabN.hidden = !state.journalUnread;
        tabN.textContent = String(state.journalUnread);
        tabN.title = 'Cer acțiune';
    }
    function renderJournal() {
        var html = '', day = null;
        state.journal.forEach(function (j) {
            if (j.day !== day) { day = j.day; html += '<div class="epc-jday">' + esc(day) + '</div>'; }
            var title = j.url
                ? '<a class="epc-jt" href="' + esc(j.url) + '" title="' + esc(j.title) + '">' + esc(j.title) + '</a>'
                : '<span class="epc-jt" title="' + esc(j.title) + '">' + esc(j.title) + '</span>';
            var meta = j.label + (j.message ? ' · ' + j.message : '');
            html += '<div class="epc-jrow' + (j.attention ? ' is-att' : '') + '">'
                + '<span class="epc-jdot" style="background:' + (JCOLOR[j.color] || JCOLOR.primary) + '"></span>'
                + title
                + '<span class="epc-jtime">' + esc(j.time) + '</span>'
                + '<span class="epc-jm" title="' + esc(meta) + '">' + esc(meta) + '</span>'
                + (j.attention ? '<button type="button" class="epc-btn epc-jread" data-jread="' + j.id + '">Văzut</button>' : '')
                + '</div>';
        });
        jbody.innerHTML = html || '<p class="epc-empty">Nimic în ultimele 3 zile.</p>';
    }
    jbody.addEventListener('click', function (e) {
        var link = e.target.closest('a[href*="chat-console?c="]');
        if (link && openInDock(link.getAttribute('href'))) { e.preventDefault(); return; }
        var b = e.target.closest('[data-jread]');
        if (!b) return;
        b.disabled = true;
        fetch('/marketplace/cockpit/journal-read', {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ id: parseInt(b.getAttribute('data-jread'), 10) })
        }).then(function () { poll(); }).catch(function () { b.disabled = false; });
    });

    root.querySelectorAll('.epc-seg button').forEach(function (b) {
        b.addEventListener('click', function () {
            state.filter = b.getAttribute('data-f');
            root.querySelectorAll('.epc-seg button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
            renderBody();
        });
    });

    // ---------- „văzut” (punctul de pe pătrat) ----------
    function allKeys() {
        var keys = [];
        CATS.forEach(function (c) { (state.items[c] || []).forEach(function (i) { keys.push(i.key); }); });
        return keys;
    }
    function markSeen() {
        lsSet(LS_SEEN, JSON.stringify(allKeys()));
        setTimeout(renderTiles, 700);
    }

    // ---------- randare ----------
    function renderTiles() {
        var seen = lsJson(LS_SEEN);
        var total = 0, anyLate = false;
        tiles.innerHTML = '';
        CATS.forEach(function (cat) {
            var list = state.items[cat] || [];
            total += list.length;
            var late = list.some(function (i) { return i.late; });
            anyLate = anyLate || late;
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'epc-tile' + (list.length ? '' : ' is-empty') + (late ? ' is-hot' : '');
            b.style.setProperty('--col', COLOR[cat]);
            b.title = LABEL[cat];
            b.setAttribute('aria-label', LABEL[cat] + ': ' + list.length);
            b.innerHTML = ICONS[cat];
            if (list.length) {
                var c = document.createElement('span');
                var isNew = list.some(function (i) { return seen.indexOf(i.key) === -1; });
                c.className = 'epc-count' + (isNew ? ' is-new' : '');
                c.textContent = list.length > 99 ? '99+' : String(list.length);
                b.appendChild(c);
            }
            b.addEventListener('click', function () {
                open();
                setTab('todo');
                var h = body.querySelector('[data-g="' + cat + '"]');
                if (h) body.scrollTop = h.offsetTop - 4;
            });
            b.addEventListener('focus', open);
            tiles.appendChild(b);
        });
        fab.hidden = false;
        fab.querySelector('.epc-fab-n').textContent = total > 99 ? '99+' : String(total);
        fab.querySelector('.epc-fab-dot').hidden = !anyLate;
    }

    function passes(i) {
        if (state.filter === 'mine') return i.mine;
        if (state.filter === 'pool') return !i.owner_id && i.claimable !== false;
        if (state.filter === 'others') return !!i.owner_id && !i.mine;
        return true;
    }

    function renderBody() {
        var html = '';
        CATS.forEach(function (cat) {
            var list = (state.items[cat] || []).filter(passes);
            if (!list.length) return;
            html += '<div class="epc-grp" data-g="' + cat + '"><span class="epc-sq" style="background:' + COLOR[cat] + '"></span>' + esc(LABEL[cat]) + '<span class="epc-n">' + list.length + '</span></div>';
            var SUB = { approval: ['Evenimente noi de aprobat', 'se preiau'], changes: ['Modificări la evenimente live', 'nu se alocă, doar se aprobă'] };
            var lastKind = null;
            list.forEach(function (i) {
                if (cat === 'event' && i.kind && i.kind !== lastKind) {
                    lastKind = i.kind;
                    html += '<div class="epc-sub">' + esc(SUB[i.kind][0]) + ' <small>· ' + esc(SUB[i.kind][1]) + '</small></div>';
                }
                var chip = i.late ? '<span class="epc-chip is-late">întârziat</span>'
                    : (i.mine ? '<span class="epc-chip">al tău</span>' : (i.owner_id ? '<span class="epc-chip">' + esc(i.owner_name || 'preluat') + '</span>' : '<span class="epc-chip">nealocat</span>'));
                if (!i.late && i.kind === 'changes') chip = '<span class="epc-chip">de aprobat</span>';
                var action = i.claimable === false
                    ? '<a class="epc-btn is-pri" href="' + esc(i.url) + '">Revizuiește</a>'
                    : !i.owner_id
                    ? '<button type="button" class="epc-btn is-pri" data-claim="' + esc(i.key) + '" data-url="' + esc(i.url) + '">Preiau</button>'
                    : '<a class="epc-btn" href="' + esc(i.url) + '">Deschide</a>';
                var snooze = '<button type="button" class="epc-snooze" data-snooze="' + esc(i.key) + '" title="Amână 1h" aria-label="Amână 1h"><svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="13" r="8"/><path stroke-linecap="round" d="M12 9v4l2.5 2M9 2h6"/></svg></button>';
                html += '<div class="epc-item"><a class="epc-item-t" href="' + esc(i.url) + '" title="' + esc(i.title) + '">' + esc(i.title) + '</a>'
                    + '<span class="epc-item-m" title="' + esc(i.meta) + '">' + esc(i.meta) + '</span>'
                    + '<span class="epc-item-a">' + chip + action + snooze + '</span></div>';
            });
        });
        body.innerHTML = html || '<p class="epc-empty">Nimic de rezolvat aici. Când apare ceva, pătratul categoriei se colorează.</p>';
    }

    function showMsg(text) {
        msg.textContent = text;
        msg.hidden = false;
        clearTimeout(msgTimer);
        msgTimer = setTimeout(function () { msg.hidden = true; }, 5000);
    }

    // ---------- acțiuni ----------
    function post(url, key) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify({ key: key })
        }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
    }

    function chatRef(url) {
        try { return new URL(url, window.location.origin).searchParams.get('c'); } catch (err) { return null; }
    }
    function openInDock(url) {
        var ref = chatRef(url);
        if (!ref || !window.epDock) return false;
        window.epDock.open(ref);
        if (!isPinned()) close();
        return true;
    }

    body.addEventListener('click', function (e) {
        var link = e.target.closest('a[href*="chat-console?c="]');
        if (link && openInDock(link.getAttribute('href'))) { e.preventDefault(); return; }
        var claimBtn = e.target.closest('[data-claim]');
        var snoozeBtn = e.target.closest('[data-snooze]');
        if (claimBtn) {
            claimBtn.disabled = true;
            var url = claimBtn.getAttribute('data-url');
            post('/marketplace/cockpit/claim', claimBtn.getAttribute('data-claim')).then(function (res) {
                if (res && res.ok) {
                    if (openInDock(url)) { poll(); return; }
                    window.location.href = url;
                    return;
                }
                showMsg((res && res.message) || 'Nu am putut prelua elementul. Încearcă din nou.');
                poll();
            }).catch(function () { claimBtn.disabled = false; showMsg('Fără conexiune. Încearcă din nou.'); });
        } else if (snoozeBtn) {
            snoozeBtn.disabled = true;
            post('/marketplace/cockpit/snooze', snoozeBtn.getAttribute('data-snooze')).then(function () { poll(); });
        }
    });

    // ---------- polling ----------
    function poll() {
        lastPoll = Date.now();
        return fetch('/marketplace/cockpit/poll', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (!data || data.ok !== true) return;
                if (!data.enabled) { root.remove(); document.documentElement.classList.remove('epc-on'); stop(); return; }
                state.items = data.items || {};
                state.journal = (data.journal && data.journal.items) || [];
                state.journalUnread = (data.journal && data.journal.unread) || 0;
                renderJournal();
                if (root.classList.contains('is-open') && state.tab === 'journal') markJournalSeen(); else renderJournalBadge();
                state.super = !!data.super;
                if (data.prefs) { prefs = data.prefs; if (state.tab === 'settings' && root.classList.contains('is-open')) renderSettings(); }
                root.querySelector('[data-f="others"]').hidden = !state.super;

                // Sunet doar pentru elemente noi din afara chatului (chatul are sunetul lui).
                var keys = allKeys().filter(function (k) { return k.indexOf('chat:') !== 0; });
                var known = lsJson(LS_KNOWN);
                var hadBaseline = lsGet(LS_KNOWN, null) !== null;
                if (hadBaseline && keys.some(function (k) { return known.indexOf(k) === -1; })) playNew();
                lsSet(LS_KNOWN, JSON.stringify(keys));

                renderTiles();
                renderBody();
                if (root.classList.contains('is-open')) markSeen();
            })
            .catch(function () {});
    }
    function start() {
        stop();
        poll();
        pollTimer = setInterval(function () {
            if (document.hidden) return;
            var live = window.epRealtime && window.epRealtime.isConnected();
            if (live && Date.now() - lastPoll < SAFETY_MS) return;
            poll();
        }, pollMs);
    }
    if (window.epRealtime && clientId) {
        window.epRealtime.on('cockpit.' + clientId, true, 'changed', function () {
            clearTimeout(signalTimer);
            signalTimer = setTimeout(poll, 600);
        });
        // La reconectare ne resincronizăm, poate am pierdut semnale.
        window.epRealtime.onStatus(function (live) { if (live) poll(); });
    }
    function stop() { if (pollTimer) clearInterval(pollTimer); pollTimer = null; }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

    // ================================================================ echipa
    var AVA_COLORS = ['#3a6a8f', '#6b4f8f', '#7b3f62', '#2f7a6a', '#8a5a2b', '#4b5f9e', '#7a3f3f', '#3f6f3f'];
    function avaColor(t) { var k = t.partner_id || t.id || 0; return AVA_COLORS[k % AVA_COLORS.length]; }
    function teamPost(url, data) {
        return fetch(url, {
            method: 'POST', credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf() },
            body: JSON.stringify(data || {})
        }).then(function (r) { return r.json().catch(function () { return { ok: false }; }); });
    }
    function openTeamThread(id) {
        if (window.epDock && window.epDock.openTeam) window.epDock.openTeam(id);
        if (!isPinned()) close();
    }

    function loadTeam() {
        return fetch('/marketplace/team-chat/threads', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (res) {
                if (!res || !res.ok) return;
                team.threads = res.threads || [];
                team.colleagues = res.colleagues || [];
                team.loaded = true;
                renderTeamRail();
                if (state.tab === 'team' && !team.form) renderTeamTab();
                window.dispatchEvent(new CustomEvent('ep:team-threads', { detail: team.threads }));
            }).catch(function () {});
    }

    function renderTeamRail() {
        var slot = root.querySelector('.epc-team');
        var list = team.threads.slice().sort(function (a, b) {
            if (!!b.unread !== !!a.unread) return b.unread ? 1 : -1;
            return String(b.last_at).localeCompare(String(a.last_at));
        }).slice(0, 4);
        slot.innerHTML = '';
        list.forEach(function (t) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'epc-ava' + (t.type !== 'dm' ? ' is-group' : '');
            b.style.setProperty('--ava', avaColor(t));
            b.title = t.name + (t.unread ? ' · ' + t.unread + ' necitite' : '');
            b.setAttribute('aria-label', b.title);
            b.textContent = t.initials;
            if (t.unread && !t.muted) {
                var c = document.createElement('span');
                c.className = 'epc-count' + (t.mentioned || t.type === 'dm' ? ' is-mention' : '');
                c.textContent = t.unread > 99 ? '99+' : String(t.unread);
                b.appendChild(c);
            }
            b.addEventListener('click', function () { openTeamThread(t.id); });
            slot.appendChild(b);
        });
        var total = team.threads.reduce(function (n, t) { return n + (t.muted ? 0 : t.unread); }, 0);
        var tn = root.querySelector('.epc-team-n');
        tn.hidden = !total;
        tn.textContent = String(total);
    }

    function renderTeamTab() {
        var f = team.filter.toLowerCase();
        var html = '<div class="epc-tbar"><input type="search" class="epc-tsearch" placeholder="Caută conversație…" aria-label="Caută conversație" value="' + esc(team.filter) + '">'
            + '<button type="button" class="epc-btn" data-tf="dm">Mesaj nou</button><button type="button" class="epc-btn" data-tf="group">Grup nou</button></div>';

        if (team.form === 'dm') {
            html += '<div class="epc-form"><label for="epc-dm-sel">Scrie unui coleg</label><select id="epc-dm-sel"><option value="">Alege…</option>'
                + team.colleagues.map(function (c) { return '<option value="' + c.id + '">' + esc(c.name) + '</option>'; }).join('')
                + '</select><div class="epc-row"><button type="button" class="epc-btn" data-tf="cancel">Renunță</button><button type="button" class="epc-btn is-pri" data-tf="dm-go">Deschide</button></div></div>';
        } else if (team.form === 'group') {
            html += '<div class="epc-form"><input type="text" id="epc-g-name" maxlength="80" placeholder="Numele grupului" aria-label="Numele grupului">'
                + '<div class="epc-checks">' + team.colleagues.map(function (c) { return '<label><input type="checkbox" value="' + c.id + '"> ' + esc(c.name) + '</label>'; }).join('') + '</div>'
                + '<div class="epc-row"><button type="button" class="epc-btn" data-tf="cancel">Renunță</button><button type="button" class="epc-btn is-pri" data-tf="group-go">Creează grupul</button></div></div>';
        }

        var rows = team.threads.filter(function (t) { return !f || t.name.toLowerCase().indexOf(f) !== -1; });
        rows.forEach(function (t) {
            var last = t.last ? ((t.last.mine ? 'Tu: ' : (t.last.author && t.type !== 'dm' ? t.last.author + ': ' : '')) + t.last.text) : (t.type === 'dm' ? 'Începe conversația' : t.members + ' membri');
            html += '<button type="button" class="epc-trow' + (t.unread ? ' is-unread' : '') + '" data-thread="' + t.id + '">'
                + '<span class="epc-ava' + (t.type !== 'dm' ? ' is-group' : '') + '" style="--ava:' + avaColor(t) + '">' + esc(t.initials) + '</span>'
                + '<span class="epc-tn">' + esc(t.name) + (t.muted ? ' · fără notificări' : '') + '</span>'
                + '<span class="epc-tl">' + esc(last) + '</span>'
                + (t.unread ? '<span class="epc-tu' + (t.mentioned || t.type === 'dm' ? ' is-mention' : '') + '">' + (t.mentioned ? '@' : t.unread) + '</span>' : '')
                + '</button>';
        });
        if (!rows.length) html += '<p class="epc-empty">' + (team.loaded ? 'Nicio conversație găsită.' : 'Se încarcă…') + '</p>';
        tbody.innerHTML = html;

        var search = tbody.querySelector('.epc-tsearch');
        search.addEventListener('input', function () {
            team.filter = search.value;
            var pos = search.selectionStart;
            renderTeamTab();
            var s2 = tbody.querySelector('.epc-tsearch'); s2.focus(); try { s2.setSelectionRange(pos, pos); } catch (e) {}
        });
    }

    tbody.addEventListener('click', function (e) {
        var row = e.target.closest('[data-thread]');
        if (row) { openTeamThread(parseInt(row.getAttribute('data-thread'), 10)); return; }
        var b = e.target.closest('[data-tf]');
        if (!b) return;
        var act = b.getAttribute('data-tf');
        if (act === 'dm' || act === 'group') { team.form = team.form === act ? null : act; renderTeamTab(); return; }
        if (act === 'cancel') { team.form = null; renderTeamTab(); return; }
        if (act === 'dm-go') {
            var id = parseInt(tbody.querySelector('#epc-dm-sel').value, 10);
            if (!id) return;
            b.disabled = true;
            teamPost('/marketplace/team-chat/dm', { admin_id: id }).then(function (res) {
                if (res.ok) { team.form = null; loadTeam(); openTeamThread(res.thread_id); }
                else { b.disabled = false; showMsg(res.message || 'Nu am putut deschide conversația.'); }
            });
        }
        if (act === 'group-go') {
            var name = tbody.querySelector('#epc-g-name').value.trim();
            var ids = Array.prototype.map.call(tbody.querySelectorAll('.epc-checks input:checked'), function (i) { return parseInt(i.value, 10); });
            if (!name) { showMsg('Dă un nume grupului.'); return; }
            if (!ids.length) { showMsg('Alege cel puțin un coleg.'); return; }
            b.disabled = true;
            teamPost('/marketplace/team-chat/groups', { name: name, member_ids: ids }).then(function (res) {
                if (res.ok) { team.form = null; loadTeam(); openTeamThread(res.thread_id); }
                else { b.disabled = false; showMsg(res.message || 'Nu am putut crea grupul.'); }
            });
        }
    });
    root.querySelector('.epc-plus').addEventListener('click', function () { team.form = 'dm'; open(); setTab('team'); });

    // API pentru dock: colegi (pentru „Cere ajutor”) și starea conversațiilor.
    window.epTeam = {
        colleagues: function () { return team.colleagues.slice(); },
        thread: function (id) { for (var i = 0; i < team.threads.length; i++) if (team.threads[i].id === id) return team.threads[i]; return null; },
        reload: loadTeam,
        meId: meId
    };

    // Mesaje noi pe canalul meu: reîncărcăm lista, anunțăm dock-ul, sunăm.
    var teamTimer = null;
    if (window.epRealtime && meId) {
        window.epRealtime.on('team-user.' + meId, true, 'team.message', function (d) {
            if (!d) return;
            window.dispatchEvent(new CustomEvent('ep:team-message', { detail: d }));
            clearTimeout(teamTimer);
            teamTimer = setTimeout(loadTeam, 400);
            if (d.action !== 'new' || d.author_id === meId || !d.author_id) return;
            var t = window.epTeam.thread(d.thread_id);
            var mentioned = (d.mentions || []).indexOf(meId) !== -1;
            if (t && t.muted && !mentioned) return;
            var focused = window.epDock && window.epDock.isFocused && window.epDock.isFocused('team:' + d.thread_id);
            if (focused) return;
            window.epSound.play(mentioned || d.type === 'dm' || d.type === 'help' ? 'dm' : 'group');
        });
        window.epRealtime.on('team-user.' + meId, true, 'team.typing', function (d) {
            window.dispatchEvent(new CustomEvent('ep:team-typing', { detail: d }));
        });
        window.epRealtime.onStatus(function (live) { if (live) loadTeam(); });
    }
    setInterval(function () {
        if (document.hidden) return;
        var live = window.epRealtime && window.epRealtime.isConnected();
        if (!live || Date.now() % 60000 < 20000) loadTeam();
    }, 20000);

    // ================================================================ setări sunete
    var SOUND_ROWS = [
        ['action', 'Element nou în bară', 'Tichete, ToDos, artiști, evenimente', 'action'],
        ['chat', 'Chat cu clienții', 'Client nou în coadă, mesaj pe chatul tău', 'chat_new'],
        ['dm', 'Mesaj intern 1:1 și @mențiuni', 'Colegii care îți scriu direct', 'dm'],
        ['group', 'Mesaj în grupurile echipei', 'Toată echipa, departamente, grupuri', 'group']
    ];
    function renderSettings() {
        var dnd = dndActive();
        var until = dnd ? new Date(prefs.dnd_until) : null;
        var untilTxt = until ? until.toLocaleString('ro-RO', { weekday: 'short', hour: '2-digit', minute: '2-digit' }) : '';
        var html = '<div class="epc-set"><div><h4>Sunete</h4></div><div class="epc-opts">';
        SOUND_ROWS.forEach(function (r) {
            var on = prefs.sounds[r[0]] !== false;
            html += '<div class="epc-opt"><span>' + esc(r[1]) + '<small>' + esc(r[2]) + '</small></span>'
                + '<span style="display:flex;gap:6px"><button type="button" class="epc-btn" data-preview="' + r[3] + '">Ascultă</button>'
                + '<button type="button" class="epc-pill" data-sound="' + r[0] + '" aria-pressed="' + (on ? 'true' : 'false') + '">' + (on ? 'Pornit' : 'Oprit') + '</button></span></div>';
        });
        html += '</div><div><h4>Nu deranja</h4></div>'
            + '<p class="epc-dnd-state">' + (dnd ? 'Activ până ' + esc(untilTxt) + '. Auzi doar mesajele clienților de pe chat-urile tale.' : 'Oprit. Auzi toate sunetele pornite mai sus.') + '</p>'
            + '<div class="epc-dnd"><button type="button" class="epc-btn" data-dnd="60">1 oră</button><button type="button" class="epc-btn" data-dnd="240">4 ore</button><button type="button" class="epc-btn" data-dnd="-1">Până mâine la 8</button>'
            + (dnd ? '<button type="button" class="epc-btn is-pri" data-dnd="0">Oprește</button>' : '') + '</div>'
            + '<p style="color:var(--epc-muted);font-size:12px;margin:0">Browserul pornește sunetele doar după primul click pe pagină.</p></div>';
        sbody.innerHTML = html;
    }
    function savePrefs(data) {
        return teamPost('/marketplace/cockpit/prefs', data).then(function (res) {
            if (res && res.ok && res.prefs) { prefs = res.prefs; renderSettings(); }
            else showMsg('Nu am putut salva setarea.');
        });
    }
    sbody.addEventListener('click', function (e) {
        var pv = e.target.closest('[data-preview]');
        if (pv) {
            if (!audioCtx) { var AC = window.AudioContext || window.webkitAudioContext; if (AC) audioCtx = new AC(); }
            window.epSound.preview(pv.getAttribute('data-preview'));
            return;
        }
        var sb = e.target.closest('[data-sound]');
        if (sb) {
            var k = sb.getAttribute('data-sound'), payload = { sounds: {} };
            payload.sounds[k] = prefs.sounds[k] === false;
            savePrefs(payload);
            return;
        }
        var d = e.target.closest('[data-dnd]');
        if (d) savePrefs({ dnd_minutes: parseInt(d.getAttribute('data-dnd'), 10) });
    });
    root.querySelector('.epc-setbtn').addEventListener('click', function () { open(); setTab('settings'); });

    renderPin();
    renderTiles();
    if (isPinned()) open();
    start();
    loadTeam();
})();
</script>
