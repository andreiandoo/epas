<style>
    /* Tokenii vin din galeria demo.tics.ro: aceeași familie (Geist) și același accent */
    :root {
        --bg: #f6f6f4; --surface: #ffffff; --surface-2: #f1f1ee; --ink: #151619; --ink-2: #55575c; --ink-3: #8b8d91;
        --line: #e3e3df; --accent: #e8492a; --accent-hover: #d43f22; --accent-ink: #ffffff; --accent-soft: rgba(232, 73, 42, .1);
        --success: #13804b; --success-soft: rgba(19, 128, 75, .1); --error: #c4321a; --error-soft: rgba(196, 50, 26, .08);
        --shadow: 30 32 36; --r: 14px; --r-sm: 10px;
        --ease: cubic-bezier(.2, .8, .2, 1);
        color-scheme: light;
    }
    @media (prefers-color-scheme: dark) {
        :root {
            --bg: #111214; --surface: #18191c; --surface-2: #1f2024; --ink: #f1f1ee; --ink-2: #b4b5b1; --ink-3: #86878a;
            --line: #2a2b2f; --accent-soft: rgba(232, 73, 42, .16); --success: #3ccf86; --success-soft: rgba(60, 207, 134, .12);
            --error: #ff7a5f; --error-soft: rgba(255, 122, 95, .1); --shadow: 0 0 0; color-scheme: dark;
        }
    }
    [x-cloak] { display: none !important; }
    html { -webkit-text-size-adjust: 100%; }
    body { margin: 0; background: var(--bg); color: var(--ink); font: 400 16px/1.55 Geist, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
    a { color: inherit; }
    h1, h2, h3 { letter-spacing: -.02em; line-height: 1.15; }

    /* ---------- Bara de derulare peste conținut: transparentă, apare doar la derulare */
    html.ob-sb { scrollbar-width: none; }
    html.ob-sb::-webkit-scrollbar { display: none; width: 0; height: 0; }
    .ob-sbar { position: fixed; top: 0; right: 0; bottom: 0; width: 14px; z-index: 90; pointer-events: none; }
    .ob-sbar__thumb { position: absolute; right: 3px; width: 6px; min-height: 40px; border-radius: 6px; background: color-mix(in srgb, var(--ink) 40%, transparent);
        opacity: 0; transition: opacity .35s ease, width .18s ease, right .18s ease; pointer-events: auto; touch-action: none; }
    .ob-sbar.is-on .ob-sbar__thumb { opacity: 1; }
    .ob-sbar__thumb:hover, .ob-sbar.is-drag .ob-sbar__thumb { width: 10px; right: 2px; opacity: 1; background: color-mix(in srgb, var(--ink) 60%, transparent); }
    html.ob-dragging, html.ob-dragging * { user-select: none !important; }
    /* Orizontal: bara nativă dispare, o bandă subțire apare jos doar cât derulezi */
    .hscroll { position: relative; }
    .hscroll__track { overflow-x: auto; scrollbar-width: none; overscroll-behavior-x: contain; }
    .hscroll__track::-webkit-scrollbar { display: none; }
    .hscroll__thumb { position: absolute; left: 0; bottom: -6px; height: 4px; border-radius: 4px; background: color-mix(in srgb, var(--ink) 40%, transparent);
        opacity: 0; transition: opacity .35s ease; pointer-events: none; }
    .hscroll.is-on .hscroll__thumb { opacity: 1; }
    /* Orice altă zonă derulabilă: subțire și transparentă */
    .ob-scroll-y { scrollbar-width: thin; scrollbar-color: color-mix(in srgb, var(--ink) 30%, transparent) transparent; }
    .ob-scroll-y::-webkit-scrollbar { width: 6px; background: transparent; }
    .ob-scroll-y::-webkit-scrollbar-thumb { background: color-mix(in srgb, var(--ink) 30%, transparent); border-radius: 6px; }

    /* ---------- Componente */
    .card { background: var(--surface); border: 1px solid var(--line); border-radius: 20px; box-shadow: 0 1px 0 rgb(var(--shadow) / .03), 0 24px 60px -36px rgb(var(--shadow) / .25); }
    .label { display: block; margin-bottom: 6px; font-size: 14px; font-weight: 600; }
    .hint { margin-top: 6px; font-size: 13px; color: var(--ink-2); }
    .err { margin-top: 6px; font-size: 13px; color: var(--error); display: flex; gap: 6px; align-items: flex-start; }
    .err::before { content: "!"; flex: none; width: 16px; height: 16px; margin-top: 1px; border-radius: 50%; background: var(--error); color: #fff; font-size: 11px; font-weight: 700; display: grid; place-items: center; }
    .field { width: 100%; min-height: 48px; padding: 12px 14px; border-radius: var(--r-sm); border: 1px solid var(--line); background: var(--surface); color: var(--ink); font: inherit; transition: border-color .15s, box-shadow .15s; }
    .field::placeholder { color: var(--ink-3); }
    .field:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-soft); }
    .field[aria-invalid="true"] { border-color: var(--error); }
    select.field { appearance: none; background-image: linear-gradient(45deg, transparent 50%, var(--ink-2) 50%), linear-gradient(135deg, var(--ink-2) 50%, transparent 50%);
        background-position: calc(100% - 20px) 21px, calc(100% - 15px) 21px; background-size: 5px 5px; background-repeat: no-repeat; padding-right: 40px; }
    .btn { min-height: 48px; padding: 12px 20px; border-radius: 999px; font: 600 15px/1 Geist, system-ui, sans-serif; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
        border: 1px solid transparent; cursor: pointer; text-decoration: none; transition: background-color .15s, border-color .15s, color .15s, transform .15s var(--ease); }
    .btn:active { transform: scale(.98); }
    .btn:focus-visible, .opt:focus-within, .tile:focus-visible, .chip:focus-visible, .link:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
    .btn-primary { background: var(--accent); color: var(--accent-ink); }
    .btn-line { background: var(--surface); border-color: var(--line); color: var(--ink); }
    .btn-ghost { background: transparent; color: var(--ink-2); }
    @media (hover: hover) {
        .btn-primary:hover { background: var(--accent-hover); }
        .btn-line:hover { border-color: var(--ink-3); }
        .btn-ghost:hover { color: var(--ink); }
        .opt:hover, .tile:hover { border-color: var(--ink-3); }
        .link:hover { color: var(--ink); }
    }
    .link { color: var(--ink-2); text-decoration: underline; text-underline-offset: 4px; background: none; border: 0; padding: 10px 0; font: inherit; cursor: pointer; }
    .opt { display: block; position: relative; border: 1px solid var(--line); border-radius: var(--r); padding: 16px; cursor: pointer; background: var(--surface); transition: border-color .15s, box-shadow .15s; }
    .opt[data-on="true"], .tile[data-on="true"] { border-color: var(--accent); box-shadow: 0 0 0 1px var(--accent), 0 14px 32px -20px rgba(232, 73, 42, .55); }
    .tile { position: relative; border: 1px solid var(--line); border-radius: var(--r); background: var(--surface); transition: border-color .15s, box-shadow .15s; }
    .check { position: absolute; top: 12px; right: 12px; width: 24px; height: 24px; border-radius: 50%; border: 2px solid var(--line); display: grid; place-items: center; color: #fff; font-size: 13px; background: var(--surface); }
    [data-on="true"] > .check, [data-on="true"] .check { background: var(--accent); border-color: var(--accent); }
    .chip { display: inline-flex; align-items: center; gap: 6px; min-height: 40px; padding: 8px 14px; border-radius: 999px; border: 1px solid var(--line); background: var(--surface); color: var(--ink-2); font: 500 14px/1 Geist, system-ui, sans-serif; white-space: nowrap; cursor: pointer; }
    @media (pointer: coarse) { .chip { min-height: 44px; } }
    .chip[aria-pressed="true"] { background: var(--ink); border-color: var(--ink); color: var(--bg); }
    .price { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; border-radius: 999px; padding: 4px 10px; background: var(--surface-2); color: var(--ink-2); }
    .price--commission { background: var(--accent-soft); color: var(--accent); }
    .icon-box { width: 44px; height: 44px; flex: none; border-radius: 12px; display: grid; place-items: center; background: var(--surface-2); color: var(--accent); font-size: 22px; }
    .why { background: var(--surface-2); border-radius: 18px; padding: 20px; }
    .why li { display: flex; gap: 12px; align-items: flex-start; font-size: 14px; color: var(--ink-2); }
    .why li i { flex: none; font-size: 20px; color: var(--accent); margin-top: 1px; }
    .why strong { color: var(--ink); font-weight: 600; }
    .spin { width: 18px; height: 18px; border: 2px solid rgba(255, 255, 255, .35); border-top-color: #fff; border-radius: 50%; animation: spin .7s linear infinite; }
    .btn-line .spin { border-color: color-mix(in srgb, var(--ink) 25%, transparent); border-top-color: var(--ink); }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ---------- Skeleton */
    .skel { position: relative; overflow: hidden; background: var(--surface-2); border-radius: 8px; }
    .skel::after { content: ""; position: absolute; inset: 0; transform: translateX(-100%);
        background: linear-gradient(90deg, transparent, color-mix(in srgb, var(--surface) 70%, transparent), transparent); animation: shimmer 1.3s infinite; }
    @keyframes shimmer { to { transform: translateX(100%); } }

    /* ---------- Prima pagină */
    .intro-glow { position: absolute; inset: -20% -10% auto; height: 70vh; pointer-events: none; filter: blur(60px); opacity: .55;
        background: radial-gradient(40% 50% at 20% 30%, rgba(232, 73, 42, .35), transparent 70%), radial-gradient(35% 45% at 80% 20%, rgba(255, 170, 120, .3), transparent 70%); }
    .shot { position: absolute; border-radius: 16px; overflow: hidden; background: var(--surface); border: 1px solid var(--line); box-shadow: 0 40px 90px -40px rgb(var(--shadow) / .45); will-change: transform; }
    .shot__bar { height: 26px; display: flex; gap: 6px; align-items: center; padding: 0 12px; border-bottom: 1px solid var(--line); }
    .shot__bar i { width: 8px; height: 8px; border-radius: 50%; background: var(--line); display: block; }
    .shot img { display: block; width: 100%; height: calc(100% - 26px); object-fit: cover; object-position: top; }
    .stat-num { font-variant-numeric: tabular-nums; }

    /* ---------- Dialog „Ce primești” */
    dialog.sheet { border: 0; padding: 0; margin: 0 0 0 auto; height: 100dvh; max-height: 100dvh; width: min(440px, 100vw); background: var(--surface); color: var(--ink); box-shadow: -30px 0 80px -40px rgb(var(--shadow) / .5); }
    dialog.sheet::backdrop { background: rgb(0 0 0 / .45); backdrop-filter: blur(2px); }
    dialog.sheet[open] { animation: sheet-in .28s var(--ease); }
    @keyframes sheet-in { from { transform: translateX(24px); opacity: 0; } }
    @media (max-width: 639px) {
        dialog.sheet { margin: auto 0 0; width: 100vw; height: auto; max-height: 88dvh; border-radius: 20px 20px 0 0; }
        @keyframes sheet-in { from { transform: translateY(24px); opacity: 0; } }
    }

    /* Intrări: animație CSS, pornește din starea vizibilă dacă nu rulează */
    .ob-enter { animation: ob-enter .55s var(--ease) backwards; animation-delay: var(--d, 0ms); }
    .ob-shot.ob-enter { animation-duration: .9s; }
    @keyframes ob-enter { from { opacity: 0; transform: translateY(14px); } }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
    }
</style>
