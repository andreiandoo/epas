<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#061a15">
  <title>Autentificare — Nordvale</title>
  <meta name="description" content="Autentificare în contul Nordvale pentru bilete, rezervări și abonamente.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <style>
    :root {
      --pine-950:#061a15; --pine-900:#09251d; --pine-850:#0d3027; --pine-800:#123b30;
      --pine-700:#1b5242; --acid:#dffc62; --ember:#f27b4a; --cream:#fffdf6;
      --oat:#f0ecdf; --moss:#b7c9a3; --ink:#16231e; --sky:#b8dce0;
      --shadow-lift:0 38px 110px -42px rgba(6,26,21,.68);
      --shadow-card:0 20px 58px -30px rgba(6,26,21,.48);
    }
    * { box-sizing:border-box; }
    html { background:var(--oat); }
    body { margin:0; min-height:100vh; overflow-x:hidden; background:var(--oat); color:var(--ink); font-family:'DM Sans',system-ui,sans-serif; text-rendering:optimizeLegibility; }
    button,input { font:inherit; }
    button,a { -webkit-tap-highlight-color:transparent; }
    a { color:inherit; text-decoration:none; }
    [hidden] { display:none !important; }
    ::selection { background:var(--acid); color:var(--pine-950); }

    .page { min-height:100svh; position:relative; isolation:isolate; }
    .page::before { content:""; position:fixed; inset:0; pointer-events:none; z-index:20; opacity:.055; mix-blend-mode:multiply; background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.52'/%3E%3C/svg%3E"); }
    .topo { background-image:radial-gradient(circle at 20% 10%,rgba(223,252,98,.15),transparent 26%),radial-gradient(circle at 80% 20%,rgba(242,123,74,.1),transparent 22%),url("data:image/svg+xml,%3Csvg width='760' height='760' viewBox='0 0 760 760' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.06' stroke-width='1.15'%3E%3Cpath d='M74 208c48-107 174-168 282-110 84 45 97 151 37 216-55 60-150 44-205 105-58 65-56 176-145 207-86 30-177-48-148-137 27-83 127-91 151-170 15-48 0-79 28-111Z'/%3E%3Cpath d='M111 229c39-85 139-133 225-87 67 36 77 120 29 172-44 48-120 35-164 84-47 52-45 141-116 166-69 24-142-38-118-110 21-67 101-73 120-136 12-38 0-63 24-89Z'/%3E%3Cpath d='M466 433c62-88 182-99 250-14 59 74 21 178-66 210-77 29-150-17-215 30-53 39-64 125-133 127-76 3-128-79-85-142 39-57 116-36 164-86 40-42 43-81 85-125Z'/%3E%3Cpath d='M500 468c46-65 136-73 187-10 44 55 16 133-49 157-58 22-112-13-161 22-40 30-48 94-100 96-57 2-96-59-64-106 29-43 87-27 123-65 30-31 33-60 64-94Z'/%3E%3C/g%3E%3C/svg%3E"); background-size:auto,auto,760px 760px; }
    .font-display { font-family:'Fraunces',Georgia,serif; }

    .header-wrap { position:fixed; top:0; left:0; right:0; z-index:50; padding:max(10px,env(safe-area-inset-top)) 12px 0; }
    .header { max-width:1480px; margin:0 auto; min-height:62px; display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px 12px; border:1px solid rgba(255,255,255,.12); border-radius:20px; background:rgba(6,26,21,.82); box-shadow:0 24px 70px -42px rgba(0,0,0,.92); backdrop-filter:blur(22px) saturate(130%); -webkit-backdrop-filter:blur(22px) saturate(130%); }
    .brand { min-width:0; display:flex; align-items:center; gap:10px; color:white; }
    .brand-mark { width:42px; height:42px; flex:0 0 auto; display:grid; place-items:center; border-radius:14px; border:1px solid rgba(255,255,255,.2); background:rgba(255,255,255,.1); }
    .brand-name { display:block; line-height:1; font:600 20px/1 'Fraunces',serif; }
    .brand-sub { display:block; margin-top:5px; max-width:210px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:rgba(255,255,255,.52); font-size:8px; font-weight:700; letter-spacing:.24em; text-transform:uppercase; }
    .header-actions { flex:0 0 auto; display:flex; align-items:center; gap:7px; }
    .nav-link { display:none; align-items:center; gap:8px; padding:10px 15px; border-radius:999px; color:rgba(255,255,255,.78); font-size:14px; font-weight:700; transition:.25s ease; }
    .nav-link:hover { background:rgba(255,255,255,.08); color:white; }
    .ticket-btn { display:inline-flex; flex:0 0 auto; align-items:center; justify-content:center; gap:7px; white-space:nowrap; min-height:40px; padding:0 14px; border:0; border-radius:999px; background:var(--acid); color:var(--pine-950); box-shadow:0 18px 54px -24px rgba(223,252,98,.58); font-size:12px; font-weight:800; cursor:pointer; transition:transform .25s ease,box-shadow .25s ease; }
    .ticket-btn:hover { transform:translateY(-2px); box-shadow:0 22px 58px -24px rgba(223,252,98,.72); }
    .menu-btn { width:40px; height:40px; flex:0 0 auto; display:grid; place-items:center; border:1px solid rgba(255,255,255,.16); border-radius:999px; background:transparent; color:white; cursor:pointer; }

    .mobile-menu { position:fixed; inset:0; z-index:80; display:grid; grid-template-rows:auto 1fr; background:var(--pine-950); color:white; transform:translateX(100%); transition:transform .38s cubic-bezier(.22,.8,.2,1); }
    .mobile-menu.open { transform:translateX(0); }
    .mobile-menu-head { display:flex; align-items:center; justify-content:space-between; padding:max(18px,env(safe-area-inset-top)) 18px 14px; }
    .mobile-menu-body { padding:24px 18px max(30px,env(safe-area-inset-bottom)); display:flex; flex-direction:column; justify-content:space-between; }
    .mobile-menu nav { display:grid; gap:8px; }
    .mobile-menu nav a { padding:16px 2px; border-bottom:1px solid rgba(255,255,255,.1); font:600 30px/1.08 'Fraunces',serif; }

    .main { min-height:100svh; display:grid; grid-template-columns:minmax(0,1fr); padding-top:86px; }
    .visual { position:relative; min-height:420px; overflow:hidden; background:var(--pine-950); color:white; }
    .visual-bg { position:absolute; inset:0; }
    .visual-bg img { width:100%; height:100%; object-fit:cover; object-position:center 43%; filter:saturate(.85) contrast(1.04); opacity:.72; }
    .visual-bg::after { content:""; position:absolute; inset:0; background:linear-gradient(180deg,rgba(6,26,21,.1) 0%,rgba(6,26,21,.48) 58%,rgba(6,26,21,.97) 100%),linear-gradient(90deg,rgba(6,26,21,.25),transparent 60%); }
    .visual-content { position:relative; z-index:2; min-height:420px; padding:38px 20px 24px; display:flex; flex-direction:column; justify-content:flex-end; }
    .eyebrow { display:inline-flex; width:max-content; align-items:center; gap:8px; margin-bottom:16px; padding:8px 11px; border:1px solid rgba(255,255,255,.16); border-radius:999px; background:rgba(6,26,21,.28); color:rgba(255,255,255,.84); backdrop-filter:blur(12px); font-size:10px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; }
    .eyebrow-dot { width:7px; height:7px; border-radius:50%; background:var(--acid); box-shadow:0 0 0 5px rgba(223,252,98,.14); }
    .visual-title { max-width:690px; margin:0; font:600 clamp(38px,9.5vw,74px)/.96 'Fraunces',serif; letter-spacing:-.045em; }
    .visual-title em { color:var(--acid); font-style:normal; }
    .visual-copy { max-width:590px; margin:16px 0 0; color:rgba(255,255,255,.68); font-size:15px; line-height:1.65; }
    .trail-pass { margin-top:24px; display:grid; grid-template-columns:auto minmax(0,1fr) auto; align-items:center; gap:12px; max-width:560px; padding:12px; border:1px solid rgba(255,255,255,.13); border-radius:20px; background:rgba(6,26,21,.54); backdrop-filter:blur(18px); }
    .trail-pass-icon { width:46px; height:46px; display:grid; place-items:center; border-radius:15px; background:var(--acid); color:var(--pine-950); }
    .trail-pass strong { display:block; font-size:13px; color:white; }
    .trail-pass span { display:block; margin-top:4px; color:rgba(255,255,255,.52); font-size:11px; }
    .trail-pass-badge { padding:7px 9px; border-radius:999px; background:rgba(255,255,255,.1); color:var(--acid); font-size:9px; font-weight:800; letter-spacing:.12em; text-transform:uppercase; }

    .route { position:absolute; top:15%; right:-6%; width:min(64vw,620px); height:auto; opacity:.8; pointer-events:none; }
    .route path.main { fill:none; stroke:rgba(223,252,98,.76); stroke-width:2; stroke-linecap:round; stroke-dasharray:7 12; }
    .route path.shadow { fill:none; stroke:rgba(255,255,255,.14); stroke-width:10; stroke-linecap:round; }
    .route .pulse { transform-box:fill-box; transform-origin:center; animation:pulse 2.8s ease-in-out infinite; }
    @keyframes pulse { 0%,100%{transform:scale(.8);opacity:.4}50%{transform:scale(1.4);opacity:1} }

    .auth { position:relative; background:var(--oat); padding:24px 16px max(34px,env(safe-area-inset-bottom)); }
    .auth::before { content:""; position:absolute; inset:0; pointer-events:none; background-image:linear-gradient(rgba(9,37,29,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(9,37,29,.045) 1px,transparent 1px); background-size:30px 30px; mask-image:linear-gradient(to bottom,black,transparent 88%); }
    .auth-inner { position:relative; z-index:2; width:min(100%,560px); margin:0 auto; }
    .back { display:inline-flex; align-items:center; gap:8px; margin:0 0 22px; color:rgba(22,35,30,.62); font-size:13px; font-weight:700; transition:color .2s ease; }
    .back:hover { color:var(--pine-900); }
    .form-card { padding:24px 18px; border:1px solid rgba(9,37,29,.1); border-radius:28px; background:rgba(255,253,246,.9); box-shadow:var(--shadow-lift); backdrop-filter:blur(18px); }
    .form-kicker { margin:0 0 10px; color:var(--pine-700); font-size:10px; font-weight:800; letter-spacing:.2em; text-transform:uppercase; }
    .form-title { margin:0; font:600 clamp(34px,8vw,48px)/1 'Fraunces',serif; letter-spacing:-.04em; }
    .form-copy { margin:13px 0 0; color:rgba(22,35,30,.62); font-size:14px; line-height:1.65; }

    .mode-tabs { margin-top:22px; display:grid; grid-template-columns:1fr 1fr; padding:4px; border-radius:16px; background:rgba(9,37,29,.075); }
    .mode-tab { min-height:42px; border:0; border-radius:12px; background:transparent; color:rgba(22,35,30,.6); font-size:12px; font-weight:800; cursor:pointer; transition:.22s ease; }
    .mode-tab.active { background:var(--cream); color:var(--pine-900); box-shadow:0 10px 30px -20px rgba(6,26,21,.6); }

    .form { margin-top:22px; display:grid; gap:16px; }
    .field { display:grid; gap:8px; }
    .field label { font-size:12px; font-weight:800; color:rgba(22,35,30,.76); }
    .input-wrap { position:relative; }
    .input { width:100%; min-height:52px; padding:0 46px 0 15px; border:1px solid rgba(9,37,29,.16); border-radius:15px; background:rgba(255,255,255,.72); color:var(--ink); outline:none; font-size:14px; transition:border-color .2s ease,box-shadow .2s ease,background .2s ease; }
    .input::placeholder { color:rgba(22,35,30,.36); }
    .input:focus { border-color:var(--pine-700); background:white; box-shadow:0 0 0 4px rgba(27,82,66,.1); }
    .input.invalid { border-color:#c94c3c; box-shadow:0 0 0 4px rgba(201,76,60,.09); }
    .input-icon { position:absolute; right:14px; top:50%; transform:translateY(-50%); width:20px; height:20px; display:grid; place-items:center; color:rgba(22,35,30,.4); border:0; background:transparent; padding:0; cursor:pointer; }
    .field-error { min-height:16px; margin-top:-3px; color:#a43c31; font-size:11px; font-weight:700; }
    .field-row { display:flex; align-items:center; justify-content:space-between; gap:12px; }
    .check { display:flex; align-items:center; gap:9px; color:rgba(22,35,30,.66); font-size:12px; font-weight:600; cursor:pointer; }
    .check input { width:17px; height:17px; accent-color:var(--pine-700); }
    .text-link { color:var(--pine-700); font-size:12px; font-weight:800; }
    .text-link:hover { text-decoration:underline; }
    .submit { width:100%; min-height:54px; display:inline-flex; align-items:center; justify-content:center; gap:9px; border:0; border-radius:16px; background:var(--pine-900); color:white; box-shadow:0 20px 48px -24px rgba(6,26,21,.72); font-size:14px; font-weight:800; cursor:pointer; transition:.25s ease; }
    .submit:hover { transform:translateY(-2px); background:var(--pine-850); }
    .submit:disabled { cursor:not-allowed; opacity:.65; transform:none; }
    .spinner { width:18px; height:18px; border:2px solid rgba(255,255,255,.3); border-top-color:var(--acid); border-radius:50%; animation:spin .75s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }

    .divider { margin:20px 0; display:flex; align-items:center; gap:12px; color:rgba(22,35,30,.36); font-size:10px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; }
    .divider::before,.divider::after { content:""; flex:1; height:1px; background:rgba(9,37,29,.1); }
    .socials { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .social { min-height:48px; display:flex; align-items:center; justify-content:center; gap:9px; border:1px solid rgba(9,37,29,.12); border-radius:15px; background:rgba(255,255,255,.62); color:var(--ink); font-size:12px; font-weight:800; cursor:pointer; transition:.22s ease; }
    .social:hover { transform:translateY(-2px); border-color:rgba(9,37,29,.24); background:white; }
    .account-note { margin:20px 0 0; text-align:center; color:rgba(22,35,30,.55); font-size:12px; }
    .account-note a { color:var(--pine-700); font-weight:800; }

    .magic-success { margin-top:22px; padding:18px; border:1px solid rgba(27,82,66,.15); border-radius:18px; background:rgba(183,201,163,.2); }
    .magic-success-icon { width:44px; height:44px; display:grid; place-items:center; margin-bottom:14px; border-radius:14px; background:var(--acid); color:var(--pine-950); }
    .magic-success h3 { margin:0; font:600 22px/1.1 'Fraunces',serif; }
    .magic-success p { margin:8px 0 0; color:rgba(22,35,30,.62); font-size:13px; line-height:1.55; }

    .security { margin-top:16px; display:grid; grid-template-columns:1fr; gap:8px; }
    .security-item { display:flex; align-items:center; gap:9px; padding:11px 12px; border-radius:14px; background:rgba(9,37,29,.055); color:rgba(22,35,30,.6); font-size:11px; font-weight:700; }
    .security-item svg { color:var(--pine-700); flex:0 0 auto; }

    .toast { position:fixed; left:50%; bottom:max(20px,env(safe-area-inset-bottom)); z-index:100; max-width:calc(100vw - 28px); transform:translate(-50%,24px); opacity:0; pointer-events:none; padding:12px 16px; border-radius:14px; background:var(--pine-950); color:white; box-shadow:0 24px 70px -28px rgba(0,0,0,.8); font-size:12px; font-weight:700; transition:.28s ease; }
    .toast.show { transform:translate(-50%,0); opacity:1; }

    @media (min-width:410px) {
      .ticket-btn { padding:0 16px; font-size:13px; }
      .form-card { padding:28px 24px; }
      .security { grid-template-columns:1fr 1fr; }
    }
    @media (min-width:720px) {
      .header-wrap { padding-left:18px; padding-right:18px; }
      .header { padding-left:16px; padding-right:16px; }
      .main { padding-top:0; grid-template-columns:minmax(0,.96fr) minmax(460px,.74fr); min-height:100svh; }
      .visual { min-height:100svh; }
      .visual-content { min-height:100svh; padding:134px clamp(34px,5vw,74px) 54px; }
      .auth { min-height:100svh; display:flex; align-items:center; padding:112px 34px 48px; }
      .form-card { padding:34px 32px; border-radius:32px; }
      .route { top:14%; right:-16%; width:min(55vw,650px); }
    }
    @media (min-width:1040px) {
      .nav-link { display:inline-flex; }
      .menu-btn { display:none; }
      .main { grid-template-columns:minmax(0,1.14fr) minmax(520px,.76fr); }
      .visual-content { padding-left:clamp(54px,7vw,110px); padding-right:70px; }
      .auth { padding-left:clamp(44px,5vw,84px); padding-right:clamp(44px,5vw,84px); }
      .visual-copy { font-size:16px; }
    }
    @media (max-width:380px) {
      .brand-sub { display:none; }
      .brand-name { font-size:18px; }
      .brand-mark { width:38px; height:38px; border-radius:12px; }
      .header { min-height:58px; padding:8px 9px; }
      .ticket-btn { min-height:38px; padding:0 12px; }
      .menu-btn { width:38px; height:38px; }
      .auth { padding-left:12px; padding-right:12px; }
      .form-card { padding:22px 15px; border-radius:24px; }
      .socials { grid-template-columns:1fr; }
      .trail-pass-badge { display:none; }
    }
    @media (prefers-reduced-motion:reduce) {
      *,*::before,*::after { scroll-behavior:auto!important; animation-duration:.01ms!important; animation-iteration-count:1!important; transition-duration:.01ms!important; }
    }
  </style>
</head>
<body>
<div class="page">
  <header class="header-wrap">
    <div class="header">
      <a class="brand" href="/" aria-label="Nordvale — homepage">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 48 48" width="30" height="30" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/></svg>
        </span>
        <span><span class="brand-name">Nordvale</span><span class="brand-sub">wild park · forest reserve</span></span>
      </a>
      <div class="header-actions">
        <a class="nav-link" href="/">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>
          Înapoi în parc
        </a>
        <a class="ticket-btn" href="/bilete">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5A2.5 2.5 0 0 1 5.5 6H18l3 3v9.5A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5v-10Z"/><path d="M8 6V3m8 3V3M3 11h18"/></svg>
          Bilete
        </a>
        <button class="menu-btn" id="menuOpen" type="button" aria-label="Deschide meniul">
          <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
      </div>
    </div>
  </header>

  <aside class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <div class="mobile-menu-head">
      <a class="brand" href="/"><span class="brand-mark"><svg viewBox="0 0 48 48" width="30" height="30" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/></svg></span><span class="brand-name">Nordvale</span></a>
      <button class="menu-btn" id="menuClose" type="button" aria-label="Închide meniul"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
    </div>
    <div class="mobile-menu-body">
      <nav>
        <a href="/">Acasă</a>
        <a href="/experiente">Experiențe</a>
        <a href="/planifica">Planifică vizita</a>
        <a href="/bilete">Bilete</a>
      </nav>
      <p style="color:rgba(255,255,255,.45);font-size:12px;line-height:1.6">Ai nevoie de ajutor?<br><strong style="color:white">support@nordvale.demo</strong></p>
    </div>
  </aside>

  <main class="main">
    <section class="visual topo" aria-label="Prezentare Nordvale">
      <div class="visual-bg"><img src="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1800&q=86" alt="Potecă printr-o pădure de munte"></div>
      <svg class="route" viewBox="0 0 650 480" fill="none" aria-hidden="true">
        <path class="shadow" d="M42 405c64-15 87-91 155-108 68-18 117 39 178-3 61-43 43-122 111-157 40-21 87-8 122-46"/>
        <path class="main" d="M42 405c64-15 87-91 155-108 68-18 117 39 178-3 61-43 43-122 111-157 40-21 87-8 122-46"/>
        <circle cx="42" cy="405" r="8" fill="#DFFC62"/><circle class="pulse" cx="608" cy="91" r="10" fill="#F27B4A"/>
      </svg>
      <div class="visual-content">
        <div class="eyebrow"><span class="eyebrow-dot"></span>Contul tău de explorator</div>
        <h1 class="visual-title">Toate aventurile tale, <em>într-un singur loc.</em></h1>
        <p class="visual-copy">Accesează biletele, rezervările, abonamentele și traseele salvate. Contul Nordvale este pașaportul tău digital pentru fiecare revenire în pădure.</p>
        <div class="trail-pass">
          <span class="trail-pass-icon"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-4.4 7-11V5l-7-2-7 2v5c0 6.6 7 11 7 11Z"/><path d="m9 12 2 2 4-5"/></svg></span>
          <span><strong>Acces rapid la biletele QR</strong><span>Disponibile și fără conexiune, direct din cont.</span></span>
          <span class="trail-pass-badge">Secure</span>
        </div>
      </div>
    </section>

    <section class="auth">
      <div class="auth-inner">
        <a class="back" href="/"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>Înapoi la Nordvale</a>
        <article class="form-card">
          <p class="form-kicker">Cont client</p>
          <h2 class="form-title">Bine ai revenit.</h2>
          <p class="form-copy">Intră în cont pentru a vedea biletele, vizitele viitoare și avantajele tale de membru.</p>

          <div class="mode-tabs" role="tablist" aria-label="Metodă de autentificare">
            <button class="mode-tab active" type="button" data-mode="password" role="tab" aria-selected="true">Email + parolă</button>
            <button class="mode-tab" type="button" data-mode="magic" role="tab" aria-selected="false">Link magic</button>
          </div>

          <form class="form" id="passwordForm" novalidate>
            <div class="field">
              <label for="email">Adresă de email</label>
              <div class="input-wrap">
                <input class="input" id="email" name="email" type="email" autocomplete="email" placeholder="nume@email.ro" required>
                <span class="input-icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg></span>
              </div>
              <div class="field-error" id="emailError"></div>
            </div>
            <div class="field">
              <label for="password">Parolă</label>
              <div class="input-wrap">
                <input class="input" id="password" name="password" type="password" autocomplete="current-password" placeholder="Minimum 8 caractere" minlength="8" required>
                <button class="input-icon" id="togglePassword" type="button" aria-label="Afișează parola"><svg id="eyeIcon" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button>
              </div>
              <div class="field-error" id="passwordError"></div>
            </div>
            <div class="field-row">
              <label class="check"><input type="checkbox" name="remember">Ține-mă minte</label>
              <a class="text-link" href="/recuperare-parola">Ai uitat parola?</a>
            </div>
            <button class="submit" id="loginButton" type="submit"><span>Intră în cont</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></button>
          </form>

          <form class="form" id="magicForm" hidden novalidate>
            <div id="magicFields">
              <div class="field">
                <label for="magicEmail">Adresă de email</label>
                <div class="input-wrap">
                  <input class="input" id="magicEmail" name="magicEmail" type="email" autocomplete="email" placeholder="nume@email.ro" required>
                  <span class="input-icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg></span>
                </div>
                <div class="field-error" id="magicEmailError"></div>
              </div>
              <p style="margin:0;color:rgba(22,35,30,.56);font-size:12px;line-height:1.6">Îți trimitem un link securizat, valabil 15 minute. Nu ai nevoie de parolă.</p>
              <button class="submit" id="magicButton" type="submit"><span>Trimite linkul magic</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg></button>
            </div>
            <div class="magic-success" id="magicSuccess" hidden>
              <div class="magic-success-icon"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg></div>
              <h3>Verifică emailul.</h3>
              <p>Am trimis linkul de autentificare la <strong id="sentEmail"></strong>. Poți închide această pagină după ce deschizi mesajul.</p>
              <button class="text-link" id="resendMagic" type="button" style="margin-top:12px;border:0;background:transparent;padding:0;cursor:pointer">Trimite din nou</button>
            </div>
          </form>

          <div class="divider">sau continuă cu</div>
          <div class="socials">
            <button class="social" type="button" data-social="Google"><svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M21.6 12.23c0-.71-.06-1.4-.18-2.06H12v3.9h5.38a4.6 4.6 0 0 1-1.99 3.02v2.53h3.22c1.88-1.73 2.99-4.28 2.99-7.39Z"/><path fill="#34A853" d="M12 22c2.7 0 4.96-.89 6.61-2.38l-3.22-2.53c-.89.6-2.03.96-3.39.96-2.6 0-4.8-1.75-5.59-4.11H3.08v2.61A9.99 9.99 0 0 0 12 22Z"/><path fill="#FBBC05" d="M6.41 13.94A5.99 5.99 0 0 1 6.1 12c0-.67.11-1.32.31-1.94V7.45H3.08A10 10 0 0 0 2 12c0 1.61.38 3.13 1.08 4.55l3.33-2.61Z"/><path fill="#EA4335" d="M12 5.95c1.47 0 2.79.5 3.83 1.5l2.87-2.87C16.95 2.95 14.69 2 12 2a9.99 9.99 0 0 0-8.92 5.45l3.33 2.61C7.2 7.7 9.4 5.95 12 5.95Z"/></svg>Google</button>
            <button class="social" type="button" data-social="Apple"><svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16.7 12.9c0-2.8 2.3-4.1 2.4-4.2-1.3-1.9-3.4-2.2-4.1-2.2-1.7-.2-3.4 1-4.3 1-.9 0-2.3-1-3.8-.9-2 .1-3.8 1.1-4.8 2.9-2.1 3.6-.5 8.9 1.5 11.8 1 1.4 2.1 2.9 3.6 2.8 1.4-.1 2-.9 3.7-.9s2.2.9 3.7.9c1.5 0 2.5-1.4 3.4-2.8 1.1-1.6 1.5-3.1 1.5-3.2-.1 0-2.8-1.1-2.8-5.2Zm-2.8-8.2c.8-1 1.3-2.3 1.2-3.7-1.2.1-2.6.8-3.4 1.8-.7.8-1.4 2.2-1.2 3.5 1.3.1 2.6-.7 3.4-1.6Z"/></svg>Apple</button>
          </div>

          <p class="account-note">Nu ai cont? <a href="/inregistrare">Creează unul gratuit</a></p>
          <div class="security">
            <div class="security-item"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Date criptate SSL</div>
            <div class="security-item"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-4.4 7-11V5l-7-2-7 2v5c0 6.6 7 11 7 11Z"/><path d="m9 12 2 2 4-5"/></svg>Cont protejat</div>
          </div>
        </article>
      </div>
    </section>
  </main>

  <div class="toast" id="toast" role="status" aria-live="polite"></div>
</div>
<script>
(function(){
  'use strict';
  const $ = (selector, root=document) => root.querySelector(selector);
  const $$ = (selector, root=document) => Array.from(root.querySelectorAll(selector));
  const menu = $('#mobileMenu');
  const menuOpen = $('#menuOpen');
  const menuClose = $('#menuClose');
  const toast = $('#toast');
  let toastTimer;

  function setMenu(open){
    if(!menu) return;
    menu.classList.toggle('open', open);
    menu.setAttribute('aria-hidden', String(!open));
    document.body.style.overflow = open ? 'hidden' : '';
  }
  menuOpen?.addEventListener('click',()=>setMenu(true));
  menuClose?.addEventListener('click',()=>setMenu(false));
  menu?.addEventListener('click',(e)=>{ if(e.target===menu) setMenu(false); });
  document.addEventListener('keydown',(e)=>{ if(e.key==='Escape') setMenu(false); });

  function showToast(message){
    if(!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(()=>toast.classList.remove('show'),2600);
  }

  const tabs = $$('.mode-tab');
  const passwordForm = $('#passwordForm');
  const magicForm = $('#magicForm');
  function setMode(mode){
    tabs.forEach(tab=>{
      const active = tab.dataset.mode===mode;
      tab.classList.toggle('active',active);
      tab.setAttribute('aria-selected',String(active));
    });
    if(passwordForm) passwordForm.hidden = mode!=='password';
    if(magicForm) magicForm.hidden = mode!=='magic';
  }
  tabs.forEach(tab=>tab.addEventListener('click',()=>setMode(tab.dataset.mode)));

  const password = $('#password');
  const togglePassword = $('#togglePassword');
  togglePassword?.addEventListener('click',()=>{
    if(!password) return;
    const reveal = password.type==='password';
    password.type = reveal ? 'text' : 'password';
    togglePassword.setAttribute('aria-label',reveal?'Ascunde parola':'Afișează parola');
  });

  function validEmail(value){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim()); }
  function setInvalid(input,error,message){
    input?.classList.toggle('invalid',Boolean(message));
    if(error) error.textContent = message || '';
  }

  passwordForm?.addEventListener('submit',(event)=>{
    event.preventDefault();
    const email = $('#email');
    const emailError = $('#emailError');
    const passwordError = $('#passwordError');
    let valid = true;
    if(!email || !validEmail(email.value)){ setInvalid(email,emailError,'Introdu o adresă de email validă.'); valid=false; } else setInvalid(email,emailError,'');
    if(!password || password.value.length<8){ setInvalid(password,passwordError,'Parola trebuie să aibă minimum 8 caractere.'); valid=false; } else setInvalid(password,passwordError,'');
    if(!valid) return;
    const button = $('#loginButton');
    if(!button) return;
    const original = button.innerHTML;
    button.disabled = true;
    button.innerHTML = '<span class="spinner" aria-hidden="true"></span><span>Se autentifică…</span>';
    (async ()=>{
      try{
        const r = await fetch('/api/proxy.php?action=login', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ email: email.value.trim(), password: password.value }) });
        const d = await r.json().catch(()=>({}));
        if (r.ok && d.success && d.data && d.data.token) {
          localStorage.setItem('nordvale_auth', JSON.stringify({ token: d.data.token, user: d.data.user }));
          const next = new URLSearchParams(location.search).get('next');
          window.location.href = (next && next.startsWith('/')) ? next : '/cont';
          return;
        }
        button.disabled = false; button.innerHTML = original;
        const msg = (d && (d.message || (d.errors && d.errors.email && d.errors.email[0]))) || 'Date de autentificare incorecte.';
        setInvalid(password, passwordError, msg);
        showToast(msg);
      }catch(e){ button.disabled = false; button.innerHTML = original; showToast('Eroare de conexiune.'); }
    })();
  });

  const magicFormEl = $('#magicForm');
  magicFormEl?.addEventListener('submit',(event)=>{
    event.preventDefault();
    const input = $('#magicEmail');
    const error = $('#magicEmailError');
    if(!input || !validEmail(input.value)){ setInvalid(input,error,'Introdu o adresă de email validă.'); return; }
    setInvalid(input,error,'');
    const button = $('#magicButton');
    const original = button?.innerHTML || '';
    if(button){ button.disabled=true; button.innerHTML='<span class="spinner" aria-hidden="true"></span><span>Se trimite…</span>'; }
    setTimeout(()=>{
      if(button){ button.disabled=false; button.innerHTML=original; }
      const fields = $('#magicFields'); const success = $('#magicSuccess'); const sent = $('#sentEmail');
      if(sent) sent.textContent=input.value.trim();
      if(fields) fields.hidden=true;
      if(success) success.hidden=false;
    },900);
  });
  $('#resendMagic')?.addEventListener('click',()=>showToast('Linkul magic a fost retrimis.'));

  $$('[data-social]').forEach(button=>button.addEventListener('click',()=>showToast(`Demo: autentificare cu ${button.dataset.social}.`)));
  $$('.input').forEach(input=>input.addEventListener('input',()=>input.classList.remove('invalid')));

  if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'animate' in Element.prototype){
    $$('.visual-content > *').forEach((element,index)=>{
      element.animate([{opacity:0,transform:'translateY(24px)'},{opacity:1,transform:'translateY(0)'}],{duration:650,delay:120+index*90,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'});
    });
    $('.form-card')?.animate([{opacity:0,transform:'translateY(20px) scale(.985)'},{opacity:1,transform:'translateY(0) scale(1)'}],{duration:700,delay:220,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'});
  }
})();
</script>
</body>
</html>
