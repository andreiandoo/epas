<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="robots" content="noindex,nofollow" />
  <meta name="theme-color" content="#061a15" />
  <title>Despre Nordvale — Rezervația vie</title>
  <meta name="description" content="Povestea dummy a rezervației forestiere Nordvale, ecosistem, conservare și comunitate." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <style>
    :root{
      --pine:#09251d;--pine-dark:#061a15;--pine-mid:#123b30;--acid:#dffc62;--ember:#f27b4a;
      --cream:#fffdf6;--oat:#f0ecdf;--ink:#16231e;--moss:#b7c9a3;--sky:#b8dce0;
      --shadow:0 36px 100px -38px rgba(6,26,21,.58);
      --card-shadow:0 18px 48px -28px rgba(6,26,21,.46);
      --radius:28px;
    }
    *{box-sizing:border-box}
    html{scroll-behavior:smooth;background:var(--oat)}
    body{margin:0;overflow-x:hidden;background:var(--oat);color:var(--ink);font-family:'DM Sans',sans-serif;text-rendering:optimizeLegibility}
    body.menu-open{overflow:hidden}
    button,a,input{font:inherit}
    button{cursor:pointer}
    a{text-decoration:none;color:inherit}
    img{display:block;max-width:100%}
    ::selection{background:var(--acid);color:var(--pine-dark)}
    .display{font-family:'Fraunces',serif}
    .wrap{width:min(1460px,calc(100% - 32px));margin-inline:auto}
    .safe-top{padding-top:max(10px,env(safe-area-inset-top))}
    .safe-bottom{padding-bottom:max(16px,env(safe-area-inset-bottom))}
    .grain::after{content:"";position:absolute;inset:0;pointer-events:none;z-index:3;opacity:.075;mix-blend-mode:soft-light;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.58'/%3E%3C/svg%3E")}
    .topo-dark{background-image:radial-gradient(circle at 18% 22%,rgba(223,252,98,.14),transparent 20%),radial-gradient(circle at 84% 18%,rgba(242,123,74,.12),transparent 17%),url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.055' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M119 231c31-83 127-143 219-107 76 30 95 118 53 180-40 58-121 56-160 115-42 61-28 159-107 197-77 37-165-25-154-110 10-83 94-98 102-174 4-44 0-74 47-101Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3C/g%3E%3C/svg%3E");background-size:auto,auto,820px 820px}
    .paper-grid{background-image:linear-gradient(rgba(9,37,29,.052) 1px,transparent 1px),linear-gradient(90deg,rgba(9,37,29,.052) 1px,transparent 1px);background-size:30px 30px}
    .eyebrow{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.24em;color:var(--ember)}
    .eyebrow.light{color:var(--acid)}
    .title-xl{font-family:'Fraunces',serif;font-size:clamp(48px,8vw,118px);line-height:.88;letter-spacing:-.055em;font-weight:600;margin:0}
    .title-lg{font-family:'Fraunces',serif;font-size:clamp(42px,7vw,84px);line-height:.94;letter-spacing:-.045em;font-weight:600;margin:0}
    .title-md{font-family:'Fraunces',serif;font-size:clamp(32px,4.2vw,58px);line-height:.98;letter-spacing:-.035em;font-weight:600;margin:0}
    .muted{color:rgba(9,37,29,.62)}
    .muted-light{color:rgba(255,255,255,.62)}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:52px;border-radius:999px;padding:0 22px;border:0;font-weight:700;white-space:nowrap;transition:.25s ease}
    .btn:hover{transform:translateY(-2px)}
    .btn-acid{background:var(--acid);color:var(--pine-dark);box-shadow:0 18px 54px -24px rgba(223,252,98,.58)}
    .btn-ghost{border:1px solid rgba(255,255,255,.2);background:transparent;color:white}
    .btn-dark{background:var(--pine);color:white}
    .btn-outline{border:1px solid rgba(9,37,29,.2);background:transparent;color:var(--pine)}
    .icon-round{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;flex:none}
    .progress{position:fixed;left:0;right:0;top:0;height:3px;z-index:160}.progress>span{display:block;width:100%;height:100%;background:var(--acid);transform-origin:left;transform:scaleX(0)}

    /* Header */
    .site-header{position:fixed;inset:0 0 auto;z-index:140;padding-inline:10px}
    .nav{max-width:1540px;margin:2px auto 0;min-height:62px;display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid transparent;border-radius:20px;padding:10px 12px;transition:.4s ease}
    .nav.scrolled{background:rgba(6,26,21,.8);border-color:rgba(255,255,255,.12);box-shadow:0 24px 70px -42px rgba(0,0,0,.9);backdrop-filter:blur(22px) saturate(130%)}
    .brand{display:flex;align-items:center;gap:10px;min-width:0;color:white}
    .brand-mark{width:40px;height:40px;border-radius:14px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);display:grid;place-items:center;flex:none}
    .brand-name{font-family:'Fraunces',serif;font-size:19px;font-weight:600;line-height:1;white-space:nowrap}
    .brand-sub{display:none;margin-top:5px;font-size:7px;font-weight:700;text-transform:uppercase;letter-spacing:.25em;color:rgba(255,255,255,.52);white-space:nowrap}
    .desktop-nav{display:none;align-items:center;gap:27px}.desktop-nav a{font-size:14px;font-weight:600;color:rgba(255,255,255,.7);transition:.2s}.desktop-nav a:hover,.desktop-nav a.active{color:var(--acid)}
    .nav-actions{display:flex;align-items:center;gap:6px;flex:none}.account-link{display:none;color:rgba(255,255,255,.82);font-size:14px;font-weight:600;border:1px solid rgba(255,255,255,.15);padding:10px 16px;border-radius:999px}
    .ticket-btn{min-height:40px;padding:0 13px;font-size:12px}.menu-btn{width:40px;height:40px;border-radius:50%;border:1px solid rgba(255,255,255,.15);background:transparent;color:white;display:grid;place-items:center}
    .mobile-menu{position:fixed;inset:0;z-index:155;visibility:hidden;pointer-events:none}.mobile-menu.open{visibility:visible;pointer-events:auto}.menu-backdrop{position:absolute;inset:0;background:rgba(6,26,21,.74);backdrop-filter:blur(7px);opacity:0;transition:.3s}.mobile-menu.open .menu-backdrop{opacity:1}.menu-panel{position:absolute;right:0;top:0;height:100%;width:min(88vw,430px);background:var(--cream);padding:22px;display:flex;flex-direction:column;transform:translateX(100%);transition:.42s cubic-bezier(.2,.8,.2,1)}.mobile-menu.open .menu-panel{transform:none}.menu-head{display:flex;align-items:center;justify-content:space-between}.menu-links{margin-top:45px}.menu-links a{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(9,37,29,.1);padding:16px 0;font-family:'Fraunces',serif;font-size:27px;font-weight:600;color:var(--pine)}.menu-status{margin-top:auto;background:var(--pine);border-radius:22px;padding:20px;color:white}.status-dot{width:8px;height:8px;border-radius:50%;background:var(--acid);display:inline-block;box-shadow:0 0 0 0 rgba(223,252,98,.45);animation:pulse 2.2s infinite}@keyframes pulse{70%{box-shadow:0 0 0 10px rgba(223,252,98,0)}}

    /* Hero */
    .hero{position:relative;min-height:100svh;background:var(--pine-dark);color:white;overflow:hidden;display:flex;align-items:flex-end}.hero::before{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(6,26,21,.08),rgba(6,26,21,.65));z-index:1}.hero-photo{position:absolute;inset:0}.hero-photo img{width:100%;height:100%;object-fit:cover;opacity:.5;transform:scale(1.05)}.hero-grid{position:relative;z-index:4;display:grid;gap:34px;padding:126px 0 42px}.hero-copy{max-width:820px}.hero-kicker{display:inline-flex;align-items:center;gap:9px;padding:9px 13px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.07);font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.76)}.hero h1 span{display:block}.outline{color:transparent;-webkit-text-stroke:1px rgba(255,255,255,.45)}.hero-text{max-width:650px;margin:25px 0 0;font-size:16px;line-height:1.8;color:rgba(255,255,255,.68)}.hero-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:26px;max-width:510px}.hero-meta{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}.meta-card{border:1px solid rgba(255,255,255,.13);background:rgba(6,26,21,.36);backdrop-filter:blur(12px);border-radius:20px;padding:16px}.meta-value{font-family:'Fraunces',serif;font-size:27px;font-weight:600;color:var(--acid)}.meta-label{margin-top:4px;font-size:10px;line-height:1.4;color:rgba(255,255,255,.55)}
    .hero-line{position:absolute;right:-70px;top:16%;width:540px;height:540px;opacity:.8;z-index:2}.hero-line path{fill:none;stroke:var(--acid);stroke-width:2;stroke-linecap:round;stroke-dasharray:10 14}.hero-line circle{fill:var(--acid)}

    /* Intro manifesto */
    .manifesto{position:relative;background:var(--cream);padding:90px 0;overflow:hidden}.manifesto-grid{display:grid;gap:44px}.manifesto-copy p{font-size:18px;line-height:1.8;margin:25px 0 0;color:rgba(9,37,29,.66)}.manifesto-note{position:relative;min-height:500px}.manifesto-image{position:absolute;inset:0;border-radius:28px;overflow:hidden;box-shadow:var(--shadow)}.manifesto-image img{width:100%;height:100%;object-fit:cover}.field-note{position:absolute;left:14px;right:14px;bottom:14px;background:var(--acid);padding:20px;transform:rotate(-1.5deg);box-shadow:var(--card-shadow)}.field-note small{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:rgba(9,37,29,.58)}.field-note p{margin:10px 0 0;font-family:'Fraunces',serif;font-size:22px;line-height:1.35;color:var(--pine)}

    /* Ecosystem map */
    .ecosystem{background:var(--pine-dark);color:white;padding:90px 0;position:relative;overflow:hidden}.ecosystem-grid{display:grid;gap:40px}.eco-copy p{font-size:17px;line-height:1.8;color:rgba(255,255,255,.62);max-width:560px}.eco-tabs{display:flex;gap:8px;overflow:auto;padding-bottom:5px;scrollbar-width:none}.eco-tabs::-webkit-scrollbar{display:none}.eco-tab{border:1px solid rgba(255,255,255,.14);background:transparent;color:rgba(255,255,255,.65);border-radius:999px;padding:11px 15px;font-size:12px;font-weight:700;white-space:nowrap}.eco-tab.active{background:var(--acid);color:var(--pine);border-color:var(--acid)}.eco-map{position:relative;min-height:520px;border:1px solid rgba(255,255,255,.12);border-radius:28px;background:rgba(255,255,255,.03);overflow:hidden}.eco-map svg{position:absolute;inset:0;width:100%;height:100%}.zone-shape{transition:.3s;cursor:pointer}.zone-shape.dim{opacity:.12}.zone-shape.active{filter:drop-shadow(0 0 12px rgba(223,252,98,.36))}.eco-info{position:absolute;left:14px;right:14px;bottom:14px;background:rgba(6,26,21,.82);border:1px solid rgba(255,255,255,.12);backdrop-filter:blur(16px);border-radius:20px;padding:18px}.eco-info-head{display:flex;align-items:center;justify-content:space-between;gap:14px}.eco-info h3{margin:0;font-family:'Fraunces',serif;font-size:27px}.eco-info p{margin:9px 0 0;font-size:13px;line-height:1.65;color:rgba(255,255,255,.62)}.eco-stat{font-family:'Fraunces',serif;font-size:27px;color:var(--acid);white-space:nowrap}

    /* Numbers */
    .impact{background:var(--oat);padding:82px 0}.impact-head{display:grid;gap:22px}.metrics{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:38px}.metric{min-height:190px;padding:20px;border-radius:24px;background:var(--cream);display:flex;flex-direction:column;justify-content:space-between}.metric.dark{background:var(--pine);color:white}.metric.acid{background:var(--acid)}.metric-value{font-family:'Fraunces',serif;font-size:clamp(40px,7vw,70px);font-weight:600;line-height:1}.metric-label{font-size:12px;line-height:1.55;color:rgba(9,37,29,.58)}.metric.dark .metric-label{color:rgba(255,255,255,.58)}

    /* Timeline */
    .history{background:var(--cream);padding:90px 0}.history-grid{display:grid;gap:38px}.history-visual{min-height:450px;border-radius:28px;overflow:hidden;position:relative;box-shadow:var(--shadow)}.history-visual img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0;transition:opacity .45s}.history-badge{position:absolute;left:16px;top:16px;border-radius:999px;padding:10px 13px;background:var(--acid);font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:var(--pine)}.timeline{border-top:1px solid rgba(9,37,29,.12)}.timeline-item{width:100%;display:grid;grid-template-columns:64px 1fr auto;gap:12px;align-items:start;text-align:left;border:0;border-bottom:1px solid rgba(9,37,29,.12);background:transparent;padding:21px 0;color:rgba(9,37,29,.42)}.timeline-item.active{color:var(--pine)}.timeline-year{font-family:'Fraunces',serif;font-size:20px;font-weight:600}.timeline-title{font-family:'Fraunces',serif;font-size:22px;font-weight:600}.timeline-copy{margin-top:7px;font-size:12px;line-height:1.55;max-width:520px}.timeline-arrow{width:40px;height:40px;border-radius:50%;border:1px solid rgba(9,37,29,.15);display:grid;place-items:center;transition:.3s}.timeline-item.active .timeline-arrow{background:var(--pine);color:var(--acid);transform:rotate(45deg)}

    /* Field notes */
    .notes{position:relative;background:var(--pine);color:white;padding:90px 0;overflow:hidden}.notes-grid{display:grid;gap:28px;margin-top:36px}.note-card{position:relative;min-height:420px;border-radius:28px;overflow:hidden;border:1px solid rgba(255,255,255,.1)}.note-card img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .8s}.note-card:hover img{transform:scale(1.05)}.note-overlay{position:absolute;inset:0;background:linear-gradient(to top,rgba(6,26,21,.92),rgba(6,26,21,.08))}.note-content{position:absolute;left:0;right:0;bottom:0;padding:22px}.note-date{font-size:9px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;color:var(--acid)}.note-card h3{font-family:'Fraunces',serif;font-size:27px;line-height:1.08;margin:10px 0}.note-card p{font-size:13px;line-height:1.65;color:rgba(255,255,255,.62);margin:0}

    /* Stewards */
    .stewards{background:var(--oat);padding:90px 0}.steward-intro{display:grid;gap:20px}.team{display:grid;gap:12px;margin-top:38px}.person{display:grid;grid-template-columns:88px 1fr;gap:15px;align-items:center;background:var(--cream);border-radius:22px;padding:11px;box-shadow:var(--card-shadow)}.person img{width:88px;height:108px;object-fit:cover;border-radius:16px}.person-role{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:var(--ember)}.person h3{font-family:'Fraunces',serif;font-size:23px;margin:7px 0 0}.person p{font-size:12px;line-height:1.55;color:rgba(9,37,29,.56);margin:7px 0 0}

    /* Principles */
    .principles{background:var(--cream);padding:90px 0}.principle-list{margin-top:38px;border-top:1px solid rgba(9,37,29,.12)}.principle{display:grid;grid-template-columns:48px 1fr;gap:14px;border-bottom:1px solid rgba(9,37,29,.12);padding:24px 0}.principle-num{font-family:'Fraunces',serif;font-size:27px;color:var(--ember)}.principle h3{font-family:'Fraunces',serif;font-size:27px;margin:0}.principle p{font-size:13px;line-height:1.65;color:rgba(9,37,29,.58);margin:8px 0 0}

    /* CTA & footer */
    .cta{position:relative;background:var(--pine-dark);color:white;padding:90px 0;overflow:hidden}.cta-grid{display:grid;gap:30px;align-items:end}.cta p{font-size:16px;line-height:1.8;color:rgba(255,255,255,.62);max-width:620px}.cta-actions{display:flex;flex-wrap:wrap;gap:10px}.footer{background:#04130f;color:white;padding:58px 0 32px}.footer-grid{display:grid;gap:34px}.footer-brand p{max-width:360px;color:rgba(255,255,255,.52);line-height:1.7;font-size:13px}.footer-links{display:grid;grid-template-columns:1fr 1fr;gap:28px}.footer-links h4{font-size:10px;text-transform:uppercase;letter-spacing:.2em;color:var(--acid);margin:0 0 14px}.footer-links a{display:block;font-size:13px;color:rgba(255,255,255,.65);margin:9px 0}.footer-bottom{margin-top:38px;padding-top:22px;border-top:1px solid rgba(255,255,255,.1);display:flex;flex-direction:column;gap:10px;font-size:11px;color:rgba(255,255,255,.42)}

    [data-reveal]{opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s cubic-bezier(.2,.8,.2,1)}[data-reveal].visible{opacity:1;transform:none}

    @media(min-width:410px){.brand-sub{display:block}}
    @media(min-width:640px){.wrap{width:min(1460px,calc(100% - 48px))}.nav{padding-inline:16px;gap:16px}.brand-mark{width:44px;height:44px}.brand-name{font-size:21px}.ticket-btn{font-size:14px;padding-inline:17px}.menu-btn{width:44px;height:44px}.hero-grid{padding-top:145px;padding-bottom:55px}.hero-text{font-size:18px}.hero-actions{display:flex}.hero-meta{gap:12px}.meta-card{padding:20px}.meta-value{font-size:34px}.manifesto,.ecosystem,.history,.notes,.stewards,.principles,.cta{padding-block:115px}.manifesto-note{min-height:650px}.field-note{left:24px;right:auto;bottom:24px;max-width:310px;padding:25px}.eco-map{min-height:650px}.eco-info{left:22px;right:auto;bottom:22px;width:min(470px,calc(100% - 44px));padding:22px}.metrics{grid-template-columns:repeat(4,1fr);gap:12px}.metric{min-height:230px;padding:24px}.history-visual{min-height:600px}.timeline-item{grid-template-columns:90px 1fr auto;gap:20px;padding:28px 0}.timeline-year{font-size:25px}.timeline-title{font-size:28px}.timeline-copy{font-size:13px}.notes-grid{grid-template-columns:1fr 1fr}.note-card:first-child{grid-row:span 2;min-height:700px}.note-card{min-height:340px}.team{grid-template-columns:1fr 1fr}.person{grid-template-columns:105px 1fr}.person img{width:105px;height:128px}.principle{grid-template-columns:72px 1fr;gap:22px}.footer-grid{grid-template-columns:1.15fr .85fr}.footer-bottom{flex-direction:row;justify-content:space-between}}
    @media(min-width:1024px){.site-header{padding-inline:24px}.nav{margin-top:16px;padding-inline:20px}.account-link{display:inline-flex}.manifesto-grid{grid-template-columns:.85fr 1.15fr;align-items:center;gap:70px}.manifesto-note{min-height:760px}.ecosystem-grid{grid-template-columns:.82fr 1.18fr;align-items:center;gap:70px}.impact-head{grid-template-columns:1fr .75fr;align-items:end}.history-grid{grid-template-columns:.9fr 1.1fr;align-items:center;gap:70px}.history-visual{position:sticky;top:110px;min-height:720px}.notes-grid{grid-template-columns:1.1fr .72fr .72fr}.note-card:first-child{grid-row:auto;min-height:620px}.note-card{min-height:620px}.steward-intro{grid-template-columns:1fr .72fr;align-items:end}.team{grid-template-columns:repeat(4,1fr)}.person{display:block;padding:10px}.person img{width:100%;height:300px}.person>div{padding:16px 8px 12px}.principles .wrap{display:grid;grid-template-columns:.82fr 1.18fr;gap:80px;align-items:start}.principle-list{margin-top:0}.cta-grid{grid-template-columns:1fr auto}.footer-links{grid-template-columns:repeat(3,1fr)}}
    @media(min-width:1280px){.desktop-nav{display:flex}.menu-btn{display:none}.hero-grid{grid-template-columns:1fr .42fr;align-items:end;padding-top:150px}.hero-meta{grid-template-columns:1fr;max-width:300px;justify-self:end}.meta-card{min-height:125px}.hero-line{right:4%;top:14%;width:660px;height:660px}}
    @media(max-width:480px){.wrap{width:min(100% - 24px,1460px)}.nav{padding-inline:10px}.brand-mark{width:38px;height:38px}.brand-name{font-size:18px}.ticket-btn{padding-inline:12px}.hero-actions .btn{padding-inline:12px;font-size:13px}.hero-meta{grid-template-columns:1fr 1fr}.meta-card:last-child{grid-column:1/-1}.metrics{grid-template-columns:1fr 1fr}.metric{min-height:170px}.eco-map{min-height:500px}.eco-info-head{align-items:flex-start}.eco-stat{font-size:22px}.timeline-item{grid-template-columns:56px 1fr 38px}.timeline-arrow{width:38px;height:38px}.footer-links{grid-template-columns:1fr 1fr}}
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}[data-reveal]{opacity:1;transform:none}.hero-line{display:none}}
  </style>
