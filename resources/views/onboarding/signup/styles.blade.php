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

    /* ---------- Prima pagină: hero întunecat cu peretele de site-uri demo */
    .ob-hero { position: relative; overflow: hidden; isolation: isolate; background: #0a0a0c; color: #f4f4f1; --mx: 50%; --my: 30%; }
    .ob-hero__spot { position: absolute; inset: 0; z-index: 2; pointer-events: none;
        background: radial-gradient(600px circle at var(--mx) var(--my), rgba(232, 73, 42, .16), transparent 60%); }
    .ob-hero__aurora { position: absolute; inset: 0; z-index: 0; filter: blur(80px); opacity: .7; }
    .ob-hero__aurora i { position: absolute; display: block; border-radius: 50%; }
    .ob-hero__aurora i:nth-child(1) { width: 46vw; height: 46vw; left: -10vw; top: -14vw; background: #e8492a; opacity: .35; animation: ob-drift 18s ease-in-out infinite alternate; }
    .ob-hero__aurora i:nth-child(2) { width: 38vw; height: 38vw; right: -8vw; top: 6vw; background: #ff9a5a; opacity: .22; animation: ob-drift 22s ease-in-out infinite alternate-reverse; }
    .ob-hero__aurora i:nth-child(3) { width: 30vw; height: 30vw; left: 30vw; bottom: -14vw; background: #7a4dff; opacity: .18; animation: ob-drift 26s ease-in-out infinite alternate; }
    @keyframes ob-drift { to { transform: translate(6vw, 4vw) scale(1.15); } }
    .ob-wall { position: absolute; z-index: 1; top: -10%; left: 38%; width: 120%; display: flex; flex-direction: column; gap: 22px; opacity: .5;
        transform: perspective(1600px) rotateX(24deg) rotateY(-18deg) rotateZ(-8deg); transform-origin: left top;
        -webkit-mask-image: linear-gradient(90deg, transparent, #000 30%, #000 80%, transparent); mask-image: linear-gradient(90deg, transparent, #000 30%, #000 80%, transparent); }
    .ob-wall__row { display: flex; gap: 22px; width: max-content; animation: ob-marquee 70s linear infinite; }
    .ob-wall__row.is-rev { animation-direction: reverse; animation-duration: 85s; }
    .ob-wall__shot { width: 340px; aspect-ratio: 16 / 10; flex: none; border-radius: 14px; overflow: hidden; border: 1px solid rgba(255, 255, 255, .1); background: #17171a; box-shadow: 0 30px 60px -30px #000; }
    .ob-wall__shot img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
    @keyframes ob-marquee { to { transform: translateX(-50%); } }
    .ob-hero__fade { position: absolute; inset: 0; z-index: 3; pointer-events: none;
        background: linear-gradient(90deg, #0a0a0c 0%, rgba(10, 10, 12, .92) 34%, rgba(10, 10, 12, .35) 70%, rgba(10, 10, 12, .2)), linear-gradient(0deg, #0a0a0c, transparent 30%); }
    @media (max-width: 1023px) {
        .ob-wall { left: 0; top: 30%; opacity: .25; }
        .ob-hero__fade { background: linear-gradient(0deg, #0a0a0c 10%, rgba(10, 10, 12, .85) 60%, rgba(10, 10, 12, .7)); }
    }
    .ob-glass { border-radius: 16px; background: rgba(255, 255, 255, .06); border: 1px solid rgba(255, 255, 255, .1); backdrop-filter: blur(12px); }
    .ob-live { width: 8px; height: 8px; border-radius: 50%; background: #3ccf86; box-shadow: 0 0 0 0 rgba(60, 207, 134, .6); animation: ob-pulse 2s infinite; flex: none; }
    @keyframes ob-pulse { 70% { box-shadow: 0 0 0 8px rgba(60, 207, 134, 0); } 100% { box-shadow: 0 0 0 0 rgba(60, 207, 134, 0); } }
    .ob-rotator { display: inline-block; position: relative; }
    .ob-rotator__word { display: inline-block; background: linear-gradient(90deg, #ff7a4d, #e8492a 45%, #ffb36b); -webkit-background-clip: text; background-clip: text; color: transparent; }
    .ob-rotator__word.is-in { animation: ob-word .55s var(--ease); }
    @keyframes ob-word { from { opacity: 0; transform: translateY(.35em); filter: blur(6px); } }
    .ob-glow { box-shadow: 0 18px 40px -12px rgba(232, 73, 42, .7); }
    @media (hover: hover) { .ob-glow:hover { box-shadow: 0 0 0 6px rgba(232, 73, 42, .18), 0 22px 50px -12px rgba(232, 73, 42, .8); } }
    @media (prefers-reduced-motion: reduce) { .ob-wall__row, .ob-hero__aurora i, .ob-live { animation: none !important; } }
    .stat-num { font-variant-numeric: tabular-nums; }

    /* ---------- Ce vinzi: două căi, două platforme. Imaginea are zona ei, textul începe dedesubt. */
    .ob-paths { background: radial-gradient(60% 50% at 50% 0%, var(--accent-soft), transparent 70%), var(--bg); }
    .ob-path { position: relative; overflow: hidden; border-radius: 28px; display: flex; flex-direction: column;
        border: 1px solid var(--line); transition: transform .35s var(--ease), box-shadow .35s var(--ease); }
    @media (hover: hover) { .ob-path:hover { transform: translateY(-4px); box-shadow: 0 40px 80px -40px rgb(var(--shadow) / .55); } }
    .ob-path__media { position: relative; flex: none; height: 250px; overflow: hidden; }
    .ob-path__body { position: relative; flex: 1; display: flex; flex-direction: column; padding: 8px 28px 32px; }
    @media (min-width: 640px) { .ob-path__body { padding: 8px 40px 40px; } }
    .ob-path__body > .ob-path__actions { margin-top: auto; padding-top: 28px; }
    .ob-path__brand { display: inline-flex; align-self: flex-start; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; padding: 6px 12px; border-radius: 999px; }
    .ob-tag { font-size: 13px; font-weight: 500; padding: 6px 12px; border-radius: 999px; }

    /* Evenimente: întunecat, cu trei site-uri demo înclinate în zona de sus */
    .ob-path--events { background: #0e0e11; color: #f4f4f1; border-color: #24242a; }
    .ob-path--events .ob-path__brand { background: rgba(255, 255, 255, .08); }
    .ob-path--events .ob-tag { background: rgba(255, 255, 255, .07); color: rgba(255, 255, 255, .8); }
    .ob-path--events .ob-path__media { background: radial-gradient(70% 90% at 50% 100%, rgba(232, 73, 42, .28), transparent 70%); }
    .ob-path--events .ob-path__media::after { content: ""; position: absolute; inset: auto 0 0; height: 70px; background: linear-gradient(180deg, transparent, #0e0e11); z-index: 3; }
    .ob-path__shot { position: absolute; width: 46%; aspect-ratio: 16 / 10; border-radius: 12px; overflow: hidden; border: 1px solid rgba(255, 255, 255, .14);
        box-shadow: 0 24px 50px -18px #000; background: #17171a; transition: transform .6s var(--ease); }
    .ob-path__shot img { width: 100%; height: 100%; object-fit: cover; object-position: top; display: block; }
    .ob-path__shot--0 { left: 6%; top: 40px; transform: rotate(-7deg); }
    .ob-path__shot--1 { right: 6%; top: 28px; transform: rotate(6deg); }
    .ob-path__shot--2 { left: 27%; top: 62px; transform: rotate(-1deg); z-index: 2; }
    @media (hover: hover) {
        .ob-path--events:hover .ob-path__shot--0 { transform: rotate(-10deg) translate(-8px, -4px); }
        .ob-path--events:hover .ob-path__shot--1 { transform: rotate(9deg) translate(8px, -6px); }
        .ob-path--events:hover .ob-path__shot--2 { transform: rotate(0deg) translateY(-10px); }
    }

    /* Agrement: deschis, verde, activitățile ca iconițe într-un rând care plutește */
    .ob-path--leisure { background: linear-gradient(170deg, #e6f6ef 0%, #f6fbf8 60%, var(--surface) 100%); border-color: #cfe9de; }
    .ob-path--leisure .ob-path__brand { background: rgba(31, 157, 114, .12); color: #137a57; }
    .ob-path--leisure .ob-tag { background: rgba(31, 157, 114, .1); color: #145c43; }
    .ob-path--leisure h3 { color: #10241c; }
    .ob-path--leisure .ob-path__body li { color: #22362e; }
    .ob-path__icons { display: grid; grid-template-columns: repeat(4, 64px); gap: 18px 22px; justify-content: center; align-content: center; }
    .ob-path__icon { width: 64px; height: 64px; border-radius: 20px; display: grid; place-items: center; font-size: 30px; color: #1f9d72; background: #fff;
        box-shadow: 0 14px 30px -18px rgba(16, 80, 58, .5); animation: ob-bob 6s ease-in-out infinite; animation-delay: calc(var(--i) * -.7s); }
    .ob-path__icon:nth-child(even) { margin-top: 18px; }
    @keyframes ob-bob { 50% { translate: 0 -7px; } }
    .btn-leisure { background: #1f9d72; color: #fff; }
    @media (hover: hover) { .btn-leisure:hover { background: #188761; } }
    @media (prefers-color-scheme: dark) {
        .ob-path--leisure { background: linear-gradient(170deg, #0f2a21 0%, #13201b 60%, var(--surface) 100%); border-color: #1d3a2f; }
        .ob-path--leisure h3, .ob-path--leisure .ob-path__body li { color: #eaf6f0; }
        .ob-path--leisure .ob-path__brand { color: #7fe0b8; }
        .ob-path--leisure .ob-tag { color: #bde9d6; }
        .ob-path__icon { background: #17342a; color: #5fd3a5; }
    }
    @media (max-width: 639px) {
        .ob-path__media { height: 200px; }
        .ob-path__shot { width: 50%; }
        .ob-path__shot--0 { top: 30px; } .ob-path__shot--1 { top: 20px; } .ob-path__shot--2 { top: 52px; left: 25%; }
        .ob-path__icons { grid-template-columns: repeat(4, 52px); gap: 12px 14px; }
        .ob-path__icon { width: 52px; height: 52px; font-size: 24px; border-radius: 16px; }
    }
    @media (prefers-reduced-motion: reduce) { .ob-path__icon { animation: none; } }

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
