<!DOCTYPE html>
<html lang="ro">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#061a15">
  <title>Modificare sau rambursare — Nordvale</title>
  <meta name="description" content="Solicită modificarea unei rezervări sau rambursarea unei comenzi Nordvale.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap" rel="stylesheet">
  <style>
    :root{
      --pine-950:#061a15;--pine-900:#09251d;--pine-850:#0d3027;--pine-800:#123b30;--pine-700:#1b5242;
      --acid:#dffc62;--ember:#f27b4a;--cream:#fffdf6;--oat:#f0ecdf;--moss:#b7c9a3;--ink:#16231e;
      --line:rgba(9,37,29,.12);--line-dark:rgba(255,255,255,.12);--shadow:0 28px 80px -48px rgba(6,26,21,.62);
      --card-shadow:0 20px 54px -38px rgba(6,26,21,.48);--radius-xl:30px;--radius-lg:24px;--radius-md:18px;
    }
    *{box-sizing:border-box}
    html{background:var(--oat);scroll-behavior:smooth}
    body{margin:0;overflow-x:hidden;background:var(--oat);color:var(--ink);font-family:"DM Sans",sans-serif;text-rendering:optimizeLegibility}
    body.locked{overflow:hidden}
    button,input,select,textarea{font:inherit}
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
    .nav-icon{position:relative}.nav-icon:after{content:"";position:absolute;top:8px;right:8px;width:7px;height:7px;border:2px solid var(--pine-900);border-radius:50%;background:var(--ember)}
    .ticket-cta{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 16px;border-radius:13px;background:var(--acid);color:var(--pine-950);font-size:13px;font-weight:800;white-space:nowrap;box-shadow:0 15px 38px -24px rgba(223,252,98,.8)}
    .menu-btn{display:none}

    .layout{display:grid;grid-template-columns:250px minmax(0,1fr);gap:24px;padding:18px 0 88px}
    .sidebar{position:sticky;top:92px;align-self:start;padding:18px;border:1px solid var(--line);border-radius:26px;background:rgba(255,253,246,.78);box-shadow:var(--card-shadow);backdrop-filter:blur(18px)}
    .profile-mini{display:flex;align-items:center;gap:12px;padding:8px 8px 20px;border-bottom:1px solid var(--line)}
    .avatar{display:grid;place-items:center;width:50px;height:50px;border-radius:18px;background:var(--pine-900);color:var(--acid);font-family:"Fraunces",serif;font-size:18px;font-weight:700}
    .profile-mini strong{display:block;font-size:14px}.profile-mini span{display:block;margin-top:4px;color:rgba(22,35,30,.54);font-size:11px}
    .side-nav{display:grid;gap:6px;padding:16px 0}
    .side-nav a{display:flex;align-items:center;gap:11px;padding:11px 12px;border-radius:14px;color:rgba(22,35,30,.64);font-size:13px;font-weight:650;transition:.2s ease}
    .side-nav a:hover{background:rgba(9,37,29,.06);color:var(--pine-900)}
    .side-nav a.active{background:var(--pine-900);color:#fff;box-shadow:0 14px 28px -22px rgba(6,26,21,.9)}
    .side-nav svg{width:18px;height:18px;flex:0 0 auto}
    .side-help{padding:15px;border-radius:18px;background:var(--oat)}
    .side-help strong{display:block;font-family:"Fraunces",serif;font-size:17px}.side-help p{margin:7px 0 12px;color:rgba(22,35,30,.6);font-size:11px;line-height:1.55}.side-help a{display:inline-flex;align-items:center;gap:6px;color:var(--pine-900);font-size:12px;font-weight:800}

    .content{min-width:0}
    .hero{position:relative;overflow:hidden;display:grid;grid-template-columns:minmax(0,1.1fr) minmax(320px,.9fr);min-height:300px;border-radius:34px;background:var(--pine-900);color:#fff;box-shadow:var(--shadow)}
    .hero-copy{position:relative;z-index:4;display:flex;flex-direction:column;justify-content:center;padding:40px 44px}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;width:max-content;padding:7px 10px;border:1px solid rgba(223,252,98,.22);border-radius:999px;background:rgba(223,252,98,.08);color:var(--acid);font-size:10px;font-weight:800;letter-spacing:.14em;text-transform:uppercase}
    .eyebrow-dot{width:7px;height:7px;border-radius:50%;background:var(--acid);box-shadow:0 0 0 7px rgba(223,252,98,.1)}
    .hero h1{max-width:740px;margin:20px 0 12px;font-family:"Fraunces",serif;font-size:clamp(40px,5vw,68px);font-weight:600;letter-spacing:-.055em;line-height:.98}
    .hero h1 em{color:var(--acid);font-style:italic}.hero-copy>p{max-width:630px;margin:0;color:rgba(255,255,255,.66);font-size:14px;line-height:1.72}
    .hero-visual{position:relative;min-height:300px;background:linear-gradient(90deg,var(--pine-900),rgba(9,37,29,.2) 36%,rgba(9,37,29,.1)),url("https://images.unsplash.com/photo-1441974231531-c6227db76b6e?auto=format&fit=crop&w=1500&q=86") center/cover}
    .hero-visual:after{content:"";position:absolute;inset:0;background:linear-gradient(0deg,rgba(6,26,21,.74),transparent 58%)}
    .hero-note{position:absolute;z-index:4;right:24px;bottom:24px;width:min(290px,calc(100% - 48px));padding:17px;border:1px solid rgba(255,255,255,.15);border-radius:22px;background:rgba(6,26,21,.7);backdrop-filter:blur(16px)}
    .hero-note small{display:block;color:var(--acid);font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase}.hero-note strong{display:block;margin-top:7px;font-family:"Fraunces",serif;font-size:20px}.hero-note span{display:block;margin-top:7px;color:rgba(255,255,255,.6);font-size:11px;line-height:1.55}

    .stepper{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin:18px 0}
    .step{position:relative;display:flex;align-items:center;gap:9px;min-width:0;padding:12px 13px;border:1px solid var(--line);border-radius:16px;background:rgba(255,253,246,.72);color:rgba(22,35,30,.48)}
    .step:after{content:"";position:absolute;left:0;bottom:-1px;width:0;height:2px;border-radius:999px;background:var(--acid);transition:.32s ease}
    .step.is-active,.step.is-done{background:var(--pine-900);color:#fff;border-color:transparent}.step.is-active:after{width:100%}.step.is-done{background:var(--pine-800)}
    .step-index{display:grid;place-items:center;width:28px;height:28px;flex:0 0 auto;border-radius:10px;background:rgba(9,37,29,.07);font-size:11px;font-weight:800}.step.is-active .step-index,.step.is-done .step-index{background:rgba(223,252,98,.13);color:var(--acid)}
    .step span:last-child{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:11px;font-weight:750}

    .workarea{display:grid;grid-template-columns:minmax(0,1fr) 330px;gap:20px;align-items:start}
    .panel,.summary-card,.policy-card{border:1px solid var(--line);border-radius:28px;background:rgba(255,253,246,.86);box-shadow:var(--card-shadow)}
    .panel{overflow:hidden}.panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;padding:25px 27px 18px;border-bottom:1px solid var(--line)}
    .panel-head small,.section-label{display:block;color:var(--pine-700);font-size:9px;font-weight:800;letter-spacing:.15em;text-transform:uppercase}.panel-head h2{margin:7px 0 0;font-family:"Fraunces",serif;font-size:29px;letter-spacing:-.03em}.panel-head p{max-width:580px;margin:8px 0 0;color:rgba(22,35,30,.58);font-size:12px;line-height:1.62}
    .panel-body{padding:26px 27px 28px}.step-pane{display:none}.step-pane.is-active{display:block;animation:fadeIn .32s ease both}@keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
    .booking-list{display:grid;gap:12px}.booking-option{position:relative;display:grid;grid-template-columns:94px minmax(0,1fr) auto;gap:15px;align-items:center;padding:14px;border:1px solid var(--line);border-radius:21px;background:#fff;cursor:pointer;transition:.2s ease}.booking-option:hover{transform:translateY(-2px);box-shadow:0 18px 38px -31px rgba(6,26,21,.62)}.booking-option.selected{border-color:var(--pine-700);box-shadow:0 0 0 3px rgba(27,82,66,.08)}
    .booking-option input{position:absolute;opacity:0;pointer-events:none}.booking-thumb{width:94px;height:82px;border-radius:16px;object-fit:cover}.booking-main{min-width:0}.booking-main small{display:block;color:var(--pine-700);font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.12em}.booking-main strong{display:block;margin-top:5px;font-family:"Fraunces",serif;font-size:19px}.booking-main span{display:block;margin-top:5px;color:rgba(22,35,30,.52);font-size:11px}.booking-price{text-align:right}.booking-price strong{display:block;font-family:"Fraunces",serif;font-size:18px}.booking-price span{display:block;margin-top:4px;color:rgba(22,35,30,.5);font-size:9px}.check-dot{position:absolute;top:12px;right:12px;display:grid;place-items:center;width:24px;height:24px;border:1px solid var(--line);border-radius:9px;background:var(--cream);color:transparent}.booking-option.selected .check-dot{background:var(--pine-900);color:var(--acid);border-color:transparent}

    .choice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.choice{position:relative;min-height:150px;padding:18px;border:1px solid var(--line);border-radius:22px;background:#fff;cursor:pointer;transition:.2s ease}.choice:hover{transform:translateY(-2px)}.choice.selected{border-color:var(--pine-700);box-shadow:0 0 0 3px rgba(27,82,66,.08)}.choice input{position:absolute;opacity:0}.choice-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:14px;background:var(--oat);color:var(--pine-900)}.choice.selected .choice-icon{background:var(--pine-900);color:var(--acid)}.choice strong{display:block;margin-top:17px;font-family:"Fraunces",serif;font-size:20px}.choice p{margin:7px 0 0;color:rgba(22,35,30,.56);font-size:11px;line-height:1.55}.choice-badge{position:absolute;top:14px;right:14px;padding:5px 8px;border-radius:999px;background:rgba(223,252,98,.55);color:var(--pine-950);font-size:8px;font-weight:850;letter-spacing:.1em;text-transform:uppercase}
    .form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{display:grid;gap:7px}.field.full{grid-column:1/-1}.field label{font-size:10px;font-weight:800;color:var(--pine-900)}.field input,.field select,.field textarea{width:100%;border:1px solid var(--line);border-radius:14px;background:#fff;color:var(--ink);outline:none;transition:.2s ease}.field input,.field select{height:46px;padding:0 13px}.field textarea{min-height:118px;padding:13px;resize:vertical;line-height:1.6}.field input:focus,.field select:focus,.field textarea:focus{border-color:var(--pine-700);box-shadow:0 0 0 3px rgba(27,82,66,.08)}.field .hint{color:rgba(22,35,30,.48);font-size:9px;line-height:1.5}.field.error input,.field.error select,.field.error textarea{border-color:var(--ember)}.error-text{display:none;color:#b64321;font-size:9px}.field.error .error-text{display:block}
    .upload-zone{position:relative;display:grid;place-items:center;min-height:150px;padding:20px;border:1px dashed rgba(9,37,29,.25);border-radius:21px;background:rgba(240,236,223,.5);text-align:center;cursor:pointer}.upload-zone input{position:absolute;inset:0;opacity:0;cursor:pointer}.upload-zone svg{color:var(--pine-700)}.upload-zone strong{display:block;margin-top:10px;font-size:12px}.upload-zone span{display:block;margin-top:4px;color:rgba(22,35,30,.48);font-size:9px}.file-list{display:grid;gap:8px;margin-top:12px}.file-item{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:13px;background:var(--oat);font-size:10px}.file-item button{border:0;background:none;color:#b64321;cursor:pointer;font-weight:800}

    .resolution-grid{display:grid;gap:10px}.resolution{display:flex;align-items:flex-start;gap:12px;padding:14px;border:1px solid var(--line);border-radius:17px;background:#fff;cursor:pointer}.resolution.selected{border-color:var(--pine-700);box-shadow:0 0 0 3px rgba(27,82,66,.08)}.resolution input{margin-top:3px;accent-color:var(--pine-900)}.resolution strong{display:block;font-size:12px}.resolution span{display:block;margin-top:5px;color:rgba(22,35,30,.53);font-size:10px;line-height:1.5}
    .notice{display:flex;align-items:flex-start;gap:12px;margin-top:14px;padding:14px;border-radius:17px;background:rgba(223,252,98,.28);color:var(--pine-900)}.notice.warning{background:rgba(242,123,74,.12)}.notice svg{flex:0 0 auto}.notice strong{display:block;font-size:11px}.notice span{display:block;margin-top:4px;font-size:9px;line-height:1.55;color:rgba(22,35,30,.58)}

    .review-list{display:grid;gap:10px}.review-row{display:grid;grid-template-columns:140px minmax(0,1fr);gap:16px;padding:12px 0;border-bottom:1px solid var(--line)}.review-row:last-child{border-bottom:0}.review-row span{color:rgba(22,35,30,.48);font-size:10px}.review-row strong{font-size:11px;line-height:1.5}.consent{display:flex;align-items:flex-start;gap:10px;margin-top:17px}.consent input{margin-top:2px;accent-color:var(--pine-900)}.consent label{font-size:10px;line-height:1.55;color:rgba(22,35,30,.64)}

    .panel-actions{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:17px 27px;border-top:1px solid var(--line);background:rgba(240,236,223,.52)}
    .btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:0 17px;border:0;border-radius:14px;cursor:pointer;font-size:12px;font-weight:800;transition:.2s ease}.btn:hover{transform:translateY(-1px)}.btn-primary{background:var(--pine-900);color:#fff;box-shadow:0 18px 40px -28px rgba(6,26,21,.86)}.btn-acid{background:var(--acid);color:var(--pine-950)}.btn-light{border:1px solid var(--line);background:#fff;color:var(--pine-900)}.btn:disabled{opacity:.45;cursor:not-allowed;transform:none}

    .aside-stack{position:sticky;top:92px;display:grid;gap:14px}.summary-card,.policy-card{padding:20px}.summary-card h3,.policy-card h3{margin:0;font-family:"Fraunces",serif;font-size:21px}.summary-card>p,.policy-card>p{margin:7px 0 0;color:rgba(22,35,30,.55);font-size:10px;line-height:1.55}.summary-booking{display:flex;gap:11px;align-items:center;margin-top:16px;padding:11px;border-radius:16px;background:var(--oat)}.summary-booking img{width:62px;height:56px;border-radius:12px;object-fit:cover}.summary-booking strong{display:block;font-size:11px}.summary-booking span{display:block;margin-top:4px;color:rgba(22,35,30,.5);font-size:9px}.summary-lines{display:grid;gap:9px;margin-top:15px}.summary-line{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;font-size:10px}.summary-line span{color:rgba(22,35,30,.48)}.summary-line strong{text-align:right}.eligibility{margin-top:15px;padding:14px;border-radius:17px;background:var(--pine-900);color:#fff}.eligibility small{display:block;color:var(--acid);font-size:8px;font-weight:850;letter-spacing:.13em;text-transform:uppercase}.eligibility strong{display:block;margin-top:6px;font-family:"Fraunces",serif;font-size:18px}.eligibility span{display:block;margin-top:5px;color:rgba(255,255,255,.58);font-size:9px;line-height:1.5}.policy-list{display:grid;gap:11px;margin-top:15px}.policy-item{display:flex;gap:10px}.policy-item svg{flex:0 0 auto;color:var(--pine-700)}.policy-item strong{display:block;font-size:10px}.policy-item span{display:block;margin-top:3px;color:rgba(22,35,30,.5);font-size:9px;line-height:1.45}

    .success{display:none;overflow:hidden;border-radius:32px;background:var(--pine-900);color:#fff;box-shadow:var(--shadow)}.success.is-active{display:grid;grid-template-columns:minmax(0,1fr) 360px}.success-copy{padding:44px}.success-mark{display:grid;place-items:center;width:68px;height:68px;border-radius:22px;background:var(--acid);color:var(--pine-950)}.success h2{margin:22px 0 12px;font-family:"Fraunces",serif;font-size:clamp(38px,5vw,62px);line-height:.98;letter-spacing:-.045em}.success p{max-width:620px;margin:0;color:rgba(255,255,255,.64);font-size:13px;line-height:1.7}.success-code{display:inline-flex;margin-top:22px;padding:12px 14px;border:1px solid rgba(255,255,255,.14);border-radius:14px;background:rgba(255,255,255,.06);font-size:11px}.success-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:24px}.success-side{padding:30px;background:rgba(255,255,255,.05)}.success-side small{display:block;color:var(--acid);font-size:8px;font-weight:850;letter-spacing:.13em;text-transform:uppercase}.success-side h3{margin:8px 0 18px;font-family:"Fraunces",serif;font-size:24px}.timeline{display:grid;gap:17px}.timeline-item{position:relative;display:grid;grid-template-columns:34px minmax(0,1fr);gap:11px}.timeline-item:not(:last-child):after{content:"";position:absolute;left:16px;top:34px;bottom:-17px;width:1px;background:rgba(255,255,255,.14)}.timeline-dot{display:grid;place-items:center;width:34px;height:34px;border-radius:12px;background:rgba(223,252,98,.12);color:var(--acid);font-size:10px;font-weight:800}.timeline-item strong{display:block;font-size:11px}.timeline-item span{display:block;margin-top:4px;color:rgba(255,255,255,.48);font-size:9px;line-height:1.5}

    .mobile-nav{display:none}.mobile-menu{position:fixed;inset:0;z-index:90;transform:translateX(100%);padding:16px;background:var(--pine-950);color:#fff;transition:.3s ease}.mobile-menu.open{transform:none}.mobile-menu-head{display:flex;align-items:center;justify-content:space-between}.mobile-menu-links{display:grid;gap:8px;margin-top:34px}.mobile-menu-links a{padding:17px 15px;border-bottom:1px solid rgba(255,255,255,.09);font-family:"Fraunces",serif;font-size:27px}
    .toast{position:fixed;right:18px;bottom:18px;z-index:120;display:flex;align-items:center;gap:11px;width:min(360px,calc(100% - 36px));padding:14px;border:1px solid rgba(255,255,255,.11);border-radius:17px;background:var(--pine-950);color:#fff;box-shadow:0 26px 80px -38px #000;opacity:0;transform:translateY(18px);pointer-events:none;transition:.25s ease}.toast.show{opacity:1;transform:none}.toast-icon{display:grid;place-items:center;width:33px;height:33px;border-radius:11px;background:var(--acid);color:var(--pine-950);font-weight:900}.toast strong{display:block;font-size:11px}.toast span span{display:block;margin-top:3px;color:rgba(255,255,255,.55);font-size:9px}

    @media(max-width:1180px){.main-nav{display:none}.menu-btn{display:grid}.layout{grid-template-columns:220px minmax(0,1fr)}.hero{grid-template-columns:1fr 320px}.workarea{grid-template-columns:minmax(0,1fr) 290px}.step span:last-child{display:none}.step{justify-content:center}}
    @media(max-width:920px){.sidebar{display:none}.layout{display:block;padding-bottom:110px}.workarea{grid-template-columns:1fr}.aside-stack{position:static;grid-template-columns:repeat(2,minmax(0,1fr));margin-top:16px}.hero{grid-template-columns:1fr}.hero-visual{min-height:220px}.hero-copy{padding:34px 30px}.stepper{overflow:auto;grid-template-columns:repeat(5,132px);padding-bottom:4px;scrollbar-width:none}.stepper::-webkit-scrollbar{display:none}.step span:last-child{display:block}.step{justify-content:flex-start}.mobile-nav{position:fixed;left:12px;right:12px;bottom:max(12px,env(safe-area-inset-bottom));z-index:70;display:grid;grid-template-columns:repeat(4,1fr);padding:8px;border:1px solid rgba(255,255,255,.12);border-radius:20px;background:rgba(6,26,21,.93);backdrop-filter:blur(18px);box-shadow:0 24px 70px -38px #000;color:#fff}.mobile-nav a{display:grid;place-items:center;gap:4px;padding:7px 3px;border-radius:13px;color:rgba(255,255,255,.56);font-size:8px;font-weight:750}.mobile-nav a.active{background:rgba(223,252,98,.1);color:var(--acid)}.mobile-nav svg{width:19px;height:19px}}
    @media(max-width:680px){.shell{width:min(100% - 20px,1460px)}.site-header{padding:8px 0}.nav{padding:8px 9px 8px 11px;border-radius:18px;gap:8px}.brand-mark{width:38px;height:38px;border-radius:13px}.brand-copy strong{font-size:16px}.brand-copy span{font-size:8px}.nav-icon{display:none}.ticket-cta{min-height:38px;padding:0 12px}.menu-btn{width:38px;height:38px}.hero{min-height:0;border-radius:26px}.hero-copy{padding:28px 22px}.hero h1{font-size:clamp(38px,12vw,56px)}.hero-visual{min-height:190px}.hero-note{right:14px;bottom:14px;width:calc(100% - 28px)}.panel,.summary-card,.policy-card{border-radius:22px}.panel-head{display:block;padding:21px 19px 15px}.panel-head h2{font-size:25px}.panel-body{padding:20px 19px 22px}.panel-actions{padding:14px 19px}.booking-option{grid-template-columns:76px minmax(0,1fr);gap:12px}.booking-thumb{width:76px;height:70px}.booking-price{grid-column:2;text-align:left;display:flex;gap:8px;align-items:baseline}.check-dot{top:9px;right:9px}.choice-grid,.form-grid{grid-template-columns:1fr}.choice{min-height:134px}.field.full{grid-column:auto}.review-row{grid-template-columns:1fr;gap:5px}.panel-actions .btn{flex:1}.aside-stack{grid-template-columns:1fr}.success.is-active{grid-template-columns:1fr}.success-copy{padding:30px 22px}.success-side{padding:25px 22px}.toast{right:10px;bottom:92px;width:calc(100% - 20px)}}
    @media(max-width:390px){.brand-copy span{display:none}.ticket-cta{font-size:11px;padding:0 10px}.hero-copy{padding:25px 18px}.hero-note strong{font-size:18px}.panel-head,.panel-body,.panel-actions{padding-left:16px;padding-right:16px}.stepper{grid-template-columns:repeat(5,120px)}.booking-main strong{font-size:17px}.booking-option{grid-template-columns:64px minmax(0,1fr)}.booking-thumb{width:64px;height:64px}.btn{padding:0 13px;font-size:11px}}
    @media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;animation-duration:.01ms!important;animation-iteration-count:1!important;transition-duration:.01ms!important}}
  </style>
</head>
<body>
  <?php include __DIR__ . '/../includes/header.php'; ?>

  <main class="shell layout">
    <aside class="sidebar">
      <div class="profile-mini"><span class="avatar">AP</span><span><strong>Andrei Popescu</strong><span>Membru Wild Circle</span></span></div>
      <nav class="side-nav" aria-label="Navigație cont">
        <a href="/cont"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>Panou principal</a>
        <a href="/cont/bilete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/></svg>Biletele mele</a>
        <a href="/cont/comenzi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>Comenzi</a>
        <a href="/cont/abonamente"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v10H4z"/><path d="M8 7V5h8v2M8 12h8"/></svg>Abonamente</a>
        <a href="/cont/favorite"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 4.5a5.5 5.5 0 0 0-8 0 5.5 5.5 0 0 0-8 8L12 21l8-8.5a5.5 5.5 0 0 0 0-8z"/></svg>Favorite</a>
        <a href="/cont/profil"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="8" r="4"/><path d="M4 20c1.2-4 4-6 8-6s6.8 2 8 6"/></svg>Profil</a>
        <a href="/cont/notificari"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>Notificări</a>
        <a class="active" href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v10H4z"/><path d="m9 12 2 2 4-4"/></svg>Modificări și rambursări</a>
      </nav>
      <div class="side-help"><strong>Ai nevoie de ajutor?</strong><p>Un consultant Nordvale poate verifica manual o situație specială.</p><a href="/cont/suport">Deschide centrul de ajutor <span>→</span></a></div>
    </aside>

    <section class="content">
      <section class="hero grain topo">
        <div class="hero-copy"><span class="eyebrow"><span class="eyebrow-dot"></span> Cerere ghidată</span><h1>Schimbă planul, nu <em>aventura.</em></h1><p>Alege rezervarea și spune-ne ce s-a schimbat. Îți arătăm imediat variantele eligibile și timpul estimat de soluționare.</p></div>
        <div class="hero-visual"><div class="hero-note"><small>Politică flexibilă</small><strong>Modificările sunt prioritare</strong><span>În majoritatea cazurilor, schimbarea datei sau acordarea unui credit se rezolvă mai rapid decât rambursarea.</span></div></div>
      </section>

      <div class="stepper" id="stepper" aria-label="Progres cerere">
        <div class="step is-active" data-step="1"><span class="step-index">1</span><span>Rezervare</span></div>
        <div class="step" data-step="2"><span class="step-index">2</span><span>Solicitare</span></div>
        <div class="step" data-step="3"><span class="step-index">3</span><span>Detalii</span></div>
        <div class="step" data-step="4"><span class="step-index">4</span><span>Rezoluție</span></div>
        <div class="step" data-step="5"><span class="step-index">5</span><span>Confirmare</span></div>
      </div>

      <div class="workarea" id="wizardArea">
        <section class="panel">
          <header class="panel-head"><div><small id="panelLabel">Pasul 1 din 5</small><h2 id="panelTitle">Alege rezervarea</h2><p id="panelDescription">Poți solicita modificări doar pentru rezervările viitoare și pentru produsele eligibile.</p></div></header>
          <div class="panel-body">
            <section class="step-pane is-active" data-pane="1">
              <div class="booking-list">
                <label class="booking-option selected" data-booking-card>
                  <input type="radio" name="booking" value="NV-2026-1842" checked>
                  <img class="booking-thumb" src="https://images.unsplash.com/photo-1528659882437-34a2e58ef5e4?auto=format&fit=crop&w=500&q=84" alt="Canopy Run în pădure">
                  <span class="booking-main"><small>3 august 2026 · 12:30</small><strong>Canopy Run + Explorer Pass</strong><span>3 participanți · Comanda #NV-2026-1842</span></span>
                  <span class="booking-price"><strong>684 lei</strong><span>eligibilă</span></span>
                  <span class="check-dot">✓</span>
                </label>
                <label class="booking-option" data-booking-card>
                  <input type="radio" name="booking" value="NV-2026-1764">
                  <img class="booking-thumb" src="https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=500&q=84" alt="Tur ghidat în rezervație">
                  <span class="booking-main"><small>17 august 2026 · 10:00</small><strong>Forest Discovery</strong><span>2 participanți · Comanda #NV-2026-1764</span></span>
                  <span class="booking-price"><strong>248 lei</strong><span>eligibilă</span></span>
                  <span class="check-dot">✓</span>
                </label>
                <label class="booking-option" data-booking-card>
                  <input type="radio" name="booking" value="NV-2026-1298" disabled>
                  <img class="booking-thumb" src="https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=500&q=84" alt="Vizită în parc">
                  <span class="booking-main"><small>18 mai 2026 · utilizată</small><strong>Family Trail</strong><span>4 participanți · Comanda #NV-2026-1298</span></span>
                  <span class="booking-price"><strong>390 lei</strong><span>neeligibilă</span></span>
                </label>
              </div>
            </section>

            <section class="step-pane" data-pane="2">
              <div class="choice-grid">
                <label class="choice selected" data-choice-card><input type="radio" name="requestType" value="change-date" checked><span class="choice-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16v14H4zM8 3v6M16 3v6M4 10h16"/></svg></span><strong>Schimbă data</strong><p>Mută întreaga rezervare pe o altă zi și păstrează produsele actuale.</p><span class="choice-badge">Recomandat</span></label>
                <label class="choice" data-choice-card><input type="radio" name="requestType" value="change-people"><span class="choice-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="9" cy="8" r="3"/><path d="M3 20c.8-4 2.8-6 6-6s5.2 2 6 6M17 8h4M19 6v4"/></svg></span><strong>Modifică participanții</strong><p>Schimbă beneficiarii sau numărul de participanți, în limita capacității.</p></label>
                <label class="choice" data-choice-card><input type="radio" name="requestType" value="credit"><span class="choice-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v10H4z"/><path d="M8 11h4"/></svg></span><strong>Credit Nordvale</strong><p>Primești valoarea eligibilă în cont și o poți folosi la o vizită viitoare.</p></label>
                <label class="choice" data-choice-card><input type="radio" name="requestType" value="refund"><span class="choice-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v10H4z"/><path d="M9 12h6M12 9v6"/></svg></span><strong>Rambursare</strong><p>Solicită returnarea sumei eligibile pe metoda de plată inițială.</p></label>
              </div>
            </section>

            <section class="step-pane" data-pane="3">
              <div class="form-grid">
                <div class="field"><label for="reason">Motivul solicitării</label><select id="reason"><option value="">Alege motivul</option><option>Nu mai pot participa la data aleasă</option><option>Problemă medicală</option><option>Condiții meteo sau acces</option><option>Am cumpărat un produs greșit</option><option>Alt motiv</option></select><span class="error-text">Selectează un motiv.</span></div>
                <div class="field" id="newDateField"><label for="newDate">Noua dată preferată</label><input id="newDate" type="date" min="2026-07-30" value="2026-08-10"><span class="hint">Data finală va fi confirmată după verificarea capacității.</span></div>
                <div class="field full"><label for="message">Detalii utile</label><textarea id="message" placeholder="Descrie pe scurt situația și orice condiție importantă pentru soluționare..."></textarea><span class="error-text">Adaugă cel puțin 10 caractere.</span></div>
                <div class="field full"><label>Documente opționale</label><label class="upload-zone"><input id="documents" type="file" multiple accept=".pdf,.jpg,.jpeg,.png"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 16V4M8 8l4-4 4 4"/><path d="M4 15v5h16v-5"/></svg><strong>Adaugă documente</strong><span>PDF, JPG sau PNG · maximum 5 MB / fișier</span></label><div class="file-list" id="fileList"></div></div>
              </div>
            </section>

            <section class="step-pane" data-pane="4">
              <div class="resolution-grid" id="resolutionGrid">
                <label class="resolution selected" data-resolution><input type="radio" name="resolution" value="10 august 2026" checked><span><strong>Mută vizita pe 10 august 2026</strong><span>Canopy Run la 12:30 · aceiași participanți · fără cost suplimentar.</span></span></label>
                <label class="resolution" data-resolution><input type="radio" name="resolution" value="credit 684 lei"><span><strong>Credit Nordvale de 684 lei</strong><span>Valabil 12 luni și utilizabil pentru orice bilet sau experiență.</span></span></label>
                <label class="resolution" data-resolution><input type="radio" name="resolution" value="refund review"><span><strong>Analiză pentru rambursare</strong><span>Suma finală se stabilește după politica aplicabilă și documentele trimise.</span></span></label>
              </div>
              <div class="notice"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg><span><strong>Cea mai rapidă opțiune</strong><span>Schimbarea datei este confirmată de regulă în câteva minute, dacă noul interval este disponibil.</span></span></div>
            </section>

            <section class="step-pane" data-pane="5">
              <div class="review-list">
                <div class="review-row"><span>Rezervare</span><strong id="reviewBooking">#NV-2026-1842 · Canopy Run + Explorer Pass</strong></div>
                <div class="review-row"><span>Tip solicitare</span><strong id="reviewType">Schimbare dată</strong></div>
                <div class="review-row"><span>Motiv</span><strong id="reviewReason">—</strong></div>
                <div class="review-row"><span>Rezoluție preferată</span><strong id="reviewResolution">Mută vizita pe 10 august 2026</strong></div>
                <div class="review-row"><span>Documente</span><strong id="reviewDocuments">Niciun document atașat</strong></div>
                <div class="review-row"><span>Timp estimat</span><strong>Confirmare în aceeași zi</strong></div>
              </div>
              <div class="notice warning"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v4M12 16h.01"/></svg><span><strong>Rezervarea rămâne activă până la soluționare</strong><span>Nu elimina biletele din Wallet și nu crea o a doua comandă pentru aceeași vizită.</span></span></div>
              <div class="consent"><input id="confirmPolicy" type="checkbox"><label for="confirmPolicy">Confirm că informațiile sunt corecte și accept politica de modificare, anulare și rambursare aplicabilă produselor selectate.</label></div>
            </section>
          </div>
          <footer class="panel-actions"><button class="btn btn-light" id="backButton" type="button" disabled>Înapoi</button><button class="btn btn-primary" id="nextButton" type="button">Continuă</button></footer>
        </section>

        <aside class="aside-stack">
          <section class="summary-card"><h3>Rezumat cerere</h3><p>Se actualizează pe măsură ce completezi pașii.</p><div class="summary-booking"><img id="summaryImage" src="https://images.unsplash.com/photo-1528659882437-34a2e58ef5e4?auto=format&fit=crop&w=300&q=82" alt="Rezervarea selectată"><span><strong id="summaryTitle">Canopy Run + Explorer Pass</strong><span id="summaryMeta">3 august · 3 participanți</span></span></div><div class="summary-lines"><div class="summary-line"><span>Comandă</span><strong id="summaryOrder">#NV-2026-1842</strong></div><div class="summary-line"><span>Valoare</span><strong id="summaryValue">684 lei</strong></div><div class="summary-line"><span>Solicitare</span><strong id="summaryRequest">Schimbare dată</strong></div><div class="summary-line"><span>Rezoluție</span><strong id="summaryResolution">10 august 2026</strong></div></div><div class="eligibility"><small>Eligibilitate estimată</small><strong id="eligibilityTitle">Modificare gratuită</strong><span id="eligibilityText">Rezervarea este cu mai mult de 48 de ore înainte de vizită.</span></div></section>
          <section class="policy-card"><h3>Ce se întâmplă după?</h3><p>Procesul depinde de tipul solicitării.</p><div class="policy-list"><div class="policy-item"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span><strong>Verificare</strong><span>Confirmăm eligibilitatea și disponibilitatea.</span></span></div><div class="policy-item"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m5 12 4 4L19 6"/></svg><span><strong>Soluționare</strong><span>Primești răspunsul și documentele pe email.</span></span></div><div class="policy-item"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v10H4z"/><path d="M8 11h4"/></svg><span><strong>Rambursare</strong><span>Poate dura 3–7 zile lucrătoare după aprobare.</span></span></div></div></section>
        </aside>
      </div>

      <section class="success grain topo" id="successState">
        <div class="success-copy"><span class="success-mark"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg></span><h2>Cererea a intrat pe <em style="color:var(--acid);font-style:italic">traseu.</em></h2><p>Am înregistrat solicitarea și am trimis confirmarea la adresa asociată contului. Rezervarea rămâne activă până la răspunsul final.</p><span class="success-code">Cerere #NV-RF-2048</span><div class="success-actions"><a class="btn btn-acid" href="/cont/comenzi">Vezi comenzile</a><a class="btn btn-light" href="/cont">Înapoi în cont</a></div></div>
        <aside class="success-side"><small>Ce urmează</small><h3>Urmărirea solicitării</h3><div class="timeline"><div class="timeline-item"><span class="timeline-dot">1</span><span><strong>Cerere primită</strong><span>Acum · confirmare trimisă prin email.</span></span></div><div class="timeline-item"><span class="timeline-dot">2</span><span><strong>Verificare automată</strong><span>Disponibilitate, bilete și politica aplicabilă.</span></span></div><div class="timeline-item"><span class="timeline-dot">3</span><span><strong>Răspuns final</strong><span>În aceeași zi pentru modificări simple.</span></span></div></div></aside>
      </section>
    </section>
  </main>

  <nav class="mobile-nav" aria-label="Navigație cont mobil"><a href="/cont"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"/></svg>Acasă</a><a href="/cont/bilete"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v4a2 2 0 0 0 0 4v4H4v-4a2 2 0 0 0 0-4z"/></svg>Bilete</a><a href="/cont/comenzi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 4h14v16H5z"/><path d="M8 8h8M8 12h8M8 16h5"/></svg>Comenzi</a><a class="active" href="#"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v10H4z"/><path d="m9 12 2 2 4-4"/></svg>Cereri</a></nav>

  <div class="toast" id="toast"><span class="toast-icon">✓</span><span><strong id="toastTitle">Actualizat</strong><span id="toastText">Informația a fost salvată.</span></span></div>

  <script>
    (()=>{
      const $=(s,c=document)=>c.querySelector(s), $$=(s,c=document)=>[...c.querySelectorAll(s)];
      let currentStep=1; let files=[]; let toastTimer;
      const bookings={
        'NV-2026-1842':{title:'Canopy Run + Explorer Pass',meta:'3 august · 3 participanți',order:'#NV-2026-1842',value:'684 lei',image:'https://images.unsplash.com/photo-1528659882437-34a2e58ef5e4?auto=format&fit=crop&w=300&q=82'},
        'NV-2026-1764':{title:'Forest Discovery',meta:'17 august · 2 participanți',order:'#NV-2026-1764',value:'248 lei',image:'https://images.unsplash.com/photo-1511497584788-876760111969?auto=format&fit=crop&w=300&q=82'}
      };
      const typeLabels={'change-date':'Schimbare dată','change-people':'Modificare participanți','credit':'Credit Nordvale','refund':'Rambursare'};
      const panelContent={1:['Alege rezervarea','Poți solicita modificări doar pentru rezervările viitoare și pentru produsele eligibile.'],2:['Ce dorești să modifici?','Alege varianta care descrie cel mai bine rezultatul dorit.'],3:['Spune-ne ce s-a schimbat','Detaliile corecte reduc timpul de verificare și numărul de mesaje suplimentare.'],4:['Alege rezoluția preferată','Îți afișăm variantele disponibile pentru rezervarea și solicitarea selectată.'],5:['Verifică și trimite','Revizuiește cererea înainte de transmitere.']};
      function showToast(title,text){$('#toastTitle').textContent=title;$('#toastText').textContent=text;$('#toast').classList.add('show');clearTimeout(toastTimer);toastTimer=setTimeout(()=>$('#toast').classList.remove('show'),2600)}
      function getSelected(name){return $(`input[name="${name}"]:checked`)?.value||''}
      function updateStepper(){$$('.step').forEach(el=>{const n=+el.dataset.step;el.classList.toggle('is-active',n===currentStep);el.classList.toggle('is-done',n<currentStep)});$$('.step-pane').forEach(el=>el.classList.toggle('is-active',+el.dataset.pane===currentStep));$('#panelLabel').textContent=`Pasul ${currentStep} din 5`;$('#panelTitle').textContent=panelContent[currentStep][0];$('#panelDescription').textContent=panelContent[currentStep][1];$('#backButton').disabled=currentStep===1;$('#nextButton').textContent=currentStep===5?'Trimite cererea':'Continuă';if(currentStep===5)updateReview();window.scrollTo({top:Math.max(0,$('#wizardArea').offsetTop-120),behavior:'smooth'})}
      function updateSummary(){const booking=bookings[getSelected('booking')]||bookings['NV-2026-1842'];$('#summaryTitle').textContent=booking.title;$('#summaryMeta').textContent=booking.meta;$('#summaryOrder').textContent=booking.order;$('#summaryValue').textContent=booking.value;$('#summaryImage').src=booking.image;const type=getSelected('requestType')||'change-date';$('#summaryRequest').textContent=typeLabels[type];const res=getSelected('resolution')||'10 august 2026';$('#summaryResolution').textContent=res.includes('credit')?'Credit în cont':res.includes('refund')?'Analiză rambursare':'10 august 2026';if(type==='refund'){$('#eligibilityTitle').textContent='Necesită analiză';$('#eligibilityText').textContent='Suma rambursabilă depinde de produs și momentul solicitării.'}else if(type==='credit'){$('#eligibilityTitle').textContent='Credit integral estimat';$('#eligibilityText').textContent='Creditul este emis în cont după verificarea automată.'}else{$('#eligibilityTitle').textContent='Modificare gratuită';$('#eligibilityText').textContent='Rezervarea este cu mai mult de 48 de ore înainte de vizită.'}}
      function updateReview(){const booking=bookings[getSelected('booking')]||bookings['NV-2026-1842'];$('#reviewBooking').textContent=`${booking.order} · ${booking.title}`;$('#reviewType').textContent=typeLabels[getSelected('requestType')||'change-date'];$('#reviewReason').textContent=$('#reason').value||'Neselectat';const resolution=$('input[name="resolution"]:checked')?.parentElement.querySelector('strong')?.textContent||'Neselectată';$('#reviewResolution').textContent=resolution;$('#reviewDocuments').textContent=files.length?`${files.length} document${files.length>1?'e':''} atașat${files.length>1?'e':''}`:'Niciun document atașat'}
      function validateStep(){if(currentStep===3){let ok=true;const reason=$('#reason'),message=$('#message');reason.closest('.field').classList.toggle('error',!reason.value);message.closest('.field').classList.toggle('error',message.value.trim().length<10);ok=!!reason.value&&message.value.trim().length>=10;if(!ok)showToast('Mai sunt câmpuri incomplete','Completează motivul și detaliile solicitării.');return ok}if(currentStep===5&&!$('#confirmPolicy').checked){showToast('Confirmare necesară','Acceptă politica aplicabilă înainte de trimitere.');return false}return true}
      function submitRequest(){const wizard=$('#wizardArea'),stepper=$('#stepper'),success=$('#successState');wizard.style.display='none';stepper.style.display='none';success.classList.add('is-active');success.scrollIntoView({behavior:'smooth',block:'start'});showToast('Cerere înregistrată','Numărul cererii este #NV-RF-2048.')}
      $('#nextButton').addEventListener('click',()=>{if(!validateStep())return;if(currentStep<5){currentStep++;updateStepper()}else submitRequest()});$('#backButton').addEventListener('click',()=>{if(currentStep>1){currentStep--;updateStepper()}});
      $$('[data-booking-card]').forEach(card=>card.addEventListener('click',()=>{if(card.querySelector('input').disabled)return;$$('[data-booking-card]').forEach(c=>c.classList.remove('selected'));card.classList.add('selected');card.querySelector('input').checked=true;updateSummary()}));
      $$('[data-choice-card]').forEach(card=>card.addEventListener('click',()=>{$$('[data-choice-card]').forEach(c=>c.classList.remove('selected'));card.classList.add('selected');card.querySelector('input').checked=true;$('#newDateField').hidden=getSelected('requestType')!=='change-date';updateSummary()}));
      $$('[data-resolution]').forEach(card=>card.addEventListener('click',()=>{$$('[data-resolution]').forEach(c=>c.classList.remove('selected'));card.classList.add('selected');card.querySelector('input').checked=true;updateSummary()}));
      $('#documents').addEventListener('change',e=>{files=[...e.target.files].slice(0,4);renderFiles()});
      function renderFiles(){const box=$('#fileList');box.innerHTML='';files.forEach((f,i)=>{const row=document.createElement('div');row.className='file-item';row.innerHTML=`<span>${f.name}</span><button type="button" data-remove-file="${i}">Elimină</button>`;box.appendChild(row)});$$('[data-remove-file]').forEach(btn=>btn.addEventListener('click',()=>{files.splice(+btn.dataset.removeFile,1);renderFiles()}))}
      const mobileMenu=$('#mobileMenu');$('#menuOpen').addEventListener('click',()=>{mobileMenu.classList.add('open');mobileMenu.setAttribute('aria-hidden','false');document.body.classList.add('locked')});$('#menuClose').addEventListener('click',()=>{mobileMenu.classList.remove('open');mobileMenu.setAttribute('aria-hidden','true');document.body.classList.remove('locked')});$('#notificationButton')?.addEventListener('click',()=>showToast('Nicio alertă nouă','Toate cererile și rezervările sunt în regulă.'));
      if(!matchMedia('(prefers-reduced-motion: reduce)').matches){const observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting){entry.target.animate([{opacity:0,transform:'translateY(14px)'},{opacity:1,transform:'translateY(0)'}],{duration:450,easing:'cubic-bezier(.22,.8,.2,1)',fill:'both'});observer.unobserve(entry.target)}}),{threshold:.08});$$('.panel,.summary-card,.policy-card').forEach(el=>observer.observe(el))}
      updateSummary();updateStepper();
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
    function hydrateUser(u){ if(!u)return; var name=u.name||(((u.first_name||'')+' '+(u.last_name||'')).trim())||'Cont Nordvale'; window.__nvName=name; var box=document.querySelector('.profile-mini'); if(box){ var av=box.querySelector('.avatar'); if(av)av.textContent=initials(name); var st=box.querySelector('strong'); if(st)st.textContent=name; } }
    document.querySelectorAll('a,button').forEach(function(el){ if((el.textContent||'').trim()==='Deconectare'){ el.addEventListener('click',function(ev){ ev.preventDefault(); post('logout').finally(function(){ localStorage.removeItem('nordvale_auth'); location.href='/'; }); }); } });
    hydrateUser(a.user);
    api('me').then(function(d){ if(d&&d.success&&d.data){ a.user=Object.assign({},a.user,d.data); try{localStorage.setItem('nordvale_auth',JSON.stringify(a));}catch(e){} hydrateUser(a.user); } });

    var selOrder=null;
    function setSummaryFromSel(){ if(!selOrder)return; var st=document.querySelector('#summaryTitle'); if(st)st.textContent=selOrder.event||('Comanda #'+selOrder.order_number); var sm=document.querySelector('#summaryMeta'); if(sm)sm.textContent=(selOrder.event_date?fmtDate(selOrder.event_date)+' · ':'')+(selOrder.tickets_count||0)+' participanți'; var so=document.querySelector('#summaryOrder'); if(so)so.textContent='#'+selOrder.order_number; var sv=document.querySelector('#summaryValue'); if(sv)sv.textContent=money(selOrder.total,selOrder.currency); }
    api('acc-orders').then(function(d){ if(!(d&&d.success&&Array.isArray(d.data)))return; var elig=d.data.filter(function(o){return o.is_paid;}); if(!elig.length)return; var bl=document.querySelector('.booking-list'); if(!bl)return;
      var map={}; elig.forEach(function(o){ map[String(o.id)]=o; });
      bl.innerHTML=elig.map(function(o,i){ return '<label class="booking-option'+(i===0?' selected':'')+'" data-booking-card><input type="radio" name="booking" value="'+esc(String(o.id))+'"'+(i===0?' checked':'')+'><span class="booking-thumb" style="background:var(--pine-800)"></span><span class="booking-main"><small>'+esc(o.event_date?fmtDate(o.event_date):fmtDate(o.created_at))+'</small><strong>'+esc(o.event||('Comanda #'+o.order_number))+'</strong><span>'+(o.tickets_count||0)+' bilete · Comanda #'+esc(o.order_number)+'</span></span><span class="booking-price"><strong>'+esc(money(o.total,o.currency))+'</strong><span>eligibilă</span></span><span class="check-dot">✓</span></label>'; }).join('');
      selOrder=elig[0];
      bl.querySelectorAll('[data-booking-card]').forEach(function(card){ card.addEventListener('click',function(){ bl.querySelectorAll('[data-booking-card]').forEach(function(c){c.classList.remove('selected');}); card.classList.add('selected'); var inp=card.querySelector('input'); inp.checked=true; selOrder=map[inp.value]||selOrder; setSummaryFromSel(); }); });
      setSummaryFromSel();
      document.addEventListener('click',function(){ setTimeout(setSummaryFromSel,0); });
    });
    var nb=document.querySelector('#nextButton');
    if(nb){ nb.addEventListener('click',function(){ if((nb.textContent||'').trim().indexOf('Trimite')>=0){ var cp=document.querySelector('#confirmPolicy'); if(cp&&!cp.checked)return; var reason=(document.querySelector('#reason')||{}).value||''; var details=(document.querySelector('#message')||{}).value||''; var oid=selOrder?selOrder.id:null; if(oid){ post('acc-refund-request',{order_id:oid,reason:reason,details:details}); } } }, true); }
  })();
  </script>
  <?php include __DIR__ . '/../includes/footer.php'; ?>
</body>
</html>
