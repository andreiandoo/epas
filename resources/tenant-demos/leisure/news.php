<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <meta name="robots" content="noindex,nofollow" />
  <meta name="theme-color" content="#061a15" />
  <title>Noutăți — Nordvale</title>
  <meta name="description" content="Noutăți dummy din rezervația Nordvale: povești din teren, actualizări operaționale și experiențe noi." />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <style>
    :root{
      --pine:#09251d;--pine-dark:#061a15;--pine-mid:#123b30;--pine-light:#1b5242;
      --acid:#dffc62;--ember:#f27b4a;--cream:#fffdf6;--oat:#f0ecdf;--ink:#16231e;
      --moss:#b7c9a3;--sky:#b8dce0;--shadow:0 36px 100px -38px rgba(6,26,21,.58);
      --card-shadow:0 18px 48px -28px rgba(6,26,21,.46);--radius:28px;
    }
    *{box-sizing:border-box}
    html{scroll-behavior:smooth;background:var(--oat)}
    body{margin:0;overflow-x:hidden;background:var(--oat);color:var(--ink);font-family:'DM Sans',sans-serif;text-rendering:optimizeLegibility}
    body.menu-open,body.modal-open{overflow:hidden}
    button,a,input{font:inherit}button{cursor:pointer}a{text-decoration:none;color:inherit}img{display:block;max-width:100%}
    ::selection{background:var(--acid);color:var(--pine-dark)}
    .display{font-family:'Fraunces',serif}.wrap{width:min(1460px,calc(100% - 32px));margin-inline:auto}
    .safe-top{padding-top:max(10px,env(safe-area-inset-top))}.safe-bottom{padding-bottom:max(16px,env(safe-area-inset-bottom))}
    .eyebrow{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.24em;color:var(--ember)}
    .eyebrow.light{color:var(--acid)}
    .title-xl{font-family:'Fraunces',serif;font-size:clamp(54px,9vw,124px);line-height:.86;letter-spacing:-.058em;font-weight:600;margin:0}
    .title-lg{font-family:'Fraunces',serif;font-size:clamp(42px,6.7vw,86px);line-height:.92;letter-spacing:-.048em;font-weight:600;margin:0}
    .title-md{font-family:'Fraunces',serif;font-size:clamp(34px,4.4vw,62px);line-height:.96;letter-spacing:-.04em;font-weight:600;margin:0}
    .muted{color:rgba(9,37,29,.62)}.muted-light{color:rgba(255,255,255,.62)}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:52px;border-radius:999px;padding:0 22px;border:0;font-weight:700;white-space:nowrap;transition:.25s ease}
    .btn:hover{transform:translateY(-2px)}.btn-acid{background:var(--acid);color:var(--pine-dark);box-shadow:0 18px 54px -24px rgba(223,252,98,.58)}
    .btn-dark{background:var(--pine);color:white}.btn-outline{border:1px solid rgba(9,37,29,.2);background:transparent;color:var(--pine)}
    .btn-ghost{border:1px solid rgba(255,255,255,.2);background:transparent;color:white}
    .icon-round{width:48px;height:48px;border-radius:50%;display:grid;place-items:center;flex:none}
    .grain::after{content:"";position:absolute;inset:0;pointer-events:none;z-index:3;opacity:.075;mix-blend-mode:soft-light;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.58'/%3E%3C/svg%3E")}
    .topo-dark{background-image:radial-gradient(circle at 18% 22%,rgba(223,252,98,.14),transparent 20%),radial-gradient(circle at 84% 18%,rgba(242,123,74,.12),transparent 17%),url("data:image/svg+xml,%3Csvg width='820' height='820' viewBox='0 0 820 820' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23ffffff' stroke-opacity='.055' stroke-width='1.1'%3E%3Cpath d='M88 212c37-105 158-179 273-133 94 37 117 148 65 224-49 72-150 69-199 143-51 77-34 200-133 247-95 45-205-31-192-137 13-103 118-121 127-216 5-55-2-92 59-128Z'/%3E%3Cpath d='M119 231c31-83 127-143 219-107 76 30 95 118 53 180-40 58-121 56-160 115-42 61-28 159-107 197-77 37-165-25-154-110 10-83 94-98 102-174 4-44 0-74 47-101Z'/%3E%3Cpath d='M513 479c52-81 159-106 232-43 72 60 50 172-26 217-68 40-149 11-202 68-45 48-39 134-105 151-74 19-142-53-112-125 28-67 109-63 148-123 31-49 30-97 65-145Z'/%3E%3C/g%3E%3C/svg%3E");background-size:auto,auto,820px 820px}
    .paper-grid{background-image:linear-gradient(rgba(9,37,29,.052) 1px,transparent 1px),linear-gradient(90deg,rgba(9,37,29,.052) 1px,transparent 1px);background-size:30px 30px}
    .progress{position:fixed;left:0;right:0;top:0;height:3px;z-index:170}.progress>span{display:block;width:100%;height:100%;background:var(--acid);transform-origin:left;transform:scaleX(0)}

    /* Header */
    .site-header{position:fixed;inset:0 0 auto;z-index:150;padding-inline:10px}.nav{max-width:1540px;margin:2px auto 0;min-height:62px;display:flex;align-items:center;justify-content:space-between;gap:8px;border:1px solid transparent;border-radius:20px;padding:10px 12px;transition:.4s ease}
    .nav.scrolled{background:rgba(6,26,21,.8);border-color:rgba(255,255,255,.12);box-shadow:0 24px 70px -42px rgba(0,0,0,.9);backdrop-filter:blur(22px) saturate(130%)}
    .brand{display:flex;align-items:center;gap:10px;min-width:0;color:white}.brand-mark{width:40px;height:40px;border-radius:14px;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.1);display:grid;place-items:center;flex:none}
    .brand-name{font-family:'Fraunces',serif;font-size:19px;font-weight:600;line-height:1;white-space:nowrap}.brand-sub{display:none;margin-top:5px;font-size:7px;font-weight:700;text-transform:uppercase;letter-spacing:.25em;color:rgba(255,255,255,.52);white-space:nowrap}
    .desktop-nav{display:none;align-items:center;gap:27px}.desktop-nav a{font-size:14px;font-weight:600;color:rgba(255,255,255,.7);transition:.2s}.desktop-nav a:hover,.desktop-nav a.active{color:var(--acid)}
    .nav-actions{display:flex;align-items:center;gap:6px;flex:none}.account-link{display:none;color:rgba(255,255,255,.82);font-size:14px;font-weight:600;border:1px solid rgba(255,255,255,.15);padding:10px 16px;border-radius:999px}
    .ticket-btn{min-height:40px;padding:0 13px;font-size:12px}.menu-btn{width:40px;height:40px;border-radius:50%;border:1px solid rgba(255,255,255,.15);background:transparent;color:white;display:grid;place-items:center}
    .mobile-menu{position:fixed;inset:0;z-index:165;visibility:hidden;pointer-events:none}.mobile-menu.open{visibility:visible;pointer-events:auto}.menu-backdrop{position:absolute;inset:0;background:rgba(6,26,21,.74);backdrop-filter:blur(7px);opacity:0;transition:.3s}.mobile-menu.open .menu-backdrop{opacity:1}
    .menu-panel{position:absolute;right:0;top:0;height:100%;width:min(88vw,430px);background:var(--cream);padding:22px;display:flex;flex-direction:column;transform:translateX(100%);transition:.42s cubic-bezier(.2,.8,.2,1)}.mobile-menu.open .menu-panel{transform:none}
    .menu-head{display:flex;align-items:center;justify-content:space-between}.menu-links{margin-top:45px}.menu-links a{display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid rgba(9,37,29,.1);padding:16px 0;font-family:'Fraunces',serif;font-size:27px;font-weight:600;color:var(--pine)}
    .menu-status{margin-top:auto;background:var(--pine);border-radius:22px;padding:20px;color:white}.status-dot{width:8px;height:8px;border-radius:50%;background:var(--acid);display:inline-block;box-shadow:0 0 0 0 rgba(223,252,98,.45);animation:pulse 2.2s infinite}@keyframes pulse{70%{box-shadow:0 0 0 10px rgba(223,252,98,0)}}

    /* Hero */
    .hero{position:relative;min-height:100svh;background:var(--pine-dark);color:white;overflow:hidden;display:flex;align-items:flex-end}
    .hero-media{position:absolute;inset:0}.hero-media img{width:100%;height:100%;object-fit:cover;opacity:.55;transform:scale(1.045)}.hero-media::after{content:"";position:absolute;inset:0;background:linear-gradient(90deg,rgba(6,26,21,.93) 0%,rgba(6,26,21,.7) 48%,rgba(6,26,21,.2) 100%),linear-gradient(0deg,rgba(6,26,21,.82) 0%,transparent 55%)}
    .hero-grid{position:relative;z-index:5;display:grid;gap:32px;padding:128px 0 44px}.hero-copy{max-width:980px}.hero-kicker{display:inline-flex;align-items:center;gap:9px;padding:9px 13px;border-radius:999px;border:1px solid rgba(255,255,255,.14);background:rgba(255,255,255,.07);font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.76)}
    .hero-title span{display:block}.outline{color:transparent;-webkit-text-stroke:1px rgba(255,255,255,.46)}.hero-intro{max-width:640px;margin:24px 0 0;font-size:16px;line-height:1.75;color:rgba(255,255,255,.68)}
    .hero-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:26px}.hero-feature{display:grid;gap:18px;border-top:1px solid rgba(255,255,255,.14);padding-top:25px;max-width:960px}.feature-meta{display:flex;gap:12px;align-items:center;flex-wrap:wrap;font-size:10px;text-transform:uppercase;letter-spacing:.16em;color:rgba(255,255,255,.58)}
    .feature-meta span:first-child{color:var(--acid);font-weight:700}.hero-feature h2{margin:0;font-family:'Fraunces',serif;font-size:clamp(29px,4vw,55px);line-height:1.02;letter-spacing:-.035em;font-weight:600}.hero-feature p{margin:0;max-width:610px;font-size:14px;line-height:1.7;color:rgba(255,255,255,.6)}
    .hero-route{position:absolute;right:-60px;top:13%;width:620px;height:620px;z-index:2;opacity:.72}.hero-route path{fill:none;stroke:var(--acid);stroke-width:2;stroke-linecap:round;stroke-dasharray:10 14}.hero-route circle{fill:var(--acid)}
    .issue-stamp{position:absolute;right:18px;bottom:34px;z-index:6;width:124px;height:124px;border-radius:50%;border:1px solid rgba(255,255,255,.22);display:grid;place-items:center;text-align:center;transform:rotate(8deg);background:rgba(6,26,21,.36);backdrop-filter:blur(10px)}
    .issue-stamp strong{display:block;font-family:'Fraunces',serif;font-size:31px;color:var(--acid);line-height:1}.issue-stamp small{display:block;margin-top:5px;font-size:8px;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.56)}

    /* Signal strip */
    .signal{background:var(--acid);color:var(--pine-dark);overflow:hidden}.signal-track{display:flex;width:max-content;animation:marquee 35s linear infinite}.signal-item{display:flex;align-items:center;gap:12px;padding:15px 28px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.14em;white-space:nowrap}.signal-item::after{content:"";width:7px;height:7px;border-radius:50%;background:var(--ember);margin-left:16px}@keyframes marquee{to{transform:translateX(-50%)}}

    /* Filters */
    .news-shell{background:var(--oat);padding:78px 0 96px}.news-head{display:grid;gap:22px}.news-tools{display:grid;gap:12px;margin-top:30px}.filter-row{display:flex;gap:8px;overflow:auto;padding-bottom:5px;scrollbar-width:none}.filter-row::-webkit-scrollbar{display:none}.filter{border:1px solid rgba(9,37,29,.15);background:rgba(255,255,255,.48);color:rgba(9,37,29,.62);border-radius:999px;padding:12px 16px;font-size:12px;font-weight:700;white-space:nowrap;transition:.25s}.filter:hover{border-color:rgba(9,37,29,.35)}.filter.active{background:var(--pine);border-color:var(--pine);color:var(--acid)}
    .search{position:relative}.search input{width:100%;min-height:52px;border:1px solid rgba(9,37,29,.15);border-radius:999px;background:rgba(255,255,255,.72);padding:0 48px 0 18px;outline:none;color:var(--pine)}.search input:focus{border-color:var(--pine);box-shadow:0 0 0 3px rgba(9,37,29,.08)}.search svg{position:absolute;right:17px;top:50%;transform:translateY(-50%);color:rgba(9,37,29,.42)}

    /* Editorial grid */
    .editorial-grid{display:grid;gap:14px;margin-top:36px}.story-card{position:relative;border-radius:26px;overflow:hidden;background:var(--cream);box-shadow:var(--card-shadow);min-height:360px;isolation:isolate;transition:transform .35s ease,box-shadow .35s ease}.story-card:hover{transform:translateY(-5px);box-shadow:var(--shadow)}
    .story-card img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:transform .9s cubic-bezier(.18,.8,.22,1)}.story-card:hover img{transform:scale(1.05)}.story-card::after{content:"";position:absolute;inset:0;z-index:1;background:linear-gradient(0deg,rgba(6,26,21,.9) 0%,rgba(6,26,21,.15) 70%)}
    .story-content{position:absolute;inset:auto 0 0;z-index:2;padding:22px;color:white}.story-tag{display:inline-flex;padding:8px 11px;border-radius:999px;background:var(--acid);color:var(--pine);font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.16em}.story-meta{display:flex;gap:9px;align-items:center;margin-top:14px;font-size:9px;text-transform:uppercase;letter-spacing:.16em;color:rgba(255,255,255,.55)}.story-card h3{margin:9px 0 0;font-family:'Fraunces',serif;font-size:clamp(26px,3.2vw,44px);line-height:1.05;letter-spacing:-.035em;font-weight:600}.story-card p{margin:10px 0 0;max-width:560px;font-size:12px;line-height:1.65;color:rgba(255,255,255,.64)}
    .story-card.text-card{background:var(--cream);color:var(--pine);min-height:330px;display:flex;flex-direction:column;justify-content:space-between;padding:24px}.story-card.text-card::after{display:none}.story-card.text-card .story-tag{align-self:flex-start;background:var(--ember);color:white}.story-card.text-card h3{color:var(--pine);font-size:clamp(27px,3vw,39px)}.story-card.text-card p{color:rgba(9,37,29,.6)}.story-card.text-card .story-meta{color:rgba(9,37,29,.45)}
    .story-card.dark-card{background:var(--pine);color:white}.story-card.dark-card::after{display:none}.story-card.dark-card h3{color:white}.story-card.dark-card p{color:rgba(255,255,255,.62)}.story-card.dark-card .story-meta{color:rgba(255,255,255,.45)}
    .story-icon{width:56px;height:56px;border-radius:20px;background:var(--acid);color:var(--pine);display:grid;place-items:center}
    .no-results{display:none;margin-top:36px;border:1px dashed rgba(9,37,29,.22);border-radius:26px;padding:48px 22px;text-align:center;background:rgba(255,255,255,.5)}.no-results.show{display:block}
    .load-more{display:flex;justify-content:center;margin-top:34px}

    /* Dispatches */
    .dispatch{background:var(--pine-dark);color:white;padding:92px 0;position:relative;overflow:hidden}.dispatch-grid{display:grid;gap:40px}.dispatch-lead p{max-width:560px;font-size:17px;line-height:1.78;color:rgba(255,255,255,.62)}
    .dispatch-list{border-top:1px solid rgba(255,255,255,.13)}.dispatch-item{display:grid;grid-template-columns:68px 1fr auto;gap:12px;align-items:center;border-bottom:1px solid rgba(255,255,255,.13);padding:22px 0;transition:.25s}.dispatch-item:hover{padding-left:8px}.dispatch-date{font-family:'Fraunces',serif;font-size:20px;color:var(--acid)}.dispatch-item h3{margin:0;font-family:'Fraunces',serif;font-size:22px;font-weight:600}.dispatch-item p{margin:5px 0 0;font-size:11px;color:rgba(255,255,255,.48)}.dispatch-arrow{width:42px;height:42px;border-radius:50%;border:1px solid rgba(255,255,255,.17);display:grid;place-items:center;color:var(--acid)}

    /* Field note */
    .field-feature{background:var(--cream);padding:92px 0}.field-grid{display:grid;gap:28px}.field-image{position:relative;min-height:520px;border-radius:28px;overflow:hidden;box-shadow:var(--shadow)}.field-image img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.field-note{position:absolute;left:14px;right:14px;bottom:14px;background:var(--acid);padding:20px;transform:rotate(-1.2deg);box-shadow:var(--card-shadow)}.field-note small{font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.18em;color:rgba(9,37,29,.58)}.field-note p{margin:10px 0 0;font-family:'Fraunces',serif;font-size:22px;line-height:1.35;color:var(--pine)}
    .field-copy{align-self:center}.field-copy>p{font-size:17px;line-height:1.8;color:rgba(9,37,29,.64)}.field-points{display:grid;gap:10px;margin-top:24px}.field-point{display:flex;gap:12px;align-items:flex-start;border-top:1px solid rgba(9,37,29,.11);padding-top:15px}.field-point strong{font-family:'Fraunces',serif;font-size:20px}.field-point span{display:block;margin-top:5px;font-size:12px;line-height:1.55;color:rgba(9,37,29,.52)}

    /* Newsletter */
    .newsletter{padding:80px 0;background:var(--oat)}.newsletter-card{position:relative;overflow:hidden;background:var(--ember);border-radius:32px;padding:28px;color:white}.newsletter-grid{display:grid;gap:28px}.newsletter p{max-width:590px;line-height:1.7;color:rgba(255,255,255,.76)}.newsletter-form{display:grid;gap:10px}.newsletter-form input{min-height:54px;border-radius:999px;border:1px solid rgba(255,255,255,.28);background:rgba(255,255,255,.13);padding:0 18px;color:white;outline:none}.newsletter-form input::placeholder{color:rgba(255,255,255,.62)}.newsletter-form input:focus{border-color:white}.newsletter-check{display:flex;gap:10px;align-items:flex-start;font-size:10px;line-height:1.5;color:rgba(255,255,255,.68)}.newsletter-check input{width:17px;height:17px;accent-color:var(--acid);margin-top:1px;flex:none}.newsletter-topo{position:absolute;right:-110px;top:-120px;width:430px;height:430px;border:1px solid rgba(255,255,255,.2);border-radius:45% 55% 53% 47%;transform:rotate(18deg)}

    /* Footer */
    footer{background:var(--pine-dark);color:white;padding:68px 0 24px}.footer-grid{display:grid;gap:38px}.footer-brand p{max-width:380px;font-size:13px;line-height:1.7;color:rgba(255,255,255,.52)}.footer-cols{display:grid;grid-template-columns:1fr 1fr;gap:28px}.footer-col h4{margin:0 0 14px;font-size:10px;text-transform:uppercase;letter-spacing:.18em;color:var(--acid)}.footer-col a{display:block;margin:10px 0;font-size:13px;color:rgba(255,255,255,.62)}.footer-bottom{margin-top:38px;padding-top:22px;border-top:1px solid rgba(255,255,255,.11);display:flex;gap:14px;justify-content:space-between;flex-wrap:wrap;font-size:10px;color:rgba(255,255,255,.4)}

    [data-reveal]{opacity:0;transform:translateY(30px)}
    @media(min-width:640px){
      .wrap{width:min(1460px,calc(100% - 48px))}.brand-sub{display:block}.hero-grid{padding-bottom:56px}.hero-feature{grid-template-columns:1.2fr .8fr;align-items:end}.news-tools{grid-template-columns:1fr 330px;align-items:center}.editorial-grid{grid-template-columns:1fr 1fr}.story-card:first-child{grid-column:span 2;min-height:550px}.newsletter-form{grid-template-columns:1fr auto;align-items:start}.newsletter-check{grid-column:1/-1}.footer-cols{grid-template-columns:repeat(4,1fr)}
    }
    @media(min-width:900px){
      .nav{padding-inline:18px}.desktop-nav{display:flex}.menu-btn{display:none}.account-link{display:block}.ticket-btn{font-size:13px;padding-inline:18px}.hero-grid{grid-template-columns:1fr .44fr;align-items:end}.issue-stamp{right:42px;bottom:48px}.editorial-grid{grid-template-columns:1.15fr .85fr .85fr;grid-auto-rows:minmax(330px,auto)}.story-card:first-child{grid-column:span 2;grid-row:span 2;min-height:690px}.story-card:nth-child(4){grid-column:span 2}.dispatch-grid{grid-template-columns:.8fr 1.2fr;align-items:start}.field-grid{grid-template-columns:1.08fr .92fr;gap:64px}.newsletter-card{padding:54px}.newsletter-grid{grid-template-columns:1fr .85fr;align-items:center}.footer-grid{grid-template-columns:1fr 1.2fr}
    }
    @media(max-width:639px){
      .wrap{width:min(100% - 24px,1460px)}.site-header{padding-inline:6px}.nav{padding:8px;border-radius:17px}.brand-mark{width:38px;height:38px}.brand-name{font-size:17px}.ticket-btn{min-height:38px;padding:0 12px;font-size:11px}.menu-btn{width:38px;height:38px}.hero{min-height:auto}.hero-grid{padding-top:112px;padding-bottom:30px}.hero-title{font-size:clamp(49px,16vw,73px)}.hero-actions{display:grid;grid-template-columns:1fr 1fr}.hero-actions .btn{padding-inline:14px;font-size:12px}.hero-feature{margin-top:8px}.issue-stamp{position:relative;right:auto;bottom:auto;width:106px;height:106px;margin:0 0 0 auto}.hero-route{right:-250px;top:20%;width:520px;height:520px}.signal-item{padding-inline:18px}.news-shell,.dispatch,.field-feature{padding-block:72px}.story-card{min-height:390px}.story-card.text-card{min-height:310px}.dispatch-item{grid-template-columns:56px 1fr 38px}.dispatch-date{font-size:17px}.dispatch-item h3{font-size:19px}.field-image{min-height:420px}.newsletter{padding:62px 0}.newsletter-card{border-radius:26px}.newsletter-form .btn{width:100%}.footer-cols{grid-template-columns:1fr 1fr}.load-more .btn{width:100%}
    }
    @media(max-width:380px){.hero-actions{grid-template-columns:1fr}.title-xl{font-size:47px}.nav-actions{gap:4px}.ticket-btn{padding-inline:10px}.menu-panel{width:92vw}}
    @media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}*,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}[data-reveal]{opacity:1;transform:none}.signal-track{animation:none}}
  </style>
