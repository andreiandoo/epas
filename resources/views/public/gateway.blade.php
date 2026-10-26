<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Tixello</title>
    <meta name="description" content="Intră în contul Tixello sau înscrie-te: bilete pentru teatre, filarmonici, festivaluri, concerte și competiții.">
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --accent: #e8492a; --accent-hover: #d43f22; }
        * { box-sizing: border-box; }
        html, body { margin: 0; height: 100%; }
        body { min-height: 100svh; display: grid; grid-template-rows: 1fr auto; background: #0a0a0c; color: #f4f4f1; font: 400 16px/1.55 Geist, system-ui, sans-serif; -webkit-font-smoothing: antialiased; overflow: hidden; }
        .glow { position: fixed; inset: 0; pointer-events: none; filter: blur(90px); opacity: .7; z-index: 0; }
        .glow i { position: absolute; border-radius: 50%; display: block; }
        .glow i:nth-child(1) { width: 52vw; height: 52vw; left: -14vw; top: -20vw; background: #e8492a; opacity: .32; animation: drift 20s ease-in-out infinite alternate; }
        .glow i:nth-child(2) { width: 40vw; height: 40vw; right: -12vw; bottom: -16vw; background: #ff9a5a; opacity: .2; animation: drift 24s ease-in-out infinite alternate-reverse; }
        @keyframes drift { to { transform: translate(5vw, 3vw) scale(1.12); } }
        main { position: relative; z-index: 1; display: grid; place-items: center; padding: 32px 16px; }
        .box { width: 100%; max-width: 440px; text-align: center; animation: rise .6s cubic-bezier(.2, .8, .2, 1) backwards; }
        @keyframes rise { from { opacity: 0; transform: translateY(14px); } }
        .logo { font-size: 44px; font-weight: 700; letter-spacing: -.03em; margin: 0; }
        .logo span { color: var(--accent); }
        .lead { color: rgba(244, 244, 241, .65); margin: 12px 0 36px; font-size: 17px; }
        .actions { display: grid; gap: 12px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 56px; padding: 14px 24px; border-radius: 999px; font: 600 16px/1 Geist, system-ui, sans-serif; text-decoration: none; transition: background-color .15s, border-color .15s, transform .15s; }
        .btn:active { transform: scale(.98); }
        .btn:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
        .btn-primary { background: var(--accent); color: #fff; box-shadow: 0 18px 40px -14px rgba(232, 73, 42, .75); }
        .btn-line { border: 1px solid rgba(255, 255, 255, .2); color: #f4f4f1; }
        @media (hover: hover) { .btn-primary:hover { background: var(--accent-hover); } .btn-line:hover { border-color: rgba(255, 255, 255, .45); background: rgba(255, 255, 255, .05); } }
        footer { position: relative; z-index: 1; text-align: center; padding: 20px 16px 28px; font-size: 13px; color: rgba(244, 244, 241, .45); }
        footer a { color: rgba(244, 244, 241, .7); }
        @media (prefers-reduced-motion: reduce) { .glow i, .box { animation: none; } }
    </style>
</head>
<body>
    <div class="glow" aria-hidden="true"><i></i><i></i></div>
    <main>
        <div class="box">
            <h1 class="logo">tixello<span>.</span></h1>
            <p class="lead">Bilete pentru teatre, filarmonici, festivaluri, concerte și competiții.</p>
            <div class="actions">
                @auth
                    <a href="/tenant" class="btn btn-primary">Mergi la panou</a>
                @else
                    <a href="/inregistrare" class="btn btn-primary">Înscrie-te</a>
                    <a href="/tenant/login" class="btn btn-line">Intră în cont</a>
                @endauth
            </div>
        </div>
    </main>
    <footer>Ai un muzeu sau o locație de agrement? Te înscrii pe <a href="https://bilete.online/parteneri">bilete.online</a>.</footer>
</body>
</html>
