<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Contul meu') · Tixello</title>
    <link rel="icon" href="/favicon.ico">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg: #f6f6f4; --surface: #fff; --ink: #151619; --ink-2: #55575c; --ink-3: #8b8d91; --line: #e3e3df; --accent: #e8492a; --accent-hover: #d43f22; --soft: rgba(232, 73, 42, .08); --ok: #13804b; --err: #c4321a; color-scheme: light; }
        @media (prefers-color-scheme: dark) { :root { --bg: #111214; --surface: #18191c; --ink: #f1f1ee; --ink-2: #b4b5b1; --ink-3: #86878a; --line: #2a2b2f; --soft: rgba(232, 73, 42, .14); --ok: #3ccf86; --err: #ff7a5f; color-scheme: dark; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--ink); font: 400 16px/1.55 Geist, system-ui, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: inherit; text-underline-offset: 3px; }
        .top { max-width: 960px; margin: 0 auto; padding: 0 16px; height: 64px; display: flex; align-items: center; justify-content: space-between; }
        .logo { font-weight: 700; font-size: 20px; letter-spacing: -.02em; text-decoration: none; }
        .logo span { color: var(--accent); }
        main { max-width: 960px; margin: 0 auto; padding: 24px 16px 64px; }
        .narrow { max-width: 460px; margin: 6vh auto 0; }
        .card { background: var(--surface); border: 1px solid var(--line); border-radius: 20px; padding: 28px; }
        h1 { font-size: 28px; line-height: 1.15; letter-spacing: -.02em; margin: 0 0 8px; }
        h2 { font-size: 20px; margin: 0 0 12px; }
        .muted { color: var(--ink-2); }
        .small { font-size: 14px; }
        label.field-label { display: block; font-size: 14px; font-weight: 600; margin: 20px 0 6px; }
        input[type=email] { width: 100%; min-height: 48px; padding: 12px 14px; border-radius: 10px; border: 1px solid var(--line); background: var(--surface); color: var(--ink); font: inherit; }
        input[type=email]:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px var(--soft); }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 48px; padding: 12px 22px; border-radius: 999px; border: 1px solid transparent; font: 600 15px/1 Geist, system-ui, sans-serif; cursor: pointer; text-decoration: none; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-line { background: var(--surface); border-color: var(--line); color: var(--ink); }
        .btn-danger { background: transparent; border-color: var(--err); color: var(--err); }
        .btn:focus-visible, a:focus-visible, input:focus-visible { outline: 2px solid var(--accent); outline-offset: 2px; }
        @media (hover: hover) { .btn-primary:hover { background: var(--accent-hover); } .btn-line:hover { border-color: var(--ink-3); } }
        .full { width: 100%; margin-top: 16px; }
        .err { color: var(--err); font-size: 14px; margin-top: 6px; }
        .notice { background: var(--soft); border-radius: 12px; padding: 14px 16px; margin-bottom: 20px; }
        .check { display: flex; gap: 10px; align-items: flex-start; margin-top: 16px; font-size: 14px; color: var(--ink-2); cursor: pointer; }
        .check input { width: 18px; height: 18px; margin-top: 2px; accent-color: var(--accent); flex: none; }
        .orders { display: grid; gap: 12px; }
        .order { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; padding: 18px 20px; display: grid; gap: 12px; }
        @media (min-width: 720px) { .order { grid-template-columns: 1fr auto; align-items: center; } }
        .order h3 { margin: 0; font-size: 17px; }
        .order p { margin: 2px 0 0; }
        .grid2 { display: grid; gap: 16px; margin-top: 32px; }
        @media (min-width: 720px) { .grid2 { grid-template-columns: 1fr 1fr; } }
        footer { text-align: center; color: var(--ink-3); font-size: 13px; padding: 24px 16px 40px; }
    </style>
</head>
<body>
    <header class="top">
        <a href="/" class="logo">tixello<span>.</span></a>
        @yield('top')
    </header>
    <main>@yield('content')</main>
    <footer><a href="/politica-confidentialitate#cumparatori">Cum folosim datele</a> · <a href="/termeni-si-conditii">Termeni</a></footer>
</body>
</html>
