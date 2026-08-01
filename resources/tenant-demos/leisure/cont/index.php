<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#061a15">
  <title>Contul meu — Nordvale</title>
  <meta name="description" content="Dashboard client Nordvale pentru bilete, vizite, abonamente și notificări.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <style>
    :root {
      --pine-950:#061a15;
      --pine-900:#09251d;
      --pine-850:#0d3027;
      --pine-800:#123b30;
      --pine-700:#1b5242;
      --acid:#dffc62;
      --ember:#f27b4a;
      --cream:#fffdf6;
      --oat:#f0ecdf;
      --moss:#b7c9a3;
      --ink:#16231e;
      --line:rgba(9,37,29,.12);
      --shadow:0 28px 80px -48px rgba(6,26,21,.62);
      --card-shadow:0 20px 54px -38px rgba(6,26,21,.48);
      --radius-xl:30px;
      --radius-lg:24px;
      --radius-md:18px;
    }
    *{box-sizing:border-box}
    html{background:var(--oat);scroll-behavior:smooth}
    body{margin:0;overflow-x:hidden;background:var(--oat);color:var(--ink);font-family:"DM Sans",sans-serif;text-rendering:optimizeLegibility}
    button,input{font:inherit}
    button,a{touch-action:manipulation}
    a{color:inherit;text-decoration:none}
    img{display:block;max-width:100%}
    [hidden]{display:none!important}
    ::selection{background:var(--acid);color:var(--pine-950)}
    .shell{width:min(1460px,calc(100% - 32px));margin-inline:auto}
    .grain{position:relative}
    .grain:after{content:"";position:absolute;inset:0;pointer-events:none;z-index:2;opacity:.07;mix-blend-mode:soft-light;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.82' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noise)' opacity='.56'/%3E%3C/svg%3E")}
    .topo{background-image:radial-gradient(circle at 14% 16%,rgba(223,252,98,.14),transparent 20%),radial-gradient(circle at 88% 20%,rgba(242,123,74,.13),transparent 16%),url("data:image/svg+xml,%3Csvg width='760' height='760' viewBox='0 0 760 760' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' stroke='%23fff' stroke-opacity='.06' stroke-width='1.05'%3E%3Cpath d='M74 206c43-106 166-167 278-119 89 39 107 147 52 218-51 66-143 59-194 124-58 73-49 188-142 228-91 38-186-37-164-132 20-89 111-106 122-190 8-57-5-88 48-129Z'/%3E%3Cpath d='M112 231c34-81 128-126 214-90 69 29 82 113 41 167-39 50-110 45-150 95-44 55-38 144-109 174-70 29-143-29-126-102 15-68 85-81 94-146 6-43-4-67 36-98Z'/%3E%3Cpath d='M493 410c57-79 161-89 227-18 60 64 24 169-55 198-71 26-142-17-201 28-52 40-62 123-129 129-74 6-126-73-84-135 38-56 112-39 155-92 35-43 39-79 87-110Z'/%3E%3C/g%3E%3C/svg%3E");background-size:auto,auto,760px 760px}

    .site-header{position:sticky;top:0;z-index:50;padding:12px 0;background:linear-gradient(180deg,rgba(240,236,223,.94),rgba(240,236,223,.76),transparent)}
    .nav{display:flex;align-items:center;gap:16px;padding:10px 12px 10px 16px;border:1px solid rgba(255,255,255,.12);border-radius:22px;background:rgba(6,26,21,.87);box-shadow:0 24px 70px -46px rgba(0,0,0,.9);backdrop-filter:blur(22px) saturate(130%);color:#fff}
    .brand{display:flex;align-items:center;gap:11px;min-width:0}
    .brand-mark{display:grid;place-items:center;width:42px;height:42px;flex:0 0 auto;border:1px solid rgba(223,252,98,.42);border-radius:15px;background:rgba(223,252,98,.08);color:var(--acid)}
    .brand-copy{min-width:0;line-height:1}
    .brand-copy strong{display:block;font-family:"Fraunces",serif;font-size:18px;font-weight:600;letter-spacing:-.02em}
    .brand-copy span{display:block;margin-top:5px;color:rgba(255,255,255,.5);font-size:10px;font-weight:700;letter-spacing:.18em;text-transform:uppercase;white-space:nowrap}
    .main-nav{display:flex;align-items:center;gap:6px;margin-left:auto}
    .main-nav a{padding:10px 12px;border-radius:12px;color:rgba(255,255,255,.68);font-size:13px;font-weight:600;transition:.2s ease}
    .main-nav a:hover{background:rgba(255,255,255,.07);color:#fff}
    .nav-actions{display:flex;align-items:center;gap:8px}
    .nav-icon,.menu-btn{display:grid;place-items:center;width:40px;height:40px;border:0;border-radius:13px;background:rgba(255,255,255,.07);color:#fff;cursor:pointer}
    .nav-icon{position:relative}
    .nav-icon:after{content:"";position:absolute;top:8px;right:8px;width:7px;height:7px;border:2px solid var(--pine-900);border-radius:50%;background:var(--ember)}
    .ticket-cta{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 16px;border-radius:13px;background:var(--acid);color:var(--pine-950);font-size:13px;font-weight:800;white-space:nowrap;box-shadow:0 15px 38px -24px rgba(223,252,98,.8)}
    .menu-btn{display:none}

    .layout{display:grid;grid-template-columns:250px minmax(0,1fr);gap:24px;padding:18px 0 72px}
    .sidebar{position:sticky;top:92px;align-self:start;padding:18px;border:1px solid var(--line);border-radius:26px;background:rgba(255,253,246,.78);box-shadow:var(--card-shadow);backdrop-filter:blur(18px)}
    .profile-mini{display:flex;align-items:center;gap:12px;padding:8px 8px 20px;border-bottom:1px solid var(--line)}
    .avatar{display:grid;place-items:center;width:50px;height:50px;border-radius:18px;background:var(--pine-900);color:var(--acid);font-family:"Fraunces",serif;font-size:18px;font-weight:700}
    .profile-mini strong{display:block;font-size:14px}
    .profile-mini span{display:block;margin-top:4px;color:rgba(22,35,30,.54);font-size:11px}
    .side-nav{display:grid;gap:6px;padding:16px 0}
    .side-nav a{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:14px;color:rgba(22,35,30,.64);font-size:13px;font-weight:650;transition:.2s ease}
    .side-nav a:hover{background:rgba(9,37,29,.06);color:var(--pine-900)}
    .side-nav a.active{background:var(--pine-900);color:#fff;box-shadow:0 14px 28px -22px rgba(6,26,21,.9)}
    .side-nav svg{width:18px;height:18px;flex:0 0 auto}
    .side-help{padding:15px;border-radius:18px;background:var(--oat)}
    .side-help strong{display:block;font-family:"Fraunces",serif;font-size:17px}
    .side-help p{margin:7px 0 12px;color:rgba(22,35,30,.6);font-size:11px;line-height:1.55}
    .side-help a{display:inline-flex;align-items:center;gap:6px;color:var(--pine-900);font-size:12px;font-weight:800}

    .content{min-width:0}
    .welcome{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.08fr) minmax(340px,.92fr);min-height:360px;border-radius:34px;background:var(--pine-900);color:#fff;box-shadow:var(--shadow)}
    .welcome-copy{position:relative;z-index:4;display:flex;flex-direction:column;justify-content:center;padding:42px 44px}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;width:max-content;padding:7px 10px;border:1px solid rgba(223,252,98,.22);border-radius:999px;background:rgba(223,252,98,.08);color:var(--acid);font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
    .eyebrow-dot{width:7px;height:7px;border-radius:50%;background:var(--acid);box-shadow:0 0 0 7px rgba(223,252,98,.1)}
    .welcome h1{max-width:710px;margin:22px 0 14px;font-family:"Fraunces",serif;font-size:clamp(42px,5vw,74px);font-weight:600;letter-spacing:-.055em;line-height:.97}
    .welcome h1 em{color:var(--acid);font-style:italic}
    .welcome-copy>p{max-width:610px;margin:0;color:rgba(255,255,255,.66);font-size:15px;line-height:1.7}
    .hero-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:26px}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 17px;border:0;border-radius:14px;cursor:pointer;font-size:13px;font-weight:800;transition:.22s ease}
    .btn-primary{background:var(--acid);color:var(--pine-950);box-shadow:0 18px 44px -28px rgba(223,252,98,.86)}
    .btn-primary:hover{transform:translateY(-2px)}
    .btn-ghost{border:1px solid rgba(255,255,255,.17);background:rgba(255,255,255,.07);color:#fff}
    .welcome-visual{position:relative;min-height:360px}
    .welcome-image{position:absolute;inset:0;background:linear-gradient(90deg,var(--pine-900) 0%,rgba(9,37,29,.26) 30%,rgba(9,37,29,.1)),url("https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1600&q=86") center/cover}
    .welcome-image:after{content:"";position:absolute;inset:0;background:linear-gradient(0deg,rgba(6,26,21,.72),transparent 60%)}
    .route-svg{position:absolute;inset:7% 6% 9% 0;width:94%;height:84%;z-index:2;filter:drop-shadow(0 0 9px rgba(223,252,98,.28))}
    .route-svg path{fill:none;stroke:var(--acid);stroke-width:2.4;stroke-linecap:round;stroke-dasharray:10 12}
    .next-chip{position:absolute;right:22px;bottom:22px;z-index:5;width:min(300px,calc(100% - 44px));padding:17px;border:1px solid rgba(255,255,255,.17);border-radius:20px;background:rgba(6,26,21,.73);backdrop-filter:blur(18px)}
    .next-chip-top{display:flex;justify-content:space-between;gap:12px;align-items:flex-start}
    .next-chip small{display:block;color:rgba(255,255,255,.48);font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
    .next-chip strong{display:block;margin-top:5px;font-family:"Fraunces",serif;font-size:18px}
    .next-chip time{color:var(--acid);font-size:12px;font-weight:800;white-space:nowrap}
    .next-chip-meta{display:flex;gap:12px;margin-top:12px;padding-top:12px;border-top:1px solid rgba(255,255,255,.12);color:rgba(255,255,255,.64);font-size:10px}

    .stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-top:18px}
    .stat{padding:18px;border:1px solid var(--line);border-radius:20px;background:rgba(255,253,246,.76);box-shadow:var(--card-shadow)}
    .stat-top{display:flex;align-items:center;justify-content:space-between;gap:10px}
    .stat-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:13px;background:rgba(9,37,29,.07);color:var(--pine-900)}
    .stat-badge{padding:5px 8px;border-radius:999px;background:rgba(223,252,98,.58);font-size:9px;font-weight:800}
    .stat strong{display:block;margin-top:16px;font-family:"Fraunces",serif;font-size:27px;line-height:1}
    .stat span{display:block;margin-top:6px;color:rgba(22,35,30,.56);font-size:10px}

    .dashboard-grid{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr);gap:18px;margin-top:18px}
    .stack{display:grid;gap:18px}
    .card{position:relative;overflow:hidden;padding:24px;border:1px solid var(--line);border-radius:26px;background:rgba(255,253,246,.8);box-shadow:var(--card-shadow)}
    .card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:18px;margin-bottom:20px}
    .card-kicker{margin:0 0 6px;color:var(--pine-700);font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
    .card h2{margin:0;font-family:"Fraunces",serif;font-size:25px;font-weight:600;letter-spacing:-.035em}
    .link-arrow{display:inline-flex;align-items:center;gap:7px;color:var(--pine-900);font-size:11px;font-weight:800;white-space:nowrap}

    .visit-card{padding:0}
    .visit-cover{position:relative;height:190px;background:linear-gradient(180deg,rgba(6,26,21,.04),rgba(6,26,21,.72)),url("https://images.unsplash.com/photo-1500534314209-a25ddb2bd4297?auto=format&fit=crop&w=1500&q=84") center 57%/cover}
    .visit-cover-content{position:absolute;inset:auto 22px 20px;color:#fff}
    .visit-cover-content small{font-size:9px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--acid)}
    .visit-cover-content h2{margin:6px 0 0;font-size:29px}
    .visit-body{display:grid;grid-template-columns:1fr auto;gap:18px;padding:22px}
    .visit-meta{display:flex;flex-wrap:wrap;gap:9px}
    .meta-pill{display:inline-flex;align-items:center;gap:7px;padding:8px 10px;border-radius:12px;background:var(--oat);font-size:10px;font-weight:700}
    .visit-timeline{display:grid;gap:0;margin-top:20px}
    .timeline-item{position:relative;display:grid;grid-template-columns:58px 16px 1fr;gap:9px;align-items:start;min-height:58px}
    .timeline-item:not(:last-child):after{content:"";position:absolute;left:73px;top:18px;bottom:-2px;width:1px;background:rgba(9,37,29,.16)}
    .timeline-time{padding-top:2px;font-size:10px;font-weight:800;color:rgba(22,35,30,.52)}
    .timeline-dot{position:relative;z-index:2;width:11px;height:11px;margin-top:2px;border:3px solid var(--cream);border-radius:50%;background:var(--pine-700);box-shadow:0 0 0 1px rgba(9,37,29,.18)}
    .timeline-copy strong{display:block;font-size:12px}
    .timeline-copy span{display:block;margin-top:4px;color:rgba(22,35,30,.54);font-size:10px}
    .qr-button{align-self:start;display:grid;place-items:center;gap:8px;width:104px;min-height:104px;border:1px dashed rgba(9,37,29,.22);border-radius:18px;background:var(--oat);cursor:pointer;color:var(--pine-900);font-size:9px;font-weight:800}
    .qr-mini{width:58px;height:58px;background:repeating-conic-gradient(#09251d 0 25%,transparent 0 50%) 0 0/12px 12px,linear-gradient(90deg,#09251d 18%,transparent 18% 35%,#09251d 35% 53%,transparent 53% 70%,#09251d 70%);border:7px solid #fff;box-shadow:0 0 0 1px rgba(9,37,29,.1)}

    .quick-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px}
    .quick-action{display:flex;flex-direction:column;gap:15px;min-height:126px;padding:16px;border:1px solid var(--line);border-radius:19px;background:var(--cream);transition:.22s ease}
    .quick-action:hover{transform:translateY(-3px);border-color:rgba(9,37,29,.24);box-shadow:var(--card-shadow)}
    .quick-action-icon{display:grid;place-items:center;width:38px;height:38px;border-radius:13px;background:var(--pine-900);color:var(--acid)}
    .quick-action strong{font-size:12px}
    .quick-action span{color:rgba(22,35,30,.5);font-size:9px;line-height:1.45}

    .orders{display:grid;gap:10px}
    .order{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:12px;align-items:center;padding:12px;border-radius:17px;background:var(--oat)}
    .order-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:14px;background:var(--cream);color:var(--pine-900)}
    .order strong{display:block;font-size:11px}
    .order span{display:block;margin-top:4px;color:rgba(22,35,30,.52);font-size:9px}
    .order-price{text-align:right;font-size:11px;font-weight:800}
    .order-price small{display:block;margin-top:5px;color:var(--pine-700);font-size:8px;text-transform:uppercase}

    .membership{position:relative;overflow:hidden;background:var(--pine-900);color:#fff}
    .membership:before{content:"";position:absolute;right:-90px;top:-110px;width:270px;height:270px;border:1px solid rgba(223,252,98,.14);border-radius:50%;box-shadow:0 0 0 28px rgba(223,252,98,.04),0 0 0 56px rgba(223,252,98,.025)}
    .membership-card{position:relative;z-index:2;margin-top:18px;padding:20px;border:1px solid rgba(255,255,255,.15);border-radius:23px;background:linear-gradient(135deg,rgba(255,255,255,.1),rgba(255,255,255,.035));backdrop-filter:blur(12px)}
    .membership-row{display:flex;justify-content:space-between;gap:14px;align-items:flex-start}
    .membership-row small{display:block;color:rgba(255,255,255,.5);font-size:8px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
    .membership-row strong{display:block;margin-top:5px;font-family:"Fraunces",serif;font-size:20px}
    .membership-badge{padding:7px 9px;border-radius:999px;background:var(--acid);color:var(--pine-950);font-size:8px;font-weight:900}
    .membership-progress{margin-top:20px}
    .membership-progress-head{display:flex;justify-content:space-between;gap:12px;color:rgba(255,255,255,.64);font-size:9px}
    .progress{height:7px;margin-top:8px;overflow:hidden;border-radius:999px;background:rgba(255,255,255,.1)}
    .progress span{display:block;width:68%;height:100%;border-radius:inherit;background:linear-gradient(90deg,var(--acid),#f3ffae)}
    .membership-footer{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:18px;padding-top:16px;border-top:1px solid rgba(255,255,255,.1)}
    .membership-footer span{color:rgba(255,255,255,.52);font-size:9px}
    .membership-footer a{color:var(--acid);font-size:10px;font-weight:800}

    .notice-list{display:grid;gap:8px}
    .notice{position:relative;display:grid;grid-template-columns:auto 1fr auto;gap:11px;align-items:start;padding:12px;border-radius:16px;background:var(--oat);transition:.2s ease}
    .notice.unread{background:rgba(223,252,98,.2)}
    .notice-icon{display:grid;place-items:center;width:36px;height:36px;border-radius:12px;background:var(--cream);color:var(--pine-900)}
    .notice strong{display:block;font-size:10px}
    .notice p{margin:4px 0 0;color:rgba(22,35,30,.54);font-size:9px;line-height:1.45}
    .notice time{color:rgba(22,35,30,.42);font-size:8px;white-space:nowrap}
    .notice button{position:absolute;inset:0;border:0;background:transparent;cursor:pointer}

    .weather-card{display:grid;grid-template-columns:1fr auto;gap:16px;align-items:end;background:linear-gradient(135deg,#b8dce0,#d8eeee 58%,#fffdf6)}
    .weather-card strong{display:block;font-family:"Fraunces",serif;font-size:42px;line-height:1}
    .weather-card p{margin:9px 0 0;color:rgba(22,35,30,.57);font-size:10px;line-height:1.55}
    .weather-icon{display:grid;place-items:center;width:74px;height:74px;border-radius:24px;background:rgba(255,255,255,.48);color:var(--pine-900)}

    .modal{position:fixed;inset:0;z-index:100;display:grid;place-items:center;padding:20px;background:rgba(6,26,21,.66);backdrop-filter:blur(12px);opacity:0;visibility:hidden;transition:.25s ease}
    .modal.open{opacity:1;visibility:visible}
    .modal-card{position:relative;width:min(440px,100%);padding:26px;border-radius:28px;background:var(--cream);box-shadow:0 40px 100px -30px rgba(0,0,0,.65);transform:translateY(14px) scale(.98);transition:.25s ease}
    .modal.open .modal-card{transform:none}
    .modal-close{position:absolute;right:14px;top:14px;display:grid;place-items:center;width:38px;height:38px;border:0;border-radius:13px;background:var(--oat);cursor:pointer}
    .modal-kicker{color:var(--pine-700);font-size:9px;font-weight:900;letter-spacing:.14em;text-transform:uppercase}
    .modal h3{margin:8px 0 7px;font-family:"Fraunces",serif;font-size:28px}
    .modal p{margin:0;color:rgba(22,35,30,.58);font-size:11px;line-height:1.6}
    .ticket-preview{margin-top:20px;padding:19px;border-radius:22px;background:var(--pine-900);color:#fff}
    .ticket-preview-top{display:flex;justify-content:space-between;gap:12px}
    .ticket-preview small{display:block;color:rgba(255,255,255,.48);font-size:8px;letter-spacing:.14em;text-transform:uppercase}
    .ticket-preview strong{display:block;margin-top:5px;font-family:"Fraunces",serif;font-size:19px}
    .qr-large{width:154px;height:154px;margin:22px auto;background:repeating-conic-gradient(#09251d 0 25%,#fff 0 50%) 0 0/14px 14px,linear-gradient(#fff,#fff);border:14px solid #fff;box-shadow:0 0 0 1px rgba(255,255,255,.15),0 18px 40px -25px rgba(0,0,0,.7)}
    .ticket-code{text-align:center;color:var(--acid);font-size:10px;font-weight:900;letter-spacing:.18em}
    .modal-actions{display:grid;grid-template-columns:1fr 1fr;gap:9px;margin-top:14px}

    .mobile-nav{display:none}
    .mobile-menu{position:fixed;inset:0;z-index:90;padding:16px;background:var(--pine-950);color:#fff;transform:translateX(100%);transition:.3s cubic-bezier(.22,.8,.2,1)}
    .mobile-menu.open{transform:none}
    .mobile-menu-head{display:flex;align-items:center;justify-content:space-between}
    .mobile-menu-links{display:grid;gap:8px;margin-top:34px}
    .mobile-menu-links a{padding:16px;border-bottom:1px solid rgba(255,255,255,.1);font-family:"Fraunces",serif;font-size:27px}

    @media (max-width:1180px){
      .layout{grid-template-columns:220px minmax(0,1fr)}
      .welcome{grid-template-columns:1fr minmax(300px,.78fr)}
      .dashboard-grid{grid-template-columns:minmax(0,1fr) 310px}
      .quick-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:980px){
      .main-nav{display:none}.menu-btn{display:grid}
      .layout{grid-template-columns:1fr;padding-top:10px}.sidebar{display:none}
      .welcome{grid-template-columns:1fr}.welcome-copy{padding-bottom:24px}.welcome-visual{min-height:300px}
      .stats{grid-template-columns:repeat(2,minmax(0,1fr))}
      .dashboard-grid{grid-template-columns:1fr}
      .stack.side-stack{grid-template-columns:repeat(2,minmax(0,1fr));align-items:start}
    }
    @media (max-width:640px){
      body{padding-bottom:82px}
      .shell{width:min(100% - 20px,1460px)}
      .site-header{padding:8px 0}
      .nav{padding:8px 8px 8px 10px;border-radius:17px;gap:8px}
      .brand-mark{width:38px;height:38px;border-radius:13px}
      .brand-copy strong{font-size:16px}.brand-copy span{display:none}
      .nav-icon{display:none}.ticket-cta{min-height:38px;padding:0 12px;font-size:12px}.menu-btn{width:38px;height:38px}
      .layout{padding-bottom:22px}
      .welcome{min-height:0;border-radius:25px}
      .welcome-copy{padding:30px 22px 22px}
      .welcome h1{font-size:41px}
      .welcome-copy>p{font-size:13px}
      .hero-actions{display:grid;grid-template-columns:1fr 1fr;width:100%}
      .btn{padding-inline:12px;font-size:11px}
      .welcome-visual{min-height:255px}
      .route-svg{inset:3% 0 8%;width:100%;height:88%}
      .next-chip{right:14px;bottom:14px;width:calc(100% - 28px);padding:14px;border-radius:17px}
      .next-chip strong{font-size:16px}.next-chip-meta{gap:8px;font-size:9px}
      .stats{gap:8px;margin-top:10px}.stat{padding:14px;border-radius:17px}.stat strong{font-size:23px}.stat-icon{width:34px;height:34px}
      .dashboard-grid,.stack{gap:10px;margin-top:10px}
      .card{padding:18px;border-radius:22px}.card h2{font-size:22px}.card-head{margin-bottom:16px}
      .visit-cover{height:165px}.visit-cover-content{left:17px;bottom:16px}.visit-cover-content h2{font-size:25px}
      .visit-body{grid-template-columns:1fr;padding:17px}.qr-button{width:100%;min-height:66px;display:flex;flex-direction:row}.qr-mini{width:44px;height:44px;border-width:5px}
      .quick-grid{grid-template-columns:1fr 1fr;gap:8px}.quick-action{min-height:112px;padding:14px}.quick-action span{display:none}
      .stack.side-stack{grid-template-columns:1fr}
      .order{grid-template-columns:auto 1fr}.order-price{grid-column:2;text-align:left}.order-price small{display:inline;margin-left:7px}
      .modal{padding:12px}.modal-card{padding:22px 17px;border-radius:24px}.qr-large{width:138px;height:138px}.modal-actions{grid-template-columns:1fr}
      .mobile-nav{position:fixed;left:10px;right:10px;bottom:max(10px,env(safe-area-inset-bottom));z-index:60;display:grid;grid-template-columns:repeat(4,1fr);padding:7px;border:1px solid rgba(255,255,255,.12);border-radius:20px;background:rgba(6,26,21,.91);backdrop-filter:blur(20px);box-shadow:0 24px 60px -24px rgba(0,0,0,.8)}
      .mobile-nav a{display:grid;place-items:center;gap:4px;min-height:52px;border-radius:14px;color:rgba(255,255,255,.54);font-size:8px;font-weight:800}
      .mobile-nav a.active{background:rgba(223,252,98,.12);color:var(--acid)}
      .mobile-nav svg{width:19px;height:19px}
    }
    @media (max-width:390px){
      .brand-copy strong{font-size:15px}.ticket-cta{padding:0 10px;font-size:11px}
      .hero-actions{grid-template-columns:1fr}
      .stats{grid-template-columns:1fr 1fr}
      .stat-badge{display:none}
      .quick-grid{grid-template-columns:1fr 1fr}
      .quick-action{min-height:102px}
      .weather-card{grid-template-columns:1fr}.weather-icon{width:58px;height:58px}
    }
    @media (prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;animation:none!important;transition-duration:.01ms!important}}
  </style>
</head>
<body>
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <main class="shell layout">
    <aside class="sidebar" aria-label="Navigație cont">
      <div class="profile-mini">
        <span class="avatar">AP</span>
        <span><strong>Andrei Popescu</strong><span>andrei.popescu@email.ro</span></span>
      </div>
      <nav class="side-nav">
        <a class="active" href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>Panou principal</a>
        <a href="/cont/bilete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/><path d="M9 7v12"/></svg>Biletele mele</a>
        <a href="/cont/comenzi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>Comenzi</a>
        <a href="/cont/abonamente"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16v12H4z"/><path d="M8 10h8M8 14h4"/></svg>Abonamente</a>
        <a href="/cont/favorite"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20s-7-4.2-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.8-7 10-7 10Z"/></svg>Lista mea</a>
        <a href="/cont/profil"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.2-4 4-6 8-6s6.8 2 8 6"/></svg>Profil și setări</a>
      </nav>
      <div class="side-help"><strong>Ai nevoie de ajutor?</strong><p>Echipa Nordvale poate verifica o comandă sau o rezervare direct din cont.</p><a href="/contact">Deschide suportul <span>→</span></a></div>
    </aside>

    <section class="content">
      <section class="welcome grain topo" aria-labelledby="welcomeTitle">
        <div class="welcome-copy">
          <span class="eyebrow"><span class="eyebrow-dot"></span><span id="greeting">Bun venit înapoi</span></span>
          <h1 id="welcomeTitle">Următoarea ta <em>expediție</em> începe aici.</h1>
          <p>Ai o vizită rezervată, două experiențe active și un abonament care îți deschide noi trasee în acest sezon.</p>
          <div class="hero-actions">
            <button class="btn btn-primary" id="heroQrButton" type="button">Arată biletele</button>
            <a class="btn btn-ghost" href="/planifica">Pregătește vizita</a>
          </div>
        </div>
        <div class="welcome-visual" aria-hidden="true">
          <div class="welcome-image"></div>
          <svg class="route-svg" viewBox="0 0 520 360"><path id="heroRoute" d="M22 305C88 280 92 195 164 183s98 62 171 17 51-112 116-147"/></svg>
          <div class="next-chip">
            <div class="next-chip-top"><span><small>Următoarea vizită</small><strong>Canopy Run Day</strong></span><time datetime="2026-08-08">08 aug</time></div>
            <div class="next-chip-meta"><span>10:30</span><span>4 participanți</span><span>Poarta Nord</span></div>
          </div>
        </div>
      </section>

      <section class="stats" aria-label="Rezumat cont">
        <article class="stat"><div class="stat-top"><span class="stat-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/></svg></span><span class="stat-badge">Active</span></div><strong>4</strong><span>Bilete disponibile</span></article>
        <article class="stat"><div class="stat-top"><span class="stat-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s-7-4.2-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.8-7 10-7 10Z"/></svg></span></div><strong>7</strong><span>Experiențe salvate</span></article>
        <article class="stat"><div class="stat-top"><span class="stat-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16v12H4z"/><path d="M8 10h8M8 14h4"/></svg></span><span class="stat-badge">Wild Circle</span></div><strong>68%</strong><span>Beneficii utilizate</span></article>
        <article class="stat"><div class="stat-top"><span class="stat-icon"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18"/></svg></span></div><strong>240</strong><span>Puncte de aventură</span></article>
      </section>

      <section class="dashboard-grid">
        <div class="stack">
          <article class="card visit-card">
            <div class="visit-cover">
              <div class="visit-cover-content"><small>Rezervare #NV-260808-1842</small><h2>Canopy Run Day</h2></div>
            </div>
            <div class="visit-body">
              <div>
                <div class="visit-meta">
                  <span class="meta-pill"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3v3M19 3v3M4 8h16v13H4z"/></svg>8 august 2026</span>
                  <span class="meta-pill"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>10:30</span>
                  <span class="meta-pill"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 20V8l8-5 8 5v12"/><path d="M9 20v-6h6v6"/></svg>Poarta Nord</span>
                </div>
                <div class="visit-timeline">
                  <div class="timeline-item"><span class="timeline-time">10:00</span><span class="timeline-dot"></span><span class="timeline-copy"><strong>Check-in și echipare</strong><span>Ajungi cu 30 de minute înainte.</span></span></div>
                  <div class="timeline-item"><span class="timeline-time">10:30</span><span class="timeline-dot"></span><span class="timeline-copy"><strong>Canopy Run</strong><span>Traseu ghidat, aproximativ 90 de minute.</span></span></div>
                  <div class="timeline-item"><span class="timeline-time">12:30</span><span class="timeline-dot"></span><span class="timeline-copy"><strong>Forest Table</strong><span>Prânz inclus în pachetul Family Trail.</span></span></div>
                </div>
              </div>
              <button class="qr-button" id="visitQrButton" type="button"><span class="qr-mini" aria-hidden="true"></span><span>Deschide QR</span></button>
            </div>
          </article>

          <article class="card">
            <div class="card-head"><span><p class="card-kicker">Acțiuni rapide</p><h2>Tot ce ai nevoie, într-un singur loc.</h2></span></div>
            <div class="quick-grid">
              <a class="quick-action" href="/cont/bilete"><span class="quick-action-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/></svg></span><span><strong>Biletele mele</strong><span>QR, Wallet și distribuire.</span></span></a>
              <a class="quick-action" href="/calendar"><span class="quick-action-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3v3M19 3v3M4 8h16v13H4z"/></svg></span><span><strong>Rezervă o dată</strong><span>Vezi disponibilitatea live.</span></span></a>
              <a class="quick-action" href="/cont/favorite"><span class="quick-action-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20s-7-4.2-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.8-7 10-7 10Z"/></svg></span><span><strong>Lista mea</strong><span>7 aventuri salvate.</span></span></a>
              <a class="quick-action" href="/cont/suport"><span class="quick-action-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v12H7l-3 3z"/></svg></span><span><strong>Ajutor rapid</strong><span>Trimite o solicitare.</span></span></a>
            </div>
          </article>

          <article class="card">
            <div class="card-head"><span><p class="card-kicker">Activitate recentă</p><h2>Ultimele comenzi</h2></span><a class="link-arrow" href="/cont/comenzi">Vezi toate <span>→</span></a></div>
            <div class="orders">
              <a class="order" href="/cont/comanda"><span class="order-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/></svg></span><span><strong>Family Trail + Canopy Run</strong><span>Comanda #NV-1842 · 4 bilete</span></span><span class="order-price">596 lei<small>Plătită</small></span></a>
              <a class="order" href="/cont/comanda"><span class="order-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16v12H4z"/></svg></span><span><strong>Wild Circle Membership</strong><span>Comanda #NV-1694 · 1 abonament</span></span><span class="order-price">690 lei<small>Activ</small></span></a>
              <a class="order" href="/cont/comanda"><span class="order-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18"/></svg></span><span><strong>Voucher cadou Nordvale</strong><span>Comanda #NV-1518 · trimis pe email</span></span><span class="order-price">300 lei<small>Livrat</small></span></a>
            </div>
          </article>
        </div>

        <aside class="stack side-stack">
          <article class="card membership topo">
            <div class="card-head"><span><p class="card-kicker" style="color:var(--acid)">Membership</p><h2>Wild Circle</h2></span><a class="link-arrow" href="/cont/abonamente" style="color:var(--acid)">Detalii →</a></div>
            <div class="membership-card">
              <div class="membership-row"><span><small>Membru</small><strong>Andrei Popescu</strong></span><span class="membership-badge">Activ</span></div>
              <div class="membership-progress"><div class="membership-progress-head"><span>Beneficii utilizate</span><strong>68%</strong></div><div class="progress"><span></span></div></div>
              <div class="membership-footer"><span>Valabil până la 31 mai 2027</span><a href="/cont/abonamente">Card digital</a></div>
            </div>
          </article>

          <article class="card">
            <div class="card-head"><span><p class="card-kicker">Mesaje</p><h2>Notificări</h2></span><button class="link-arrow" id="markAllButton" type="button" style="border:0;background:transparent;cursor:pointer">Marchează citite</button></div>
            <div class="notice-list" id="noticeList">
              <article class="notice unread"><span class="notice-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3v3M19 3v3M4 8h16v13H4z"/></svg></span><span><strong>Vizita se apropie</strong><p>Mai sunt 10 zile până la Canopy Run Day.</p></span><time>Acum</time><button type="button" aria-label="Marchează notificarea ca citită"></button></article>
              <article class="notice unread"><span class="notice-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18"/></svg></span><span><strong>240 de puncte disponibile</strong><p>Le poți folosi la o experiență ghidată.</p></span><time>Ieri</time><button type="button" aria-label="Marchează notificarea ca citită"></button></article>
              <article class="notice"><span class="notice-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v12H7l-3 3z"/></svg></span><span><strong>Program extins în august</strong><p>Parcul rămâne deschis până la 20:30.</p></span><time>26 iul</time><button type="button" aria-label="Deschide notificarea"></button></article>
            </div>
          </article>

          <article class="card weather-card">
            <span><p class="card-kicker">Sâmbătă, 8 august</p><strong>22°C</strong><p>Vreme bună pentru trasee. Posibilă ploaie scurtă după ora 17:00.</p></span>
            <span class="weather-icon"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="9" cy="9" r="4"/><path d="M9 2v2M9 14v2M2 9h2M14 9h2M4 4l1.4 1.4M12.6 12.6 14 14"/><path d="M9 19h8a4 4 0 0 0 0-8 5 5 0 0 0-9.7 1.6A3.5 3.5 0 0 0 9 19Z"/></svg></span>
          </article>
        </aside>
      </section>
    </section>
  </main>

  <nav class="mobile-nav" aria-label="Navigație cont mobil">
    <a class="active" href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>Acasă</a>
    <a href="/cont/bilete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/></svg>Bilete</a>
    <a href="/cont/comenzi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>Comenzi</a>
    <a href="/cont/profil"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.2-4 4-6 8-6s6.8 2 8 6"/></svg>Profil</a>
  </nav>

  <div class="modal" id="ticketModal" aria-hidden="true">
    <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="ticketModalTitle">
      <button class="modal-close" id="modalClose" type="button" aria-label="Închide"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
      <span class="modal-kicker">Acces digital</span><h3 id="ticketModalTitle">Biletele tale</h3><p>Prezintă acest cod la poarta Nord. Luminozitatea ecranului va fi mărită automat în aplicația mobilă.</p>
      <div class="ticket-preview"><div class="ticket-preview-top"><span><small>Canopy Run Day</small><strong>4 participanți</strong></span><span><small>Data</small><strong>08.08.2026</strong></span></div><div class="qr-large" aria-label="Cod QR demonstrativ"></div><div class="ticket-code">NV-260808-1842</div></div>
      <div class="modal-actions"><button class="btn btn-primary" id="downloadButton" type="button">Descarcă biletele</button><button class="btn" id="walletButton" type="button" style="background:var(--oat);color:var(--pine-900)">Adaugă în Wallet</button></div>
    </div>
  </div>

  <script>
    (()=>{
      'use strict';
      const $=(s,c=document)=>c.querySelector(s); const $$=(s,c=document)=>[...c.querySelectorAll(s)];
      const body=document.body,menu=$('#mobileMenu'),modal=$('#ticketModal');
      const openMenu=()=>{if(!menu)return;menu.classList.add('open');menu.setAttribute('aria-hidden','false');body.style.overflow='hidden'};
      const closeMenu=()=>{if(!menu)return;menu.classList.remove('open');menu.setAttribute('aria-hidden','true');body.style.overflow=''};
      $('#menuOpen')?.addEventListener('click',openMenu); $('#menuClose')?.addEventListener('click',closeMenu);
      const openModal=()=>{if(!modal)return;modal.classList.add('open');modal.setAttribute('aria-hidden','false');body.style.overflow='hidden';$('#modalClose')?.focus()};
      const closeModal=()=>{if(!modal)return;modal.classList.remove('open');modal.setAttribute('aria-hidden','true');body.style.overflow=''};
      $('#heroQrButton')?.addEventListener('click',openModal); $('#visitQrButton')?.addEventListener('click',openModal); $('#modalClose')?.addEventListener('click',closeModal);
      modal?.addEventListener('click',e=>{if(e.target===modal)closeModal()});
      document.addEventListener('keydown',e=>{if(e.key==='Escape'){closeMenu();closeModal()}});
      $$('.notice button').forEach(button=>button.addEventListener('click',()=>button.closest('.notice')?.classList.remove('unread')));
      $('#markAllButton')?.addEventListener('click',()=>$$('.notice').forEach(item=>item.classList.remove('unread')));
      const hour=new Date().getHours(),greeting=$('#greeting'); if(greeting)greeting.textContent=hour<12?'Bună dimineața':hour<18?'Bună ziua':'Bună seara';
      $('#downloadButton')?.addEventListener('click',()=>alert('Demo: biletele au fost pregătite pentru descărcare.'));
      $('#walletButton')?.addEventListener('click',()=>alert('Demo: biletele au fost adăugate în Wallet.'));
      $('#notificationButton')?.addEventListener('click',()=>document.querySelector('.notice-list')?.scrollIntoView({behavior:'smooth',block:'center'}));
      if(!matchMedia('(prefers-reduced-motion: reduce)').matches){
        $$('.welcome-copy > *').forEach((el,i)=>el.animate([{opacity:0,transform:'translateY(24px)'},{opacity:1,transform:'translateY(0)'}],{duration:640,delay:80+i*80,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'}));
        const route=$('#heroRoute'); if(route){const length=route.getTotalLength();route.style.strokeDasharray=length;route.style.strokeDashoffset=length;route.animate([{strokeDashoffset:length},{strokeDashoffset:0}],{duration:1800,delay:280,easing:'cubic-bezier(.22,.8,.2,1)',fill:'forwards'})}
        const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.animate([{opacity:0,transform:'translateY(18px)'},{opacity:1,transform:'translateY(0)'}],{duration:520,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'});observer.unobserve(entry.target)}}),{threshold:.12});$$('.stat,.card').forEach(el=>observer.observe(el));
      }
    })();
  </script>
  <script>
  (function(){
    function nvAuth(){ try { return JSON.parse(localStorage.getItem('nordvale_auth')||'null'); } catch(e){ return null; } }
    var a = nvAuth();
    if(!a || !a.token){ location.href='/autentificare?next='+encodeURIComponent(location.pathname+location.search); return; }
    var AH = { 'Authorization':'Bearer '+a.token };
    function api(ac,opt){ opt=opt||{}; opt.headers=Object.assign({},AH,opt.headers||{}); return fetch('/api/proxy.php?action='+ac,opt).then(function(r){return r.json().catch(function(){return{};});}).catch(function(){return{};}); }
    function post(ac,b){ return api(ac,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(b||{})}); }
    function esc(s){ var d=document.createElement('div'); d.textContent=(s==null?'':String(s)); return d.innerHTML; }
    function money(v,c){ if(v==null||v==='') return ''; c=(c||'lei'); if(String(c).toLowerCase()==='ron')c='lei'; return new Intl.NumberFormat('ro-RO').format(Math.round(Number(v)))+' '+c; }
    function initials(n){ n=(n||'').trim(); if(!n)return 'N'; var p=n.split(/\s+/); return (((p[0]||'')[0]||'')+((p[1]||'')[0]||'')).toUpperCase()||'N'; }
    function fmtDate(iso){ if(!iso)return ''; var d=new Date(iso); if(isNaN(d))return ''; return d.toLocaleDateString('ro-RO',{day:'2-digit',month:'short',year:'numeric'}); }
    function hydrateUser(u){ if(!u)return; var name=u.name||(((u.first_name||'')+' '+(u.last_name||'')).trim())||'Cont Nordvale'; window.__nvName=name; var box=document.querySelector('.profile-mini'); if(box){ var av=box.querySelector('.avatar'); if(av)av.textContent=initials(name); var st=box.querySelector('strong'); if(st)st.textContent=name; var em=box.querySelector('strong + span'); if(em&&u.email)em.textContent=u.email; } }
    document.querySelectorAll('a,button').forEach(function(el){ if((el.textContent||'').trim()==='Deconectare'){ el.addEventListener('click',function(ev){ ev.preventDefault(); post('logout').finally(function(){ localStorage.removeItem('nordvale_auth'); location.href='/'; }); }); } });
    hydrateUser(a.user);
    api('me').then(function(d){ if(d&&d.success&&d.data){ a.user=Object.assign({},a.user,d.data); try{localStorage.setItem('nordvale_auth',JSON.stringify(a));}catch(e){} hydrateUser(a.user); if(window.__onUser)window.__onUser(a.user); } });

    var g=document.querySelector('#greeting');
    function setGreeting(){ if(!g)return; var h=new Date().getHours(); var base=h<12?'Bună dimineața':h<18?'Bună ziua':'Bună seara'; var fn=(window.__nvName||'').split(' ')[0]; g.textContent= fn?(base+', '+fn):base; }
    window.__onUser=function(){ setGreeting(); var mn=document.querySelector('.membership-row strong'); if(mn&&window.__nvName)mn.textContent=window.__nvName; };
    setGreeting();
    var mn0=document.querySelector('.membership-row strong'); if(mn0&&window.__nvName)mn0.textContent=window.__nvName;

    api('acc-stats').then(function(d){ if(!(d&&d.success&&d.data))return; var s=d.data; var st=document.querySelectorAll('.stats .stat strong'); if(st[0]&&s.upcoming_tickets!=null)st[0].textContent=s.upcoming_tickets; if(st[1]&&s.favorites!=null)st[1].textContent=s.favorites; if(st[3]&&s.points!=null)st[3].textContent=s.points; });

    api('acc-orders').then(function(d){ if(!(d&&d.success&&Array.isArray(d.data))||!d.data.length)return; var wrap=document.querySelector('.orders'); if(!wrap)return; wrap.innerHTML=d.data.slice(0,3).map(function(o){ var status=o.is_paid?'Plătită':(o.status||''); return '<a class="order" href="/cont/comanda?id='+o.id+'"><span class="order-icon"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/></svg></span><span><strong>'+esc(o.event||('Comanda '+o.order_number))+'</strong><span>Comanda #'+esc(o.order_number)+' · '+(o.tickets_count||0)+' bilete</span></span><span class="order-price">'+esc(money(o.total,o.currency))+'<small>'+esc(status)+'</small></span></a>'; }).join(''); });

    api('acc-tickets').then(function(d){ if(!(d&&d.success&&d.data))return; var up=d.data.upcoming||[]; if(!up.length)return; var t=up[0]; var chip=document.querySelector('.next-chip'); if(chip){ var cs=chip.querySelector('strong'); if(cs&&t.event)cs.textContent=t.event; var ct=chip.querySelector('time'); if(ct&&t.date){ var dd=new Date(t.date); if(!isNaN(dd)){ ct.setAttribute('datetime',String(t.date).slice(0,10)); ct.textContent=dd.toLocaleDateString('ro-RO',{day:'2-digit',month:'short'}); } } var m=chip.querySelectorAll('.next-chip-meta span'); if(m[0]&&t.time)m[0].textContent=String(t.time).slice(0,5); if(m[1])m[1].textContent=up.length+(up.length===1?' bilet':' bilete'); if(m[2]&&t.venue)m[2].textContent=t.venue; } var mc=document.querySelector('.ticket-code'); if(mc&&t.code)mc.textContent=t.code; var mt=document.querySelector('.ticket-preview small'); if(mt&&t.event)mt.textContent=t.event; });
  })();
  </script>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
