<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#061a15">
  <title>Creează cont — Nordvale</title>
  <meta name="description" content="Creează un cont Nordvale pentru bilete, rezervări, abonamente și itinerarii personalizate.">
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
  

    .register-auth { align-items:flex-start; }
    .register-card { overflow:hidden; }
    .progress { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; margin:22px 0 24px; }
    .progress-step { position:relative; min-width:0; }
    .progress-step::after { content:""; position:absolute; top:18px; left:calc(50% + 19px); right:calc(-50% + 19px); height:1px; background:rgba(9,37,29,.12); }
    .progress-step:last-child::after { display:none; }
    .progress-head { display:flex; align-items:center; gap:9px; min-width:0; }
    .progress-dot { position:relative; z-index:2; width:36px; height:36px; flex:0 0 auto; display:grid; place-items:center; border:1px solid rgba(9,37,29,.14); border-radius:50%; background:var(--cream); color:rgba(22,35,30,.46); font-size:12px; font-weight:800; transition:.25s ease; }
    .progress-step.active .progress-dot { border-color:var(--pine-900); background:var(--pine-900); color:white; box-shadow:0 12px 30px -18px rgba(6,26,21,.9); }
    .progress-step.done .progress-dot { border-color:var(--acid); background:var(--acid); color:var(--pine-950); }
    .progress-label { min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:rgba(22,35,30,.48); font-size:10px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; }
    .progress-step.active .progress-label,.progress-step.done .progress-label { color:var(--pine-900); }

    .step-panel { display:none; }
    .step-panel.active { display:block; animation:stepIn .38s cubic-bezier(.22,.8,.2,1) both; }
    @keyframes stepIn { from{opacity:0;transform:translateX(12px)} to{opacity:1;transform:translateX(0)} }
    .form-grid { display:grid; grid-template-columns:1fr; gap:15px; }
    .field-note { margin:6px 0 0; color:rgba(22,35,30,.48); font-size:10px; line-height:1.5; }
    .password-meter { margin-top:9px; display:grid; grid-template-columns:repeat(4,1fr); gap:5px; }
    .password-meter span { height:4px; border-radius:999px; background:rgba(9,37,29,.1); transition:.25s ease; }
    .password-meter[data-strength="1"] span:nth-child(-n+1) { background:#e36f57; }
    .password-meter[data-strength="2"] span:nth-child(-n+2) { background:#e7a94f; }
    .password-meter[data-strength="3"] span:nth-child(-n+3) { background:#9fbf65; }
    .password-meter[data-strength="4"] span:nth-child(-n+4) { background:var(--pine-700); }
    .password-label { margin-top:7px; color:rgba(22,35,30,.52); font-size:10px; font-weight:700; }

    .choice-grid { display:grid; grid-template-columns:1fr; gap:10px; }
    .choice { position:relative; display:block; cursor:pointer; }
    .choice input { position:absolute; opacity:0; pointer-events:none; }
    .choice-card { min-height:104px; display:flex; align-items:flex-start; gap:13px; padding:15px; border:1px solid rgba(9,37,29,.11); border-radius:18px; background:rgba(255,255,255,.56); transition:.24s ease; }
    .choice-card:hover { transform:translateY(-2px); border-color:rgba(9,37,29,.24); background:white; }
    .choice input:checked + .choice-card { border-color:var(--pine-700); background:rgba(183,201,163,.22); box-shadow:inset 0 0 0 1px rgba(27,82,66,.08); }
    .choice-icon { width:40px; height:40px; flex:0 0 auto; display:grid; place-items:center; border-radius:13px; background:var(--pine-900); color:var(--acid); }
    .choice-card strong { display:block; font:600 16px/1.15 'Fraunces',serif; }
    .choice-card span { display:block; margin-top:5px; color:rgba(22,35,30,.55); font-size:11px; line-height:1.45; }

    .interest-grid { display:flex; flex-wrap:wrap; gap:8px; }
    .interest { position:relative; cursor:pointer; }
    .interest input { position:absolute; opacity:0; pointer-events:none; }
    .interest span { display:inline-flex; align-items:center; gap:7px; min-height:38px; padding:0 12px; border:1px solid rgba(9,37,29,.12); border-radius:999px; background:rgba(255,255,255,.56); color:rgba(22,35,30,.68); font-size:11px; font-weight:800; transition:.22s ease; }
    .interest input:checked + span { border-color:var(--pine-700); background:var(--pine-900); color:white; }
    .interest span::before { content:"+"; font-size:14px; color:var(--pine-700); }
    .interest input:checked + span::before { content:"✓"; color:var(--acid); }

    .consent-box { display:grid; gap:10px; padding:15px; border:1px solid rgba(9,37,29,.1); border-radius:18px; background:rgba(9,37,29,.035); }
    .consent { display:flex; align-items:flex-start; gap:10px; color:rgba(22,35,30,.63); font-size:11px; line-height:1.5; cursor:pointer; }
    .consent input { width:17px; height:17px; flex:0 0 auto; margin-top:1px; accent-color:var(--pine-700); }
    .consent a { color:var(--pine-700); font-weight:800; text-decoration:underline; text-underline-offset:2px; }

    .step-actions { display:flex; flex-direction:column-reverse; gap:9px; margin-top:20px; }
    .secondary { min-height:48px; display:inline-flex; align-items:center; justify-content:center; gap:8px; border:1px solid rgba(9,37,29,.12); border-radius:15px; background:transparent; color:var(--pine-900); font-size:12px; font-weight:800; cursor:pointer; transition:.22s ease; }
    .secondary:hover { background:rgba(9,37,29,.05); }

    .success-wrap { text-align:center; padding:8px 0 2px; }
    .success-badge { position:relative; width:76px; height:76px; display:grid; place-items:center; margin:0 auto 18px; border-radius:24px; background:var(--acid); color:var(--pine-950); box-shadow:0 26px 54px -30px rgba(223,252,98,.85); }
    .success-badge::before { content:""; position:absolute; inset:-9px; border:1px solid rgba(27,82,66,.17); border-radius:29px; animation:successPulse 2.4s ease-in-out infinite; }
    @keyframes successPulse { 0%,100%{transform:scale(.96);opacity:.5}50%{transform:scale(1.05);opacity:1} }
    .success-wrap h3 { margin:0; font:600 30px/1.05 'Fraunces',serif; }
    .success-wrap p { max-width:420px; margin:10px auto 0; color:rgba(22,35,30,.58); font-size:12px; line-height:1.6; }
    .passport { position:relative; margin:20px 0; padding:18px; overflow:hidden; border-radius:22px; background:var(--pine-950); color:white; text-align:left; box-shadow:var(--shadow-card); }
    .passport::after { content:""; position:absolute; width:150px; height:150px; right:-45px; top:-45px; border:1px solid rgba(223,252,98,.18); border-radius:50%; box-shadow:0 0 0 18px rgba(223,252,98,.04),0 0 0 42px rgba(223,252,98,.025); }
    .passport-kicker { color:var(--acid); font-size:9px; font-weight:800; letter-spacing:.18em; text-transform:uppercase; }
    .passport-name { margin-top:9px; font:600 23px/1.1 'Fraunces',serif; }
    .passport-meta { margin-top:7px; color:rgba(255,255,255,.52); font-size:10px; }
    .passport-id { margin-top:18px; display:flex; align-items:end; justify-content:space-between; gap:12px; }
    .passport-id strong { font-size:11px; letter-spacing:.1em; }
    .passport-mark { width:44px; height:44px; display:grid; place-items:center; border:1px solid rgba(255,255,255,.16); border-radius:14px; background:rgba(255,255,255,.08); }

    .benefit-list { margin-top:20px; display:grid; grid-template-columns:1fr; gap:8px; }
    .benefit { display:flex; align-items:center; gap:10px; padding:11px 12px; border-radius:14px; background:rgba(183,201,163,.17); color:rgba(22,35,30,.68); font-size:11px; font-weight:700; text-align:left; }
    .benefit svg { flex:0 0 auto; color:var(--pine-700); }

    @media (min-width:520px) {
      .form-grid.two { grid-template-columns:1fr 1fr; }
      .choice-grid { grid-template-columns:repeat(3,1fr); }
      .step-actions { flex-direction:row; }
      .step-actions .submit { flex:1; }
      .step-actions .secondary { min-width:132px; }
      .benefit-list { grid-template-columns:1fr 1fr; }
    }
    @media (min-width:720px) {
      .register-auth { align-items:flex-start; overflow:auto; }
      .register-card { margin-top:8px; margin-bottom:24px; }
    }
    @media (max-width:420px) {
      .progress { gap:4px; }
      .progress-label { display:none; }
      .progress-step::after { left:calc(50% + 18px); right:calc(-50% + 18px); }
      .choice-card { min-height:unset; }
      .interest span { min-height:36px; padding:0 10px; }
    }

  </style>
</head>
<body>
<div class="page">
  <header class="header-wrap">
    <div class="header">
      <a class="brand" href="/" aria-label="Nordvale — homepage">
        <span class="brand-mark" aria-hidden="true"><svg viewBox="0 0 48 48" width="30" height="30" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/></svg></span>
        <span><span class="brand-name">Nordvale</span><span class="brand-sub">wild park · forest reserve</span></span>
      </a>
      <div class="header-actions">
        <a class="nav-link" href="/autentificare"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>Am deja cont</a>
        <a class="ticket-btn" href="/bilete"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8.5A2.5 2.5 0 0 1 5.5 6H18l3 3v9.5A2.5 2.5 0 0 1 18.5 21h-13A2.5 2.5 0 0 1 3 18.5v-10Z"/><path d="M8 6V3m8 3V3M3 11h18"/></svg>Bilete</a>
        <button class="menu-btn" id="menuOpen" type="button" aria-label="Deschide meniul"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
      </div>
    </div>
  </header>

  <aside class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <div class="mobile-menu-head">
      <a class="brand" href="/"><span class="brand-mark"><svg viewBox="0 0 48 48" width="30" height="30" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/></svg></span><span class="brand-name">Nordvale</span></a>
      <button class="menu-btn" id="menuClose" type="button" aria-label="Închide meniul"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
    </div>
    <div class="mobile-menu-body">
      <nav><a href="/">Acasă</a><a href="/experiente">Experiențe</a><a href="/planifica">Planifică vizita</a><a href="/bilete">Bilete</a></nav>
      <p style="color:rgba(255,255,255,.45);font-size:12px;line-height:1.6">Ai deja un cont?<br><a href="/autentificare" style="color:white;font-weight:800">Autentifică-te</a></p>
    </div>
  </aside>

  <main class="main">
    <section class="visual topo" aria-label="Comunitatea Nordvale">
      <div class="visual-bg"><img src="https://images.unsplash.com/photo-1517825738774-7de9363ef735?auto=format&fit=crop&w=1800&q=86" alt="Familie explorând o pădure de munte"></div>
      <svg class="route" viewBox="0 0 650 480" fill="none" aria-hidden="true"><path class="shadow" d="M42 405c64-15 87-91 155-108 68-18 117 39 178-3 61-43 43-122 111-157 40-21 87-8 122-46"/><path class="main" d="M42 405c64-15 87-91 155-108 68-18 117 39 178-3 61-43 43-122 111-157 40-21 87-8 122-46"/><circle cx="42" cy="405" r="8" fill="#DFFC62"/><circle class="pulse" cx="608" cy="91" r="10" fill="#F27B4A"/></svg>
      <div class="visual-content">
        <div class="eyebrow"><span class="eyebrow-dot"></span>Pașaportul tău Nordvale</div>
        <h1 class="visual-title">Începe o relație mai lungă cu <em>pădurea.</em></h1>
        <p class="visual-copy">Salvezi trasee, primești biletele instant și construiești itinerarii pentru fiecare revenire. Contul rămâne gratuit, indiferent cât de des ne vizitezi.</p>
        <div class="trail-pass">
          <span class="trail-pass-icon"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M5 8l7-5 7 5M6 13l6-4 6 4M8 18l4-3 4 3"/></svg></span>
          <span><strong>Itinerarii care se adaptează familiei tale</strong><span>Preferințele pot fi schimbate oricând din cont.</span></span>
          <span class="trail-pass-badge">Free</span>
        </div>
      </div>
    </section>

    <section class="auth register-auth">
      <div class="auth-inner">
        <a class="back" href="/autentificare"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>Am deja cont</a>
        <article class="form-card register-card">
          <p class="form-kicker">Cont client gratuit</p>
          <h2 class="form-title">Creează-ți pașaportul.</h2>
          <p class="form-copy">Durează mai puțin de două minute. Îți poți completa profilul și după prima rezervare.</p>

          <div class="progress" aria-label="Progres creare cont">
            <div class="progress-step active" data-progress="1"><div class="progress-head"><span class="progress-dot">1</span><span class="progress-label">Date</span></div></div>
            <div class="progress-step" data-progress="2"><div class="progress-head"><span class="progress-dot">2</span><span class="progress-label">Preferințe</span></div></div>
            <div class="progress-step" data-progress="3"><div class="progress-head"><span class="progress-dot">3</span><span class="progress-label">Gata</span></div></div>
          </div>

          <form id="registerForm" novalidate>
            <section class="step-panel active" data-step="1">
              <div class="form-grid two">
                <div class="field"><label for="firstName">Prenume</label><div class="input-wrap"><input class="input" id="firstName" name="firstName" autocomplete="given-name" placeholder="Andrei" required><span class="input-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span></div><div class="field-error" id="firstNameError"></div></div>
                <div class="field"><label for="lastName">Nume</label><div class="input-wrap"><input class="input" id="lastName" name="lastName" autocomplete="family-name" placeholder="Popescu" required><span class="input-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></span></div><div class="field-error" id="lastNameError"></div></div>
              </div>
              <div class="form-grid" style="margin-top:15px">
                <div class="field"><label for="email">Adresă de email</label><div class="input-wrap"><input class="input" id="email" name="email" type="email" autocomplete="email" placeholder="nume@email.ro" required><span class="input-icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg></span></div><div class="field-error" id="emailError"></div></div>
                <div class="field"><label for="phone">Telefon <span style="font-weight:500;color:rgba(22,35,30,.42)">(opțional)</span></label><div class="input-wrap"><input class="input" id="phone" name="phone" type="tel" autocomplete="tel" placeholder="07xx xxx xxx"><span class="input-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.3 1.8.7 2.6a2 2 0 0 1-.5 2.1L8 9.7a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.8.4 1.7.6 2.6.7a2 2 0 0 1 2 2.3Z"/></svg></span></div></div>
                <div class="field"><label for="password">Parolă</label><div class="input-wrap"><input class="input" id="password" name="password" type="password" autocomplete="new-password" placeholder="Minimum 8 caractere" minlength="8" required><button class="input-icon" id="togglePassword" type="button" aria-label="Afișează parola"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg></button></div><div class="password-meter" id="passwordMeter" data-strength="0"><span></span><span></span><span></span><span></span></div><div class="password-label" id="passwordLabel">Folosește litere, cifre și un simbol.</div><div class="field-error" id="passwordError"></div></div>
                <div class="field"><label for="confirmPassword">Confirmă parola</label><div class="input-wrap"><input class="input" id="confirmPassword" name="confirmPassword" type="password" autocomplete="new-password" placeholder="Repetă parola" required><span class="input-icon" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-4.4 7-11V5l-7-2-7 2v5c0 6.6 7 11 7 11Z"/><path d="m9 12 2 2 4-5"/></svg></span></div><div class="field-error" id="confirmPasswordError"></div></div>
              </div>
              <div class="step-actions"><button class="submit" type="button" data-next="2"><span>Continuă</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></button></div>
            </section>

            <section class="step-panel" data-step="2">
              <div class="field"><label>Cum vizitezi de obicei?</label><div class="choice-grid">
                <label class="choice"><input type="radio" name="profileType" value="solo"><span class="choice-card"><span class="choice-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"/><path d="M5 21a7 7 0 0 1 14 0"/></svg></span><span><strong>Individual</strong><span>Trasee personale și ritm flexibil.</span></span></span></label>
                <label class="choice"><input type="radio" name="profileType" value="family" checked><span class="choice-card"><span class="choice-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="8" r="3"/><circle cx="17" cy="9" r="2.5"/><path d="M3 21a6 6 0 0 1 12 0M13 21a5 5 0 0 1 8 0"/></svg></span><span><strong>Familie</strong><span>Recomandări după vârstă și energie.</span></span></span></label>
                <label class="choice"><input type="radio" name="profileType" value="group"><span class="choice-card"><span class="choice-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="3"/><circle cx="16" cy="8" r="3"/><path d="M2 21a6 6 0 0 1 12 0M10 21a6 6 0 0 1 12 0"/></svg></span><span><strong>Grup</strong><span>Coordonare simplă pentru mai mulți.</span></span></span></label>
              </div></div>

              <div class="field" style="margin-top:18px"><label>Ce te atrage la Nordvale?</label><p class="field-note">Alege minimum două. Le folosim doar pentru recomandări.</p><div class="interest-grid" style="margin-top:10px">
                <label class="interest"><input type="checkbox" name="interests" value="adrenalina" checked><span>Adrenalină</span></label>
                <label class="interest"><input type="checkbox" name="interests" value="natura" checked><span>Natură</span></label>
                <label class="interest"><input type="checkbox" name="interests" value="copii"><span>Copii</span></label>
                <label class="interest"><input type="checkbox" name="interests" value="trasee"><span>Trasee lungi</span></label>
                <label class="interest"><input type="checkbox" name="interests" value="relaxare"><span>Relaxare</span></label>
                <label class="interest"><input type="checkbox" name="interests" value="evenimente"><span>Evenimente</span></label>
              </div><div class="field-error" id="interestsError"></div></div>

              <div class="consent-box" style="margin-top:18px">
                <label class="consent"><input id="terms" type="checkbox" required><span>Sunt de acord cu <a href="/termeni">Termenii și condițiile</a> și confirm că am citit <a href="/confidentialitate">Politica de confidențialitate</a>.</span></label>
                <label class="consent"><input id="newsletter" type="checkbox"><span>Vreau să primesc noutăți, lansări de trasee și oferte Nordvale. Pot renunța oricând.</span></label>
                <div class="field-error" id="termsError"></div>
              </div>
              <div class="step-actions"><button class="secondary" type="button" data-back="1"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>Înapoi</button><button class="submit" id="createButton" type="submit"><span>Creează contul</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></button></div>
            </section>

            <section class="step-panel" data-step="3">
              <div class="success-wrap">
                <div class="success-badge"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="m5 12 4 4L19 6"/></svg></div>
                <h3>Bun venit în Nordvale.</h3>
                <p>Contul este gata. Ți-am pregătit pașaportul digital și un spațiu în care vei găsi fiecare bilet și rezervare.</p>
                <div class="passport"><div class="passport-kicker">Nordvale Explorer Passport</div><div class="passport-name" id="passportName">Explorator Nordvale</div><div class="passport-meta" id="passportMeta">Profil familie · 2 interese selectate</div><div class="passport-id"><strong id="passportId">NV-000000</strong><span class="passport-mark"><svg viewBox="0 0 48 48" width="30" height="30" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/></svg></span></div></div>
                <div class="benefit-list"><div class="benefit"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h5"/></svg>Bilete și facturi într-un singur loc</div><div class="benefit"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11 12 3l9 8-9 10L3 11Z"/><circle cx="12" cy="11" r="2"/></svg>Recomandări adaptate profilului</div></div>
                <button class="submit" id="openAccount" type="button" style="margin-top:20px"><span>Deschide contul meu</span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14m-5-5 5 5-5 5"/></svg></button>
              </div>
            </section>
          </form>

          <p class="account-note" id="loginNote">Ai deja cont? <a href="/autentificare">Autentifică-te</a></p>
          <div class="security"><div class="security-item"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Date criptate SSL</div><div class="security-item"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-4.4 7-11V5l-7-2-7 2v5c0 6.6 7 11 7 11Z"/><path d="m9 12 2 2 4-5"/></svg>Fără taxe de cont</div></div>
        </article>
      </div>
    </section>
  </main>
  <div class="toast" id="toast" role="status" aria-live="polite"></div>
</div>
<script>
(function(){
  'use strict';
  const $=(s,r=document)=>r.querySelector(s); const $$=(s,r=document)=>Array.from(r.querySelectorAll(s));
  const menu=$('#mobileMenu'); const toast=$('#toast'); let toastTimer; let currentStep=1;
  function setMenu(open){ if(!menu)return; menu.classList.toggle('open',open); menu.setAttribute('aria-hidden',String(!open)); document.body.style.overflow=open?'hidden':''; }
  $('#menuOpen')?.addEventListener('click',()=>setMenu(true)); $('#menuClose')?.addEventListener('click',()=>setMenu(false)); document.addEventListener('keydown',e=>{if(e.key==='Escape')setMenu(false)});
  function showToast(message){ if(!toast)return; toast.textContent=message; toast.classList.add('show'); clearTimeout(toastTimer); toastTimer=setTimeout(()=>toast.classList.remove('show'),2600); }
  function validEmail(v){return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim())}
  function setInvalid(input,error,message){input?.classList.toggle('invalid',Boolean(message)); if(error)error.textContent=message||'';}
  function goTo(step){ currentStep=step; $$('.step-panel').forEach(p=>p.classList.toggle('active',Number(p.dataset.step)===step)); $$('.progress-step').forEach(p=>{const n=Number(p.dataset.progress);p.classList.toggle('active',n===step);p.classList.toggle('done',n<step)}); if(step===3){$('#loginNote')?.setAttribute('hidden','');} else {$('#loginNote')?.removeAttribute('hidden');} document.querySelector('.register-card')?.scrollIntoView({behavior:'smooth',block:'start'}); }
  function validateStep1(){let ok=true; const first=$('#firstName'),last=$('#lastName'),email=$('#email'),pass=$('#password'),confirm=$('#confirmPassword'); if(!first?.value.trim()){setInvalid(first,$('#firstNameError'),'Introdu prenumele.');ok=false}else setInvalid(first,$('#firstNameError'),''); if(!last?.value.trim()){setInvalid(last,$('#lastNameError'),'Introdu numele.');ok=false}else setInvalid(last,$('#lastNameError'),''); if(!email||!validEmail(email.value)){setInvalid(email,$('#emailError'),'Introdu o adresă de email validă.');ok=false}else setInvalid(email,$('#emailError'),''); if(!pass||pass.value.length<8){setInvalid(pass,$('#passwordError'),'Parola trebuie să aibă minimum 8 caractere.');ok=false}else setInvalid(pass,$('#passwordError'),''); if(!confirm||confirm.value!==pass?.value){setInvalid(confirm,$('#confirmPasswordError'),'Parolele nu coincid.');ok=false}else setInvalid(confirm,$('#confirmPasswordError'),''); return ok;}
  $$('[data-next]').forEach(b=>b.addEventListener('click',()=>{if(validateStep1())goTo(Number(b.dataset.next))})); $$('[data-back]').forEach(b=>b.addEventListener('click',()=>goTo(Number(b.dataset.back))));
  const password=$('#password'); const meter=$('#passwordMeter'); const passLabel=$('#passwordLabel');
  function strength(value){let score=0;if(value.length>=8)score++;if(/[A-Z]/.test(value)&&/[a-z]/.test(value))score++;if(/\d/.test(value))score++;if(/[^A-Za-z0-9]/.test(value))score++;return score;}
  password?.addEventListener('input',()=>{const s=strength(password.value);meter?.setAttribute('data-strength',String(s));if(passLabel)passLabel.textContent=['Folosește litere, cifre și un simbol.','Parolă slabă.','Parolă acceptabilă.','Parolă bună.','Parolă puternică.'][s];});
  $('#togglePassword')?.addEventListener('click',()=>{if(!password)return; const show=password.type==='password'; password.type=show?'text':'password'; $('#togglePassword')?.setAttribute('aria-label',show?'Ascunde parola':'Afișează parola');});
  $$('.input').forEach(i=>i.addEventListener('input',()=>i.classList.remove('invalid')));
  $('#registerForm')?.addEventListener('submit',e=>{e.preventDefault();const interests=$$('input[name="interests"]:checked');let ok=true;if(interests.length<2){$('#interestsError').textContent='Alege cel puțin două interese.';ok=false}else $('#interestsError').textContent='';if(!$('#terms')?.checked){$('#termsError').textContent='Trebuie să accepți termenii pentru a continua.';ok=false}else $('#termsError').textContent='';if(!ok)return;const button=$('#createButton');const original=button?.innerHTML||'';if(button){button.disabled=true;button.innerHTML='<span class="spinner" aria-hidden="true"></span><span>Se creează…</span>';}(async()=>{const first=$('#firstName')?.value.trim()||'';const last=$('#lastName')?.value.trim()||'';const email=$('#email')?.value.trim()||'';const pass=$('#password')?.value||'';try{const r=await fetch('/api/proxy.php?action=register',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({first_name:first,last_name:last,name:(first+' '+last).trim(),email:email,password:pass,password_confirmation:$('#confirmPassword')?.value||pass})});const d=await r.json().catch(()=>({}));if(button){button.disabled=false;button.innerHTML=original;}if(r.ok&&d.success&&d.data&&d.data.token){localStorage.setItem('nordvale_auth',JSON.stringify({token:d.data.token,user:d.data.user}));}else if(!(r.ok&&d.success)){const msg=(d&&(d.message||(d.errors&&d.errors.email&&d.errors.email[0])))||'Nu am putut crea contul.';showToast(msg);return;}const profile=$('input[name="profileType"]:checked')?.value||'family';const labels={solo:'individual',family:'familie',group:'grup'};$('#passportName').textContent=`${first||'Explorator'} ${last||'Nordvale'}`;$('#passportMeta').textContent=`Profil ${labels[profile]} · ${interests.length} interese selectate`;$('#passportId').textContent=`NV-${String(Math.floor(100000+Math.random()*900000))}`;goTo(3);}catch(e){if(button){button.disabled=false;button.innerHTML=original;}showToast('Eroare de conexiune.');}})();});
  $('#openAccount')?.addEventListener('click',()=>{showToast('Demo: cont creat cu succes.');setTimeout(()=>window.location.href='/cont',450)});
  if(!window.matchMedia('(prefers-reduced-motion: reduce)').matches&&'animate'in Element.prototype){$$('.visual-content > *').forEach((el,i)=>el.animate([{opacity:0,transform:'translateY(24px)'},{opacity:1,transform:'translateY(0)'}],{duration:650,delay:100+i*90,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'}));$('.form-card')?.animate([{opacity:0,transform:'translateY(20px) scale(.985)'},{opacity:1,transform:'translateY(0) scale(1)'}],{duration:700,delay:220,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'});}
})();
</script>
</body>
</html>