</head>
<body>
  <div class="progress"><span id="pageProgress"></span></div>

  <header class="site-header safe-top">
    <nav class="nav" id="nav">
      <a href="/" class="brand" aria-label="Nordvale homepage">
        <span class="brand-mark">
          <svg viewBox="0 0 48 48" width="31" height="31" fill="none" aria-hidden="true"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/></svg>
        </span>
        <span><span class="brand-name">Nordvale</span><span class="brand-sub">wild park · forest reserve</span></span>
      </a>
      <div class="desktop-nav">
        <a href="/experiente">Experiențe</a><a href="/planifica">Planifică</a><a href="/calendar">Program</a><a class="active" href="#top">Rezervația</a><a href="/abonamente">Abonamente</a>
      </div>
      <div class="nav-actions">
        <a class="account-link" href="/autentificare">Contul meu</a>
        <a class="btn btn-acid ticket-btn" href="/bilete">Bilete</a>
        <button class="menu-btn" id="menuOpen" aria-label="Deschide meniul"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 17h16"/></svg></button>
      </div>
    </nav>
  </header>

  <div class="mobile-menu" id="mobileMenu" aria-hidden="true">
    <div class="menu-backdrop" id="menuBackdrop"></div>
    <aside class="menu-panel safe-bottom" role="dialog" aria-modal="true">
      <div class="menu-head"><span class="display" style="font-size:26px;font-weight:600;color:var(--pine)">Nordvale</span><button class="icon-round" id="menuClose" style="border:1px solid rgba(9,37,29,.14);background:transparent" aria-label="Închide meniul"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
      <div class="menu-links"><a href="/experiente">Experiențe <span>↗</span></a><a href="/planifica">Planifică <span>↗</span></a><a href="/calendar">Program <span>↗</span></a><a href="#top">Rezervația <span>↗</span></a><a href="/abonamente">Abonamente <span>↗</span></a><a href="/autentificare">Contul meu <span>↗</span></a></div>
      <div class="menu-status"><div style="display:flex;align-items:center;gap:9px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:var(--acid)"><span class="status-dot"></span> Parc deschis</div><p style="font-size:13px;color:rgba(255,255,255,.58);margin:12px 0 18px">Astăzi · 09:00–20:00</p><a href="/bilete" class="btn btn-acid" style="width:100%">Rezervă acum</a></div>
    </aside>
  </div>

  <main>
    <section class="hero grain topo-dark" id="top">
      <div class="hero-photo"><img src="https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=2200&q=88" alt="Pădurea Nordvale" /></div>
      <svg class="hero-line" viewBox="0 0 600 600" aria-hidden="true"><path id="heroPath" d="M32 505C96 480 82 373 156 346c69-25 108 48 171 15 71-38 30-126 107-176 49-31 92-17 132-67"/><circle r="7"><animateMotion dur="8s" repeatCount="indefinite" rotate="auto"><mpath href="#heroPath"/></animateMotion></circle></svg>
      <div class="wrap hero-grid">
        <div class="hero-copy">
          <div class="hero-kicker"><span class="status-dot"></span> Despre locul care ne găzduiește</div>
          <h1 class="title-xl" style="margin-top:19px"><span>Nu am construit</span><span style="color:var(--acid)">peste pădure.</span><span class="outline" style="font-style:italic">Am învățat-o.</span></h1>
          <p class="hero-text">Nordvale este o rezervație forestieră cu acces controlat și un parc de aventură desenat în jurul ecosistemului existent. Fiecare traseu, oră de vizitare și experiență pornește de la aceeași regulă: natura stabilește ritmul.</p>
          <div class="hero-actions"><a href="#ecosistem" class="btn btn-acid">Explorează rezervația <span>↓</span></a><a href="/planifica" class="btn btn-ghost">Planifică o vizită</a></div>
        </div>
        <div class="hero-meta">
          <div class="meta-card"><div class="meta-value">34 ha</div><div class="meta-label">teritoriu protejat și monitorizat</div></div>
          <div class="meta-card"><div class="meta-value">71%</div><div class="meta-label">suprafață fără acces public</div></div>
          <div class="meta-card"><div class="meta-value">2008</div><div class="meta-label">primul sezon de observație</div></div>
        </div>
      </div>
    </section>

    <section class="manifesto paper-grid">
      <div class="wrap manifesto-grid">
        <div class="manifesto-copy" data-reveal>
          <div class="eyebrow">Manifestul Nordvale</div>
          <h2 class="title-lg" style="margin-top:15px">Pădurea nu este fundalul experienței. Este experiența.</h2>
          <p>Nordvale a început ca un proiect de refacere a unei păduri fragmentate. Abia după ani de cartografiere, regenerare și monitorizare au apărut primele poteci accesibile publicului.</p>
          <p>Astăzi, zonele de aventură ocupă o fracțiune din teritoriu. Restul este lăsat să crească, să se regenereze și să rămână suficient de liniștit pentru speciile care îl folosesc.</p>
          <a href="#principii" class="btn btn-dark" style="margin-top:25px">Cum luăm deciziile <span>↘</span></a>
        </div>
        <div class="manifesto-note" data-reveal>
          <div class="manifesto-image"><img src="https://images.unsplash.com/photo-1473448912268-2022ce9509d8?auto=format&fit=crop&w=1500&q=88" alt="Potecă prin rezervație" /></div>
          <div class="field-note"><small>Jurnal de teren · 071</small><p>„În dimineața aceasta, urmele de cerb au traversat poteca albastră înainte de deschiderea parcului.”</p></div>
        </div>
      </div>
    </section>

    <section class="ecosystem topo-dark" id="ecosistem">
      <div class="wrap ecosystem-grid">
        <div class="eco-copy" data-reveal>
          <div class="eyebrow light">Harta vie a teritoriului</div>
          <h2 class="title-lg" style="margin-top:15px">Patru zone. Patru ritmuri de regenerare.</h2>
          <p>Harta nu împarte rezervația în atracții, ci în grade diferite de intervenție. Selectează un strat pentru a vedea cum este folosit teritoriul.</p>
          <div class="eco-tabs" id="ecoTabs">
            <button class="eco-tab active" data-zone="core">Nucleu protejat</button><button class="eco-tab" data-zone="buffer">Zonă tampon</button><button class="eco-tab" data-zone="trails">Poteci și trasee</button><button class="eco-tab" data-zone="wetland">Coridor umed</button>
          </div>
        </div>
        <div class="eco-map" data-reveal>
          <svg viewBox="0 0 760 620" aria-label="Hartă schematică Nordvale">
            <defs><filter id="soft"><feGaussianBlur stdDeviation="8"/></filter></defs>
            <path class="zone-shape active" data-shape="core" d="M205 69c112-54 277 3 315 124 35 111-45 183-120 233-79 54-164 112-251 66-99-53-92-179-56-276 26-68 46-115 112-147Z" fill="#dffc62" opacity=".9"/>
            <path class="zone-shape" data-shape="buffer" d="M140 38c152-85 380-14 449 148 62 145-41 262-143 336-108 78-238 122-348 46-119-81-112-245-66-370C62 116 83 71 140 38Z" fill="none" stroke="#b7c9a3" stroke-width="36" opacity=".55"/>
            <path class="zone-shape" data-shape="wetland" d="M520 91c35 75-18 115-7 177 12 68 82 84 90 154 6 53-28 99-59 131" fill="none" stroke="#b8dce0" stroke-width="27" stroke-linecap="round" opacity=".85"/>
            <path class="zone-shape" data-shape="trails" d="M94 500c102-43 120-128 207-156 89-29 114 47 190 16 60-25 84-94 119-159" fill="none" stroke="#f27b4a" stroke-width="8" stroke-linecap="round" stroke-dasharray="10 16"/>
            <g fill="#fffdf6"><circle cx="168" cy="421" r="7"/><circle cx="300" cy="345" r="7"/><circle cx="497" cy="356" r="7"/></g>
          </svg>
          <div class="eco-info"><div class="eco-info-head"><div><div class="eyebrow light" id="ecoEyebrow">Acces interzis publicului</div><h3 id="ecoTitle">Nucleul protejat</h3></div><div class="eco-stat" id="ecoStat">24 ha</div></div><p id="ecoDescription">Zona cu arbori maturi, cuibărit și regenerare naturală. Este monitorizată, dar nu traversată de vizitatori.</p></div>
        </div>
      </div>
    </section>

    <section class="impact">
      <div class="wrap">
        <div class="impact-head" data-reveal><div><div class="eyebrow">Impact măsurabil</div><h2 class="title-lg" style="margin-top:15px">Conservarea nu este un mesaj. Este o linie de buget.</h2></div><p class="muted" style="font-size:16px;line-height:1.75;margin:0">Date dummy pentru conceptul de tenant leisure. În implementarea reală, aceste valori pot fi sincronizate cu rapoarte, proiecte și indicatori de mediu.</p></div>
        <div class="metrics">
          <article class="metric dark" data-reveal><div class="metric-value" data-count="113">0</div><div class="metric-label">specii observate în ultimele 12 luni</div></article>
          <article class="metric acid" data-reveal><div class="metric-value" data-count="4800">0</div><div class="metric-label">puieți plantați și protejați din 2018</div></article>
          <article class="metric" data-reveal><div class="metric-value" data-count="31">0</div><div class="metric-label">procent din fiecare bilet reinvestit în conservare</div></article>
          <article class="metric" data-reveal><div class="metric-value" data-count="62">0</div><div class="metric-label">zile pe an cu acces redus pentru protejarea habitatelor</div></article>
        </div>
      </div>
    </section>

    <section class="history">
      <div class="wrap history-grid">
        <div class="history-visual" data-reveal><img id="historyImage" src="https://images.unsplash.com/photo-1513836279014-a89f7a76ae86?auto=format&fit=crop&w=1400&q=86" alt="Istoria Nordvale"><span class="history-badge" id="historyBadge">2008 · începutul observațiilor</span></div>
        <div data-reveal><div class="eyebrow">Repere</div><h2 class="title-lg" style="margin-top:15px">O rezervație construită încet.</h2><p class="muted" style="font-size:16px;line-height:1.75">Selectează un moment pentru a vedea cum s-a transformat teritoriul.</p><div class="timeline" id="timeline"></div></div>
      </div>
    </section>

    <section class="notes topo-dark">
      <div class="wrap">
        <div data-reveal><div class="eyebrow light">Din jurnalul rangerilor</div><h2 class="title-lg" style="margin-top:15px;max-width:950px">Pădurea se schimbă zilnic. Noi doar ținem evidența.</h2></div>
        <div class="notes-grid">
          <article class="note-card" data-reveal><img src="https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1400&q=86" alt="Coroana pădurii"><div class="note-overlay"></div><div class="note-content"><div class="note-date">14 iunie · sector nord</div><h3>O pereche de ulii a revenit în vechiul cuib.</h3><p>Traseul de observare a fost deviat cu 80 de metri pentru restul sezonului.</p></div></article>
          <article class="note-card" data-reveal><img src="https://images.unsplash.com/photo-1425913397330-cf8af2ff40a1?auto=format&fit=crop&w=1000&q=86" alt="Podeaua pădurii"><div class="note-overlay"></div><div class="note-content"><div class="note-date">02 iulie · coridor umed</div><h3>Nivelul apei a revenit la valoarea de primăvară.</h3><p>Zona rămâne închisă vizitatorilor până la stabilizarea malurilor.</p></div></article>
          <article class="note-card" data-reveal><img src="https://images.unsplash.com/photo-1440342359743-84fcb8c21f21?auto=format&fit=crop&w=1000&q=86" alt="Urme pe potecă"><div class="note-overlay"></div><div class="note-content"><div class="note-date">19 iulie · poteca albastră</div><h3>Urme proaspete de cerb înainte de răsărit.</h3><p>Primele două tururi ghidate au pornit cu 30 de minute mai târziu.</p></div></article>
        </div>
      </div>
    </section>

    <section class="stewards">
      <div class="wrap">
        <div class="steward-intro" data-reveal><div><div class="eyebrow">Oamenii locului</div><h2 class="title-lg" style="margin-top:15px">Nu administrăm pădurea. O însoțim.</h2></div><p class="muted" style="font-size:16px;line-height:1.75;margin:0">Echipa dummy reunește biologi, rangeri, ghizi și specialiști în siguranță. Fiecare experiență publică pornește de la observațiile lor.</p></div>
        <div class="team">
          <article class="person" data-reveal><img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=700&q=85" alt="Mara Dobre"><div><div class="person-role">Ecolog coordonator</div><h3>Mara Dobre</h3><p>Cartografiază habitatele și stabilește ferestrele de acces.</p></div></article>
          <article class="person" data-reveal><img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=700&q=85" alt="Tudor Mirea"><div><div class="person-role">Ranger principal</div><h3>Tudor Mirea</h3><p>Coordonează patrulele și monitorizarea zilnică.</p></div></article>
          <article class="person" data-reveal><img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=700&q=85" alt="Iris Neagu"><div><div class="person-role">Educație și ghidaj</div><h3>Iris Neagu</h3><p>Transformă datele de teren în experiențe pentru vizitatori.</p></div></article>
          <article class="person" data-reveal><img src="https://images.unsplash.com/photo-1472099645785-5658abf4ff4e?auto=format&fit=crop&w=700&q=85" alt="Luca Vornic"><div><div class="person-role">Siguranță trasee</div><h3>Luca Vornic</h3><p>Verifică zilnic traseele suspendate și punctele de acces.</p></div></article>
        </div>
      </div>
    </section>

    <section class="principles paper-grid" id="principii">
      <div class="wrap">
        <div data-reveal><div class="eyebrow">Cum luăm deciziile</div><h2 class="title-lg" style="margin-top:15px">Patru reguli care au prioritate în fața oricărei atracții.</h2></div>
        <div class="principle-list">
          <article class="principle" data-reveal><div class="principle-num">01</div><div><h3>Habitatul înaintea programului.</h3><p>Dacă o zonă are nevoie de liniște, programul se schimbă. Accesul public nu este niciodată garantat în detrimentul ecosistemului.</p></div></article>
          <article class="principle" data-reveal><div class="principle-num">02</div><div><h3>Capacitate limitată, nu trafic maxim.</h3><p>Numărul de vizitatori este stabilit pe intervale, nu după potențialul comercial al zilei.</p></div></article>
          <article class="principle" data-reveal><div class="principle-num">03</div><div><h3>Intervenții reversibile.</h3><p>Traseele și platformele sunt proiectate astfel încât să poată fi mutate sau retrase cu impact minim.</p></div></article>
          <article class="principle" data-reveal><div class="principle-num">04</div><div><h3>Date publice, decizii explicate.</h3><p>Închiderea unei zone, schimbarea unui traseu sau limitarea accesului trebuie să poată fi explicate vizitatorilor.</p></div></article>
        </div>
      </div>
    </section>

    <section class="cta topo-dark grain">
      <div class="wrap cta-grid">
        <div data-reveal><div class="eyebrow light">Vizitează cu sens</div><h2 class="title-lg" style="margin-top:15px;max-width:980px">O zi în Nordvale începe cu o alegere simplă: cât de aproape vrei să ajungi?</h2><p>Planifică traseul potrivit ritmului tău și lasă restul pădurii exact așa cum l-ai găsit.</p></div>
        <div class="cta-actions" data-reveal><a href="/planifica" class="btn btn-acid">Planifică vizita</a><a href="/bilete" class="btn btn-ghost">Vezi biletele</a></div>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="wrap"><div class="footer-grid"><div class="footer-brand"><div class="brand"><span class="brand-mark"><svg viewBox="0 0 48 48" width="31" height="31" fill="none"><path d="M7 35 18 11l7 14 5-10 11 20H7Z" fill="#DFFC62"/><path d="m8 37 11-10 6 6 7-8 8 12H8Z" fill="#FFFDF6"/></svg></span><span><span class="brand-name">Nordvale</span><span class="brand-sub" style="display:block">wild park · forest reserve</span></span></div><p>Un concept dummy de tenant Tixello pentru parcuri de aventură și rezervații naturale.</p></div><div class="footer-links"><div><h4>Explorează</h4><a href="/experiente">Experiențe</a><a href="/calendar">Calendar</a><a href="/planifica">Planifică</a></div><div><h4>Nordvale</h4><a href="#top">Rezervația</a><a href="/grupuri">Grupuri</a><a href="/cont/suport">Suport</a></div><div><h4>Cont</h4><a href="/autentificare">Autentificare</a><a href="/cont">Dashboard</a><a href="/cont/bilete">Bilete</a></div></div></div><div class="footer-bottom"><span>© 2026 Nordvale. Date și identitate dummy.</span><span>Ticketing & access by Tixello</span></div></div>
  </footer>

  <script>
    const $=(s,c=document)=>c.querySelector(s), $$=(s,c=document)=>[...c.querySelectorAll(s)];
    const nav=$('#nav'), progress=$('#pageProgress');
    const onScroll=()=>{const y=window.scrollY;nav.classList.toggle('scrolled',y>18);const h=document.documentElement.scrollHeight-innerHeight;progress.style.transform=`scaleX(${h>0?y/h:0})`};
    addEventListener('scroll',onScroll,{passive:true});onScroll();

    const menu=$('#mobileMenu');
    function setMenu(open){menu.classList.toggle('open',open);menu.setAttribute('aria-hidden',String(!open));document.body.classList.toggle('menu-open',open)}
    $('#menuOpen').addEventListener('click',()=>setMenu(true));$('#menuClose').addEventListener('click',()=>setMenu(false));$('#menuBackdrop').addEventListener('click',()=>setMenu(false));$$('.menu-links a').forEach(a=>a.addEventListener('click',()=>setMenu(false)));addEventListener('keydown',e=>{if(e.key==='Escape')setMenu(false)});

    const revealObserver=new IntersectionObserver(entries=>entries.forEach(e=>{if(e.isIntersecting){e.target.classList.add('visible');revealObserver.unobserve(e.target)}}),{threshold:.13,rootMargin:'0px 0px -5%'});$$('[data-reveal]').forEach(el=>revealObserver.observe(el));

    const ecoData={
      core:{eyebrow:'Acces interzis publicului',title:'Nucleul protejat',stat:'24 ha',description:'Zona cu arbori maturi, cuibărit și regenerare naturală. Este monitorizată, dar nu traversată de vizitatori.'},
      buffer:{eyebrow:'Acces doar ghidat',title:'Zona tampon',stat:'7 ha',description:'Reduce presiunea asupra nucleului protejat. Aici au loc observații ghidate și intervenții limitate de refacere.'},
      trails:{eyebrow:'Acces public controlat',title:'Poteci și trasee',stat:'3,4 km',description:'Coridoarele publice concentrează vizitarea în zone stabile, cu intervale și capacități definite.'},
      wetland:{eyebrow:'Coridor sezonier',title:'Zona umedă',stat:'1,8 km',description:'Leagă habitatele și colectează apa sezonieră. Accesul variază în funcție de nivel și perioada de reproducere.'}
    };
    $$('.eco-tab').forEach(tab=>tab.addEventListener('click',()=>{const zone=tab.dataset.zone;$$('.eco-tab').forEach(t=>t.classList.toggle('active',t===tab));$$('.zone-shape').forEach(s=>{s.classList.toggle('dim',s.dataset.shape!==zone);s.classList.toggle('active',s.dataset.shape===zone)});const d=ecoData[zone];$('#ecoEyebrow').textContent=d.eyebrow;$('#ecoTitle').textContent=d.title;$('#ecoStat').textContent=d.stat;$('#ecoDescription').textContent=d.description}));

    const history=[
      {year:'2008',title:'Primele observații',copy:'Teritoriul este cartografiat, iar zonele fragmentate sunt incluse într-un plan de regenerare.',image:'https://images.unsplash.com/photo-1513836279014-a89f7a76ae86?auto=format&fit=crop&w=1400&q=86'},
      {year:'2012',title:'Coridorul verde',copy:'Două parcele sunt reunite printr-o zonă tampon plantată cu specii locale.',image:'https://images.unsplash.com/photo-1448375240586-882707db888b?auto=format&fit=crop&w=1400&q=86'},
      {year:'2017',title:'Prima potecă publică',copy:'Se deschide un traseu ghidat de 800 de metri, cu maximum 40 de vizitatori pe zi.',image:'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1400&q=86'},
      {year:'2021',title:'Traseele suspendate',copy:'Structurile de aventură sunt montate în zona stabilă, fără fundații permanente în nucleul forestier.',image:'https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1400&q=86'},
      {year:'2026',title:'Nordvale astăzi',copy:'Rezervația funcționează cu acces controlat, raportare anuală și programe de educație pentru școli.',image:'https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1400&q=86'}
    ];
    const timeline=$('#timeline');
    history.forEach((item,i)=>{const b=document.createElement('button');b.className='timeline-item'+(i===0?' active':'');b.innerHTML=`<span class="timeline-year">${item.year}</span><span><span class="timeline-title">${item.title}</span><span class="timeline-copy">${item.copy}</span></span><span class="timeline-arrow">↗</span>`;b.addEventListener('click',()=>{$$('.timeline-item').forEach(x=>x.classList.remove('active'));b.classList.add('active');const img=$('#historyImage');img.style.opacity='0';setTimeout(()=>{img.src=item.image;img.alt=item.title;$('#historyBadge').textContent=`${item.year} · ${item.title.toLowerCase()}`;img.onload=()=>img.style.opacity='1'},180)});timeline.appendChild(b)});

    const counterObserver=new IntersectionObserver(entries=>entries.forEach(e=>{if(!e.isIntersecting)return;const el=e.target,target=+el.dataset.count,dur=1200,start=performance.now();const fmt=v=>target>=1000?Math.round(v).toLocaleString('ro-RO'):Math.round(v);function tick(now){const p=Math.min(1,(now-start)/dur),ease=1-Math.pow(1-p,3);el.textContent=fmt(target*ease)+(target===31?'%':'');if(p<1)requestAnimationFrame(tick)}requestAnimationFrame(tick);counterObserver.unobserve(el)}),{threshold:.55});$$('[data-count]').forEach(el=>counterObserver.observe(el));
  </script>
</body>
</html>
