<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#061a15">
  <title>Recuperare parolă — Nordvale</title>
  <meta name="description" content="Recuperează accesul la contul Nordvale.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <style>
    :root{
      --pine-950:#061a15;--pine-900:#09251d;--pine-850:#0d3027;--pine-800:#123b30;--pine-700:#1b5242;
      --acid:#dffc62;--ember:#f27b4a;--cream:#fffdf6;--oat:#f0ecdf;--moss:#b7c9a3;--ink:#16231e;
      --shadow-lift:0 38px 110px -42px rgba(6,26,21,.68);--shadow-card:0 20px 58px -30px rgba(6,26,21,.48)
    }
    *{box-sizing:border-box} html{background:var(--oat)}
    body{margin:0;min-height:100vh;overflow-x:hidden;background:var(--oat);color:var(--ink);font-family:'DM Sans',system-ui,sans-serif;text-rendering:optimizeLegibility}
    button,input{font:inherit} button,a{-webkit-tap-highlight-color:transparent} a{color:inherit;text-decoration:none} [hidden]{display:none!important}
    ::selection{background:var(--acid);color:var(--pine-950)}
    .page{min-height:100svh;position:relative;isolation:isolate}
    .page:before{content:"";position:fixed;inset:0;z-index:30;pointer-events:none;opacity:.055;mix-blend-mode:multiply;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.52'/%3E%3C/svg%3E")}
    .topo{background-image:radial-gradient(circle at 22% 12%,rgba(223,252,98,.15),transparent 27%),radial-gradient(circle at 82% 18%,rgba(242,123,74,.1),transparent 23%),url("data:image/svg+xml,%3Csvg width='760' height='760' viewBox='0 0 760 760' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.06' stroke-width='1.15'%3E%3Cpath d='M74 208c48-107 174-168 282-110 84 45 97 151 37 216-55 60-150 44-205 105-58 65-56 176-145 207-86 30-177-48-148-137 27-83 127-91 151-170 15-48 0-79 28-111Z'/%3E%3Cpath d='M111 229c39-85 139-133 225-87 67 36 77 120 29 172-44 48-120 35-164 84-47 52-45 141-116 166-69 24-142-38-118-110 21-67 101-73 120-136 12-38 0-63 24-89Z'/%3E%3Cpath d='M466 433c62-88 182-99 250-14 59 74 21 178-66 210-77 29-150-17-215 30-53 39-64 125-133 127-76 3-128-79-85-142 39-57 116-36 164-86 40-42 43-81 85-125Z'/%3E%3C/g%3E%3C/svg%3E");background-size:auto,auto,760px 760px}
    .header-wrap{position:fixed;top:0;left:0;right:0;z-index:50;padding:max(10px,env(safe-area-inset-top)) 12px 0}
    .header{max-width:1480px;margin:0 auto;min-height:62px;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 12px;border:1px solid rgba(255,255,255,.12);border-radius:20px;background:rgba(6,26,21,.82);box-shadow:0 24px 70px -42px rgba(0,0,0,.92);backdrop-filter:blur(22px) saturate(130%)}
    .brand{min-width:0;display:flex;align-items:center;gap:10px;color:white}.brand-mark{width:42px;height:42px;flex:0 0 auto;display:grid;place-items:center;border-radius:14px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1)}
    .brand-name{display:block;line-height:1;font:600 20px/1 'Fraunces',serif}.brand-sub{display:block;margin-top:5px;max-width:210px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:rgba(255,255,255,.52);font-size:8px;font-weight:700;letter-spacing:.24em;text-transform:uppercase}
    .header-actions{flex:0 0 auto;display:flex;align-items:center;gap:7px}.nav-link{display:none;align-items:center;gap:8px;padding:10px 15px;border-radius:999px;color:rgba(255,255,255,.78);font-size:14px;font-weight:700}.nav-link:hover{background:rgba(255,255,255,.08);color:white}
    .ticket-btn{display:inline-flex;flex:0 0 auto;align-items:center;justify-content:center;gap:7px;white-space:nowrap;min-height:40px;padding:0 14px;border:0;border-radius:999px;background:var(--acid);color:var(--pine-950);font-size:12px;font-weight:800;box-shadow:0 18px 54px -24px rgba(223,252,98,.58)}
    .menu-btn{width:40px;height:40px;flex:0 0 auto;display:grid;place-items:center;border:1px solid rgba(255,255,255,.16);border-radius:999px;background:transparent;color:white;cursor:pointer}
    .mobile-menu{position:fixed;inset:0;z-index:80;display:grid;grid-template-rows:auto 1fr;background:var(--pine-950);color:white;transform:translateX(100%);transition:transform .38s cubic-bezier(.22,.8,.2,1)}.mobile-menu.open{transform:translateX(0)}
    .mobile-menu-head{display:flex;align-items:center;justify-content:space-between;padding:max(18px,env(safe-area-inset-top)) 18px 14px}.mobile-menu-body{padding:24px 18px max(30px,env(safe-area-inset-bottom));display:flex;flex-direction:column;justify-content:space-between}.mobile-menu nav{display:grid;gap:8px}.mobile-menu nav a{padding:16px 2px;border-bottom:1px solid rgba(255,255,255,.1);font:600 30px/1.08 'Fraunces',serif}
    .main{min-height:100svh;display:grid;grid-template-columns:minmax(0,1fr);padding-top:86px}.visual{position:relative;min-height:390px;overflow:hidden;background:var(--pine-950);color:white}.visual-bg{position:absolute;inset:0}.visual-bg img{width:100%;height:100%;object-fit:cover;object-position:center 47%;filter:saturate(.82) contrast(1.04);opacity:.7}.visual-bg:after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(6,26,21,.12),rgba(6,26,21,.52) 58%,rgba(6,26,21,.98)),linear-gradient(90deg,rgba(6,26,21,.35),transparent 62%)}
    .visual-content{position:relative;z-index:2;min-height:390px;padding:38px 20px 25px;display:flex;flex-direction:column;justify-content:flex-end}.eyebrow{display:inline-flex;width:max-content;align-items:center;gap:8px;margin-bottom:16px;padding:8px 11px;border:1px solid rgba(255,255,255,.16);border-radius:999px;background:rgba(6,26,21,.28);color:rgba(255,255,255,.84);backdrop-filter:blur(12px);font-size:10px;font-weight:800;letter-spacing:.18em;text-transform:uppercase}.eyebrow-dot{width:7px;height:7px;border-radius:50%;background:var(--acid);box-shadow:0 0 0 5px rgba(223,252,98,.14)}
    .visual-title{max-width:700px;margin:0;font:600 clamp(38px,9.4vw,72px)/.96 'Fraunces',serif;letter-spacing:-.045em}.visual-title em{color:var(--acid);font-style:normal}.visual-copy{max-width:590px;margin:16px 0 0;color:rgba(255,255,255,.68);font-size:15px;line-height:1.65}
    .trail-note{margin-top:24px;display:grid;grid-template-columns:auto minmax(0,1fr);gap:12px;max-width:560px;padding:13px;border:1px solid rgba(255,255,255,.13);border-radius:20px;background:rgba(6,26,21,.54);backdrop-filter:blur(18px)}.trail-note-icon{width:46px;height:46px;display:grid;place-items:center;border-radius:15px;background:var(--acid);color:var(--pine-950)}.trail-note strong{display:block;font-size:13px;color:white}.trail-note span{display:block;margin-top:4px;color:rgba(255,255,255,.52);font-size:11px;line-height:1.5}
    .route{position:absolute;top:13%;right:-8%;width:min(65vw,620px);height:auto;opacity:.82;pointer-events:none}.route path.shadow{fill:none;stroke:rgba(255,255,255,.13);stroke-width:10;stroke-linecap:round}.route path.main{fill:none;stroke:rgba(223,252,98,.76);stroke-width:2;stroke-linecap:round;stroke-dasharray:7 12;animation:dash 12s linear infinite}@keyframes dash{to{stroke-dashoffset:-190}}
    .auth{position:relative;background:var(--oat);padding:24px 16px max(36px,env(safe-area-inset-bottom))}.auth:before{content:"";position:absolute;inset:0;pointer-events:none;background-image:linear-gradient(rgba(9,37,29,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(9,37,29,.045) 1px,transparent 1px);background-size:30px 30px;mask-image:linear-gradient(to bottom,black,transparent 88%)}.auth-inner{position:relative;z-index:2;width:min(100%,560px);margin:0 auto}.back{display:inline-flex;align-items:center;gap:8px;margin:0 0 22px;color:rgba(22,35,30,.62);font-size:13px;font-weight:700}.form-card{padding:24px 18px;border:1px solid rgba(9,37,29,.1);border-radius:28px;background:rgba(255,253,246,.92);box-shadow:var(--shadow-lift);backdrop-filter:blur(18px)}
    .form-kicker{margin:0 0 10px;color:var(--pine-700);font-size:10px;font-weight:800;letter-spacing:.2em;text-transform:uppercase}.form-title{margin:0;font:600 clamp(32px,8vw,46px)/1 'Fraunces',serif;letter-spacing:-.035em}.form-copy{margin:13px 0 0;color:rgba(22,35,30,.6);font-size:13px;line-height:1.65}
    .form{margin-top:24px;display:grid;gap:16px}.field{display:grid;gap:7px}.field label{font-size:12px;font-weight:800;color:var(--ink)}.input-wrap{position:relative}.input{width:100%;min-height:52px;padding:0 48px 0 15px;border:1px solid rgba(9,37,29,.14);border-radius:16px;background:rgba(255,255,255,.75);color:var(--ink);outline:none;transition:.22s ease}.input:focus{border-color:var(--pine-700);box-shadow:0 0 0 4px rgba(27,82,66,.09);background:white}.input.error{border-color:#c9533d;box-shadow:0 0 0 4px rgba(201,83,61,.08)}.input-icon{position:absolute;right:15px;top:50%;transform:translateY(-50%);color:rgba(22,35,30,.44)}.field-error{min-height:16px;color:#a74633;font-size:11px;font-weight:700}
    .primary{width:100%;min-height:52px;display:flex;align-items:center;justify-content:center;gap:9px;border:0;border-radius:16px;background:var(--pine-950);color:white;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 18px 38px -24px rgba(6,26,21,.72);transition:.22s ease}.primary:hover{transform:translateY(-2px);background:var(--pine-850)}.primary:disabled{opacity:.55;cursor:wait;transform:none}.secondary{width:100%;min-height:48px;display:flex;align-items:center;justify-content:center;gap:9px;border:1px solid rgba(9,37,29,.13);border-radius:15px;background:rgba(255,255,255,.62);font-size:12px;font-weight:800;cursor:pointer}.secondary:hover{background:white;border-color:rgba(9,37,29,.24)}
    .security{margin-top:16px;display:grid;grid-template-columns:1fr;gap:8px}.security-item{display:flex;align-items:center;gap:9px;padding:11px 12px;border-radius:14px;background:rgba(9,37,29,.055);color:rgba(22,35,30,.6);font-size:11px;font-weight:700}.security-item svg{color:var(--pine-700);flex:0 0 auto}
    .success{margin-top:22px;padding:20px;border:1px solid rgba(27,82,66,.16);border-radius:20px;background:linear-gradient(145deg,rgba(223,252,98,.2),rgba(183,201,163,.18))}.success-icon{width:50px;height:50px;display:grid;place-items:center;margin-bottom:15px;border-radius:16px;background:var(--acid);color:var(--pine-950);box-shadow:0 18px 34px -22px rgba(6,26,21,.55)}.success h3{margin:0;font:600 26px/1.08 'Fraunces',serif}.success p{margin:10px 0 0;color:rgba(22,35,30,.64);font-size:13px;line-height:1.6}.success-email{margin:12px 0 0;padding:11px 12px;border-radius:13px;background:rgba(255,255,255,.58);font-size:12px;font-weight:800;overflow-wrap:anywhere}.success-actions{display:grid;gap:9px;margin-top:16px}.countdown{margin-top:12px;color:rgba(22,35,30,.5);font-size:11px;font-weight:700;text-align:center}.account-note{margin:18px 0 0;text-align:center;color:rgba(22,35,30,.55);font-size:12px}.account-note a{color:var(--pine-700);font-weight:800}.toast{position:fixed;left:50%;bottom:max(20px,env(safe-area-inset-bottom));z-index:100;max-width:calc(100vw - 28px);transform:translate(-50%,24px);opacity:0;pointer-events:none;padding:12px 16px;border-radius:14px;background:var(--pine-950);color:white;box-shadow:0 24px 70px -28px rgba(0,0,0,.8);font-size:12px;font-weight:700;transition:.28s ease}.toast.show{transform:translate(-50%,0);opacity:1}
    @media(min-width:410px){.ticket-btn{padding:0 16px;font-size:13px}.form-card{padding:28px 24px}.security{grid-template-columns:1fr 1fr}}
    @media(min-width:720px){.header-wrap{padding-left:18px;padding-right:18px}.header{padding-left:16px;padding-right:16px}.main{padding-top:0;grid-template-columns:minmax(0,.96fr) minmax(460px,.74fr);min-height:100svh}.visual{min-height:100svh}.visual-content{min-height:100svh;padding:134px clamp(34px,5vw,74px) 54px}.auth{min-height:100svh;display:flex;align-items:center;padding:112px 34px 48px}.form-card{padding:34px 32px;border-radius:32px}.route{top:14%;right:-16%;width:min(55vw,650px)}}
    @media(min-width:1040px){.nav-link{display:inline-flex}.menu-btn{display:none}.main{grid-template-columns:minmax(0,1.14fr) minmax(520px,.76fr)}.visual-content{padding-left:clamp(54px,7vw,110px);padding-right:70px}.auth{padding-left:clamp(44px,5vw,84px);padding-right:clamp(44px,5vw,84px)}.visual-copy{font-size:16px}}
    @media(max-width:380px){.brand-sub{display:none}.brand-name{font-size:18px}.brand-mark{width:38px;height:38px;border-radius:12px}.header{min-height:58px;padding:8px 9px}.ticket-btn{min-height:38px;padding:0 12px}.menu-btn{width:38px;height:38px}.auth{padding-left:12px;padding-right:12px}.form-card{padding:22px 15px;border-radius:24px}}
    @media(prefers-reduced-motion:reduce){*,*:before,*:after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important;scroll-behavior:auto!important}}
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
        <a class="nav-link" href="/autentificare"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>Înapoi la login</a>
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
      <nav><a href="/">Acasă</a><a href="/experiente">Experiențe</a><a href="/planifica">Planifică vizita</a><a href="/autentificare">Autentificare</a></nav>
      <p style="color:rgba(255,255,255,.45);font-size:12px;line-height:1.6">Ai nevoie de ajutor?<br><strong style="color:white">support@nordvale.demo</strong></p>
    </div>
  </aside>

  <main class="main">
    <section class="visual topo" aria-label="Recuperare acces Nordvale">
      <div class="visual-bg"><img src="https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1800&q=86" alt="Potecă luminată prin pădure"></div>
      <svg class="route" viewBox="0 0 650 480" fill="none" aria-hidden="true"><path class="shadow" d="M42 405c64-15 87-91 155-108 68-18 117 39 178-3 61-43 43-122 111-157 40-21 87-8 122-46"/><path class="main" d="M42 405c64-15 87-91 155-108 68-18 117 39 178-3 61-43 43-122 111-157 40-21 87-8 122-46"/><circle cx="42" cy="405" r="8" fill="#DFFC62"/><circle cx="608" cy="91" r="10" fill="#F27B4A"/></svg>
      <div class="visual-content">
        <div class="eyebrow"><span class="eyebrow-dot"></span>Recuperează accesul</div>
        <h1 class="visual-title">Drumul spre contul tău este <em>încă deschis.</em></h1>
        <p class="visual-copy">Introdu adresa de email folosită la rezervare. Îți trimitem un link securizat pentru alegerea unei parole noi.</p>
        <div class="trail-note"><span class="trail-note-icon"><svg width="23" height="23" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg></span><span><strong>Link valabil 30 de minute</strong><span>Poate fi folosit o singură dată și expiră automat după resetarea parolei.</span></span></div>
      </div>
    </section>

    <section class="auth">
      <div class="auth-inner">
        <a class="back" href="/autentificare"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6"/></svg>Înapoi la autentificare</a>
        <article class="form-card">
          <div id="requestState">
            <p class="form-kicker">Securitate cont</p>
            <h2 class="form-title">Ai uitat parola?</h2>
            <p class="form-copy">Spune-ne unde să trimitem linkul de resetare. Nu îți vom cere niciodată parola actuală.</p>
            <form class="form" id="resetForm" novalidate>
              <div class="field">
                <label for="resetEmail">Adresă de email</label>
                <div class="input-wrap"><input class="input" id="resetEmail" name="email" type="email" autocomplete="email" placeholder="nume@email.ro" required><span class="input-icon" aria-hidden="true"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/></svg></span></div>
                <div class="field-error" id="resetEmailError"></div>
              </div>
              <button class="primary" id="submitButton" type="submit"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2 11 13"/><path d="m22 2-7 20-4-9-9-4Z"/></svg><span>Trimite linkul securizat</span></button>
            </form>
            <div class="security">
              <div class="security-item"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Date protejate</div>
              <div class="security-item"><svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s7-4.4 7-11V5l-7-2-7 2v5c0 6.6 7 11 7 11Z"/><path d="m9 12 2 2 4-5"/></svg>Link unic</div>
            </div>
            <p class="account-note">Ți-ai amintit parola? <a href="/autentificare">Intră în cont</a></p>
          </div>

          <div id="successState" hidden>
            <p class="form-kicker">Mesaj trimis</p>
            <h2 class="form-title">Verifică inboxul.</h2>
            <div class="success">
              <div class="success-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16v16H4z"/><path d="m4 6 8 7 8-7"/><path d="m9 17 2 2 4-5"/></svg></div>
              <h3>Linkul este pe drum.</h3>
              <p>Am trimis instrucțiunile de resetare la:</p>
              <div class="success-email" id="successEmail"></div>
              <p>Verifică și folderul Spam. Linkul expiră în 30 de minute.</p>
              <div class="success-actions">
                <a class="primary" href="/autentificare">Înapoi la autentificare</a>
                <button class="secondary" id="resendButton" type="button" disabled>Retrimite linkul</button>
              </div>
              <div class="countdown" id="countdown">Poți retrimite peste 30 secunde.</div>
            </div>
          </div>
        </article>
      </div>
    </section>
  </main>
  <div class="toast" id="toast" role="status" aria-live="polite"></div>
</div>
<script>
(() => {
  'use strict';
  const $ = (selector, scope = document) => scope.querySelector(selector);
  const menu = $('#mobileMenu');
  const openMenu = () => { if (!menu) return; menu.classList.add('open'); menu.setAttribute('aria-hidden','false'); document.body.style.overflow='hidden'; };
  const closeMenu = () => { if (!menu) return; menu.classList.remove('open'); menu.setAttribute('aria-hidden','true'); document.body.style.overflow=''; };
  $('#menuOpen')?.addEventListener('click', openMenu);
  $('#menuClose')?.addEventListener('click', closeMenu);
  menu?.addEventListener('click', e => { if (e.target === menu) closeMenu(); });
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });

  const form = $('#resetForm');
  const emailInput = $('#resetEmail');
  const emailError = $('#resetEmailError');
  const submitButton = $('#submitButton');
  const requestState = $('#requestState');
  const successState = $('#successState');
  const successEmail = $('#successEmail');
  const resendButton = $('#resendButton');
  const countdown = $('#countdown');
  const toast = $('#toast');
  let timerId = null;

  const showToast = message => {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.setTimeout(() => toast.classList.remove('show'), 2600);
  };

  const validEmail = value => /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
  const validate = () => {
    const value = emailInput?.value.trim() || '';
    const ok = validEmail(value);
    if (emailInput) emailInput.classList.toggle('error', !ok);
    if (emailError) emailError.textContent = ok ? '' : 'Introdu o adresă de email validă.';
    return ok;
  };

  const startCountdown = () => {
    let remaining = 30;
    if (resendButton) resendButton.disabled = true;
    if (countdown) countdown.textContent = `Poți retrimite peste ${remaining} secunde.`;
    window.clearInterval(timerId);
    timerId = window.setInterval(() => {
      remaining -= 1;
      if (remaining <= 0) {
        window.clearInterval(timerId);
        if (resendButton) resendButton.disabled = false;
        if (countdown) countdown.textContent = 'Nu ai primit mesajul? Poți retrimite acum.';
        return;
      }
      if (countdown) countdown.textContent = `Poți retrimite peste ${remaining} secunde.`;
    }, 1000);
  };

  emailInput?.addEventListener('input', () => {
    if (emailInput.classList.contains('error')) validate();
  });

  form?.addEventListener('submit', event => {
    event.preventDefault();
    if (!validate()) { emailInput?.focus(); return; }
    if (submitButton) {
      submitButton.disabled = true;
      submitButton.querySelector('span').textContent = 'Se trimite…';
    }
    fetch('/api/proxy.php?action=forgot-password', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ email: emailInput.value.trim() }) }).catch(()=>{});
    window.setTimeout(() => {
      if (successEmail) successEmail.textContent = emailInput.value.trim();
      requestState.hidden = true;
      successState.hidden = false;
      successState.animate([{opacity:0,transform:'translateY(16px)'},{opacity:1,transform:'translateY(0)'}],{duration:420,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'});
      startCountdown();
    }, 650);
  });

  resendButton?.addEventListener('click', () => {
    showToast('Am retrimis linkul de resetare.');
    startCountdown();
  });

  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.visual-content > *').forEach((el, index) => {
      el.animate([{opacity:0,transform:'translateY(22px)'},{opacity:1,transform:'translateY(0)'}],{duration:620,delay:110 + index*90,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'});
    });
    $('.form-card')?.animate([{opacity:0,transform:'translateY(24px) scale(.985)'},{opacity:1,transform:'translateY(0) scale(1)'}],{duration:700,delay:180,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'});
  }
})();
</script>
</body>
</html>