</head>
<body>
  <div class="progress" aria-hidden="true"><span id="scrollProgress"></span></div>

  <?php $nvNoSpacer = true; include __DIR__ . '/includes/header.php'; ?>

  <main>
    <section class="hero grain topo-dark">
      <div class="hero-media" aria-hidden="true"><img src="https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=2200&q=85" alt=""></div>
      <svg class="hero-route" viewBox="0 0 620 620" aria-hidden="true"><path id="heroPath" d="M66 486c84-48 45-153 137-183 90-29 104 88 199 54 93-33 38-138 132-189"/><circle id="heroMarker" cx="66" cy="486" r="7"/></svg>
      <div class="wrap hero-grid">
        <div class="hero-copy" data-reveal>
          <span class="hero-kicker"><span class="status-dot"></span> Jurnalul Nordvale · Ediția de vară</span>
          <h1 class="title-xl hero-title" style="margin-top:24px"><span>Din teren.</span><span class="outline">Fără filtru.</span></h1>
          <p class="hero-intro">Povești din rezervație, oameni care o protejează, experiențe noi și toate actualizările care îți pot schimba următoarea vizită.</p>
          <div class="hero-actions"><a class="btn btn-acid" href="#ultimele">Citește ultimele</a><a class="btn btn-ghost" href="#newsletter">Primește jurnalul</a></div>
        </div>
        <div class="issue-stamp" data-reveal><div><strong>08</strong><small>Iulie<br>2026</small></div></div>
        <a class="hero-feature" href="/noutati/detaliu" data-reveal>
          <div><div class="feature-meta"><span>Poveste de copertă</span><span>29 iulie 2026</span><span>6 min</span></div><h2>Pădurea care se răcorește singură</h2></div>
          <p>În cele mai calde zile, diferența dintre marginea parcului și nucleul rezervației ajunge la aproape șase grade. Rangerii explică de ce.</p>
        </a>
      </div>
    </section>

    <div class="signal" aria-label="Actualizări rapide"><div class="signal-track" id="signalTrack">
      <span class="signal-item">Canopy Run revine la program complet</span><span class="signal-item">Shuttle suplimentar sâmbătă</span><span class="signal-item">Traseul Creekline rămâne închis după ploaie</span><span class="signal-item">Locuri noi la turul de apus</span>
      <span class="signal-item">Canopy Run revine la program complet</span><span class="signal-item">Shuttle suplimentar sâmbătă</span><span class="signal-item">Traseul Creekline rămâne închis după ploaie</span><span class="signal-item">Locuri noi la turul de apus</span>
    </div></div>

    <section class="news-shell paper-grid" id="ultimele">
      <div class="wrap">
        <div class="news-head" data-reveal><div><span class="eyebrow">Ultimele din Nordvale</span><h2 class="title-lg" style="margin-top:14px">Un jurnal viu,<br>nu un panou de anunțuri.</h2></div><p class="muted" style="max-width:660px;font-size:16px;line-height:1.75;margin:0">Filtrează după ceea ce te interesează: viața rezervației, actualizări operaționale, experiențe, oameni sau proiecte pentru comunitate.</p></div>
        <div class="news-tools" data-reveal>
          <div class="filter-row" id="filters" aria-label="Filtrează articolele">
            <button class="filter active" type="button" data-filter="all">Toate</button><button class="filter" type="button" data-filter="teren">Din teren</button><button class="filter" type="button" data-filter="experiente">Experiențe</button><button class="filter" type="button" data-filter="operational">Operațional</button><button class="filter" type="button" data-filter="comunitate">Comunitate</button>
          </div>
          <label class="search"><input id="newsSearch" type="search" placeholder="Caută în jurnal…" aria-label="Caută în jurnal"><svg width="19" height="19" viewBox="0 0 24 24" fill="none"><circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.7"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg></label>
        </div>

        <div class="editorial-grid" id="newsGrid">
          <a class="story-card" href="/noutati/detaliu" data-category="teren" data-title="pădurea care se răcorește singură" data-reveal><img src="https://images.unsplash.com/photo-1513836279014-a89f7a76ae86?auto=format&fit=crop&w=1600&q=85" alt="Privire verticală către coroanele copacilor"><div class="story-content"><span class="story-tag">Din teren</span><div class="story-meta"><span>29 iulie 2026</span><span>6 min</span></div><h3>Pădurea care se răcorește singură</h3><p>Un experiment simplu, 18 senzori și o diferență de temperatură care arată cât de mult contează nucleul protejat.</p></div></a>

          <a class="story-card text-card dark-card" href="/noutati/detaliu" data-category="operational" data-title="ghid de vizită pentru weekendul aglomerat" data-reveal><div><span class="story-tag">Update parc</span><div class="story-meta"><span>28 iulie 2026</span><span>3 min</span></div><h3>Weekend aglomerat. Ce merită să știi înainte să pornești.</h3><p>Shuttle suplimentar, intervale recomandate și două modificări temporare de traseu.</p></div><div class="story-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M5 18 11 6l3 7 2-4 3 9H5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg></div></a>

          <a class="story-card" href="/noutati/detaliu" data-category="experiente" data-title="canopy run revine pe traseul complet" data-reveal><img src="https://images.unsplash.com/photo-1522163182402-834f871fd851?auto=format&fit=crop&w=1000&q=85" alt="Persoană într-un traseu de aventură"><div class="story-content"><span class="story-tag">Experiențe</span><div class="story-meta"><span>27 iulie 2026</span><span>4 min</span></div><h3>Canopy Run revine pe traseul complet</h3><p>Sectorul North Ridge se redeschide după inspecția tehnică de vară.</p></div></a>

          <a class="story-card text-card" href="/noutati/detaliu" data-category="comunitate" data-title="o zi cu junior rangerii" data-reveal><div><span class="story-tag">Comunitate</span><div class="story-meta"><span>24 iulie 2026</span><span>5 min</span></div><h3>O zi cu junior rangerii</h3><p>Douăzeci și patru de copii au învățat să citească urmele animalelor și să cartografieze o poiană.</p></div><div style="display:flex;align-items:center;gap:11px"><div style="display:flex"><img src="https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?auto=format&fit=crop&w=100&q=80" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:50%;border:3px solid var(--cream)"><img src="https://images.unsplash.com/photo-1497485692312-a26e1cc30f1d?auto=format&fit=crop&w=100&q=80" alt="" style="width:42px;height:42px;object-fit:cover;border-radius:50%;border:3px solid var(--cream);margin-left:-10px"></div><span style="font-size:11px;color:rgba(9,37,29,.48)">Galerie · 18 fotografii</span></div></a>

          <a class="story-card" href="/noutati/detaliu" data-category="teren" data-title="apariție rară în coridorul umed" data-reveal><img src="https://images.unsplash.com/photo-1473448912268-2022ce9509d8?auto=format&fit=crop&w=1200&q=85" alt="Pădure cu vegetație densă"><div class="story-content"><span class="story-tag">Din teren</span><div class="story-meta"><span>22 iulie 2026</span><span>7 min</span></div><h3>O apariție rară în coridorul umed</h3><p>Camerele de monitorizare au surprins o specie care nu mai fusese observată aici de trei sezoane.</p></div></a>

          <a class="story-card text-card" href="/noutati/detaliu" data-category="operational" data-title="creekline rămâne temporar închis" data-reveal><div><span class="story-tag">Siguranță</span><div class="story-meta"><span>21 iulie 2026</span><span>2 min</span></div><h3>Creekline rămâne temporar închis</h3><p>Solul este încă instabil după ploaie. Echipa verifică traseul zilnic, iar redeschiderea va fi anunțată aici.</p></div><div style="display:flex;align-items:center;gap:9px;font-size:11px;font-weight:700;color:var(--ember)"><span style="width:8px;height:8px;border-radius:50%;background:var(--ember)"></span> Actualizare operațională</div></a>

          <a class="story-card" href="/noutati/detaliu" data-category="experiente" data-title="primele seri ale turului nocturn" data-reveal><img src="https://images.unsplash.com/photo-1500534314209-a25ddb2bd429?auto=format&fit=crop&w=1200&q=85" alt="Pădure la apus"><div class="story-content"><span class="story-tag">Experiențe</span><div class="story-meta"><span>19 iulie 2026</span><span>4 min</span></div><h3>Primele seri ale turului nocturn</h3><p>Cum arată rezervația după ora închiderii și de ce păstrăm grupurile foarte mici.</p></div></a>
          
          <a class="story-card text-card dark-card extra-story" href="/noutati/detaliu" data-category="comunitate" data-title="atelierul de hărți pentru profesori" data-reveal hidden><div><span class="story-tag">Educație</span><div class="story-meta"><span>15 iulie 2026</span><span>5 min</span></div><h3>Atelierul de hărți pentru profesori</h3><p>Un nou kit educațional transformă rezervația într-un laborator de geografie vie.</p></div><div class="story-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 5.5 9 3l6 2.5L20 3v15.5L15 21l-6-2.5L4 21V5.5Z" stroke="currentColor" stroke-width="1.6"/><path d="M9 3v15.5M15 5.5V21" stroke="currentColor" stroke-width="1.6"/></svg></div></a>
          <a class="story-card extra-story" href="/noutati/detaliu" data-category="teren" data-title="jurnalul unei dimineți cu ceață" data-reveal hidden><img src="https://images.unsplash.com/photo-1487621167305-5d248087c724?auto=format&fit=crop&w=1200&q=85" alt="Pădure în ceață"><div class="story-content"><span class="story-tag">Jurnal de teren</span><div class="story-meta"><span>12 iulie 2026</span><span>6 min</span></div><h3>O dimineață cu ceață, notată minut cu minut</h3><p>Înainte de prima intrare a vizitatorilor, pădurea are propriul ei program.</p></div></a>
        </div>
        <div class="no-results" id="noResults"><strong class="display" style="font-size:28px">Nicio poveste nu corespunde filtrului.</strong><p class="muted">Încearcă altă categorie sau un termen de căutare mai scurt.</p></div>
        <div class="load-more"><button class="btn btn-outline" id="loadMore" type="button">Arată mai multe povești</button></div>
      </div>
    </section>

    <section class="dispatch topo-dark">
      <div class="wrap dispatch-grid">
        <div class="dispatch-lead" data-reveal><span class="eyebrow light">Dispeceratul parcului</span><h2 class="title-md" style="margin-top:14px">Actualizări care contează acum.</h2><p>Mesaje scurte, fără dramatizare. Tot ce îți poate afecta accesul, programul sau experiența în următoarele zile.</p><a class="btn btn-acid" href="/calendar" style="margin-top:18px">Vezi disponibilitatea</a></div>
        <div class="dispatch-list" data-reveal>
          <a class="dispatch-item" href="#"><span class="dispatch-date">29.07</span><span><h3>Shuttle suplimentar în weekend</h3><p>Plecări la fiecare 20 de minute între 09:00 și 12:00.</p></span><span class="dispatch-arrow">↗</span></a>
          <a class="dispatch-item" href="#"><span class="dispatch-date">28.07</span><span><h3>Creekline · acces suspendat</h3><p>Reevaluare programată joi la ora 08:00.</p></span><span class="dispatch-arrow">↗</span></a>
          <a class="dispatch-item" href="#"><span class="dispatch-date">27.07</span><span><h3>Canopy Run · program complet</h3><p>Toate cele patru sectoare sunt din nou disponibile.</p></span><span class="dispatch-arrow">↗</span></a>
          <a class="dispatch-item" href="#"><span class="dispatch-date">26.07</span><span><h3>Alertă temperatură ridicată</h3><p>Recomandăm rezervări înainte de ora 13:00.</p></span><span class="dispatch-arrow">↗</span></a>
        </div>
      </div>
    </section>

    <section class="field-feature">
      <div class="wrap field-grid">
        <div class="field-image" data-reveal><img src="https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=1500&q=85" alt="Ranger într-o pădure deasă"><div class="field-note"><small>Nota rangerului · 06:18</small><p>„Când pădurea tace complet, nu înseamnă că nu se întâmplă nimic.”</p></div></div>
        <div class="field-copy" data-reveal><span class="eyebrow">Jurnal de teren</span><h2 class="title-md" style="margin-top:14px">Ce observăm când parcul încă doarme.</h2><p>În fiecare dimineață, înainte de deschidere, rangerii parcurg aceleași patru puncte de observație. Nu caută spectacol. Caută schimbări mici.</p><div class="field-points"><div class="field-point"><span class="story-icon" style="width:44px;height:44px;border-radius:16px">01</span><div><strong>Sunetul</strong><span>Primele semnale vin de la păsări, nu de la camere.</span></div></div><div class="field-point"><span class="story-icon" style="width:44px;height:44px;border-radius:16px">02</span><div><strong>Urmele</strong><span>Un singur traseu nou poate schimba harta de monitorizare.</span></div></div><div class="field-point"><span class="story-icon" style="width:44px;height:44px;border-radius:16px">03</span><div><strong>Apa</strong><span>Nivelul pârâului decide uneori programul înaintea noastră.</span></div></div></div><a class="btn btn-dark" href="/noutati/detaliu" style="margin-top:26px">Citește jurnalul complet</a></div>
      </div>
    </section>

    <section class="newsletter" id="newsletter">
      <div class="wrap"><div class="newsletter-card grain" data-reveal><span class="newsletter-topo" aria-hidden="true"></span><div class="newsletter-grid"><div><span class="eyebrow light">Scrisori din pădure</span><h2 class="title-md" style="margin-top:12px">O dată pe lună.<br>Doar ce merită păstrat.</h2><p>Jurnal de teren, experiențe noi și actualizări utile. Fără reduceri inventate și fără emailuri zilnice.</p></div><form class="newsletter-form" id="newsletterForm"><input type="email" id="newsletterEmail" placeholder="adresa@email.ro" required><button class="btn btn-acid" type="submit">Abonează-mă</button><label class="newsletter-check"><input type="checkbox" required> <span>Sunt de acord să primesc jurnalul Nordvale și pot retrage acordul oricând.</span></label></form></div></div></div>
    </section>
  </main>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <div id="toast" style="position:fixed;left:50%;bottom:22px;z-index:190;transform:translate(-50%,130%);background:var(--pine);color:white;border:1px solid rgba(255,255,255,.12);border-radius:999px;padding:13px 18px;box-shadow:var(--shadow);font-size:12px;font-weight:700;transition:.35s ease;white-space:nowrap;max-width:calc(100% - 24px);overflow:hidden;text-overflow:ellipsis">Te-ai abonat la jurnalul Nordvale.</div>

  <script>
    (()=>{
      'use strict';
      const $=(s,p=document)=>p.querySelector(s), $$=(s,p=document)=>[...p.querySelectorAll(s)];
      const body=document.body, nav=$('#siteNav'), progress=$('#scrollProgress');
      const menu=$('#mobileMenu'), menuOpen=$('#menuOpen'), menuClose=$('#menuClose'), menuBackdrop=$('#menuBackdrop');
      function setMenu(open){menu?.classList.toggle('open',open);menu?.setAttribute('aria-hidden',String(!open));body.classList.toggle('menu-open',open)}
      menuOpen?.addEventListener('click',()=>setMenu(true));menuClose?.addEventListener('click',()=>setMenu(false));menuBackdrop?.addEventListener('click',()=>setMenu(false));
      window.addEventListener('keydown',e=>{if(e.key==='Escape')setMenu(false)});

      function onScroll(){const y=window.scrollY;nav?.classList.toggle('scrolled',y>24);const max=document.documentElement.scrollHeight-innerHeight;progress.style.transform=`scaleX(${max>0?Math.min(1,y/max):0})`}
      window.addEventListener('scroll',onScroll,{passive:true});onScroll();

      const reduce=matchMedia('(prefers-reduced-motion: reduce)').matches;
      if(!reduce && 'IntersectionObserver' in window){
        const io=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.animate([{opacity:0,transform:'translateY(30px)'},{opacity:1,transform:'none'}],{duration:720,easing:'cubic-bezier(.2,.75,.2,1)',fill:'forwards'});io.unobserve(entry.target)}}),{threshold:.12});
        $$('[data-reveal]').forEach(el=>io.observe(el));
      }else{$$('[data-reveal]').forEach(el=>{el.style.opacity=1;el.style.transform='none'})}

      const path=$('#heroPath'), marker=$('#heroMarker');
      if(path && marker && !reduce){
        const length=path.getTotalLength();path.style.strokeDasharray=`${length}`;path.style.strokeDashoffset=`${length}`;path.animate([{strokeDashoffset:length},{strokeDashoffset:0}],{duration:2100,easing:'cubic-bezier(.2,.75,.2,1)',fill:'forwards',delay:250});
        const start=performance.now()+250;
        function move(t){const p=Math.min(1,Math.max(0,(t-start)/2100));const pt=path.getPointAtLength(length*p);marker.setAttribute('cx',pt.x);marker.setAttribute('cy',pt.y);if(p<1)requestAnimationFrame(move)}requestAnimationFrame(move);
      }

      let active='all';const filters=$$('.filter'), stories=$$('.story-card'), search=$('#newsSearch'), noResults=$('#noResults'), loadMore=$('#loadMore');
      function filterStories(){const term=(search?.value||'').trim().toLowerCase();let visible=0;stories.forEach(card=>{const category=card.dataset.category||'',title=card.dataset.title||'',isExtra=card.classList.contains('extra-story'),loaded=loadMore?.dataset.loaded==='true';const matchCategory=active==='all'||category===active;const matchTerm=!term||title.includes(term)||card.textContent.toLowerCase().includes(term);const show=matchCategory&&matchTerm&&(!isExtra||loaded);card.hidden=!show;if(show)visible++});noResults?.classList.toggle('show',visible===0)}
      filters.forEach(btn=>btn.addEventListener('click',()=>{filters.forEach(b=>b.classList.remove('active'));btn.classList.add('active');active=btn.dataset.filter;filterStories()}));search?.addEventListener('input',filterStories);
      loadMore?.addEventListener('click',()=>{loadMore.dataset.loaded='true';loadMore.textContent='Toate poveștile sunt afișate';loadMore.disabled=true;filterStories()});filterStories();

      const toast=$('#toast');function showToast(text){toast.textContent=text;toast.style.transform='translate(-50%,0)';clearTimeout(showToast.timer);showToast.timer=setTimeout(()=>toast.style.transform='translate(-50%,130%)',3200)}
      $('#newsletterForm')?.addEventListener('submit',e=>{e.preventDefault();const email=$('#newsletterEmail');if(!email?.checkValidity()){email?.reportValidity();return}showToast('Te-ai abonat la jurnalul Nordvale.');e.currentTarget.reset()});
    })();
  </script>
</body>
</html>
