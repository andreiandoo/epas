<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Tixello pentru teatre — bilete, abonamente și casă de bilete, 1% comision</title>
<meta name="description" content="Platformă de ticketing pentru teatrele din România: hartă de sală cu locuri numerotate, abonamente de stagiune, eFactura ANAF, casă de bilete proprie. 1% comision, banii instant în contul teatrului, configurare în 24h.">
<meta name="robots" content="index,follow">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Tixello">
<meta property="og:title" content="Tixello pentru teatre — sistemul complet de bilete și abonamente">
<meta property="og:description" content="Peste 4.500 de evenimente și 24 mil. RON procesate. Hartă de sală, abonamente de stagiune, eFactura ANAF, casă proprie. 1% comision, banii instant.">
<meta property="og:locale" content="ro_RO">
<meta property="og:url" content="https://teatru.tixello.ro">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Tixello pentru teatre — bilete, abonamente, casă de bilete">
<meta name="twitter:description" content="Peste 4.500 de evenimente și 24 mil. RON procesate. 1% comision, banii instant în contul teatrului.">
<meta name="theme-color" content="#0B0B14">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#0B0B14; --bg2:#12121F; --card:#181829; --line:#26263C;
  --brand:#7C5CFF; --brand2:#A78BFA; --glow:rgba(124,92,255,.18);
  --acc:#22D3A6; --warn:#FFB020; --red:#FF6B6B;
  --txt:#F4F4F8; --txt2:#A2A2B8; --txt3:#8A8AA0;
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{background:var(--bg);color:var(--txt);font-family:'Plus Jakarta Sans',system-ui,sans-serif;font-size:16px;line-height:1.65;-webkit-font-smoothing:antialiased;overflow-x:hidden}
.wrap{max-width:1120px;margin:0 auto;padding:0 32px}
h1,h2,h3{font-weight:800;letter-spacing:-.03em;line-height:1.08}
h1{font-size:clamp(38px,5.8vw,68px)}
h2{font-size:clamp(28px,3.9vw,44px);margin-bottom:16px}
h3{font-size:24px;letter-spacing:-.02em;margin-bottom:10px}
h4{font-size:15px;font-weight:700;margin-bottom:8px}
p{margin-bottom:14px;max-width:74ch;color:var(--txt2)}
.grad{background:linear-gradient(100deg,var(--brand2),var(--brand) 55%,#5B8CFF);-webkit-background-clip:text;background-clip:text;color:transparent}
.eyebrow{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.2em;text-transform:uppercase;color:var(--brand2);margin-bottom:20px;display:inline-flex;align-items:center;gap:9px}
.eyebrow::before{content:"";width:22px;height:1px;background:var(--brand)}

/* ===== SCROLL REVEAL ===== */
.rv{opacity:0;transform:translateY(28px);transition:opacity .7s cubic-bezier(.16,1,.3,1),transform .7s cubic-bezier(.16,1,.3,1)}
.rv.in{opacity:1;transform:none}
.rv-l{opacity:0;transform:translateX(-34px);transition:opacity .75s cubic-bezier(.16,1,.3,1),transform .75s cubic-bezier(.16,1,.3,1)}
.rv-l.in{opacity:1;transform:none}
.rv-r{opacity:0;transform:translateX(34px);transition:opacity .75s cubic-bezier(.16,1,.3,1),transform .75s cubic-bezier(.16,1,.3,1)}
.rv-r.in{opacity:1;transform:none}
.rv-s{opacity:0;transform:scale(.94);transition:opacity .6s ease,transform .6s cubic-bezier(.16,1,.3,1)}
.rv-s.in{opacity:1;transform:none}
@media(max-width:1100px){
  .rv-l,.rv-r{transform:translateY(24px)}
}
[data-d="1"]{transition-delay:.08s}[data-d="2"]{transition-delay:.16s}[data-d="3"]{transition-delay:.24s}
[data-d="4"]{transition-delay:.32s}[data-d="5"]{transition-delay:.4s}[data-d="6"]{transition-delay:.48s}
[data-d="7"]{transition-delay:.56s}[data-d="8"]{transition-delay:.64s}

/* draw-on SVG paths */
.dash{stroke-dasharray:var(--len,300);stroke-dashoffset:var(--len,300);transition:stroke-dashoffset 1.4s cubic-bezier(.16,1,.3,1) .2s}
.in .dash{stroke-dashoffset:0}
/* grow bars */
.gw{transform:scaleY(0);transform-origin:bottom;transition:transform .9s cubic-bezier(.16,1,.3,1)}
.in .gw{transform:scaleY(1)}
.gx{transform:scaleX(0);transform-origin:left;transition:transform 1.1s cubic-bezier(.16,1,.3,1)}
.in .gx{transform:scaleX(1)}
.fi{opacity:0;transition:opacity .55s ease}
.in .fi{opacity:1}
.pop{opacity:0;transform:scale(.6);transition:opacity .45s ease,transform .5s cubic-bezier(.34,1.56,.64,1)}
.in .pop{opacity:1;transform:scale(1)}
.sd1{transition-delay:.15s}.sd2{transition-delay:.3s}.sd3{transition-delay:.45s}.sd4{transition-delay:.6s}
.sd5{transition-delay:.75s}.sd6{transition-delay:.9s}.sd7{transition-delay:1.05s}.sd8{transition-delay:1.2s}
.sd9{transition-delay:1.35s}.sd10{transition-delay:1.5s}

@keyframes pulse{0%,100%{opacity:.25;r:9}50%{opacity:.75;r:13}}
@keyframes scanline{0%{transform:translateY(0)}50%{transform:translateY(78px)}100%{transform:translateY(0)}}
@keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
@keyframes flowdot{0%{offset-distance:0%;opacity:0}10%{opacity:1}90%{opacity:1}100%{offset-distance:100%;opacity:0}}
@keyframes shimmer{0%{background-position:-360px 0}100%{background-position:360px 0}}
@keyframes blink{0%,45%{opacity:1}50%,95%{opacity:.25}100%{opacity:1}}
.in .anim-scan{animation:scanline 2.6s ease-in-out infinite .6s}
.in .anim-pulse{animation:pulse 2.2s ease-in-out infinite}
.in .anim-float{animation:float 3.4s ease-in-out infinite}
.in .anim-blink{animation:blink 1.8s ease-in-out infinite}
@media(prefers-reduced-motion:reduce){
  .rv,.rv-l,.rv-r,.rv-s{opacity:1!important;transform:none!important;transition:none!important}
  .dash{stroke-dashoffset:0!important;transition:none!important}
  .gw,.gx{transform:none!important;transition:none!important}
  .fi,.pop{opacity:1!important;transform:none!important;transition:none!important}
  .anim-scan,.anim-pulse,.anim-float,.anim-blink{animation:none!important}
}

/* ===== HERO ===== */
.hero{position:relative;overflow:hidden;padding:70px 0 56px;border-bottom:1px solid var(--line)}
.hero::before{content:"";position:absolute;inset:0;background:radial-gradient(58% 78% at 78% 8%,var(--glow),transparent 68%),radial-gradient(44% 60% at 6% 92%,rgba(34,211,166,.10),transparent 70%);pointer-events:none}
.hero::after{content:"";position:absolute;inset:0;opacity:.32;pointer-events:none;background-image:linear-gradient(var(--line) 1px,transparent 1px),linear-gradient(90deg,var(--line) 1px,transparent 1px);background-size:64px 64px;mask-image:radial-gradient(70% 70% at 50% 30%,#000,transparent 78%);-webkit-mask-image:radial-gradient(70% 70% at 50% 30%,#000,transparent 78%)}
.hero .wrap{position:relative;z-index:2;max-width:1260px;padding-left:32px;padding-right:32px}
.hero-grid{display:grid;grid-template-columns:.86fr 1.14fr;gap:44px;align-items:center}
@media(max-width:900px){.hero-grid{grid-template-columns:1fr;gap:32px}}
.logo{font-size:23px;font-weight:800;letter-spacing:-.045em;margin-bottom:42px;display:inline-flex;align-items:center;gap:9px}
.logo-mark{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,var(--brand),#5B8CFF);display:grid;place-items:center;font-size:15px;font-weight:800;color:#fff}
.hero p.lede{font-size:19px;max-width:600px;margin-top:18px}
.pills{display:flex;flex-wrap:wrap;gap:9px;margin-top:28px}
.pill{font-size:13px;font-weight:600;padding:8px 15px;border-radius:100px;background:var(--card);border:1px solid var(--line);color:var(--txt2);transition:border-color .3s,color .3s}
.pill:hover{border-color:var(--brand);color:var(--txt)}
.pill b{color:var(--brand2)}
.hero-meta{margin-top:38px;padding-top:20px;border-top:1px solid var(--line);display:flex;flex-wrap:wrap;gap:28px;font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.1em;text-transform:uppercase;color:var(--txt3)}

/* counters strip */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--line);border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.stats-note{max-width:1180px;margin:0 auto;padding:12px 32px 0;font-size:11.5px;color:var(--txt3);line-height:1.5}
@media(max-width:640px){.stats-note{padding:12px 18px 0}}
@media(max-width:700px){.stats{grid-template-columns:repeat(2,1fr)}}
.stat{background:var(--bg2);padding:26px 20px;text-align:center}
.stat .v{font-size:30px;font-weight:800;letter-spacing:-.04em;background:linear-gradient(140deg,var(--brand2),var(--brand));-webkit-background-clip:text;background-clip:text;color:transparent}
.stat .k{font-family:'JetBrains Mono',monospace;font-size:9.5px;letter-spacing:.18em;text-transform:uppercase;color:var(--txt3);margin-top:6px}

/* deal */
.deal{background:var(--bg2);border-bottom:1px solid var(--line);padding:42px 0}
.deal-grid{display:grid;grid-template-columns:auto 1fr;gap:36px;align-items:center}
.deal-num{font-size:80px;font-weight:800;letter-spacing:-.05em;line-height:.85;background:linear-gradient(160deg,var(--brand2),var(--brand));-webkit-background-clip:text;background-clip:text;color:transparent;padding-right:36px;border-right:1px solid var(--line)}
.deal-num span{display:block;font-family:'JetBrains Mono',monospace;font-size:10.5px;letter-spacing:.18em;text-transform:uppercase;color:var(--txt3);margin-top:12px;-webkit-text-fill-color:var(--txt3)}
.deal-example{display:inline-block;font-size:15px;color:var(--acc);border-left:2px solid var(--acc);padding-left:14px}
.deal-example b{color:var(--txt);font-weight:800}
.deal p{margin:0;font-size:16.5px}
.deal p strong{color:var(--txt)}

section{padding:74px 0}
.alt{background:var(--bg2);border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.lede2{font-size:18px;color:var(--txt2);max-width:72ch}

/* cards */
.cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:38px}
@media(max-width:960px){.cards{grid-template-columns:repeat(2,1fr)}}
@media(max-width:540px){.cards{grid-template-columns:1fr}}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:22px 20px;position:relative;overflow:hidden;transition:border-color .3s,transform .3s}
.card::after{content:"";position:absolute;inset:0;background:radial-gradient(70% 60% at 50% 0%,var(--glow),transparent 70%);opacity:0;transition:opacity .35s}
.card:hover{border-color:var(--brand);transform:translateY(-3px)}
.card:hover::after{opacity:1}
.card>*{position:relative;z-index:1}
.card .ic{width:38px;height:38px;border-radius:10px;margin-bottom:14px;background:rgba(124,92,255,.13);border:1px solid rgba(124,92,255,.28);display:grid;place-items:center}
.card p{font-size:14px;margin:0}

/* part header */
.part{padding:54px 0;background:linear-gradient(180deg,rgba(124,92,255,.1),transparent);border-top:1px solid var(--line)}
.part .n{font-family:'JetBrains Mono',monospace;font-size:12px;letter-spacing:.2em;color:var(--brand2);margin-bottom:12px}
.part p{max-width:66ch;margin:0}

/* feature */
.feat{display:grid;grid-template-columns:1.02fr .98fr;gap:46px;align-items:start;padding:52px 0;border-bottom:1px solid var(--line)}
.feat:last-child{border-bottom:none}
.feat.flip .fig{order:-1}
@media(max-width:900px){.feat{grid-template-columns:1fr;gap:26px}.feat.flip .fig{order:0}}
.ftag{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.16em;color:var(--brand2);margin-bottom:10px;display:flex;align-items:center;gap:10px}
.ftag i{display:block;width:26px;height:1px;background:linear-gradient(90deg,var(--brand),transparent)}
.feat ul{list-style:none;margin-top:16px}
.feat li{padding-left:24px;position:relative;margin-bottom:9px;font-size:15px;color:var(--txt2)}
.feat li::before{content:"";position:absolute;left:0;top:8px;width:10px;height:10px;border-radius:3px;background:rgba(124,92,255,.2);border:1px solid var(--brand)}
.feat li strong{color:var(--txt);font-weight:600}
.why{margin-top:18px;padding:15px 18px;border-radius:11px;background:rgba(124,92,255,.07);border:1px solid rgba(124,92,255,.22);font-size:14.5px;color:var(--txt2)}
.why b{color:var(--brand2)}
.note{margin-top:15px;padding:13px 17px;border-radius:11px;border:1px dashed var(--line);font-size:13.5px;color:var(--txt3)}
.fig{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;position:sticky;top:20px}
.fig svg{width:100%;height:auto;display:block}
.fcap{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--txt3);margin-top:14px;text-align:center}
@media(max-width:900px){.fig{position:static}}

/* flow */
.flow{display:grid;grid-template-columns:repeat(4,1fr);margin-top:36px;border:1px solid var(--line);border-radius:14px;overflow:hidden}
@media(max-width:840px){.flow{grid-template-columns:1fr}}
.step{padding:24px 20px;border-right:1px solid var(--line);background:var(--card);position:relative}
.step:last-child{border-right:none}
@media(max-width:840px){.step{border-right:none;border-bottom:1px solid var(--line)}}
.step .s{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.16em;color:var(--brand2);margin-bottom:10px}
.step p{font-size:14px;margin:0}
.callout{margin-top:26px;padding:22px 26px;border-radius:14px;background:linear-gradient(100deg,rgba(124,92,255,.12),rgba(91,140,255,.06));border:1px solid rgba(124,92,255,.3);font-size:16px;color:var(--txt2)}
.callout strong{color:var(--brand2)}

/* table */
.tw{border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-top:30px}
table{width:100%;border-collapse:collapse;font-size:14.8px}
th{text-align:left;padding:14px 18px;background:var(--card);font-family:'JetBrains Mono',monospace;font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--brand2);font-weight:500;border-bottom:1px solid var(--line)}
td{padding:14px 18px;border-bottom:1px solid var(--line);color:var(--txt2);vertical-align:top;transition:background .25s}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(124,92,255,.05)}
td:first-child{color:var(--txt);font-weight:600;width:36%}

/* part II */
.cols{columns:2;column-gap:38px;margin-top:12px}
@media(max-width:760px){.cols{columns:1}}
.block{break-inside:avoid;margin-bottom:24px;background:var(--card);border:1px solid var(--line);border-radius:13px;padding:20px 22px;transition:border-color .3s}
.block:hover{border-color:rgba(124,92,255,.5)}
.block h4{font-family:'JetBrains Mono',monospace;font-size:10.5px;letter-spacing:.15em;text-transform:uppercase;color:var(--brand2);margin-bottom:13px;display:flex;align-items:center;gap:8px}
.block ul{list-style:none}
.block li{font-size:14px;padding-left:16px;position:relative;margin-bottom:6px;color:var(--txt2)}
.block li::before{content:"";position:absolute;left:0;top:9px;width:5px;height:5px;border-radius:50%;background:var(--brand)}

/* impl */
.impl{counter-reset:st;margin-top:32px;list-style:none}
.impl li{counter-increment:st;position:relative;padding:0 0 24px 62px;border-left:1px solid var(--line);margin-left:18px}
.impl li:last-child{border-left-color:transparent}
.impl li::before{content:counter(st,decimal-leading-zero);position:absolute;left:-18px;top:-3px;width:36px;height:36px;border-radius:10px;background:var(--card);border:1px solid var(--brand);color:var(--brand2);font-family:'JetBrains Mono',monospace;font-size:12px;font-weight:600;display:flex;align-items:center;justify-content:center}
.impl p{font-size:14.5px;margin:0}

/* trust band */
.trust{background:var(--bg2);border-bottom:1px solid var(--line)}
.proof{margin-bottom:26px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:24px;position:relative;overflow:hidden}
.proof::before{content:"";position:absolute;inset:0;background:radial-gradient(60% 120% at 100% 0%,rgba(34,211,166,.1),transparent 60%);pointer-events:none}
.proof-tag{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--acc);margin-bottom:18px}
.proof-grid{display:grid;grid-template-columns:repeat(3,1fr) 1.4fr;gap:20px;align-items:center}
@media(max-width:860px){.proof-grid{grid-template-columns:1fr 1fr;gap:22px}}
@media(max-width:460px){.proof-grid{grid-template-columns:1fr}}
.proof-item{position:relative;z-index:1}
.proof-item.wide{border-left:1px solid var(--line);padding-left:20px}
@media(max-width:860px){.proof-item.wide{border-left:none;padding-left:0;border-top:1px solid var(--line);padding-top:18px;grid-column:1/-1}}
.proof-v{font-size:32px;font-weight:800;letter-spacing:-.03em;color:var(--txt);line-height:1;font-variant-numeric:tabular-nums}
.proof-item.wide .proof-v{font-size:27px;background:linear-gradient(120deg,var(--acc),var(--brand2));-webkit-background-clip:text;background-clip:text;color:transparent}
.proof-cur{font-size:16px;font-weight:700}
.proof-k{font-size:12px;color:var(--txt2);margin-top:8px}
.trust-lead{display:grid;grid-template-columns:180px 1fr;gap:24px;align-items:center;margin-bottom:26px}
@media(max-width:700px){.trust-lead{grid-template-columns:1fr;gap:10px}}
.trust-lead p{margin:0;font-size:16px;color:var(--txt);line-height:1.55}
.trust-lead .eyebrow{margin:0}
.trust-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
@media(max-width:820px){.trust-grid{grid-template-columns:1fr 1fr}}
@media(max-width:520px){.trust-grid{grid-template-columns:1fr}}
.trust-item{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:18px}
.trust-item b{display:block;font-size:14px;color:var(--txt);margin-bottom:6px}
.trust-item p{margin:0;font-size:12.5px;color:var(--txt2);line-height:1.5}
.trust-fine{margin:18px 0 0;font-size:13px;color:var(--txt3);font-style:italic}

/* tldr summary */
.tldr{border-bottom:1px solid var(--line)}.tldr-head{display:flex;justify-content:space-between;align-items:flex-end;gap:20px;margin-bottom:26px;flex-wrap:wrap}
.tldr-head h2{margin:0}
.tldr-print{display:inline-flex;align-items:center;gap:8px;font-size:13px;font-weight:700;color:var(--txt);background:var(--card);border:1px solid var(--line);border-radius:10px;padding:11px 16px;cursor:pointer;font-family:inherit;transition:border-color .2s,transform .15s}
.tldr-print:hover{border-color:var(--brand);transform:translateY(-1px)}
.tldr-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
@media(max-width:820px){.tldr-grid{grid-template-columns:1fr 1fr}}
@media(max-width:560px){.tldr-grid{grid-template-columns:1fr}}
.tldr-item{display:flex;gap:14px;background:var(--card);border:1px solid var(--line);border-radius:14px;padding:20px}
.tldr-n{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:700;color:var(--brand2);flex:0 0 auto;width:26px}
.tldr-item b{display:block;font-size:15px;color:var(--txt);margin-bottom:6px;letter-spacing:-.01em}
.tldr-item p{margin:0;font-size:13px;color:var(--txt2);line-height:1.55}

.mig-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:32px}@media(max-width:820px){.mig-grid{grid-template-columns:1fr 1fr}}
@media(max-width:560px){.mig-grid{grid-template-columns:1fr}}
.mig-card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:22px}
.mig-ic{font-size:24px;margin-bottom:12px}
.mig-card h4{margin:0 0 7px;font-size:15px;color:var(--txt);letter-spacing:-.01em}
.mig-card p{margin:0;font-size:13px;color:var(--txt2);line-height:1.55}
.mig-timeline{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:24px;position:relative}
@media(max-width:640px){.mig-timeline{grid-template-columns:1fr;gap:12px}}
.mig-step{background:var(--bg2);border:1px solid var(--line);border-radius:12px;padding:18px 20px;position:relative}
.mig-step b{display:block;font-size:12px;font-family:'JetBrains Mono',monospace;letter-spacing:.06em;color:var(--brand2);margin-bottom:6px}
.mig-step p{margin:0;font-size:12.5px;color:var(--txt2);line-height:1.5}
.mig-dot{position:absolute;top:-7px;left:20px;width:12px;height:12px;border-radius:50%;background:var(--line);border:2px solid var(--bg)}
.mig-dot.on{background:var(--acc);box-shadow:0 0 0 4px rgba(34,211,166,.18)}
.closer{padding:80px 0;position:relative;overflow:hidden;border-top:1px solid var(--line)}
.closer::before{content:"";position:absolute;inset:0;background:radial-gradient(60% 90% at 50% 0%,var(--glow),transparent 70%)}
.closer .wrap{position:relative;z-index:2}
.chain{font-family:'JetBrains Mono',monospace;font-size:13px;line-height:2.2;color:var(--brand2);margin:22px 0}
footer{padding:26px 0;border-top:1px solid var(--line);font-family:'JetBrains Mono',monospace;font-size:10.5px;letter-spacing:.14em;text-transform:uppercase;color:var(--txt3);text-align:center}

/* live demo window */
.demo-window{display:block;margin-top:32px;border-radius:16px;overflow:hidden;border:1px solid var(--line);background:var(--card);text-decoration:none;transition:transform .25s,box-shadow .25s;box-shadow:0 12px 40px rgba(0,0,0,.3)}
.demo-window:hover{transform:translateY(-3px);box-shadow:0 20px 60px rgba(124,92,255,.22)}
.demo-bar{display:flex;align-items:center;gap:14px;padding:11px 16px;background:var(--bg2);border-bottom:1px solid var(--line)}
.demo-dots{display:flex;gap:6px}
.demo-dots i{width:11px;height:11px;border-radius:50%;background:var(--line)}
.demo-dots i:nth-child(1){background:#FF6B6B}.demo-dots i:nth-child(2){background:#FFB020}.demo-dots i:nth-child(3){background:#22D3A6}
.demo-url{flex:1;display:flex;align-items:center;gap:7px;font-family:'JetBrains Mono',monospace;font-size:12.5px;color:var(--txt);background:var(--card);border:1px solid var(--line);border-radius:8px;padding:6px 12px}
.demo-open{font-size:12px;font-weight:700;color:var(--brand2);white-space:nowrap}
.demo-view{padding:0}
.demo-poster{position:relative;padding:44px 40px;background:radial-gradient(70% 120% at 20% 0%,rgba(124,92,255,.28),transparent 60%),radial-gradient(60% 100% at 100% 100%,rgba(34,211,166,.16),transparent 60%),var(--bg)}
.demo-badge{position:absolute;top:18px;right:22px;font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.16em;color:var(--acc);border:1px solid rgba(34,211,166,.4);border-radius:20px;padding:5px 12px;background:rgba(34,211,166,.08)}
.demo-show-tag{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.14em;color:var(--brand2);margin-bottom:14px}
.demo-show-title{font-size:26px;font-weight:800;letter-spacing:-.03em;color:var(--txt);line-height:1.2;margin-bottom:24px}
@media(max-width:600px){.demo-show-title{font-size:20px}.demo-poster{padding:32px 22px}}
.demo-cta-row{display:flex;gap:12px;flex-wrap:wrap}
.demo-fakebtn{font-size:13.5px;font-weight:700;color:#fff;background:var(--brand);border-radius:10px;padding:11px 18px}
.demo-fakebtn.ghost{background:transparent;color:var(--txt);border:1px solid var(--line)}
.demo-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:1px;background:var(--line);border-top:1px solid var(--line)}
@media(max-width:600px){.demo-strip{grid-template-columns:repeat(2,1fr)}}
.demo-mini{background:var(--card);padding:16px;font-size:12.5px;font-weight:600;color:var(--txt2);display:flex;align-items:center;gap:9px}
.demo-mini span{font-size:16px}
.demo-note{display:flex;gap:12px;align-items:center;margin-top:22px;background:var(--card);border:1px solid var(--line);border-radius:13px;padding:18px 20px}
.demo-note-ic{font-size:20px;flex:0 0 auto}
.demo-note p{margin:0;font-size:14px;color:var(--txt2)}
.demo-note b{color:var(--txt)}

/* POS mockup */
.pos{background:#F6F7FB;border:1px solid var(--line);border-radius:16px;overflow:hidden;margin-top:32px;color:#1A1A2E;box-shadow:0 20px 60px rgba(0,0,0,.35)}
.pos-head{background:#fff;border-bottom:1px solid #E6E8F0;padding:16px 20px;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap}
.pos-title{font-size:17px;font-weight:800;letter-spacing:-.02em;color:#1A1A2E;display:flex;align-items:center;gap:9px}
.pos-ic{font-size:17px}
.pos-head-right{display:flex;gap:8px;flex-wrap:wrap}
.pos-chip{font-size:11px;font-weight:600;color:#5A5A72;background:#F1F2F7;border:1px solid #E6E8F0;border-radius:8px;padding:6px 10px;display:inline-flex;align-items:center;gap:6px}
.pos-chip.amber{background:#FFF4E0;border-color:#F5D9A8;color:#B87400}
.pos-chip b{font-weight:700}
.dot-amber{width:7px;height:7px;border-radius:50%;background:#FFB020;display:inline-block}
.pos-cal{opacity:.7}
.pos-body{display:grid;grid-template-columns:1.5fr 1fr;gap:0}
@media(max-width:820px){.pos-body{grid-template-columns:1fr}}
.pos-cat{padding:12px 18px;border-right:1px solid #E6E8F0}
@media(max-width:820px){.pos-cat{border-right:none;border-bottom:1px solid #E6E8F0}}
.pos-catacc{border-bottom:1px solid #EEF0F7}
.pos-catacc:last-child{border-bottom:none}
.pos-catacc-head{width:100%;display:flex;justify-content:space-between;align-items:center;background:none;border:none;cursor:pointer;font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.08em;color:#8A8AA0;padding:15px 4px;font-weight:600;text-align:left;transition:color .18s}
.pos-catacc-head:hover{color:#7C5CFF}
.pos-catacc-head b{background:#EEF0F7;border-radius:20px;padding:1px 8px;color:#5A5A72;margin-left:6px}
.pos-caret{color:#B0B0C0;transition:transform .2s}
.pos-catacc.open .pos-catacc-head .pos-caret{transform:rotate(0)}
.pos-catacc-body{display:none;padding:2px 0 16px}
.pos-catacc.open .pos-catacc-body{display:block}
.pos-tickets{display:grid;grid-template-columns:1fr 1fr;gap:12px}
@media(max-width:520px){.pos-tickets{grid-template-columns:1fr}}
.pos-ticket{text-align:left;background:#fff;border:1px solid #E6E8F0;border-radius:12px;padding:16px;cursor:pointer;display:flex;flex-direction:column;gap:6px;transition:border-color .2s,box-shadow .2s,transform .15s;font-family:inherit}
.pos-ticket:hover{border-color:#7C5CFF;box-shadow:0 4px 16px rgba(124,92,255,.15);transform:translateY(-1px)}
.pos-ticket:active{transform:scale(.98)}
.pos-tag{font-size:9px;font-weight:700;letter-spacing:.1em;color:#7C5CFF;background:#EFEBFF;border-radius:5px;padding:2px 7px;align-self:flex-start}
.pos-tname{font-size:14px;font-weight:700;color:#1A1A2E}
.pos-tprice{font-size:18px;font-weight:800;color:#3B5BFF;letter-spacing:-.02em}
.pos-cart{padding:18px;background:#fff;display:flex;flex-direction:column}
.pos-cart-head{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px}
.pos-cart-head>span{font-size:17px;font-weight:800;color:#1A1A2E}
.pos-empty{font-size:11px;color:#D0455B;background:#FDECEF;border:1px solid #F5C9D2;border-radius:8px;padding:5px 10px;cursor:pointer;font-family:inherit}
.pos-cart-items{min-height:70px;border:1px dashed #E6E8F0;border-radius:11px;padding:12px;margin-bottom:14px}
.pos-cart-empty{text-align:center;color:#9A9AB0;font-size:13px;padding:14px 0}
.pos-line{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid #F1F2F7}
.pos-line:last-child{border-bottom:none}
.pos-line-name{font-size:13px;font-weight:600;color:#1A1A2E}
.pos-line-right{display:flex;align-items:center;gap:10px}
.pos-qty{display:flex;align-items:center;gap:8px;background:#F1F2F7;border-radius:8px;padding:2px}
.pos-qty button{width:22px;height:22px;border:none;background:#fff;border-radius:6px;cursor:pointer;font-weight:700;color:#5A5A72;font-size:14px;line-height:1}
.pos-qty span{font-size:13px;font-weight:700;min-width:16px;text-align:center}
.pos-line-price{font-size:13px;font-weight:700;color:#3B5BFF;min-width:74px;text-align:right}
/* event selector */
.pos-event{padding:16px 20px;background:#F1F2F7;border-bottom:1px solid #E6E8F0;display:flex;align-items:center;gap:14px}
.pos-event-label{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#8A8AA0;font-weight:600;flex:0 0 auto}
.pos-select{position:relative;flex:1;background:#fff;border:1px solid #D8DAE5;border-radius:10px;padding:12px 16px;display:flex;align-items:center;justify-content:space-between;cursor:pointer;font-weight:700;color:#1A1A2E;font-size:14px;transition:border-color .18s}
.pos-select:hover,.pos-select.open{border-color:#7C5CFF}
.pos-select-caret{color:#8A8AA0;transition:transform .2s}
.pos-select.open .pos-select-caret{transform:rotate(180deg)}
.pos-select-menu{position:absolute;top:calc(100% + 6px);left:0;right:0;background:#fff;border:1px solid #D8DAE5;border-radius:10px;box-shadow:0 12px 32px rgba(0,0,0,.14);z-index:20;overflow:hidden;display:none}
.pos-select.open .pos-select-menu{display:block}
.pos-opt{padding:12px 16px;font-size:13.5px;font-weight:600;color:#3A3A52;cursor:pointer;transition:background .15s}
.pos-opt:hover{background:#F1F2F7}
.pos-opt.active{background:#EFEBFF;color:#7C5CFF}
/* accordions */
.pos-acc{border-top:1px solid #F1F2F7}
.pos-acc-head{width:100%;display:flex;align-items:center;justify-content:space-between;gap:8px;padding:13px 0;background:none;border:none;cursor:pointer;font-family:inherit;font-size:12.5px;font-weight:600;color:#3A3A52;text-align:left}
.pos-acc-head em{color:#9A9AB0;font-style:normal;font-weight:500}
.pos-acc-caret{color:#B0B0C0;transition:transform .2s;flex:0 0 auto}
.pos-acc.open .pos-acc-caret{transform:rotate(90deg)}
.pos-acc-body{display:none;padding-bottom:12px;flex-direction:column;gap:8px}
.pos-acc.open .pos-acc-body{display:flex}
.pos-input{width:100%;box-sizing:border-box;border:1px solid #E6E8F0;border-radius:8px;padding:10px 12px;font-size:12.5px;font-family:inherit;color:#3A3A52;background:#FAFBFD}
.pos-input::placeholder{color:#9A9AB0}
.pos-input:focus{outline:none;border-color:#7C5CFF}
textarea.pos-input{resize:none}
.pos-input-row{display:flex;gap:8px}
.pos-input-row .pos-input{flex:1}
.pos-anaf{flex:0 0 auto;background:#B01E38;color:#fff;border:none;border-radius:8px;padding:0 14px;font-size:12px;font-weight:700;cursor:pointer;font-family:inherit}
.pos-totals{padding:14px 0 12px;border-top:1px solid #F1F2F7;margin-top:4px}
.pos-subtotal{display:flex;justify-content:space-between;font-size:13px;color:#6A6A82;margin-bottom:8px}
.pos-subtotal .pos-fee-pct{color:#7C5CFF;font-weight:700}
.pos-total{display:flex;justify-content:space-between;font-size:20px;font-weight:800;color:#1A1A2E;letter-spacing:-.02em;padding-top:8px;border-top:1px solid #F1F2F7}
.pos-total span:last-child{color:#D0455B}
.pos-pay-label{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.14em;color:#8A8AA0;margin:14px 0 8px;font-weight:600}
.pos-pay{display:flex;gap:8px;margin-bottom:14px}
.pos-pay-btn{flex:1;font-size:12.5px;font-weight:600;padding:11px 6px;border:1px solid #E6E8F0;background:#fff;border-radius:9px;cursor:pointer;color:#5A5A72;font-family:inherit;transition:all .18s}
.pos-pay-btn.active{border-color:#D0455B;background:#B01E38;color:#fff}
.pos-finish{width:100%;padding:15px;border:none;border-radius:11px;font-size:15px;font-weight:800;color:#fff;background:#D98B98;cursor:not-allowed;font-family:inherit;transition:background .2s,transform .15s;letter-spacing:-.01em}
.pos-finish.ready{background:#B01E38;cursor:pointer}
.pos-finish.ready:hover{background:#951730;transform:translateY(-1px)}
.pos-notes{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-top:24px}
@media(max-width:760px){.pos-notes{grid-template-columns:1fr}}
.pos-note{display:flex;gap:12px;background:var(--card);border:1px solid var(--line);border-radius:13px;padding:18px}
.pos-note-ic{font-size:20px;flex:0 0 auto}
.pos-note b{color:var(--txt);font-size:14px;display:block;margin-bottom:4px}
.pos-note p{font-size:12.5px;color:var(--txt2);margin:0}

/* web offer */
.feat-toggle{display:flex;align-items:center;gap:10px;margin:8px auto 0;padding:14px 28px;background:var(--card);border:1px solid var(--line);border-radius:12px;color:var(--txt);font-size:14px;font-weight:700;font-family:inherit;cursor:pointer;transition:border-color .2s,transform .15s}
.feat-toggle:hover{border-color:var(--brand);transform:translateY(-1px)}
.feat-toggle svg{transition:transform .25s}
.feat-toggle[aria-expanded="true"] svg{transform:rotate(180deg)}
.feat-more[hidden]{display:none}
/* comparison table */
.cmp{margin-top:32px;border:1px solid var(--line);border-radius:16px;overflow:hidden}
.cmp-row{display:grid;grid-template-columns:1fr 1.4fr 1.4fr;border-bottom:1px solid var(--line)}
.cmp-row:last-child{border-bottom:none}
.cmp-head{background:var(--bg2)}
.cmp-head .cmp-crit,.cmp-head>div{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--txt3);font-weight:600}
.cmp-row>div{padding:16px 20px;font-size:13.5px;line-height:1.5}
.cmp-crit{color:var(--txt);font-weight:700;border-right:1px solid var(--line);display:flex;align-items:center}
.cmp-old{color:var(--txt2);border-right:1px solid var(--line);display:flex;align-items:center}
.cmp-new{color:var(--txt);display:flex;align-items:center;background:linear-gradient(90deg,rgba(34,211,166,.05),transparent)}
.cmp-new b{color:var(--acc);font-weight:700}
@media(max-width:720px){
  .cmp-row{grid-template-columns:1fr}
  .cmp-crit{border-right:none;border-bottom:1px solid var(--line);background:var(--bg2);font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.08em;text-transform:uppercase}
  .cmp-old{border-right:none;border-bottom:1px solid var(--line)}
  .cmp-old::before{content:"Acum: ";color:var(--txt3);font-weight:600}
  .cmp-new::before{content:"Tixello: ";color:var(--acc);font-weight:700}
  .cmp-head{display:none}
}
/* faq */
.faq{margin-top:28px;max-width:820px}
.faq-item{border:1px solid var(--line);border-radius:12px;margin-bottom:12px;overflow:hidden;background:var(--card)}
.faq-q{width:100%;display:flex;justify-content:space-between;align-items:center;gap:14px;padding:18px 20px;background:none;border:none;cursor:pointer;font-family:inherit;font-size:15px;font-weight:700;color:var(--txt);text-align:left;transition:color .18s}
.faq-q:hover{color:var(--brand2)}
.faq-caret{color:var(--brand2);flex:0 0 auto;transition:transform .2s;font-size:13px}
.faq-item.open .faq-caret{transform:rotate(90deg)}
.faq-a{display:none;padding:0 20px 18px}
.faq-item.open .faq-a{display:block}
.faq-a p{margin:0;font-size:13.5px;color:var(--txt2);line-height:1.6}
.web-badge{display:inline-block;font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:var(--acc);background:rgba(34,211,166,.1);border:1px solid rgba(34,211,166,.3);border-radius:20px;padding:6px 14px;margin-bottom:18px}
.web-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:32px}
@media(max-width:800px){.web-grid{grid-template-columns:1fr}}
.web-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:26px;position:relative}
.web-card.featured{border-color:rgba(124,92,255,.5);background:linear-gradient(180deg,rgba(124,92,255,.06),transparent 40%),var(--card)}
.web-card-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px}
.web-card-tag{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:var(--brand2)}
.web-free{font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;letter-spacing:.1em;color:var(--acc);background:rgba(34,211,166,.12);border-radius:20px;padding:4px 11px}
.web-card h3{font-size:20px;letter-spacing:-.02em;margin:0 0 8px}
.web-card-lead{font-size:13.5px;color:var(--txt2);margin:0 0 16px;line-height:1.55}
.web-list{list-style:none;padding:0;margin:0 0 18px}
.web-list li{position:relative;padding-left:26px;font-size:13.5px;color:var(--txt);margin-bottom:10px;line-height:1.5}
.web-list li::before{content:"✓";position:absolute;left:0;top:0;color:var(--acc);font-weight:700}
.web-list li b{color:var(--txt);font-weight:700}
.web-foot{font-size:12.5px;color:var(--txt3);border-top:1px solid var(--line);padding-top:14px;font-style:italic}
.web-note{display:flex;gap:14px;align-items:flex-start;margin-top:22px;background:var(--bg2);border:1px solid var(--line);border-radius:14px;padding:20px 22px}
.web-note-ic{font-size:24px;flex:0 0 auto}
.web-note p{margin:0;font-size:14px;color:var(--txt2);line-height:1.6}
.web-note b{color:var(--txt)}

/* savings calculator */
.calc{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px;margin-top:32px}
.calc-slider-head{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:12px}
.calc-slider-head span:first-child{font-family:'JetBrains Mono',monospace;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:var(--txt3)}
.calc-val{font-size:24px;font-weight:800;letter-spacing:-.03em;background:linear-gradient(120deg,var(--brand2),var(--brand));-webkit-background-clip:text;background-clip:text;color:transparent}
.calc-val.small{font-size:19px}
.calc-controls{display:grid;grid-template-columns:1.3fr 1fr;gap:20px;margin-top:22px;padding-top:22px;border-top:1px solid var(--line)}
@media(max-width:720px){.calc-controls{grid-template-columns:1fr;gap:18px}}
#rateSlider{-webkit-appearance:none;appearance:none;width:100%;height:8px;border-radius:5px;background:linear-gradient(90deg,#FF8A6B 0%,#FF8A6B var(--rpct,66%),var(--line) var(--rpct,66%),var(--line) 100%);outline:none;margin:4px 0}
#rateSlider::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:22px;height:22px;border-radius:50%;background:#fff;border:3px solid #FF8A6B;cursor:pointer;box-shadow:0 2px 10px rgba(255,138,107,.5);transition:transform .15s}
#rateSlider::-webkit-slider-thumb:hover{transform:scale(1.12)}
#rateSlider::-moz-range-thumb{width:22px;height:22px;border-radius:50%;background:#fff;border:3px solid #FF8A6B;cursor:pointer}
#rateSlider:focus-visible{outline:2px solid #FF8A6B;outline-offset:4px}
.calc-toggle{display:flex;flex-direction:column;justify-content:center}
.calc-toggle-label{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:var(--txt3);margin-bottom:10px}
.calc-toggle-btns{display:flex;gap:8px}
.calc-tgl{flex:1;font-size:13px;font-weight:700;padding:11px;border:1px solid var(--line);background:var(--bg);border-radius:9px;cursor:pointer;color:var(--txt2);font-family:inherit;transition:all .18s}
.calc-tgl.active{border-color:var(--brand);background:rgba(124,92,255,.12);color:var(--txt)}
.calc-note-payer{font-size:12px;line-height:1.5;color:var(--txt3);margin-top:12px;padding-top:12px;border-top:1px solid var(--line)}
.calc-note-payer b{color:var(--acc);font-weight:700}
.calc-note-payer.warn b{color:var(--warn)}
#salesSlider{-webkit-appearance:none;appearance:none;width:100%;height:8px;border-radius:5px;background:linear-gradient(90deg,var(--brand) 0%,var(--brand) var(--pct,10%),var(--line) var(--pct,10%),var(--line) 100%);outline:none;margin:4px 0}
#salesSlider::-webkit-slider-thumb{-webkit-appearance:none;appearance:none;width:22px;height:22px;border-radius:50%;background:#fff;border:3px solid var(--brand);cursor:pointer;box-shadow:0 2px 10px rgba(124,92,255,.5);transition:transform .15s}
#salesSlider::-webkit-slider-thumb:hover{transform:scale(1.12)}
#salesSlider::-moz-range-thumb{width:22px;height:22px;border-radius:50%;background:#fff;border:3px solid var(--brand);cursor:pointer}
#salesSlider:focus-visible{outline:2px solid var(--brand2);outline-offset:4px}
.calc-scale{display:flex;justify-content:space-between;font-family:'JetBrains Mono',monospace;font-size:9.5px;color:var(--txt3);margin-top:6px}
.calc-body{display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:28px}
@media(max-width:820px){.calc-body{grid-template-columns:1fr;gap:20px}}
.calc-col{background:var(--bg2);border:1px solid var(--line);border-radius:13px;padding:22px}
.calc-col-label{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--brand2);margin-bottom:18px}
.bar-row{margin-bottom:16px}
.bar-meta{display:flex;justify-content:space-between;align-items:baseline;font-size:13px;color:var(--txt2);margin-bottom:6px}
.bar-meta b{color:var(--txt);font-weight:700}
.bar-fig{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:600}
.bar-fig.red{color:#FF8A6B}.bar-fig.grn{color:var(--acc)}
.bar-track{height:14px;background:var(--bg);border-radius:7px;overflow:hidden}
.bar-fill{height:100%;border-radius:7px;transition:width .35s cubic-bezier(.16,1,.3,1)}
.bar-fill.red{background:linear-gradient(90deg,#FF6B6B,#FF9F45)}
.bar-fill.grn{background:linear-gradient(90deg,var(--brand),var(--acc))}
.calc-headline{margin-top:20px;padding-top:16px;border-top:1px solid var(--line);display:flex;flex-direction:column;gap:4px}
.calc-headline-k{font-size:12px;color:var(--txt3)}
.calc-headline-v{font-size:30px;font-weight:800;letter-spacing:-.03em;color:var(--acc)}
.calc-headline-v.amber{color:var(--warn)}
.speed-item{background:var(--bg);border:1px solid var(--line);border-radius:11px;padding:14px 16px;margin-bottom:12px}
.speed-item:last-child{margin-bottom:0}
.speed-item.grn-box{border-color:rgba(34,211,166,.32)}
.speed-line{display:flex;justify-content:space-between;align-items:baseline;margin-bottom:12px}
.speed-name{font-size:13px;font-weight:700;color:var(--txt)}
.speed-name.grn{color:var(--acc)}
.speed-days{font-size:17px;font-weight:800;letter-spacing:-.02em;color:var(--warn)}
.speed-days.grn{color:var(--acc)}
.speed-track{position:relative;height:8px;background:var(--card);border-radius:5px;margin-bottom:5px}
.speed-dot{position:absolute;top:50%;width:15px;height:15px;border-radius:50%;transform:translate(-50%,-50%);transition:left 1.2s cubic-bezier(.16,1,.3,1)}
.speed-dot.amber{background:var(--warn);box-shadow:0 0 0 4px rgba(255,176,32,.18)}
.speed-dot.grn{background:var(--acc);box-shadow:0 0 0 4px rgba(34,211,166,.2)}
.speed-dot.grn.pulsing{animation:dotPulse 1.8s ease-in-out infinite}
@keyframes dotPulse{0%,100%{box-shadow:0 0 0 4px rgba(34,211,166,.2)}50%{box-shadow:0 0 0 8px rgba(34,211,166,.05)}}
.speed-scale{display:flex;justify-content:space-between;font-family:'JetBrains Mono',monospace;font-size:9px;color:var(--txt3);margin-bottom:10px}
.speed-locked{font-size:11.5px;color:var(--txt3);padding-top:10px;border-top:1px solid var(--line)}
.speed-locked b{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:700}
.speed-locked b.amber{color:var(--warn)}
.speed-locked.grn-note{color:var(--acc);border-top-color:rgba(34,211,166,.22)}
@media(prefers-reduced-motion:reduce){.speed-dot,.bar-fill{transition:none}.speed-dot.grn.pulsing{animation:none}}

/* hero live map + notification feed */
.hero-stage{position:relative}
.sim-toggle{position:absolute;bottom:8px;right:8px;display:inline-flex;align-items:center;gap:7px;font-size:11px;font-weight:600;color:var(--txt2);background:rgba(18,18,31,.9);border:1px solid var(--line);border-radius:20px;padding:6px 12px;cursor:pointer;font-family:'JetBrains Mono',monospace;letter-spacing:.04em;backdrop-filter:blur(8px);transition:border-color .2s,color .2s;z-index:5}
.sim-toggle:hover{border-color:var(--brand);color:var(--txt)}
.sim-dot{width:8px;height:8px;border-radius:50%;background:var(--acc);box-shadow:0 0 0 3px rgba(34,211,166,.2)}
.sim-toggle.paused .sim-dot{background:var(--txt3);box-shadow:none}
@media(max-width:1120px){.sim-toggle{position:static;margin-top:12px;align-self:flex-start}}
#liveMap{width:100%;height:auto;display:block}
#liveMap rect[data-row]{transition:fill .45s ease,opacity .45s ease}
.livefeed{position:absolute;right:8px;top:5%;width:206px;display:flex;flex-direction:column;gap:8px;pointer-events:none}
@media(max-width:1240px){.livefeed{position:static;width:100%;flex-direction:row;flex-wrap:wrap;margin-top:16px}
  .livefeed .toast{flex:1 1 168px}}
.toast{
  background:rgba(18,18,31,.94);border:1px solid var(--line);border-radius:11px;
  padding:10px 12px;backdrop-filter:blur(8px);
  display:flex;gap:9px;align-items:flex-start;
  opacity:0;transform:translateX(16px);
  animation:toastIn .45s cubic-bezier(.16,1,.3,1) forwards;
  box-shadow:0 6px 20px rgba(0,0,0,.35);
}
.toast.out{animation:toastOut .4s ease forwards}
@keyframes toastIn{to{opacity:1;transform:none}}
@keyframes toastOut{to{opacity:0;transform:translateX(16px) scale(.96)}}
.toast .tdot{width:8px;height:8px;border-radius:50%;flex:0 0 8px;margin-top:5px}
.toast .tbody{min-width:0}
.toast .tmain{font-size:12px;font-weight:700;color:var(--txt);line-height:1.35}
.toast .tsub{font-family:'JetBrains Mono',monospace;font-size:9px;letter-spacing:.06em;color:var(--txt3);margin-top:3px}
.toast.cart{border-color:rgba(255,176,32,.4)}
.toast.cart .tdot{background:var(--warn)}
.toast.paid{border-color:rgba(34,211,166,.45)}
.toast.paid .tdot{background:var(--acc)}
.toast.sub{border-color:rgba(124,92,255,.45)}
.toast.sub .tdot{background:var(--brand)}
@media(prefers-reduced-motion:reduce){
  .toast{opacity:1;transform:none;animation:none}
  #liveMap rect[data-row]{transition:none}
}

/* savings cards */
.savings-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:22px}
@media(max-width:700px){.savings-cards{grid-template-columns:1fr}}
/* dynamic benefits */
.benefit-block{margin-top:26px;padding-top:22px;border-top:1px solid var(--line)}
.benefit-lead{font-size:15px;color:var(--txt2)}
.benefit-lead b{color:var(--acc);font-weight:800;letter-spacing:-.02em}
.benefit-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:16px}
@media(max-width:720px){.benefit-grid{grid-template-columns:1fr}}
.benefit-card{background:var(--bg2);border:1px solid var(--line);border-radius:11px;padding:16px;position:relative;overflow:hidden;transition:border-color .3s,transform .3s;animation:benefitIn .4s cubic-bezier(.16,1,.3,1)}
.benefit-card.hi{border-color:rgba(34,211,166,.35)}
.benefit-card .bemoji{font-size:22px;margin-bottom:7px}
.benefit-card h4{margin:0 0 4px;font-size:14px;color:var(--txt)}
.benefit-card p{font-size:12.5px;margin:0;color:var(--txt2)}
.benefit-card .betag{position:absolute;top:12px;right:12px;font-family:'JetBrains Mono',monospace;font-size:8.5px;letter-spacing:.08em;color:var(--brand2);background:rgba(124,92,255,.12);border-radius:20px;padding:2px 8px}
@keyframes benefitIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}
@media(prefers-reduced-motion:reduce){.benefit-card{animation:none}}

/* speed section */
.speed-grid{display:grid;grid-template-columns:1fr 1fr;gap:40px;margin-top:38px;align-items:start}
@media(max-width:900px){.speed-grid{grid-template-columns:1fr;gap:34px}}
.speed-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:38px}
@media(max-width:760px){.speed-stats{grid-template-columns:repeat(2,1fr)}}
.sstat{background:var(--card);border:1px solid var(--line);border-radius:13px;padding:20px 18px;text-align:center;transition:border-color .3s}
.sstat:hover{border-color:var(--brand)}
.sstat .v{font-size:27px;font-weight:800;letter-spacing:-.04em;background:linear-gradient(140deg,var(--acc),var(--brand2));-webkit-background-clip:text;background-clip:text;color:transparent}
.sstat .k{font-family:'JetBrains Mono',monospace;font-size:9px;letter-spacing:.14em;text-transform:uppercase;color:var(--txt3);margin-top:7px}

/* CTA */
.cta-band{position:relative;overflow:hidden}
.cta-band::before{content:"";position:absolute;inset:0;background:radial-gradient(58% 90% at 80% 20%,var(--glow),transparent 70%);pointer-events:none}
.cta-inner{position:relative;z-index:2;display:grid;grid-template-columns:1.25fr .75fr;gap:44px;align-items:center}
@media(max-width:860px){.cta-inner{grid-template-columns:1fr;gap:30px}}
.cta-actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:26px}
.btn{display:inline-flex;align-items:center;gap:9px;padding:14px 24px;border-radius:11px;font-size:14.5px;font-weight:700;text-decoration:none;transition:transform .2s,box-shadow .25s,background .25s,border-color .25s}
.btn:focus-visible{outline:2px solid var(--brand2);outline-offset:3px}
.btn-primary{background:linear-gradient(120deg,var(--brand),#5B8CFF);color:#fff;box-shadow:0 6px 22px rgba(124,92,255,.3)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(124,92,255,.42)}
.btn-ghost{background:transparent;color:var(--txt);border:1px solid var(--line)}
.btn-ghost:hover{border-color:var(--brand);background:rgba(124,92,255,.08);transform:translateY(-2px)}
.cta-fine{font-size:13px;color:var(--txt3);margin-top:16px;margin-bottom:0}
.rep-card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:28px 26px;text-align:center}
.rep-avatar{width:66px;height:66px;border-radius:50%;margin:0 auto 16px;background:linear-gradient(135deg,var(--brand),#5B8CFF);display:grid;place-items:center;font-size:22px;font-weight:800;color:#fff;letter-spacing:-.03em}
.rep-name{font-size:18px;font-weight:800;letter-spacing:-.02em}
.rep-role{font-family:'JetBrains Mono',monospace;font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:var(--brand2);margin-top:6px}
.rep-links{margin-top:18px;padding-top:16px;border-top:1px solid var(--line);display:flex;flex-direction:column;gap:8px}
.rep-links a{color:var(--txt2);text-decoration:none;font-size:13px;transition:color .25s}
.rep-links a:hover{color:var(--brand2)}

/* progress bar */
#prog{position:fixed;top:0;left:0;height:3px;width:0;background:linear-gradient(90deg,var(--brand),var(--acc));z-index:999;transition:width .1s linear}

@media(max-width:640px){
  .wrap,.hero .wrap{padding-left:18px;padding-right:18px}
  .cta-actions{flex-direction:column;align-items:stretch}
  .btn{justify-content:center;width:100%}
  .livefeed{gap:6px}
  .speed-stats{gap:10px}
  .deal-grid{grid-template-columns:1fr;gap:20px}
  .deal-num{border-right:none;border-bottom:1px solid var(--line);padding-right:0;padding-bottom:14px}
  td:first-child{width:auto}
}
@media print{
  *{-webkit-print-color-adjust:exact;print-color-adjust:exact}
  body{background:#fff;color:#111}
  /* print only the one-page summary */
  body > *{display:none !important}
  body > .tldr{display:block !important}
  .tldr{border:none;padding:0}
  .tldr .wrap{max-width:100%;padding:0}
  .tldr-print{display:none !important}
  .tldr::before{content:"TIXELLO · Rezumat pentru decizie — platformă de ticketing pentru teatre";display:block;font-size:13px;font-weight:800;color:#7C5CFF;border-bottom:2px solid #7C5CFF;padding-bottom:8px;margin-bottom:18px;letter-spacing:-.01em}
  .tldr-head{margin-bottom:16px}
  .tldr-head .eyebrow{color:#7C5CFF}
  .tldr-head h2{color:#111;font-size:22px}
  .tldr-grid{grid-template-columns:1fr 1fr;gap:12px}
  .tldr-item{background:#fff;border:1px solid #ccc;padding:14px;break-inside:avoid}
  .tldr-n{color:#7C5CFF}
  .tldr-item b{color:#111}
  .tldr-item p{color:#333}
  .tldr::after{content:"1% comision · configurare în 24h · demo pe teatru.tixello.ro · Andrei Năstase, Fondator Tixello";display:block;margin-top:18px;padding-top:10px;border-top:1px solid #ccc;font-size:11px;color:#555}
  .rv,.rv-l,.rv-r,.rv-s{opacity:1!important;transform:none!important}
  .fi,.pop{opacity:1!important;transform:none!important}
}
</style>
</head>
<body>
<div id="prog"></div>

<!-- HERO -->
<header class="hero">
  <div class="wrap">
    <div class="logo rv in"><span class="logo-mark">tx</span>Tixello</div>
    <div class="hero-grid">
      <div>
        <div class="eyebrow rv" data-d="1">Verticala Teatru · Instituții publice de cultură</div>
        <h1 class="rv" data-d="2">Scena e a ta.<br><span class="grad">Noi gestionăm restul.</span></h1>
        <p class="lede rv" data-d="3">Hărți de sală cu locuri numerotate, abonamente de stagiune cu loc fix, spectacole recurente, eFactura către ANAF — și doar 1% comision, ca fiecare leu să ajungă unde trebuie: în artă.</p>
        <div class="pills">
          <span class="pill rv" data-d="4">💺 Selecție locuri</span>
          <span class="pill rv" data-d="4">🎫 Abonamente stagiune</span>
          <span class="pill rv" data-d="5">🔄 Spectacole recurente</span>
          <span class="pill rv" data-d="5">💰 Doar <b>1%</b> comision</span>
          <span class="pill rv" data-d="6">👥 Distribuție automată</span>
          <span class="pill rv" data-d="6">🔍 SEO automat</span>
          <span class="pill rv" data-d="7">📄 eFactura integrat</span>
          <span class="pill rv" data-d="7">🎭 Carduri culturale</span>
        </div>
      </div>
      <div class="hero-stage rv-r" data-d="3">
        <svg id="liveMap" viewBox="0 0 560 342" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Simulare de vânzări în timp real pe harta unei săli de teatru">
  <defs>
    <radialGradient id="hGlow" cx="50%" cy="0%" r="80%">
      <stop offset="0%" stop-color="#7C5CFF" stop-opacity=".45"/>
      <stop offset="60%" stop-color="#7C5CFF" stop-opacity=".08"/>
      <stop offset="100%" stop-color="#7C5CFF" stop-opacity="0"/>
    </radialGradient>
    <linearGradient id="hBar" x1="0" y1="0" x2="1" y2="0">
      <stop offset="0%" stop-color="#5B8CFF"/><stop offset="50%" stop-color="#7C5CFF"/><stop offset="100%" stop-color="#5B8CFF"/>
    </linearGradient>
  </defs>
  <ellipse cx="280.0" cy="30" rx="230" ry="60" fill="url(#hGlow)"/>
  <path d="M120 22 Q280.0 2 440 22 L440 38 Q280.0 16 120 38 Z" fill="url(#hBar)" opacity=".9"/>
  <text x="280.0" y="35" fill="#F4F4F8" font-family="JetBrains Mono,monospace" font-size="9.5" text-anchor="middle" letter-spacing="4">SCENA</text>
  <g id="seatLayer">
    <rect id="s0" data-row="A" data-seat="1" x="102" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s1" data-row="A" data-seat="2" x="122" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s2" data-row="A" data-seat="3" x="142" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s3" data-row="A" data-seat="4" x="162" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s4" data-row="A" data-seat="5" x="182" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s5" data-row="A" data-seat="6" x="202" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s6" data-row="A" data-seat="7" x="222" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s7" data-row="A" data-seat="8" x="242" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s8" data-row="A" data-seat="9" x="262" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s9" data-row="A" data-seat="10" x="282" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s10" data-row="A" data-seat="11" x="302" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s11" data-row="A" data-seat="12" x="322" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s12" data-row="A" data-seat="13" x="342" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s13" data-row="A" data-seat="14" x="362" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s14" data-row="A" data-seat="15" x="382" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s15" data-row="A" data-seat="16" x="402" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s16" data-row="A" data-seat="17" x="422" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s17" data-row="A" data-seat="18" x="442" y="62" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s18" data-row="B" data-seat="1" x="92" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s19" data-row="B" data-seat="2" x="112" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s20" data-row="B" data-seat="3" x="132" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s21" data-row="B" data-seat="4" x="152" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s22" data-row="B" data-seat="5" x="172" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s23" data-row="B" data-seat="6" x="192" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s24" data-row="B" data-seat="7" x="212" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s25" data-row="B" data-seat="8" x="232" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s26" data-row="B" data-seat="9" x="252" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s27" data-row="B" data-seat="10" x="272" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s28" data-row="B" data-seat="11" x="292" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s29" data-row="B" data-seat="12" x="312" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s30" data-row="B" data-seat="13" x="332" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s31" data-row="B" data-seat="14" x="352" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s32" data-row="B" data-seat="15" x="372" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s33" data-row="B" data-seat="16" x="392" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s34" data-row="B" data-seat="17" x="412" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s35" data-row="B" data-seat="18" x="432" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s36" data-row="B" data-seat="19" x="452" y="82" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s37" data-row="C" data-seat="1" x="82" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s38" data-row="C" data-seat="2" x="102" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s39" data-row="C" data-seat="3" x="122" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s40" data-row="C" data-seat="4" x="142" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s41" data-row="C" data-seat="5" x="162" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s42" data-row="C" data-seat="6" x="182" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s43" data-row="C" data-seat="7" x="202" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s44" data-row="C" data-seat="8" x="222" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s45" data-row="C" data-seat="9" x="242" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s46" data-row="C" data-seat="10" x="262" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s47" data-row="C" data-seat="11" x="282" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s48" data-row="C" data-seat="12" x="302" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s49" data-row="C" data-seat="13" x="322" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s50" data-row="C" data-seat="14" x="342" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s51" data-row="C" data-seat="15" x="362" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s52" data-row="C" data-seat="16" x="382" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s53" data-row="C" data-seat="17" x="402" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s54" data-row="C" data-seat="18" x="422" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s55" data-row="C" data-seat="19" x="442" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s56" data-row="C" data-seat="20" x="462" y="102" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s57" data-row="D" data-seat="1" x="82" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s58" data-row="D" data-seat="2" x="102" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s59" data-row="D" data-seat="3" x="122" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s60" data-row="D" data-seat="4" x="142" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s61" data-row="D" data-seat="5" x="162" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s62" data-row="D" data-seat="6" x="182" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s63" data-row="D" data-seat="7" x="202" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s64" data-row="D" data-seat="8" x="222" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s65" data-row="D" data-seat="9" x="242" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s66" data-row="D" data-seat="10" x="262" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s67" data-row="D" data-seat="11" x="282" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s68" data-row="D" data-seat="12" x="302" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s69" data-row="D" data-seat="13" x="322" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s70" data-row="D" data-seat="14" x="342" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s71" data-row="D" data-seat="15" x="362" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s72" data-row="D" data-seat="16" x="382" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s73" data-row="D" data-seat="17" x="402" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s74" data-row="D" data-seat="18" x="422" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s75" data-row="D" data-seat="19" x="442" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s76" data-row="D" data-seat="20" x="462" y="122" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s77" data-row="E" data-seat="1" x="72" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s78" data-row="E" data-seat="2" x="92" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s79" data-row="E" data-seat="3" x="112" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s80" data-row="E" data-seat="4" x="132" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s81" data-row="E" data-seat="5" x="152" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s82" data-row="E" data-seat="6" x="172" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s83" data-row="E" data-seat="7" x="192" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s84" data-row="E" data-seat="8" x="212" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s85" data-row="E" data-seat="9" x="232" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s86" data-row="E" data-seat="10" x="252" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s87" data-row="E" data-seat="11" x="272" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s88" data-row="E" data-seat="12" x="292" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s89" data-row="E" data-seat="13" x="312" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s90" data-row="E" data-seat="14" x="332" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s91" data-row="E" data-seat="15" x="352" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s92" data-row="E" data-seat="16" x="372" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s93" data-row="E" data-seat="17" x="392" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s94" data-row="E" data-seat="18" x="412" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s95" data-row="E" data-seat="19" x="432" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s96" data-row="E" data-seat="20" x="452" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s97" data-row="E" data-seat="21" x="472" y="142" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s98" data-row="F" data-seat="1" x="72" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s99" data-row="F" data-seat="2" x="92" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s100" data-row="F" data-seat="3" x="112" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s101" data-row="F" data-seat="4" x="132" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s102" data-row="F" data-seat="5" x="152" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s103" data-row="F" data-seat="6" x="172" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s104" data-row="F" data-seat="7" x="192" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s105" data-row="F" data-seat="8" x="212" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s106" data-row="F" data-seat="9" x="232" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s107" data-row="F" data-seat="10" x="252" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s108" data-row="F" data-seat="11" x="272" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s109" data-row="F" data-seat="12" x="292" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s110" data-row="F" data-seat="13" x="312" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s111" data-row="F" data-seat="14" x="332" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s112" data-row="F" data-seat="15" x="352" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s113" data-row="F" data-seat="16" x="372" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s114" data-row="F" data-seat="17" x="392" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s115" data-row="F" data-seat="18" x="412" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s116" data-row="F" data-seat="19" x="432" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s117" data-row="F" data-seat="20" x="452" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
    <rect id="s118" data-row="F" data-seat="21" x="472" y="162" width="15" height="12" rx="3" fill="#22D3A6" opacity=".55"/>
  </g>
  <path d="M110 194 Q280.0 186 450 194" stroke="#26263C" stroke-width="1" fill="none" stroke-dasharray="4 5"/>
  <text x="280.0" y="210" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle" letter-spacing="2">BALCON</text>
  <g id="balLayer">
    <rect id="s119" data-row="B1" data-seat="1" x="62" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s120" data-row="B1" data-seat="2" x="82" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s121" data-row="B1" data-seat="3" x="102" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s122" data-row="B1" data-seat="4" x="122" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s123" data-row="B1" data-seat="5" x="142" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s124" data-row="B1" data-seat="6" x="162" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s125" data-row="B1" data-seat="7" x="182" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s126" data-row="B1" data-seat="8" x="202" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s127" data-row="B1" data-seat="9" x="222" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s128" data-row="B1" data-seat="10" x="242" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s129" data-row="B1" data-seat="11" x="262" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s130" data-row="B1" data-seat="12" x="282" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s131" data-row="B1" data-seat="13" x="302" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s132" data-row="B1" data-seat="14" x="322" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s133" data-row="B1" data-seat="15" x="342" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s134" data-row="B1" data-seat="16" x="362" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s135" data-row="B1" data-seat="17" x="382" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s136" data-row="B1" data-seat="18" x="402" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s137" data-row="B1" data-seat="19" x="422" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s138" data-row="B1" data-seat="20" x="442" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s139" data-row="B1" data-seat="21" x="462" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s140" data-row="B1" data-seat="22" x="482" y="220" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s141" data-row="B2" data-seat="1" x="62" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s142" data-row="B2" data-seat="2" x="82" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s143" data-row="B2" data-seat="3" x="102" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s144" data-row="B2" data-seat="4" x="122" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s145" data-row="B2" data-seat="5" x="142" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s146" data-row="B2" data-seat="6" x="162" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s147" data-row="B2" data-seat="7" x="182" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s148" data-row="B2" data-seat="8" x="202" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s149" data-row="B2" data-seat="9" x="222" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s150" data-row="B2" data-seat="10" x="242" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s151" data-row="B2" data-seat="11" x="262" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s152" data-row="B2" data-seat="12" x="282" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s153" data-row="B2" data-seat="13" x="302" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s154" data-row="B2" data-seat="14" x="322" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s155" data-row="B2" data-seat="15" x="342" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s156" data-row="B2" data-seat="16" x="362" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s157" data-row="B2" data-seat="17" x="382" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s158" data-row="B2" data-seat="18" x="402" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s159" data-row="B2" data-seat="19" x="422" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s160" data-row="B2" data-seat="20" x="442" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s161" data-row="B2" data-seat="21" x="462" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
    <rect id="s162" data-row="B2" data-seat="22" x="482" y="240" width="15" height="12" rx="3" fill="#22D3A6" opacity=".42"/>
  </g>
  <g>
    <rect x="0" y="282" width="560" height="1" fill="#26263C"/>
    <text x="0" y="308" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5">VÂNDUTE ACUM</text>
    <text id="cSold" x="0" y="330" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="21" font-weight="800">0</text>
    <text x="150" y="308" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5">ÎN COȘ</text>
    <text id="cCart" x="150" y="330" fill="#FFB020" font-family="Plus Jakarta Sans,sans-serif" font-size="21" font-weight="800">0</text>
    <text x="280" y="308" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5">GRAD DE OCUPARE</text>
    <text id="cOcc" x="280" y="330" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="21" font-weight="800">0%</text>
    <circle cx="472" cy="322" r="4" fill="#22D3A6" class="anim-blink"/>
    <text x="484" y="326" fill="#22D3A6" font-family="JetBrains Mono,monospace" font-size="8.5">LIVE</text>
  </g>
</svg>
        <div class="livefeed" id="livefeed" aria-live="polite" aria-label="Activitate în timp real"></div>
        <button class="sim-toggle" id="simToggle" aria-pressed="true" aria-label="Oprește sau pornește simularea live">
          <span class="sim-dot"></span><span id="simLabel">Simulare live</span>
        </button>
      </div>
    </div>
    <div class="hero-meta rv" data-d="8">
      <span>Fără taxe lunare</span><span>Configurare în 24h</span><span>Suport dedicat</span>
      
    </div>
  </div>
</header>

<!-- STATS -->
<div class="stats rv">
  <div class="stat"><div class="v" data-count="24" data-suffix="h">24h</div><div class="k">Configurare</div></div>
  <div class="stat"><div class="v" data-count="180" data-suffix="+">180+</div><div class="k">Funcționalități</div></div>
  <div class="stat"><div class="v" data-count="5" data-suffix="K+">5K+</div><div class="k">Integrări</div></div>
  <div class="stat"><div class="v" data-count="1" data-suffix="%">1%</div><div class="k">Comision</div></div>
</div>

<!-- DEAL -->
<div class="deal">
  <div class="wrap deal-grid rv">
    <div class="deal-num">1%<span>Comision unic</span></div>
    <p><strong>Tixello costă 1% din valoarea biletelor vândute.</strong> Comisionul poate fi inclus în prețul biletului — suportat de teatru — sau adăugat peste preț la checkout, caz în care este suportat de spectator. Decizia aparține instituției și se poate schimba oricând. Fără abonament lunar, fără taxă de implementare, fără costuri ascunse.<br><br><span class="deal-example">Pe scurt: la <b>300.000 lei</b> vânzări pe stagiune, plătești <b>3.000 lei</b> — atât, total.</span></p>
  </div>
</div>

<!-- TLDR -->
<section class="tldr" id="tldr">
  <div class="wrap">
    <div class="tldr-head rv">
      <div>
        <div class="eyebrow">Dacă citești un singur lucru</div>
        <h2>Rezumat pentru decizie</h2>
      </div>
      <button class="tldr-print" onclick="window.print()" aria-label="Printează rezumatul">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v8H6z"/></svg>
        Printează / Salvează PDF
      </button>
    </div>
    <div class="tldr-grid">
      <div class="tldr-item rv" data-d="1"><span class="tldr-n">01</span><div><b>Control total și banii instant</b><p>Sala, prețurile, clienții și rapoartele rămân ale teatrului. Încasările intră direct în contul instituției la fiecare vânzare, fără intermediar.</p></div></div>
      <div class="tldr-item rv" data-d="2"><span class="tldr-n">02</span><div><b>Un singur procent, transparent</b><p>1% comision, fără abonament sau taxe ascunse. Cel mai mic din piață — vezi calculatorul cu comisionul platformei tale actuale.</p></div></div>
      <div class="tldr-item rv" data-d="3"><span class="tldr-n">03</span><div><b>Făcut pentru teatru, nu generic</b><p>Hartă de sală cu locuri numerotate, abonamente de stagiune cu loc fix, invitații de protocol, eFactura către ANAF.</p></div></div>
      <div class="tldr-item rv" data-d="4"><span class="tldr-n">04</span><div><b>Tranziție fără oprirea vânzării</b><p>Abonamentele și datele istorice se preiau. Rulare în paralel, comutare între reprezentații, plan de retragere clar.</p></div></div>
      <div class="tldr-item rv" data-d="5"><span class="tldr-n">05</span><div><b>Instituție publică, acoperită</b><p>Roluri și permisiuni pentru control intern, protecție ePas anti-fraudă la revânzare, rapoarte financiare, go-live în 24h.</p></div></div>
      <div class="tldr-item rv" data-d="6"><span class="tldr-n">✓</span><div><b>Fără obligații</b><p>Demo live pe teatru.tixello.ro sau o demonstrație de 30 de minute pe sala reală a teatrului tău.</p></div></div>
    </div>
  </div>
</section>

<!-- TRUST -->
<section class="trust">
  <div class="wrap">
    <div class="trust-lead rv">
      <span class="eyebrow">Pe ce se sprijină</span>
      <p>Tixello nu e un experiment. E o platformă deja în funcțiune, cu cifre reale procesate pentru organizatori din România — nu ești primul care o folosește.</p>
    </div>

    <div class="proof rv" data-d="1">
      <div class="proof-tag">Procesate prin platforma Tixello, până acum</div>
      <div class="proof-grid">
        <div class="proof-item"><div class="proof-v" data-count="4507">4.507</div><div class="proof-k">Evenimente</div></div>
        <div class="proof-item"><div class="proof-v" data-count="100425">100.425</div><div class="proof-k">Cumpărători unici</div></div>
        <div class="proof-item"><div class="proof-v" data-count="324687">324.687</div><div class="proof-k">Bilete vândute</div></div>
        <div class="proof-item wide"><div class="proof-v">24.473.478 <span class="proof-cur">RON</span></div><div class="proof-k">Volum total procesat (GMV) · ≈ 4.674.972 EUR</div></div>
      </div>
    </div>

    <div class="trust-grid rv" data-d="1">
      <div class="trust-item"><b>Ecosistem propriu</b><p>AmBilet.ro, bilete.online și comparatorul TICS.ro — produse Tixello, active pe piața locală.</p></div>
      <div class="trust-item"><b>Plăți reale, autorizate</b><p>Netopia (mobilPay), EuPlătesc, PayU, Stripe, plus carduri culturale Edenred, Pluxee, Up România.</p></div>
      <div class="trust-item"><b>Conform fiscal în România</b><p>eFactura către ANAF prin SPV, serii de facturi, conectori de contabilitate — nu adaptări improvizate.</p></div>
      <div class="trust-item"><b>Anti-fraudă independent</b><p>Fiecare bilet, semnat în registrul public ePas (epas.ro) — verificabil de oricine, fără date personale.</p></div>
    </div>
    <p class="trust-fine rv" data-d="2">Vrei să vorbești cu un teatru care folosește deja platforma? La o demonstrație, îți pot pune legătura cu o referință relevantă pentru instituția ta.</p>
  </div>
</section>

<!-- WEB OFFER -->
<section>
  <div class="wrap">
    <div class="web-badge rv">Inclus, fără cost suplimentar</div>
    <h2 class="rv" data-d="1">Prezența online a teatrului, <span class="grad">construită de noi. Gratuit.</span></h2>
    <p class="lede2 rv" data-d="2">Nu trebuie să plătești o agenție web și nici să te lupți cu un furnizor de site. Tixello îți pune la dispoziție tot ce ține de vânzarea online — și, dacă vrei, chiar întregul site al teatrului. Fără taxă de construcție, fără abonament: intră în cei 1%.</p>

    <div class="web-grid">
      <div class="web-card rv" data-d="1">
        <div class="web-card-top">
          <span class="web-card-tag">Varianta 1</span>
          <span class="web-free">GRATUIT</span>
        </div>
        <h3>Pagina de vânzare, completă</h3>
        <p class="web-card-lead">Integrezi vânzarea în site-ul pe care îl ai deja. Îți dăm tot fluxul de achiziție, gata făcut:</p>
        <ul class="web-list">
          <li>Coș de cumpărături și checkout complet</li>
          <li>Finalizare comandă cu plată online</li>
          <li>Cont de client: istoric bilete, abonamente, facturi</li>
          <li>Autentificare și înregistrare</li>
          <li>Hartă de sală cu selecția locului</li>
          <li>Bilet cu QR pe email și în Wallet</li>
        </ul>
        <div class="web-foot">Ideal dacă teatrul are deja un site și vrea doar partea de bilete.</div>
      </div>

      <div class="web-card featured rv" data-d="2">
        <div class="web-card-top">
          <span class="web-card-tag">Varianta 2</span>
          <span class="web-free">GRATUIT</span>
        </div>
        <h3>Tot site-ul teatrului</h3>
        <p class="web-card-lead">Nu doar vânzarea — întreg site-ul, sub domeniul și brandul teatrului. Îl construim și îl întreținem noi:</p>
        <ul class="web-list">
          <li><b>Tot ce include Varianta 1</b>, plus:</li>
          <li>Site public complet: stagiune, spectacole, distribuție</li>
          <li>Pagini de spectacol optimizate SEO</li>
          <li>Domeniu propriu, cu temă adaptată identității</li>
          <li>Modul de blog și noutăți</li>
          <li>Actualizări și găzduire incluse</li>
        </ul>
        <div class="web-foot">Ideal dacă teatrul nu are site sau vrea unul nou, fără bătaie de cap.</div>
      </div>
    </div>

    <div class="web-note rv" data-d="3">
      <span class="web-note-ic">🎁</span>
      <p><b>De ce gratuit?</b> Pentru că modelul Tixello e simplu: câștigăm din cele 1% pe bilet, deci ne interesează ca teatrul să vândă cât mai bine. Un site bun și un checkout fără fricțiune înseamnă mai multe bilete — pentru tine și pentru noi. Nu e un cost pe care ți-l ascundem; e parte din cum funcționează parteneriatul.</p>
    </div>
  </div>
</section>


<!-- SAVINGS -->
<section>
  <div class="wrap">
    <div class="eyebrow rv">Matematica economiilor</div>
    <h2 class="rv" data-d="1">Cât economisești și <span class="grad">cât de repede primești banii.</span></h2>
    <p class="lede2 rv" data-d="2">Mișcă valoarea vânzărilor pe o stagiune și vezi în timp real economia la comision — plus diferența care contează cel mai des: la Tixello banii intră instant, la alte platforme aștepți între 3 și 30 de zile.</p>

    <div class="calc rv-s" data-d="2">
      <div class="calc-slider">
        <div class="calc-slider-head">
          <span>Vânzări pe stagiune</span>
          <span class="calc-val" id="calcVal">100.000 RON</span>
        </div>
        <input type="range" id="salesSlider" min="20000" max="1000000" step="10000" value="100000" aria-label="Vânzări pe stagiune">
        <div class="calc-scale"><span>20.000</span><span>500.000</span><span>1.000.000 RON</span></div>
      </div>

      <div class="calc-controls">
        <div class="calc-slider">
          <div class="calc-slider-head">
            <span>Comisionul platformei tale actuale</span>
            <span class="calc-val small" id="rateVal">8%</span>
          </div>
          <input type="range" id="rateSlider" min="0" max="12" step="0.5" value="8" aria-label="Comisionul actual">
          <div class="calc-scale"><span>0%</span><span>6%</span><span>12%</span></div>
        </div>
        <div class="calc-toggle">
          <span class="calc-toggle-label">La platforma actuală, comisionul e suportat de:</span>
          <div class="calc-toggle-btns" role="group" aria-label="Cine suportă comisionul">
            <button class="calc-tgl active" data-payer="theatre">Teatru</button>
            <button class="calc-tgl" data-payer="buyer">Spectator</button>
          </div>
        </div>
      </div>

      <div class="calc-body">
        <!-- LEFT: commission savings -->
        <div class="calc-col">
          <div class="calc-col-label">Comision reținut de platformă</div>
          <div class="bar-row">
            <div class="bar-meta"><span>Platforma ta <b id="otherPct">8%</b></span><span class="bar-fig red" id="otherFee">8.000 RON</span></div>
            <div class="bar-track"><div class="bar-fill red" id="otherBar"></div></div>
          </div>
          <div class="bar-row">
            <div class="bar-meta"><span>Tixello <b>1%</b></span><span class="bar-fig grn" id="tixFee">1.000 RON</span></div>
            <div class="bar-track"><div class="bar-fill grn" id="tixBar"></div></div>
          </div>
          <div class="calc-headline">
            <span class="calc-headline-k" id="saveLabel">Economisești pe stagiune</span>
            <span class="calc-headline-v" id="saveVal">+7.000 RON</span>
          </div>
          <div class="calc-note-payer" id="payerNote"></div>
        </div>

        <!-- RIGHT: money speed -->
        <div class="calc-col">
          <div class="calc-col-label">Când ajung banii la tine</div>
          <div class="speed-item">
            <div class="speed-line">
              <span class="speed-name">Alte platforme</span>
              <span class="speed-days">3–30 zile</span>
            </div>
            <div class="speed-track"><div class="speed-dot amber" id="dotOther"></div></div>
            <div class="speed-scale"><span>azi</span><span>ziua 30</span></div>
            <div class="speed-locked">În tranzit, în medie <b class="amber" id="lockedVal">~5.400 RON</b></div>
          </div>
          <div class="speed-item grn-box">
            <div class="speed-line">
              <span class="speed-name grn">Tixello</span>
              <span class="speed-days grn">Instant</span>
            </div>
            <div class="speed-track"><div class="speed-dot grn" id="dotTix"></div></div>
            <div class="speed-scale"><span>azi</span><span>ziua 30</span></div>
            <div class="speed-locked grn-note">Banii intră în contul teatrului la fiecare vânzare · 0 RON blocați</div>
          </div>
        </div>
      </div>

      <div class="benefit-block">
        <div class="benefit-head">
          <span class="benefit-lead">Cu economia de <b id="benefitSum">7.000 RON</b> pe stagiune poți acoperi, de exemplu:</span>
        </div>
        <div class="benefit-grid" id="benefitGrid"></div>
      </div>
    </div>
  </div>
</section>

<!-- 8 CARDS -->
<section class="alt">
  <div class="wrap">
    <div class="eyebrow rv">Rezumat executiv</div>
    <h2 class="rv" data-d="1">Ce contează cel mai mult</h2>
    <p class="lede2 rv" data-d="2">Pentru un teatru de stat, cinci lucruri decid înaintea tuturor celorlalte: controlul total, banii instant, integrarea cu orice sistem, funcționarea pe orice dispozitiv și costul redus de promovare. Apoi vin toate celelalte.</p>
    <div class="cards">
      <div class="card rv" data-d="1"><div class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#A78BFA" stroke-width="2" stroke-linecap="round"><path d="M12 2 3 6v6c0 5 3.5 8 9 10 5.5-2 9-5 9-10V6z"/><path d="m9 12 2 2 4-4"/></svg></div><h4>Control total și toate datele</h4><p>Sală, prețuri, abonamente, clienți, rapoarte — totul într-un singur loc, al teatrului.</p></div>
      <div class="card rv" data-d="2"><div class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#22D3A6" stroke-width="2" stroke-linecap="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div><h4>Banii intră instant</h4><p>Încasările ajung direct în contul teatrului la fiecare vânzare. Fără intermediere.</p></div>
      <div class="card rv" data-d="3"><div class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#A78BFA" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v4M12 18v4M2 12h4M18 12h4M5 5l3 3M16 16l3 3M19 5l-3 3M8 16l-3 3"/></svg></div><h4>Integrări cu orice sistem</h4><p>ANAF, contabilitate, plăți, CRM, ads, 5.000+ integrări prin Zapier și API.</p></div>
      <div class="card rv" data-d="4"><div class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#A78BFA" stroke-width="2" stroke-linecap="round"><rect x="2" y="4" width="14" height="10" rx="2"/><rect x="16" y="8" width="6" height="12" rx="2"/><path d="M2 18h10"/></svg></div><h4>Funcționează pe orice dispozitiv</h4><p>Desktop, tabletă, telefon. Casierie, control acces, administrare — din browser.</p></div>
      <div class="card rv" data-d="5"><div class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#22D3A6" stroke-width="2" stroke-linecap="round"><path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/></svg></div><h4>Costuri de ads mai eficiente</h4><p>Prin Facebook CAPI și Google Ads server-side, campaniile pot deveni semnificativ mai eficiente — până la ordinul zecilor de procente, în funcție de campanie.</p></div>
      <div class="card rv" data-d="6"><div class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#A78BFA" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="7" rx="2"/><rect x="3" y="14" width="8" height="6" rx="2"/><rect x="13" y="14" width="8" height="6" rx="2"/></svg></div><h4>Control total asupra sălii</h4><p>Locuri numerotate, categorii de preț, hartă interactivă.</p></div>
    </div>
  </div>
</section>

<div class="part">
  <div class="wrap">
    <div class="n rv">PARTEA I</div>
    <h2 class="rv" data-d="1">Capabilitățile în detaliu</h2>
    <p class="rv" data-d="2">Fiecare funcționalitate decisivă pentru un teatru, explicată pe larg. Sunt cele pe care se sprijină decizia de implementare.</p>
  </div>
</div>

<section style="padding-top:16px">
  <div class="wrap">

    <!-- 01 -->
    <div class="feat">
      <div class="rv-l">
        <div class="ftag"><i></i>01 — SALA</div>
        <h3>Locuri numerotate și harta interactivă</h3>
        <p>Inima unui sistem de ticketing pentru teatru. Tixello gestionează sala la nivel de loc individual.</p>
        <ul>
          <li><strong>Hartă vizuală a sălii</strong> — parter, balcon, loje. Spectatorul își alege locul, exact ca la casă.</li>
          <li><strong>Secțiuni → rânduri → locuri</strong>, fiecare cu identificator unic.</li>
          <li><strong>Categorii de preț</strong> mapate pe zone: Loja I, Balcon, Parter Cat. I/II, VIP.</li>
          <li><strong>Locuri accesibile marcate</strong> distinct pentru persoane cu dizabilități.</li>
          <li><strong>Rezervare temporară</strong> — locul e blocat 10 minute pentru plată. Elimină dubla vânzare.</li>
          <li><strong>Actualizare în timp real</strong> — locurile ocupate dispar instant din harta celorlalți.</li>
          <li><strong>Import hartă din SVG</strong> — planul existent se importă, nu se desenează de la zero.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> este singura modalitate corectă de a vinde o sală cu locuri fixe. Permite politici de preț pe zone și susține abonamentele cu loc rezervat.</div>
      </div>
      <div class="fig rv-r">
        <svg viewBox="0 0 420 474" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Hartă interactivă a sălii: scenă, loje, cinci rânduri de parter, două rânduri de balcon, cu locuri libere, ocupate, selectate și accesibile">
  <defs>
    <radialGradient id="sgGlow" cx="50%" cy="0%" r="85%"><stop offset="0%" stop-color="#7C5CFF" stop-opacity=".5"/><stop offset="55%" stop-color="#7C5CFF" stop-opacity=".1"/><stop offset="100%" stop-color="#7C5CFF" stop-opacity="0"/></radialGradient>
    <linearGradient id="sgBar" x1="0" y1="0" x2="1" y2="0"><stop offset="0%" stop-color="#5B8CFF"/><stop offset="50%" stop-color="#7C5CFF"/><stop offset="100%" stop-color="#5B8CFF"/></linearGradient>
  </defs>
  <ellipse cx="210" cy="30" rx="180" ry="60" fill="url(#sgGlow)" class="fi"/>
  <path d="M70 22 Q210 4 350 22 L350 40 Q210 18 70 40 Z" fill="url(#sgBar)" opacity=".9" class="pop"/>
  <text x="210" y="35" fill="#F4F4F8" font-family="JetBrains Mono,monospace" font-size="10" text-anchor="middle" letter-spacing="4" class="fi sd1">SCENA</text>
  <g class="fi sd2"><rect x="14" y="66" width="392" height="18" rx="5" fill="#FFB020" opacity=".1"/><text x="24" y="79" fill="#FFB020" font-family="JetBrains Mono,monospace" font-size="8.5">PARTER · CAT. I 85 RON</text></g>
  <g transform="translate(0,8)">
  <g class="pop sd3">
    <rect x="86.2" y="90.3" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-22.9 93.2 96.3)"/>
    <rect x="104.8" y="97.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-19.1 111.8 103.5)"/>
    <rect x="123.9" y="103.4" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-15.3 130.9 109.4)"/>
    <rect x="143.4" y="108.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-11.5 150.4 114.0)"/>
    <rect x="163.1" y="111.3" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(-7.6 170.1 117.3)"/>
    <rect x="183.0" y="113.3" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(-3.8 190.0 119.3)"/>
    <rect x="203.0" y="114.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(0.0 210.0 120.0)"/>
    <rect x="223.0" y="113.3" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(3.8 230.0 119.3)"/>
    <rect x="242.9" y="111.3" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(7.6 249.9 117.3)"/>
    <rect x="262.6" y="108.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(11.5 269.6 114.0)"/>
    <rect x="282.1" y="103.4" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(15.3 289.1 109.4)"/>
    <rect x="301.2" y="97.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(19.1 308.2 103.5)"/>
    <rect x="319.8" y="90.3" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(22.9 326.8 96.3)"/>
    <text x="66.2" y="86.8" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">A</text>
    <text x="353.8" y="86.8" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">A</text>
  </g>
  <g class="pop sd4">
    <rect x="76.3" y="120.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-22.4 83.3 126.9)"/>
    <rect x="95.0" y="127.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-19.0 102.0 133.9)"/>
    <rect x="114.1" y="133.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-15.5 121.1 139.9)"/>
    <rect x="133.5" y="138.6" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-12.1 140.5 144.6)"/>
    <rect x="153.2" y="142.2" width="14" height="12" rx="3" fill="#7C5CFF" opacity="1" stroke="#A78BFA" stroke-width="1.8" transform="rotate(-8.6 160.2 148.2)"/>
    <rect x="173.0" y="144.6" width="14" height="12" rx="3" fill="#7C5CFF" opacity="1" stroke="#A78BFA" stroke-width="1.8" transform="rotate(-5.2 180.0 150.6)"/>
    <rect x="193.0" y="145.8" width="14" height="12" rx="3" fill="#7C5CFF" opacity="1" stroke="#A78BFA" stroke-width="1.8" transform="rotate(-1.7 200.0 151.8)"/>
    <rect x="213.0" y="145.8" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(1.7 220.0 151.8)"/>
    <rect x="233.0" y="144.6" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(5.2 240.0 150.6)"/>
    <rect x="252.8" y="142.2" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(8.6 259.8 148.2)"/>
    <rect x="272.5" y="138.6" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(12.1 279.5 144.6)"/>
    <rect x="291.9" y="133.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(15.5 298.9 139.9)"/>
    <rect x="311.0" y="127.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(19.0 318.0 133.9)"/>
    <rect x="329.7" y="120.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(22.4 336.7 126.9)"/>
    <text x="56.1" y="117.7" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">B</text>
    <text x="363.9" y="117.7" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">B</text>
  </g>
  <g class="pop sd5">
    <rect x="75.7" y="155.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-20.5 82.7 161.0)"/>
    <rect x="94.7" y="161.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-17.3 101.7 167.5)"/>
    <rect x="113.9" y="166.9" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(-14.2 120.9 172.9)"/>
    <rect x="133.4" y="171.3" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(-11.0 140.4 177.3)"/>
    <rect x="153.2" y="174.6" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-7.9 160.2 180.6)"/>
    <rect x="173.0" y="176.8" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-4.7 180.0 182.8)"/>
    <rect x="193.0" y="177.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-1.6 200.0 183.9)"/>
    <rect x="213.0" y="177.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(1.6 220.0 183.9)"/>
    <rect x="233.0" y="176.8" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(4.7 240.0 182.8)"/>
    <rect x="252.8" y="174.6" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(7.9 259.8 180.6)"/>
    <rect x="272.6" y="171.3" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(11.0 279.6 177.3)"/>
    <rect x="292.1" y="166.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(14.2 299.1 172.9)"/>
    <rect x="311.3" y="161.5" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(17.3 318.3 167.5)"/>
    <rect x="330.3" y="155.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(20.5 337.3 161.0)"/>
    <text x="55.1" y="152.9" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">C</text>
    <text x="364.9" y="152.9" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">C</text>
  </g>
  <g class="pop sd6">
    <rect x="65.9" y="185.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-20.3 72.9 191.5)"/>
    <rect x="84.8" y="192.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-17.4 91.8 198.0)"/>
    <rect x="104.1" y="197.4" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-14.5 111.1 203.4)"/>
    <rect x="123.5" y="201.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-11.6 130.5 207.9)"/>
    <rect x="143.2" y="205.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-8.7 150.2 211.5)"/>
    <rect x="163.1" y="208.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-5.8 170.1 214.0)"/>
    <rect x="183.0" y="209.5" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(-2.9 190.0 215.5)"/>
    <rect x="203.0" y="210.0" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(0.0 210.0 216.0)"/>
    <rect x="223.0" y="209.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(2.9 230.0 215.5)"/>
    <rect x="242.9" y="208.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(5.8 249.9 214.0)"/>
    <rect x="262.8" y="205.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(8.7 269.8 211.5)"/>
    <rect x="282.5" y="201.9" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(11.6 289.5 207.9)"/>
    <rect x="301.9" y="197.4" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(14.5 308.9 203.4)"/>
    <rect x="321.2" y="192.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(17.4 328.2 198.0)"/>
    <rect x="340.1" y="185.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(20.3 347.1 191.5)"/>
    <text x="45.2" y="183.6" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">D</text>
    <text x="374.8" y="183.6" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">D</text>
  </g>
  <g class="pop sd7">
    <rect x="65.5" y="219.3" width="14" height="12" rx="3" fill="#5B8CFF" opacity="1" transform="rotate(-18.7 72.5 225.3)"/>
    <text x="72.5" y="228.9" font-size="8" text-anchor="middle">♿</text>
    <rect x="84.6" y="225.3" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-16.1 91.6 231.3)"/>
    <rect x="103.9" y="230.4" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-13.4 110.9 236.4)"/>
    <rect x="123.5" y="234.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-10.7 130.5 240.5)"/>
    <rect x="143.2" y="237.8" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-8.0 150.2 243.8)"/>
    <rect x="163.1" y="240.1" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-5.4 170.1 246.1)"/>
    <rect x="183.0" y="241.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(-2.7 190.0 247.5)"/>
    <rect x="203.0" y="242.0" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(0.0 210.0 248.0)"/>
    <rect x="223.0" y="241.5" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(2.7 230.0 247.5)"/>
    <rect x="242.9" y="240.1" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(5.4 249.9 246.1)"/>
    <rect x="262.8" y="237.8" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(8.0 269.8 243.8)"/>
    <rect x="282.5" y="234.5" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(10.7 289.5 240.5)"/>
    <rect x="302.1" y="230.4" width="14" height="12" rx="3" fill="#3A3A55" opacity="1" transform="rotate(13.4 309.1 236.4)"/>
    <rect x="321.4" y="225.3" width="14" height="12" rx="3" fill="#22D3A6" opacity=".72" transform="rotate(16.1 328.4 231.3)"/>
    <rect x="340.5" y="219.3" width="14" height="12" rx="3" fill="#5B8CFF" opacity="1" transform="rotate(18.7 347.5 225.3)"/>
    <text x="347.5" y="228.9" font-size="8" text-anchor="middle">♿</text>
    <text x="44.4" y="218.2" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">E</text>
    <text x="375.6" y="218.2" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">E</text>
  </g>
  </g>
  <g class="pop sd8">
    <rect x="16" y="104" width="36" height="28" rx="6" fill="#FFB020" opacity=".14" stroke="#FFB020" stroke-opacity=".45"/><text x="34" y="122" fill="#FFB020" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">L1</text>
    <rect x="368" y="104" width="36" height="28" rx="6" fill="#FFB020" opacity=".14" stroke="#FFB020" stroke-opacity=".45"/><text x="386" y="122" fill="#FFB020" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">L4</text>
    <rect x="16" y="138" width="36" height="28" rx="6" fill="#FFB020" opacity=".14" stroke="#FFB020" stroke-opacity=".45"/><text x="34" y="156" fill="#FFB020" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">L2</text>
    <rect x="368" y="138" width="36" height="28" rx="6" fill="#FFB020" opacity=".14" stroke="#FFB020" stroke-opacity=".45"/><text x="386" y="156" fill="#FFB020" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">L5</text>
    <rect x="16" y="172" width="36" height="28" rx="6" fill="#FFB020" opacity=".14" stroke="#FFB020" stroke-opacity=".45"/><text x="34" y="190" fill="#FFB020" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">L3</text>
    <rect x="368" y="172" width="36" height="28" rx="6" fill="#FFB020" opacity=".14" stroke="#FFB020" stroke-opacity=".45"/><text x="386" y="190" fill="#FFB020" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">L6</text>
  </g>
  <path d="M40 282 Q210 272 380 282" stroke="#26263C" stroke-width="1" fill="none" stroke-dasharray="4 5" class="fi sd8"/>
  <g class="fi sd9"><rect x="14" y="294" width="392" height="18" rx="5" fill="#5B8CFF" opacity=".1"/><text x="24" y="307" fill="#7C9DFF" font-family="JetBrains Mono,monospace" font-size="8.5">BALCON · 45 RON</text></g>
  <g class="pop sd9">
    <rect x="60.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="79.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="98.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="117.5" y="324" width="14" height="12" rx="3" fill="#3A3A55" opacity="1"/>
    <rect x="136.5" y="324" width="14" height="12" rx="3" fill="#3A3A55" opacity="1"/>
    <rect x="155.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="174.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="193.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="212.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="231.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="250.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="269.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="288.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="307.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="326.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="345.5" y="324" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
  </g>
  <g class="pop sd10">
    <rect x="60.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="79.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="98.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="117.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="136.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="155.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="174.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="193.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="212.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="231.5" y="344" width="14" height="12" rx="3" fill="#3A3A55" opacity="1"/>
    <rect x="250.5" y="344" width="14" height="12" rx="3" fill="#3A3A55" opacity="1"/>
    <rect x="269.5" y="344" width="14" height="12" rx="3" fill="#3A3A55" opacity="1"/>
    <rect x="288.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="307.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="326.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
    <rect x="345.5" y="344" width="14" height="12" rx="3" fill="#22D3A6" opacity=".6"/>
  </g>
  <g class="fi sd10">
    <rect x="20" y="386" width="12" height="12" rx="3" fill="#22D3A6" opacity=".72"/><text x="38" y="396" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Liber</text>
    <rect x="92" y="386" width="12" height="12" rx="3" fill="#3A3A55" opacity="1"/><text x="110" y="396" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Ocupat</text>
    <rect x="172" y="386" width="12" height="12" rx="3" fill="#7C5CFF" opacity="1" stroke="#A78BFA" stroke-width="1.5"/><text x="190" y="396" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Selectat</text>
    <rect x="262" y="386" width="12" height="12" rx="3" fill="#5B8CFF" opacity="1"/><text x="280" y="396" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Accesibil</text>
  </g>
  <g class="fi sd10">
    <rect x="16" y="412" width="388" height="54" rx="10" fill="#0B0B14" stroke="#7C5CFF" stroke-opacity=".45"/>
    <circle cx="44" cy="439" r="13" fill="#FFB020" opacity=".18" class="anim-pulse"/>
    <text x="44" y="444" font-size="13" text-anchor="middle">⏱</text>
    <text x="70" y="434" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700">3 locuri selectate · rândul B, 5–7</text>
    <text x="70" y="451" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Blocate 10 minute · se eliberează automat</text>
  </g>
</svg>
        <div class="fcap">Sală curbată — parter, loje, balcon, selecție live</div>
      </div>
    </div>

    <!-- 02 -->
    <div class="feat flip">
      <div class="rv-r">
        <div class="ftag"><i></i>02 — ABONAMENTE</div>
        <h3>Abonamente de stagiune, cu loc rezervat</h3>
        <p>Vinde pachete pentru întreaga stagiune. Loc garantat, preț redus, public fidel.</p>
        <ul>
          <li><strong>Stagiune definită</strong> ca set de spectacole și reprezentații.</li>
          <li><strong>Abonament complet</strong> sau <strong>parțial</strong>, pe un set ales de spectacole.</li>
          <li><strong>Același loc numerotat</strong> la toate spectacolele din abonament.</li>
          <li><strong>Valabilitate</strong> cu dată de început și de sfârșit.</li>
          <li><strong>Reînnoire</strong> de la o stagiune la alta.</li>
          <li>Verificări automate: e valid? include acest spectacol? are loc rezervat?</li>
        </ul>
        <div class="why"><b>De ce contează:</b> abonamentele sunt coloana vertebrală a publicului fidel. Gestionarea manuală, în tabele și hărți pe hârtie, este sursa clasică de erori și suprapuneri.</div>
      </div>
      <div class="fig rv-l">
        <svg viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Abonamente Gold și Silver cu loc fix pe stagiune">
          <g class="rv-s in pop sd1">
            <rect x="16" y="14" width="368" height="76" rx="12" fill="#181829" stroke="#FFB020" stroke-opacity=".45"/>
            <circle cx="46" cy="44" r="14" fill="#FFB020" opacity=".16"/>
            <text x="46" y="49" font-size="15" text-anchor="middle">🥇</text>
            <text x="72" y="41" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="14" font-weight="700">Abonament Gold</text>
            <text x="368" y="41" fill="#FFB020" font-family="Plus Jakarta Sans,sans-serif" font-size="16" font-weight="800" text-anchor="end">599 RON</text>
            <text x="72" y="59" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="11.5">10 spectacole · loc fix rândul 3</text>
          </g>
          <g class="pop sd2" fill="#FFB020" opacity=".55">
            <rect x="72" y="68" width="9" height="9" rx="2"/><rect x="85" y="68" width="9" height="9" rx="2"/>
            <rect x="98" y="68" width="9" height="9" rx="2"/><rect x="111" y="68" width="9" height="9" rx="2"/>
            <rect x="124" y="68" width="9" height="9" rx="2"/><rect x="137" y="68" width="9" height="9" rx="2"/>
            <rect x="150" y="68" width="9" height="9" rx="2"/><rect x="163" y="68" width="9" height="9" rx="2"/>
            <rect x="176" y="68" width="9" height="9" rx="2"/><rect x="189" y="68" width="9" height="9" rx="2"/>
          </g>

          <g class="pop sd3">
            <rect x="16" y="100" width="368" height="76" rx="12" fill="#181829" stroke="#A2A2B8" stroke-opacity=".35"/>
            <circle cx="46" cy="130" r="14" fill="#A2A2B8" opacity=".14"/>
            <text x="46" y="135" font-size="15" text-anchor="middle">🥈</text>
            <text x="72" y="127" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="14" font-weight="700">Abonament Silver</text>
            <text x="368" y="127" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="16" font-weight="800" text-anchor="end">399 RON</text>
            <text x="72" y="145" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="11.5">10 spectacole · parter zona B</text>
          </g>
          <g class="pop sd4" fill="#A2A2B8" opacity=".4">
            <rect x="72" y="154" width="9" height="9" rx="2"/><rect x="85" y="154" width="9" height="9" rx="2"/>
            <rect x="98" y="154" width="9" height="9" rx="2"/><rect x="111" y="154" width="9" height="9" rx="2"/>
            <rect x="124" y="154" width="9" height="9" rx="2"/><rect x="137" y="154" width="9" height="9" rx="2"/>
            <rect x="150" y="154" width="9" height="9" rx="2"/><rect x="163" y="154" width="9" height="9" rx="2"/>
            <rect x="176" y="154" width="9" height="9" rx="2"/><rect x="189" y="154" width="9" height="9" rx="2"/>
          </g>

          <rect x="16" y="188" width="368" height="98" rx="12" fill="#0B0B14" stroke="#26263C" class="fi sd5"/>
          <text x="34" y="212" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5" class="fi sd5">ACELAȘI LOC, TOATĂ STAGIUNEA</text>
          <g>
            <g class="pop sd6"><rect x="34" y="226" width="46" height="38" rx="7" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".45"/>
            <text x="57" y="242" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">OCT</text>
            <rect x="49" y="247" width="16" height="10" rx="2" fill="#7C5CFF"/></g>
            <g class="pop sd7"><rect x="90" y="226" width="46" height="38" rx="7" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".45"/>
            <text x="113" y="242" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">NOV</text>
            <rect x="105" y="247" width="16" height="10" rx="2" fill="#7C5CFF"/></g>
            <g class="pop sd8"><rect x="146" y="226" width="46" height="38" rx="7" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".45"/>
            <text x="169" y="242" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">DEC</text>
            <rect x="161" y="247" width="16" height="10" rx="2" fill="#7C5CFF"/></g>
            <g class="pop sd9"><rect x="202" y="226" width="46" height="38" rx="7" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".45"/>
            <text x="225" y="242" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">IAN</text>
            <rect x="217" y="247" width="16" height="10" rx="2" fill="#7C5CFF"/></g>
            <g class="pop sd10"><rect x="258" y="226" width="46" height="38" rx="7" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".45"/>
            <text x="281" y="242" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">FEB</text>
            <rect x="273" y="247" width="16" height="10" rx="2" fill="#7C5CFF"/></g>
            <text x="336" y="250" fill="#7C5CFF" font-family="Plus Jakarta Sans,sans-serif" font-size="14" font-weight="700" text-anchor="middle" class="fi sd10">+5</text>
          </g>
          <text x="200" y="278" fill="#22D3A6" font-family="JetBrains Mono,monospace" font-size="8.5" text-anchor="middle" class="fi sd10">RÂNDUL 3 · LOCUL 12 · BLOCAT AUTOMAT</text>
        </svg>
        <div class="fcap">Loc fix, păstrat la fiecare reprezentație</div>
      </div>
    </div>

    <!-- 03 -->
    <div class="feat">
      <div class="rv-l">
        <div class="ftag"><i></i>03 — FISCAL</div>
        <h3>Conformitate fiscală — eFactura / ANAF</h3>
        <p><strong style="color:var(--txt)">Critic pentru o instituție de stat.</strong> Tixello este construit pentru cadrul fiscal din România.</p>
        <ul>
          <li><strong>eFactura (SPV ANAF)</strong> — UBL 2.1 / CII XML, semnare electronică, trimitere automată.</li>
          <li><strong>Coadă cu reîncercare</strong> și urmărirea stării: în așteptare → trimisă → acceptată.</li>
          <li><strong>Descărcarea recipisei</strong> de la ANAF.</li>
          <li><strong>Facturare completă</strong> — serii, TVA, status, generare PDF.</li>
          <li><strong>Conectori de contabilitate</strong> — SmartBill, Oblio, Keez, FGO. Fără re-tastare.</li>
          <li><strong>Rapoarte de taxe și TVA</strong> per eveniment.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> eFactura este obligatorie. O instituție publică nu poate lucra cu un sistem care nu comunică cu ANAF și cu programul de contabilitate.</div>
      </div>
      <div class="fig rv-r">
        <svg viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Fluxul facturii electronice către ANAF">
          <g class="pop sd1">
            <rect x="20" y="18" width="360" height="44" rx="10" fill="#12121F" stroke="#26263C"/>
            <circle cx="46" cy="40" r="11" fill="#7C5CFF" opacity=".2"/><text x="46" y="45" font-size="12" text-anchor="middle">🎫</text>
            <text x="68" y="37" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Vânzare bilet</text>
            <text x="68" y="53" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">Online sau la ghișeu</text>
          </g>
          <path d="M200 62 L200 78" stroke="#7C5CFF" stroke-width="2" class="dash sd2" style="--len:18"/>
          <path d="M195 74 l5 6 5-6" fill="#7C5CFF" class="fi sd2"/>

          <g class="pop sd3">
            <rect x="20" y="82" width="360" height="44" rx="10" fill="#12121F" stroke="#26263C"/>
            <circle cx="46" cy="104" r="11" fill="#7C5CFF" opacity=".2"/><text x="46" y="109" font-size="12" text-anchor="middle">📄</text>
            <text x="68" y="101" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Factură UBL 2.1 / CII XML</text>
            <text x="68" y="117" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">Generare + semnare electronică</text>
          </g>
          <path d="M200 126 L200 142" stroke="#7C5CFF" stroke-width="2" class="dash sd4" style="--len:18"/>
          <path d="M195 138 l5 6 5-6" fill="#7C5CFF" class="fi sd4"/>

          <g class="pop sd5">
            <rect x="20" y="146" width="360" height="44" rx="10" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".5"/>
            <circle cx="46" cy="168" r="11" fill="#7C5CFF" opacity=".28" class="anim-pulse"/><text x="46" y="173" font-size="12" text-anchor="middle">🏛</text>
            <text x="68" y="165" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">SPV ANAF</text>
            <text x="68" y="181" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">Trimitere automată, coadă cu reîncercare</text>
          </g>
          <path d="M200 190 L200 206" stroke="#22D3A6" stroke-width="2" class="dash sd6" style="--len:18"/>
          <path d="M195 202 l5 6 5-6" fill="#22D3A6" class="fi sd6"/>

          <g class="pop sd7">
            <rect x="20" y="210" width="360" height="44" rx="10" fill="#12121F" stroke="#22D3A6" stroke-opacity=".45"/>
            <circle cx="46" cy="232" r="11" fill="#22D3A6" opacity=".18"/>
            <path d="M41 232 l4 4 7-8" stroke="#22D3A6" stroke-width="2.2" fill="none" stroke-linecap="round"/>
            <text x="68" y="229" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Recipisă + contabilitate</text>
            <text x="68" y="245" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">SmartBill · Oblio · Keez · FGO</text>
          </g>

          <g class="fi sd8">
            <rect x="20" y="266" width="86" height="22" rx="11" fill="#22D3A6" opacity=".14"/>
            <text x="63" y="281" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5" font-weight="700" text-anchor="middle">SmartBill</text>
            <rect x="114" y="266" width="72" height="22" rx="11" fill="#22D3A6" opacity=".14"/>
            <text x="150" y="281" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5" font-weight="700" text-anchor="middle">Oblio</text>
            <rect x="194" y="266" width="72" height="22" rx="11" fill="#22D3A6" opacity=".14"/>
            <text x="230" y="281" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5" font-weight="700" text-anchor="middle">Keez</text>
            <rect x="274" y="266" width="72" height="22" rx="11" fill="#22D3A6" opacity=".14"/>
            <text x="310" y="281" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5" font-weight="700" text-anchor="middle">FGO</text>
          </g>
        </svg>
        <div class="fcap">De la vânzare la recipisa ANAF — automat</div>
      </div>
    </div>

    <!-- 04 -->
    <div class="feat flip">
      <div class="rv-r">
        <div class="ftag"><i></i>04 — PROTOCOL</div>
        <h3>Invitații și bilete de protocol</h3>
        <p>Instituțiile de stat emit un volum mare de invitații — autorități, presă, parteneri, sponsori.</p>
        <ul>
          <li><strong>Bilete cu valoare zero</strong>, fiecare cu QR unic și protecție anti-copiere.</li>
          <li><strong>Generare pe loturi</strong> — sute de invitații deodată.</li>
          <li><strong>Import listă din CSV</strong>, cu maparea câmpurilor.</li>
          <li><strong>Distribuție automată pe email</strong> plus descărcare PDF.</li>
          <li><strong>Categorii</strong>: VIP, presă, protocol.</li>
          <li><strong>Urmărire</strong> — cine a descărcat, cine a intrat în sală.</li>
          <li><strong>Anulare pe lot</strong> și <strong>monitorizare abuz</strong>.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> zonă sensibilă din punct de vedere al transparenței. Invitațiile apar separat în rapoarte, ca să fie clar ce s-a vândut și ce s-a oferit.</div>
      </div>
      <div class="fig rv-l">
        <svg viewBox="0 0 400 348" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Evidență separată: bilete vândute față de invitații de protocol, cu urmărirea descărcărilor și a intrărilor">
  <text x="20" y="16" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="9" class="fi">EVIDENȚĂ SEPARATĂ · O REPREZENTAȚIE</text>

  <text x="20" y="42" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="11.5" font-weight="700" class="fi sd1">Bilete vândute</text>
  <text x="380" y="42" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="800" text-anchor="end" class="fi sd1">412</text>
  <rect x="20" y="50" width="360" height="16" rx="8" fill="#181829" class="fi sd1"/>
  <rect x="20" y="50" width="284" height="16" rx="8" fill="#7C5CFF" opacity=".8" class="gx sd2"/>

  <text x="20" y="94" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="11.5" font-weight="700" class="fi sd2">Invitații de protocol</text>
  <text x="380" y="94" fill="#FFB020" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="800" text-anchor="end" class="fi sd2">88</text>
  <rect x="20" y="102" width="360" height="16" rx="8" fill="#181829" class="fi sd2"/>
  <rect x="20" y="102" width="62" height="16" rx="8" fill="#FFB020" opacity=".8" class="gx sd3"/>

  <line x1="20" y1="140" x2="380" y2="140" stroke="#26263C" class="fi sd3"/>

  <g class="pop sd4">
    <rect x="20" y="156" width="360" height="80" rx="10" fill="#12121F" stroke="#FFB020" stroke-opacity=".38" stroke-dasharray="6 4"/>
    <rect x="38" y="174" width="44" height="44" rx="6" fill="#0B0B14" stroke="#26263C"/>
    <g fill="#F4F4F8" opacity=".9">
      <rect x="44" y="180" width="7" height="7"/><rect x="55" y="180" width="7" height="7"/><rect x="69" y="180" width="7" height="7"/>
      <rect x="44" y="191" width="7" height="7"/><rect x="62" y="191" width="7" height="7"/><rect x="69" y="191" width="7" height="7"/>
      <rect x="44" y="205" width="7" height="7"/><rect x="55" y="205" width="7" height="7"/><rect x="69" y="205" width="7" height="7"/>
    </g>
    <text x="96" y="186" fill="#FFB020" font-family="JetBrains Mono,monospace" font-size="8.5" letter-spacing="1.4">INVITAȚIE · PRESĂ</text>
    <text x="96" y="204" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Valoare 0 RON · QR unic</text>
    <text x="96" y="221" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Nominală · urmăribilă · nu poate fi refolosită</text>
  </g>

  <g class="pop sd5">
    <rect x="20" y="250" width="174" height="48" rx="9" fill="#12121F" stroke="#26263C"/>
    <text x="36" y="271" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8">DESCĂRCATE</text>
    <text x="36" y="289" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="800">71 / 88</text>
  </g>
  <g class="pop sd6">
    <rect x="206" y="250" width="174" height="48" rx="9" fill="#12121F" stroke="#26263C"/>
    <text x="222" y="271" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8">CHECK-IN LA INTRARE</text>
    <text x="222" y="289" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="800">64 / 88</text>
  </g>

  <g class="fi sd7">
    <rect x="20" y="312" width="360" height="26" rx="13" fill="#FF6B6B" opacity=".1"/>
    <text x="36" y="329" fill="#FF6B6B" font-family="JetBrains Mono,monospace" font-size="8.5">⚠ MONITORIZARE ABUZ · RAPORT DEDICAT</text>
  </g>
</svg>

        <div class="fcap">Vânzări față de protocol, urmărite separat</div>
      </div>
    </div>

    <!-- 05 -->
    <div class="feat">
      <div class="rv-l">
        <div class="ftag"><i></i>05 — REDUCERI SOCIALE</div>
        <h3>Elevi, studenți, pensionari, grupuri</h3>
        <ul>
          <li><strong>Categorii de preț dedicate</strong> per spectacol, fiecare cu preț propriu.</li>
          <li><strong>Aplicare automată la checkout</strong> — spectatorul alege categoria, sistemul calculează.</li>
          <li><strong>Coduri promoționale și cupoane</strong> — validare, reguli de cumulare, statistici.</li>
          <li><strong>Reduceri de grup</strong> peste un anumit număr de bilete.</li>
          <li><strong>Portal pentru grupuri școlare</strong> — cerere, aprobare, plată, totul online.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> politica de prețuri sociale se implementează direct în sistem, cu evidență clară a fiecărei reduceri acordate.</div>
      </div>
      <div class="fig rv-r">
        <svg viewBox="0 0 400 270" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Reduceri sociale și portal grupuri școlare">
          <g class="pop sd1">
            <rect x="20" y="16" width="360" height="40" rx="9" fill="#12121F" stroke="#26263C"/>
            <text x="38" y="35" font-size="14">🎓</text>
            <text x="62" y="33" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="700">Elevi și studenți</text>
            <text x="62" y="47" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">Aplicare automată la checkout</text>
            <rect x="308" y="26" width="58" height="22" rx="11" fill="#22D3A6" opacity=".18"/>
            <text x="337" y="41" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="800" text-anchor="middle">−50%</text>
          </g>
          <g class="pop sd2">
            <rect x="20" y="64" width="360" height="40" rx="9" fill="#12121F" stroke="#26263C"/>
            <text x="38" y="83" font-size="14">👴</text>
            <text x="62" y="81" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="700">Pensionari</text>
            <text x="62" y="95" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">Categorie de preț dedicată</text>
            <rect x="308" y="74" width="58" height="22" rx="11" fill="#22D3A6" opacity=".18"/>
            <text x="337" y="89" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="800" text-anchor="middle">−30%</text>
          </g>
          <g class="pop sd3">
            <rect x="20" y="112" width="360" height="40" rx="9" fill="#12121F" stroke="#26263C"/>
            <text x="38" y="131" font-size="14">👥</text>
            <text x="62" y="129" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="700">Grupuri organizate</text>
            <text x="62" y="143" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">Preț special peste un prag de bilete</text>
            <rect x="308" y="122" width="58" height="22" rx="11" fill="#22D3A6" opacity=".18"/>
            <text x="337" y="137" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="800" text-anchor="middle">−40%</text>
          </g>

          <g class="pop sd4">
            <rect x="20" y="164" width="360" height="76" rx="10" fill="#0B0B14" stroke="#7C5CFF" stroke-opacity=".35"/>
            <text x="38" y="186" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5">PORTAL GRUPURI ȘCOLARE</text>
            <text x="38" y="210" font-size="14">🏫</text>
            <text x="62" y="209" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Colegiul Național „Mihai Viteazul"</text>
            <text x="62" y="226" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">32 elevi · „Hamlet" · 15 noiembrie</text>
            <rect x="290" y="192" width="76" height="24" rx="12" fill="#FFB020" opacity=".16"/>
            <text x="328" y="208" fill="#FFB020" font-family="Plus Jakarta Sans,sans-serif" font-size="11" font-weight="700" text-anchor="middle">În așteptare</text>
          </g>

          <g class="fi sd5">
            <text x="20" y="262" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5">CERERE → APROBARE → PLATĂ · TOTUL ONLINE</text>
          </g>
        </svg>
        <div class="fcap">Reduceri aplicate automat + portal grupuri</div>
      </div>
    </div>

    <!-- 06 -->
    <div class="feat flip">
      <div class="rv-r">
        <div class="ftag"><i></i>06 — CASĂ DE BILETE</div>
        <h3>Casă de bilete proprie — cash și card</h3>
        <p>Teatrul își păstrează ghișeul propriu și controlul la intrare.</p>
        <ul>
          <li><strong>Vânzare la ghișeu</strong> — spectacol → categorie → loc → încasare → bilet emis.</li>
          <li><strong>Încasare cash sau card</strong>, ambele în același flux.</li>
          <li><strong>Tap-to-pay</strong> pe telefon sau terminal, inclusiv Apple Pay și Google Pay.</li>
          <li><strong>Sincronizare în timp real</strong> între ghișeu și online — același inventar.</li>
          <li><strong>Check-in urmărit</strong> — câți au intrat, ce bilete sunt valide.</li>
        </ul>
        <div class="note">Notă de implementare: integrarea cu casa de marcat fiscală pentru încasările cash se configurează la punerea în funcțiune, în funcție de casa de marcat a instituției.</div>
      </div>
      <div class="fig rv-l">
        <svg viewBox="0 0 400 260" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Cash și card converg în același inventar de locuri">
          <g class="pop sd1">
            <rect x="20" y="18" width="170" height="90" rx="11" fill="#12121F" stroke="#26263C"/>
            <text x="105" y="46" font-size="22" text-anchor="middle">💵</text>
            <text x="105" y="70" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="700" text-anchor="middle">Cash</text>
            <text x="105" y="88" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5" text-anchor="middle">Încasare la ghișeu</text>
          </g>
          <g class="pop sd2">
            <rect x="210" y="18" width="170" height="90" rx="11" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".4"/>
            <text x="295" y="46" font-size="22" text-anchor="middle">💳</text>
            <text x="295" y="70" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="700" text-anchor="middle">Card / tap-to-pay</text>
            <text x="295" y="88" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5" text-anchor="middle">Apple Pay · Google Pay</text>
          </g>

          <path d="M105 108 L105 132 L295 132 L295 108" stroke="#7C5CFF" stroke-width="1.6" fill="none" class="dash sd3" style="--len:260"/>
          <path d="M200 132 L200 152" stroke="#7C5CFF" stroke-width="1.6" class="dash sd4" style="--len:22"/>
          <path d="M195 148 l5 6 5-6" fill="#7C5CFF" class="fi sd4"/>

          <g class="pop sd5">
            <rect x="20" y="158" width="360" height="66" rx="11" fill="#0B0B14" stroke="#22D3A6" stroke-opacity=".35"/>
            <text x="200" y="184" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13.5" font-weight="700" text-anchor="middle">Același inventar de locuri</text>
            <text x="200" y="203" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="11" text-anchor="middle">Ghișeul și vânzarea online lucrează pe aceeași hartă,</text>
            <text x="200" y="217" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="11" text-anchor="middle">sincronizate în timp real. Fără suprapuneri.</text>
          </g>

          <g class="fi sd6">
            <circle cx="200" cy="242" r="5" fill="#22D3A6" class="anim-pulse"/>
            <text x="216" y="246" fill="#22D3A6" font-family="JetBrains Mono,monospace" font-size="8.5">SINCRONIZARE ÎN TIMP REAL</text>
          </g>
        </svg>
        <div class="fcap">Ghișeu și online — un singur inventar</div>
      </div>
    </div>

    <!-- 07 -->
    <div class="feat-more" id="featMore" hidden>
    <div class="feat">
      <div class="rv-l">
        <div class="ftag"><i></i>07 — CONTROL ACCES</div>
        <h3>Aplicație mobilă de scanare, personalizată</h3>
        <ul>
          <li><strong>Aplicație dedicată de scanare</strong>, pusă la dispoziție de Tixello.</li>
          <li><strong>Personalizată cu identitatea teatrului</strong> — numele și brandul instituției.</li>
          <li><strong>Validare QR sub o secundă</strong>, cu semnalizare clară: valid, folosit, invalid.</li>
          <li><strong>Interfață „Gate Scanner"</strong> disponibilă și în browser.</li>
          <li><strong>Mai multe puncte de control simultan</strong>, cu evidență comună.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> personalul de sală lucrează cu un instrument care poartă numele teatrului. Publicul vede o experiență coerentă de la cumpărare până la intrarea în sală.</div>
      </div>
      <div class="fig rv-r">
        <svg viewBox="0 0 400 310" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Aplicația mobilă de scanare cu brandul teatrului">
          <g class="pop sd1">
            <rect x="128" y="14" width="144" height="278" rx="20" fill="#12121F" stroke="#26263C" stroke-width="2"/>
            <rect x="176" y="22" width="48" height="6" rx="3" fill="#26263C"/>
          </g>
          <g class="fi sd2">
            <rect x="138" y="38" width="124" height="34" rx="8" fill="#7C5CFF" opacity=".16"/>
            <text x="200" y="53" fill="#A78BFA" font-family="JetBrains Mono,monospace" font-size="7.5" text-anchor="middle" letter-spacing="1">TEATRUL</text>
            <text x="200" y="66" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="11" font-weight="800" text-anchor="middle">NUMELE TEATRULUI</text>
          </g>

          <g class="fi sd3">
            <rect x="150" y="86" width="100" height="100" rx="10" fill="#0B0B14" stroke="#7C5CFF" stroke-opacity=".5"/>
            <path d="M158 96 v-6 h6 M242 96 v-6 h-6 M158 176 v6 h6 M242 176 v6 h-6" stroke="#7C5CFF" stroke-width="2.5" fill="none" stroke-linecap="round"/>
            <g fill="#F4F4F8" opacity=".9">
              <rect x="166" y="104" width="12" height="12"/><rect x="184" y="104" width="12" height="12"/><rect x="210" y="104" width="12" height="12"/>
              <rect x="166" y="122" width="12" height="12"/><rect x="196" y="122" width="12" height="12"/><rect x="210" y="122" width="12" height="12"/>
              <rect x="166" y="146" width="12" height="12"/><rect x="184" y="146" width="12" height="12"/><rect x="210" y="146" width="12" height="12"/>
              <rect x="196" y="158" width="12" height="12"/>
            </g>
            <rect x="152" y="92" width="96" height="2" fill="#22D3A6" opacity=".85" class="anim-scan"/>
          </g>

          <g class="pop sd5">
            <rect x="140" y="198" width="120" height="40" rx="9" fill="#22D3A6" opacity=".16"/>
            <circle cx="162" cy="218" r="10" fill="#22D3A6" opacity=".3"/>
            <path d="M157 218 l4 4 7-8" stroke="#22D3A6" stroke-width="2.2" fill="none" stroke-linecap="round"/>
            <text x="180" y="214" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="11" font-weight="800">BILET VALID</text>
            <text x="180" y="228" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5">Parter · rând 3 · loc 12</text>
          </g>

          <g class="fi sd6">
            <rect x="140" y="246" width="58" height="32" rx="8" fill="#0B0B14" stroke="#26263C"/>
            <text x="169" y="259" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7" text-anchor="middle">INTRAȚI</text>
            <text x="169" y="272" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="800" text-anchor="middle">218</text>
            <rect x="202" y="246" width="58" height="32" rx="8" fill="#0B0B14" stroke="#26263C"/>
            <text x="231" y="259" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7" text-anchor="middle">AȘTEPTAȚI</text>
            <text x="231" y="272" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="800" text-anchor="middle">194</text>
          </g>

          <g class="fi sd7">
            <rect x="20" y="120" width="92" height="30" rx="8" fill="#12121F" stroke="#26263C"/>
            <text x="66" y="139" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">GATE 1</text>
            <rect x="288" y="120" width="92" height="30" rx="8" fill="#12121F" stroke="#26263C"/>
            <text x="334" y="139" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">GATE 2</text>
            <path d="M112 135 L146 135" stroke="#7C5CFF" stroke-width="1.4" stroke-dasharray="3 3" opacity=".6"/>
            <path d="M254 135 L288 135" stroke="#7C5CFF" stroke-width="1.4" stroke-dasharray="3 3" opacity=".6"/>
          </g>

          <text x="200" y="306" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle" class="fi sd8">SCANARE &lt; 1 SECUNDĂ · EVIDENȚĂ COMUNĂ</text>
        </svg>
        <div class="fcap">Aplicația de scanare, sub brandul instituției</div>
      </div>
    </div>

    <!-- 08 -->
    <div class="feat flip">
      <div class="rv-r">
        <div class="ftag"><i></i>08 — PLĂȚI FLEXIBILE</div>
        <h3>Plată în rate și metode moderne</h3>
        <p>Pentru abonamente de stagiune și bilete cu valoare mai mare.</p>
        <ul>
          <li><strong>Plată în rate până la 3 luni</strong> — valoarea se împarte pe trei tranșe.</li>
          <li><strong>Buy now, pay later</strong> — biletul imediat, plata ulterior.</li>
          <li><strong>Buy now, someone else pays</strong> — unul alege, altul achită.</li>
          <li><strong>Carduri culturale</strong> — tichetele culturale emise de angajatori (Edenred, Pluxee, Up România) se acceptă la checkout.</li>
          <li><strong>Procesatori românești</strong>: Netopia, EuPlătesc, PayU, plus Stripe.</li>
          <li><strong>Mobile Wallet</strong> — biletul în Apple Wallet și Google Pay.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> abonamentul de stagiune este achiziția cu cel mai mare preț de intrare din tot ce vinde un teatru. Împărțirea în rate scade bariera și crește numărul de abonați.</div>
      </div>
      <div class="fig rv-l">
        <svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Abonament de 599 RON împărțit în trei rate lunare, plus carduri culturale">
          <text x="20" y="16" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="9" class="fi">ABONAMENT GOLD · 599 RON</text>
          <rect x="20" y="26" width="360" height="28" rx="7" fill="#7C5CFF" opacity=".25" class="gx sd1"/>
          <text x="200" y="45" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700" text-anchor="middle" class="fi sd2">Plată integrală: 599 RON</text>

          <text x="20" y="80" fill="#A78BFA" font-family="JetBrains Mono,monospace" font-size="9" class="fi sd3">SAU ÎN 3 RATE LUNARE</text>
          <g class="pop sd4"><rect x="20" y="90" width="112" height="66" rx="9" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".45"/>
          <text x="76" y="111" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">LUNA 1</text>
          <text x="76" y="134" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="17" font-weight="800" text-anchor="middle">200</text>
          <text x="76" y="148" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9" text-anchor="middle">RON</text></g>
          <g class="pop sd5"><rect x="144" y="90" width="112" height="66" rx="9" fill="#12121F" stroke="#26263C"/>
          <text x="200" y="111" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">LUNA 2</text>
          <text x="200" y="134" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="17" font-weight="800" text-anchor="middle">200</text>
          <text x="200" y="148" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9" text-anchor="middle">RON</text></g>
          <g class="pop sd6"><rect x="268" y="90" width="112" height="66" rx="9" fill="#12121F" stroke="#26263C"/>
          <text x="324" y="111" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">LUNA 3</text>
          <text x="324" y="134" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="17" font-weight="800" text-anchor="middle">199</text>
          <text x="324" y="148" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9" text-anchor="middle">RON</text></g>

          <line x1="20" y1="176" x2="380" y2="176" stroke="#26263C" class="fi sd6"/>

          <g class="pop sd7">
            <rect x="20" y="190" width="174" height="58" rx="9" fill="#0B0B14" stroke="#26263C"/>
            <text x="36" y="214" font-size="14">⏳</text>
            <text x="58" y="214" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="11.5" font-weight="700">Buy now, pay later</text>
            <text x="36" y="234" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10">Biletul acum, plata ulterior</text>
          </g>
          <g class="pop sd8">
            <rect x="206" y="190" width="174" height="58" rx="9" fill="#0B0B14" stroke="#26263C"/>
            <text x="222" y="214" font-size="14">🎁</text>
            <text x="244" y="214" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="11.5" font-weight="700">Someone else pays</text>
            <text x="222" y="234" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10">Unul alege, altul achită</text>
          </g>

          <g class="pop sd9">
            <rect x="20" y="262" width="360" height="62" rx="10" fill="#12121F" stroke="#22D3A6" stroke-opacity=".4"/>
            <text x="36" y="284" font-size="15">🎭</text>
            <text x="60" y="284" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Carduri culturale acceptate</text>
            <text x="36" y="304" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">Tichete culturale emise de angajatori, acceptate la checkout</text>
            <text x="36" y="317" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Edenred · Pluxee · Up România</text>
          </g>

          <text x="20" y="348" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5" class="fi sd10">PROCESATORI DISPONIBILI</text>
          <g class="fi sd10">
            <rect x="20" y="358" width="84" height="20" rx="10" fill="#7C5CFF" opacity=".14"/>
            <text x="62" y="372" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="9" font-weight="700" text-anchor="middle">Netopia</text>
            <rect x="112" y="358" width="84" height="20" rx="10" fill="#7C5CFF" opacity=".14"/>
            <text x="154" y="372" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="9" font-weight="700" text-anchor="middle">EuPlătesc</text>
            <rect x="204" y="358" width="84" height="20" rx="10" fill="#7C5CFF" opacity=".14"/>
            <text x="246" y="372" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="9" font-weight="700" text-anchor="middle">PayU</text>
            <rect x="296" y="358" width="84" height="20" rx="10" fill="#7C5CFF" opacity=".14"/>
            <text x="338" y="372" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="9" font-weight="700" text-anchor="middle">Stripe</text>
          </g>
        </svg>
        <div class="fcap">Abonament împărțit în rate lunare</div>
      </div>
    </div>

    <!-- 09 -->
    <div class="feat">
      <div class="rv-l">
        <div class="ftag"><i></i>09 — OPERARE</div>
        <h3>Panou de administrare intuitiv</h3>
        <p>Toată gestiunea de date se face într-un singur panou, gândit pentru operatori non-tehnici.</p>
        <ul>
          <li><strong>Interfață clară, pe secțiuni</strong> — spectacole, abonamente, bilete, invitații, facturi, rapoarte.</li>
          <li><strong>Fără cunoștințe tehnice</strong> — casieria, marketingul și economicul operează direct.</li>
          <li><strong>Roluri și permisiuni fine</strong> — fiecare vede ce ține de atribuțiile lui.</li>
          <li><strong>Jurnal de activitate</strong> — orice acțiune înregistrată, pentru audit intern.</li>
          <li><strong>Spectacole recurente</strong> — adaugi o dată, rulează toată stagiunea.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> un sistem de ticketing se folosește zilnic de oameni care nu sunt informaticieni. Dacă panoul e greoi, sistemul rămâne nefolosit și munca se întoarce în Excel.</div>
      </div>
      <div class="fig rv-r">
        <svg viewBox="0 0 400 280" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Panoul de administrare cu spectacole recurente">
          <g class="pop sd1">
            <rect x="16" y="14" width="368" height="250" rx="12" fill="#0B0B14" stroke="#26263C"/>
            <rect x="16" y="14" width="368" height="30" rx="12" fill="#12121F"/>
            <circle cx="34" cy="29" r="4" fill="#FF6B6B" opacity=".6"/>
            <circle cx="48" cy="29" r="4" fill="#FFB020" opacity=".6"/>
            <circle cx="62" cy="29" r="4" fill="#22D3A6" opacity=".6"/>
            <text x="200" y="33" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5" text-anchor="middle">PANOU DE ADMINISTRARE</text>
          </g>

          <rect x="26" y="54" width="86" height="198" rx="8" fill="#12121F" class="fi sd2"/>
          <g class="fi sd2"><rect x="34" y="64" width="70" height="16" rx="4" fill="#7C5CFF" opacity=".28"/>
          <text x="42" y="76" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5" font-weight="700">Spectacole</text></g>
          <g class="fi sd3"><rect x="34" y="86" width="70" height="16" rx="4" fill="#181829"/>
          <text x="42" y="98" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5">Abonamente</text></g>
          <g class="fi sd3"><rect x="34" y="108" width="70" height="16" rx="4" fill="#181829"/>
          <text x="42" y="120" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5">Bilete</text></g>
          <g class="fi sd4"><rect x="34" y="130" width="70" height="16" rx="4" fill="#181829"/>
          <text x="42" y="142" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5">Invitații</text></g>
          <g class="fi sd4"><rect x="34" y="152" width="70" height="16" rx="4" fill="#181829"/>
          <text x="42" y="164" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5">Facturi</text></g>
          <g class="fi sd5"><rect x="34" y="174" width="70" height="16" rx="4" fill="#181829"/>
          <text x="42" y="186" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5">Artiști</text></g>
          <g class="fi sd5"><rect x="34" y="196" width="70" height="16" rx="4" fill="#181829"/>
          <text x="42" y="208" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5">Rapoarte</text></g>

          <text x="126" y="70" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="11" font-weight="700" class="fi sd3">„Hamlet" — Stagiunea 2026/2027</text>
          <text x="126" y="86" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5" class="fi sd3">Spectacol recurent · 16 reprezentații</text>

          <g class="pop sd6"><rect x="126" y="96" width="52" height="36" rx="6" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".4"/>
          <text x="152" y="110" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7.5" text-anchor="middle">15 OCT</text>
          <text x="152" y="124" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9" font-weight="700" text-anchor="middle">92%</text></g>
          <g class="pop sd7"><rect x="184" y="96" width="52" height="36" rx="6" fill="#12121F" stroke="#26263C"/>
          <text x="210" y="110" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7.5" text-anchor="middle">22 OCT</text>
          <text x="210" y="124" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9" font-weight="700" text-anchor="middle">78%</text></g>
          <g class="pop sd8"><rect x="242" y="96" width="52" height="36" rx="6" fill="#12121F" stroke="#26263C"/>
          <text x="268" y="110" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7.5" text-anchor="middle">5 NOV</text>
          <text x="268" y="124" fill="#FFB020" font-family="Plus Jakarta Sans,sans-serif" font-size="9" font-weight="700" text-anchor="middle">54%</text></g>
          <g class="pop sd9"><rect x="300" y="96" width="52" height="36" rx="6" fill="#12121F" stroke="#26263C"/>
          <text x="326" y="110" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7.5" text-anchor="middle">19 NOV</text>
          <text x="326" y="124" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="9" font-weight="700" text-anchor="middle">+12</text></g>

          <rect x="126" y="144" width="226" height="1" fill="#26263C" class="fi sd9"/>
          <text x="126" y="164" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8" class="fi sd9">ROLURI ACTIVE</text>
          <g class="pop sd10">
            <rect x="126" y="172" width="70" height="20" rx="10" fill="#7C5CFF" opacity=".18"/>
            <text x="161" y="186" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5" font-weight="600" text-anchor="middle">Casierie</text>
            <rect x="202" y="172" width="70" height="20" rx="10" fill="#7C5CFF" opacity=".18"/>
            <text x="237" y="186" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5" font-weight="600" text-anchor="middle">Marketing</text>
            <rect x="278" y="172" width="74" height="20" rx="10" fill="#7C5CFF" opacity=".18"/>
            <text x="315" y="186" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5" font-weight="600" text-anchor="middle">Economic</text>
          </g>

          <g class="fi sd10">
            <rect x="126" y="204" width="226" height="48" rx="8" fill="#12121F" stroke="#26263C"/>
            <text x="138" y="220" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7.5">JURNAL DE ACTIVITATE</text>
            <circle cx="142" cy="234" r="3" fill="#22D3A6" class="anim-blink"/>
            <text x="152" y="238" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="8.5">Fiecare acțiune administrativă, înregistrată</text>
          </g>
        </svg>
        <div class="fcap">Secțiuni, roluri, jurnal de activitate</div>
      </div>
    </div>


    <!-- 10 ROLES -->
    <div class="feat flip">
      <div class="rv-r">
        <div class="ftag"><i></i>10 — ECHIPĂ ȘI PERMISIUNI</div>
        <h3>Mai multe tipuri de utilizatori, fiecare cu rolul lui</h3>
        <p>Un teatru nu are un singur om la sistem. Casieria vinde, marketingul publică, economicul facturează, conducerea vede rapoartele. Tixello dă fiecăruia exact accesul de care are nevoie — nici mai mult, nici mai puțin.</p>
        <ul>
          <li><strong>Administrator</strong> — acces complet: configurare, prețuri, utilizatori, toate rapoartele.</li>
          <li><strong>Casier</strong> — vinde la ghișeu și încasează, dar nu poate schimba prețuri sau vedea date financiare globale.</li>
          <li><strong>Marketing</strong> — creează spectacole, publică pagini, rulează campanii, fără acces la casă și la facturare.</li>
          <li><strong>Economic / contabilitate</strong> — facturi, rapoarte fiscale, decontări, fără drept de a modifica spectacole.</li>
          <li><strong>Control acces</strong> — doar aplicația de scanare la intrare, nimic altceva.</li>
          <li><strong>Permisiuni granulare</strong> — fiecare drept se poate acorda sau retrage individual.</li>
          <li><strong>Jurnal de activitate</strong> — orice acțiune e înregistrată cu autor și moment, pentru audit intern.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> într-o instituție publică, separarea atribuțiilor nu e opțională — e cerință de control intern. Casierul nu trebuie să vadă marja, iar marketingul nu trebuie să atingă banii. Tixello impune asta prin roluri, cu urmă de audit pentru fiecare acțiune.</div>
      </div>
      <div class="fig rv-l">
        <svg viewBox="0 0 400 340" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Cinci tipuri de roluri de utilizator cu permisiuni diferite, prezentate ca matrice de acces">
          <text x="20" y="18" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="9" class="fi">MATRICE DE PERMISIUNI</text>
          <!-- column headers -->
          <g class="fi sd1" font-family="JetBrains Mono,monospace" font-size="7.5" fill="#8A8AA0" text-anchor="middle">
            <text x="224" y="42">CASĂ</text>
            <text x="268" y="42">PREȚ</text>
            <text x="312" y="42">FACT.</text>
            <text x="356" y="42">RAP.</text>
          </g>
          <!-- rows -->
          <g class="pop sd2">
            <rect x="20" y="52" width="360" height="40" rx="9" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".4"/>
            <circle cx="42" cy="72" r="13" fill="#7C5CFF" opacity=".2"/><text x="42" y="76" font-size="12" text-anchor="middle">👑</text>
            <text x="62" y="69" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700">Administrator</text>
            <text x="62" y="83" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9">Acces complet</text>
            <circle cx="224" cy="72" r="6" fill="#22D3A6"/><circle cx="268" cy="72" r="6" fill="#22D3A6"/><circle cx="312" cy="72" r="6" fill="#22D3A6"/><circle cx="356" cy="72" r="6" fill="#22D3A6"/>
          </g>
          <g class="pop sd3">
            <rect x="20" y="98" width="360" height="40" rx="9" fill="#12121F" stroke="#26263C"/>
            <circle cx="42" cy="118" r="13" fill="#7C5CFF" opacity=".14"/><text x="42" y="122" font-size="12" text-anchor="middle">🧾</text>
            <text x="62" y="115" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700">Casier</text>
            <text x="62" y="129" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9">Vinde și încasează</text>
            <circle cx="224" cy="118" r="6" fill="#22D3A6"/><circle cx="268" cy="118" r="6" fill="#3A3A55"/><circle cx="312" cy="118" r="6" fill="#3A3A55"/><circle cx="356" cy="118" r="6" fill="#3A3A55"/>
          </g>
          <g class="pop sd4">
            <rect x="20" y="144" width="360" height="40" rx="9" fill="#12121F" stroke="#26263C"/>
            <circle cx="42" cy="164" r="13" fill="#7C5CFF" opacity=".14"/><text x="42" y="168" font-size="12" text-anchor="middle">📣</text>
            <text x="62" y="161" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700">Marketing</text>
            <text x="62" y="175" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9">Spectacole și campanii</text>
            <circle cx="224" cy="164" r="6" fill="#3A3A55"/><circle cx="268" cy="164" r="6" fill="#22D3A6"/><circle cx="312" cy="164" r="6" fill="#3A3A55"/><circle cx="356" cy="164" r="6" fill="#FFB020"/>
          </g>
          <g class="pop sd5">
            <rect x="20" y="190" width="360" height="40" rx="9" fill="#12121F" stroke="#26263C"/>
            <circle cx="42" cy="210" r="13" fill="#7C5CFF" opacity=".14"/><text x="42" y="214" font-size="12" text-anchor="middle">💼</text>
            <text x="62" y="207" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700">Economic</text>
            <text x="62" y="221" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9">Facturi și rapoarte</text>
            <circle cx="224" cy="210" r="6" fill="#3A3A55"/><circle cx="268" cy="210" r="6" fill="#3A3A55"/><circle cx="312" cy="210" r="6" fill="#22D3A6"/><circle cx="356" cy="210" r="6" fill="#22D3A6"/>
          </g>
          <g class="pop sd6">
            <rect x="20" y="236" width="360" height="40" rx="9" fill="#12121F" stroke="#26263C"/>
            <circle cx="42" cy="256" r="13" fill="#7C5CFF" opacity=".14"/><text x="42" y="260" font-size="12" text-anchor="middle">📱</text>
            <text x="62" y="253" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700">Control acces</text>
            <text x="62" y="267" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9">Doar scanare la intrare</text>
            <circle cx="224" cy="256" r="6" fill="#3A3A55"/><circle cx="268" cy="256" r="6" fill="#3A3A55"/><circle cx="312" cy="256" r="6" fill="#3A3A55"/><circle cx="356" cy="256" r="6" fill="#3A3A55"/>
          </g>
          <!-- legend -->
          <g class="fi sd7" transform="translate(20,292)">
            <circle cx="6" cy="6" r="6" fill="#22D3A6"/><text x="18" y="10" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Permis</text>
            <circle cx="86" cy="6" r="6" fill="#FFB020"/><text x="98" y="10" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Parțial</text>
            <circle cx="166" cy="6" r="6" fill="#3A3A55"/><text x="178" y="10" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Blocat</text>
          </g>
          <g class="fi sd8">
            <text x="20" y="330" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8">FIECARE ACȚIUNE · ÎNREGISTRATĂ ÎN JURNAL</text>
          </g>
        </svg>
        <div class="fcap">Roluri cu permisiuni granulare + audit</div>
      </div>
    </div>

    <!-- 11 ePas -->
    <div class="feat">
      <div class="rv-l">
        <div class="ftag"><i></i>11 — ANTI-FRAUDĂ</div>
        <h3>Fiecare bilet, protejat ePas la revânzare</h3>
        <p>Toate biletele emise prin Tixello sunt înregistrate în ePas — un registru independent care confirmă că un bilet revândut este autentic și cine îl deține în acel moment. Fără date personale, fără procesare de plăți: doar dovada.</p>
        <ul>
          <li><strong>Semnătură criptografică</strong> — fiecare bilet e semnat Ed25519 de emitent. Nimeni din afară nu poate inventa un bilet valid.</li>
          <li><strong>Verificare publică, gratuită</strong> — un cumpărător de la revânzare verifică într-o clipă dacă biletul e real și cine îl deține.</li>
          <li><strong>Transfer atomic al proprietății</strong> — la revânzare, exact un singur cumpărător poate revendica biletul. Elimină trucul „un bilet, zece cumpărători".</li>
          <li><strong>Istoric inalterabil</strong> — un lanț de hash-uri care nu poate fi rescris.</li>
          <li><strong>Fără date personale</strong> — registrul ține doar dovada, nu identitatea spectatorilor.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> revânzarea frauduloasă și biletele false lovesc reputația teatrului, nu doar a spectatorului păgubit. Protecția ePas mută încrederea de la „am o poză cu biletul" la o dovadă verificabilă public — un plus de credibilitate pentru instituție.</div>
      </div>
      <div class="fig rv-r">
        <svg viewBox="0 0 400 320" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Protecția ePas: bilet semnat criptografic, verificare publică și transfer atomic al proprietății">
          <text x="20" y="18" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="9" class="fi">REGISTRU ePas · epas.ro</text>

          <!-- ticket with signature -->
          <g class="pop sd1">
            <rect x="20" y="30" width="360" height="70" rx="11" fill="#12121F" stroke="#22D3A6" stroke-opacity=".4"/>
            <rect x="36" y="46" width="40" height="40" rx="6" fill="#0B0B14" stroke="#26263C"/>
            <g fill="#22D3A6" opacity=".9">
              <rect x="42" y="52" width="6" height="6"/><rect x="52" y="52" width="6" height="6"/><rect x="64" y="52" width="6" height="6"/>
              <rect x="42" y="62" width="6" height="6"/><rect x="58" y="62" width="6" height="6"/><rect x="64" y="62" width="6" height="6"/>
              <rect x="42" y="74" width="6" height="6"/><rect x="52" y="74" width="6" height="6"/><rect x="64" y="74" width="6" height="6"/>
            </g>
            <text x="90" y="54" fill="#22D3A6" font-family="JetBrains Mono,monospace" font-size="8.5" letter-spacing="1">BILET SEMNAT Ed25519</text>
            <text x="90" y="72" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Autenticitate garantată</text>
            <text x="90" y="88" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Emis de teatru · înregistrat în registru</text>
          </g>

          <!-- verify -->
          <g class="pop sd2">
            <rect x="20" y="112" width="360" height="60" rx="11" fill="#0B0B14" stroke="#26263C"/>
            <circle cx="46" cy="142" r="14" fill="#7C5CFF" opacity=".18"/>
            <circle cx="46" cy="139" r="6" fill="none" stroke="#A78BFA" stroke-width="2"/>
            <path d="M50 143 l4 4" stroke="#A78BFA" stroke-width="2" stroke-linecap="round"/>
            <text x="70" y="134" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700">Cumpărătorul verifică înainte de plată</text>
            <text x="70" y="150" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Cod + PIN → e autentic? are transfer în curs?</text>
            <rect x="70" y="158" width="150" height="8" rx="4" fill="#12121F"/>
            <rect x="70" y="158" width="150" height="8" rx="4" fill="#22D3A6" opacity=".7" class="gx sd3"/>
          </g>

          <!-- atomic transfer -->
          <g class="pop sd4">
            <rect x="20" y="184" width="360" height="76" rx="11" fill="#12121F" stroke="#7C5CFF" stroke-opacity=".4"/>
            <text x="36" y="206" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8">TRANSFER ATOMIC · UN SINGUR CÂȘTIGĂTOR</text>
            <circle cx="60" cy="232" r="15" fill="#7C5CFF" opacity=".2"/><text x="60" y="237" font-size="13" text-anchor="middle">🎟</text>
            <path d="M82 232 L150 232" stroke="#22D3A6" stroke-width="2" class="dash sd5" style="--len:70"/>
            <path d="M144 227 l8 5 -8 5" fill="#22D3A6" class="fi sd6"/>
            <circle cx="172" cy="232" r="13" fill="#22D3A6" opacity=".18"/><text x="172" y="237" font-size="11" text-anchor="middle">✓</text>
            <text x="196" y="228" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="11" font-weight="700">Proprietatea trece o dată</text>
            <text x="196" y="243" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9">Ceilalți „cumpărători" nu pot revendica</text>
          </g>

          <g class="fi sd7">
            <rect x="20" y="272" width="360" height="34" rx="10" fill="#FF6B6B" opacity=".08"/>
            <text x="36" y="288" fill="#FF8A6B" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5" font-weight="700">Blochează escrocheria „un bilet, zece cumpărători"</text>
            <text x="36" y="301" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9">Banii circulă în afara registrului · zero date personale</text>
          </g>
        </svg>
        <div class="fcap">Bilet semnat · verificare publică · transfer atomic</div>
      </div>
    </div>

    <!-- 12 -->
    <div class="feat flip">
      <div class="rv-r">
        <div class="ftag"><i></i>12 — DISTRIBUȚIE</div>
        <h3>Gestiunea artiștilor și a distribuției</h3>
        <p>Adaugi distribuția o singură dată. Apare automat pe pagina spectacolului, în emailuri, pe bilete.</p>
        <ul>
          <li><strong>Fișă de artist</strong> — fotografie, biografie, legături către profilurile publice.</li>
          <li><strong>Alocare pe evenimente</strong> — artiștii se atribuie din aceeași interfață.</li>
          <li><strong>Distribuții alternative</strong> — mai multe variante pentru același rol.</li>
          <li><strong>Reutilizare</strong> — un artist introdus o dată se atașează oricâtor spectacole.</li>
          <li><strong>Afișare automată</strong> pe pagina publică, cu foto și rol.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> reduce semnificativ timpul de configurare a unui spectacol nou. Într-un teatru cu trupă stabilă, distribuția se repetă de la o producție la alta — se selectează, nu se retastează.</div>
      </div>
      <div class="fig rv-l">
        <svg viewBox="0 0 400 270" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Distribuția spectacolului cu actori și roluri">
          <text x="20" y="22" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="9" class="fi">DISTRIBUȚIE — „HAMLET"</text>
          <g class="rv-s pop sd1">
            <rect x="20" y="34" width="360" height="52" rx="10" fill="#12121F" stroke="#26263C"/>
            <circle cx="50" cy="60" r="18" fill="#7C5CFF" opacity=".2"/>
            <text x="50" y="66" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="800" text-anchor="middle">MA</text>
            <text x="80" y="56" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="700">Marcel Antonescu</text>
            <text x="80" y="73" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="11">Hamlet</text>
            <rect x="300" y="50" width="64" height="20" rx="10" fill="#22D3A6" opacity=".14"/>
            <text x="332" y="64" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5" font-weight="700" text-anchor="middle">Principal</text>
          </g>
          <g class="pop sd3">
            <rect x="20" y="94" width="360" height="52" rx="10" fill="#12121F" stroke="#26263C"/>
            <circle cx="50" cy="120" r="18" fill="#7C5CFF" opacity=".2"/>
            <text x="50" y="126" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="800" text-anchor="middle">EP</text>
            <text x="80" y="116" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="700">Elena Popescu</text>
            <text x="80" y="133" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="11">Ofelia</text>
          </g>
          <g class="pop sd5">
            <rect x="20" y="154" width="360" height="52" rx="10" fill="#12121F" stroke="#26263C"/>
            <circle cx="50" cy="180" r="18" fill="#7C5CFF" opacity=".2"/>
            <text x="50" y="186" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="800" text-anchor="middle">AI</text>
            <text x="80" y="176" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="13" font-weight="700">Adrian Ionescu</text>
            <text x="80" y="193" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="11">Claudius</text>
            <rect x="286" y="170" width="78" height="20" rx="10" fill="#FFB020" opacity=".14"/>
            <text x="325" y="184" fill="#FFB020" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5" font-weight="700" text-anchor="middle">Alternativă</text>
          </g>

          <g class="fi sd7">
            <path d="M50 206 L50 228 L350 228" stroke="#7C5CFF" stroke-width="1.4" fill="none" stroke-dasharray="4 4" opacity=".5"/>
            <rect x="20" y="236" width="360" height="26" rx="8" fill="#7C5CFF" opacity=".1"/>
            <text x="200" y="253" fill="#A78BFA" font-family="JetBrains Mono,monospace" font-size="8.5" text-anchor="middle">INTRODUS O DATĂ · REUTILIZAT LA ORICÂTE SPECTACOLE</text>
          </g>
        </svg>
        <div class="fcap">Distribuția, reutilizabilă între producții</div>
      </div>
    </div>

    <!-- 11 -->
    <div class="feat">
      <div class="rv-l">
        <div class="ftag"><i></i>13 — VIZIBILITATE</div>
        <h3>Construit SEO-first — indexare organică</h3>
        <p>Fiecare spectacol apare în Google optimizat. Titluri, descrieri, schema markup — toate automate.</p>
        <ul>
          <li><strong>Pagină proprie, indexabilă</strong> pentru fiecare spectacol și reprezentație.</li>
          <li><strong>Date structurate</strong> — apar cu dată, oră, locație și preț în rezultate.</li>
          <li><strong>Adrese curate</strong>, titluri și descrieri controlabile din panou.</li>
          <li><strong>Recenzii afișate</strong> în rezultate, cresc rata de click.</li>
          <li><strong>Vizibilitate fără buget de publicitate</strong> — publicul ajunge direct pe pagina teatrului.</li>
        </ul>
        <div class="why"><b>De ce contează:</b> un teatru de stat are buget de promovare limitat. Traficul organic aduce spectatori fără cost per click și reduce dependența de intermediarii care își vând propriile pagini în locul celor ale instituției.</div>
      </div>
      <div class="fig rv-r">
        <svg viewBox="0 0 400 250" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Cum apare spectacolul în rezultatele Google">
          <g class="pop sd1">
            <rect x="16" y="14" width="368" height="40" rx="20" fill="#12121F" stroke="#26263C"/>
            <circle cx="46" cy="34" r="7" fill="none" stroke="#8A8AA0" stroke-width="2"/>
            <path d="m52 40 5 5" stroke="#8A8AA0" stroke-width="2" stroke-linecap="round"/>
            <text x="70" y="39" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="12">bilete hamlet teatrul municipal</text>
          </g>

          <g class="pop sd3">
            <rect x="16" y="68" width="368" height="104" rx="11" fill="#0B0B14" stroke="#7C5CFF" stroke-opacity=".35"/>
            <text x="34" y="92" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">teatrul-tau.ro › spectacole › hamlet</text>
            <text x="34" y="114" fill="#7C9DFF" font-family="Plus Jakarta Sans,sans-serif" font-size="14" font-weight="700">Hamlet — Teatrul Municipal | Bilete</text>
            <text x="34" y="134" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">Tragedie de William Shakespeare. Premieră 15 octombrie 2026.</text>
            <text x="34" y="149" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">Cumpără bilete online, alege-ți locul în sală.</text>
            <text x="34" y="165" fill="#FFB020" font-family="Plus Jakarta Sans,sans-serif" font-size="10.5">★★★★★ 127 recenzii · 15 oct, 19:00 · de la 45 RON</text>
          </g>

          <g class="fi sd5">
            <rect x="16" y="186" width="118" height="24" rx="8" fill="#22D3A6" opacity=".12"/>
            <text x="75" y="202" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5" font-weight="700" text-anchor="middle">Schema markup</text>
            <rect x="141" y="186" width="118" height="24" rx="8" fill="#22D3A6" opacity=".12"/>
            <text x="200" y="202" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5" font-weight="700" text-anchor="middle">Adrese curate</text>
            <rect x="266" y="186" width="118" height="24" rx="8" fill="#22D3A6" opacity=".12"/>
            <text x="325" y="202" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5" font-weight="700" text-anchor="middle">Sitemap automat</text>
          </g>

          <g class="fi sd6">
            <text x="200" y="238" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5" text-anchor="middle">TRAFIC ORGANIC · ZERO COST PER CLICK</text>
          </g>
        </svg>
        <div class="fcap">Rezultat organic, cu date structurate</div>
      </div>
    </div>

    <!-- 12 -->
    <div class="feat flip">
      <div class="rv-r">
        <div class="ftag"><i></i>14 — RAPORTARE</div>
        <h3>Rapoarte și transparență financiară</h3>
        <ul>
          <li><strong>Rapoarte de vânzări</strong> pe spectacol, pe perioadă, pe categorie de preț.</li>
          <li><strong>Rapoarte fiscale și TVA</strong> per eveniment.</li>
          <li><strong>Livrare programată</strong> — rapoarte trimise automat, periodic.</li>
          <li><strong>Export PDF și CSV</strong> pentru orice raport.</li>
          <li><strong>Tablou de bord în timp real</strong> — încasări, bilete, grad de ocupare.</li>
          <li><strong>Evidența separată</strong> a vânzărilor față de invitații.</li>
        </ul>
      </div>
      <div class="fig rv-l">
        <svg viewBox="0 0 400 260" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Tablou de bord cu încasări pe stagiune">
          <text x="20" y="20" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="9" class="fi">ÎNCASĂRI PE STAGIUNE</text>
          <g class="pop sd1"><rect x="20" y="30" width="112" height="46" rx="9" fill="#12121F" stroke="#26263C"/>
          <text x="34" y="48" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7.5">BILETE VÂNDUTE</text>
          <text x="34" y="66" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="17" font-weight="800">6.284</text></g>
          <g class="pop sd2"><rect x="144" y="30" width="112" height="46" rx="9" fill="#12121F" stroke="#26263C"/>
          <text x="158" y="48" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7.5">ÎNCASĂRI</text>
          <text x="158" y="66" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="17" font-weight="800">412K</text></g>
          <g class="pop sd3"><rect x="268" y="30" width="112" height="46" rx="9" fill="#12121F" stroke="#26263C"/>
          <text x="282" y="48" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7.5">OCUPARE MEDIE</text>
          <text x="282" y="66" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="17" font-weight="800">81%</text></g>

          <line x1="20" y1="196" x2="380" y2="196" stroke="#26263C" class="fi sd3"/>
          <g>
            <rect x="34" y="140" width="26" height="56" rx="4" fill="#7C5CFF" opacity=".55" class="gw sd4"/>
            <rect x="80" y="120" width="26" height="76" rx="4" fill="#7C5CFF" opacity=".65" class="gw sd5"/>
            <rect x="126" y="150" width="26" height="46" rx="4" fill="#7C5CFF" opacity=".5" class="gw sd6"/>
            <rect x="172" y="106" width="26" height="90" rx="4" fill="#7C5CFF" opacity=".75" class="gw sd7"/>
            <rect x="218" y="128" width="26" height="68" rx="4" fill="#7C5CFF" opacity=".6" class="gw sd8"/>
            <rect x="264" y="98" width="26" height="98" rx="4" fill="#22D3A6" opacity=".8" class="gw sd9"/>
            <rect x="310" y="132" width="26" height="64" rx="4" fill="#7C5CFF" opacity=".6" class="gw sd10"/>
          </g>
          <g fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="7.5" text-anchor="middle" class="fi sd9">
            <text x="47" y="210">OCT</text><text x="93" y="210">NOV</text><text x="139" y="210">DEC</text>
            <text x="185" y="210">IAN</text><text x="231" y="210">FEB</text><text x="277" y="210">MAR</text><text x="323" y="210">APR</text>
          </g>
          <g class="fi sd10">
            <rect x="20" y="226" width="8" height="8" rx="2" fill="#7C5CFF" opacity=".65"/>
            <text x="34" y="234" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Vânzări</text>
            <rect x="96" y="226" width="8" height="8" rx="2" fill="#22D3A6" opacity=".8"/>
            <text x="110" y="234" fill="#A2A2B8" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Lună record</text>
            <rect x="286" y="222" width="94" height="18" rx="9" fill="#7C5CFF" opacity=".14"/>
            <text x="333" y="235" fill="#A78BFA" font-family="JetBrains Mono,monospace" font-size="8" text-anchor="middle">PDF · CSV</text>
          </g>
        </svg>
        <div class="fcap">Tablou de bord — încasări și ocupare</div>
      </div>
    </div>
    </div>
    <button class="feat-toggle" id="featToggle" aria-expanded="false" aria-controls="featMore">
      <span class="feat-toggle-txt">Vezi toate cele 14 capabilități</span>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
    </button>

  </div>
</section>

<!-- POS -->
<section class="alt">
  <div class="wrap">
    <div class="eyebrow rv">Punct de vânzare fix</div>
    <h2 class="rv" data-d="1">Casieria teatrului, <span class="grad">într-un singur ecran.</span></h2>
    <p class="lede2 rv" data-d="2">Pe lângă vânzarea online, Tixello oferă un panou de vânzare fix pentru casierii — gândit pentru viteză la ghișeu. Alegi spectacolul, apeși pe tipurile de bilete, completezi datele dacă e nevoie și emiți pe imprimantă termică. Comisionul de 1% se calculează automat.</p>

    <div class="pos rv-s" data-d="2">
      <div class="pos-head">
        <div class="pos-title"><span class="pos-ic">🎭</span> Casierie — Emite bilete</div>
        <div class="pos-head-right">
          <span class="pos-chip amber"><span>🔒</span> Închidere casă</span>
          <span class="pos-chip"><span>🖨</span> Imprimantă termică <b class="dot-amber"></b> Neconectată</span>
        </div>
      </div>

      <div class="pos-event">
        <label class="pos-event-label">Spectacol</label>
        <div class="pos-select" id="posSelect" tabindex="0" role="button" aria-haspopup="listbox" aria-expanded="false">
          <span class="pos-select-cur" id="posSelCur">Hamlet · Sala Mare · 12 nov, 19:00</span>
          <span class="pos-select-caret">▾</span>
          <div class="pos-select-menu" id="posSelMenu" role="listbox">
            <div class="pos-opt active" data-show="Hamlet" data-when="Sala Mare · 12 nov, 19:00" role="option">Hamlet · Sala Mare · 12 nov, 19:00</div>
            <div class="pos-opt" data-show="O scrisoare pierdută" data-when="Sala Mare · 15 nov, 19:00" role="option">O scrisoare pierdută · Sala Mare · 15 nov, 19:00</div>
            <div class="pos-opt" data-show="Pisica verde" data-when="Sala Studio · 18 nov, 20:00" role="option">Pisica verde · Sala Studio · 18 nov, 20:00</div>
            <div class="pos-opt" data-show="Matinee: Motanul încălțat" data-when="Sala Mare · 20 nov, 11:00" role="option">Matinee: Motanul încălțat · Sala Mare · 20 nov, 11:00</div>
          </div>
        </div>
      </div>

      <div class="pos-body">
        <!-- LEFT: ticket catalogue for the chosen show -->
        <div class="pos-cat">
          <div class="pos-catacc open">
            <button class="pos-catacc-head" aria-expanded="true">
              <span>CATEGORII DE BILET <b>4</b></span><span class="pos-caret">▾</span>
            </button>
            <div class="pos-catacc-body">
              <div class="pos-tickets">
                <button class="pos-ticket" data-price="80" data-name="Întreg (stal)"><span class="pos-tag">STAL</span><span class="pos-tname">ÎNTREG</span><span class="pos-tprice">80,00 RON</span></button>
                <button class="pos-ticket" data-price="60" data-name="Elev / student (stal)"><span class="pos-tag">STAL</span><span class="pos-tname">ELEV / STUDENT</span><span class="pos-tprice">60,00 RON</span></button>
                <button class="pos-ticket" data-price="45" data-name="Pensionar (balcon)"><span class="pos-tag">BALCON</span><span class="pos-tname">PENSIONAR</span><span class="pos-tprice">45,00 RON</span></button>
                <button class="pos-ticket" data-price="0" data-name="Invitație protocol"><span class="pos-tag">PROTOCOL</span><span class="pos-tname">INVITAȚIE</span><span class="pos-tprice">0,00 RON</span></button>
              </div>
            </div>
          </div>

          <div class="pos-catacc">
            <button class="pos-catacc-head" aria-expanded="false">
              <span>ABONAMENT DE STAGIUNE <b>3</b></span><span class="pos-caret">▸</span>
            </button>
            <div class="pos-catacc-body">
              <div class="pos-tickets">
                <button class="pos-ticket" data-price="640" data-name="Abonament întreg (10 spectacole)"><span class="pos-tag">STAGIUNE</span><span class="pos-tname">ÎNTREG · 10 SPECT.</span><span class="pos-tprice">640,00 RON</span></button>
                <button class="pos-ticket" data-price="480" data-name="Abonament redus (10 spectacole)"><span class="pos-tag">STAGIUNE</span><span class="pos-tname">REDUS · 10 SPECT.</span><span class="pos-tprice">480,00 RON</span></button>
                <button class="pos-ticket" data-price="360" data-name="Abonament weekend (6 spectacole)"><span class="pos-tag">STAGIUNE</span><span class="pos-tname">WEEKEND · 6 SPECT.</span><span class="pos-tprice">360,00 RON</span></button>
              </div>
            </div>
          </div>

          <div class="pos-catacc">
            <button class="pos-catacc-head" aria-expanded="false">
              <span>BILETE DE GRUP <b>4</b></span><span class="pos-caret">▸</span>
            </button>
            <div class="pos-catacc-body">
              <div class="pos-tickets">
                <button class="pos-ticket" data-price="55" data-name="Grup adulți (min. 10)"><span class="pos-tag">GRUP 10+</span><span class="pos-tname">ADULȚI</span><span class="pos-tprice">55,00 RON</span></button>
                <button class="pos-ticket" data-price="30" data-name="Grup școlar (min. 15)"><span class="pos-tag">GRUP 15+</span><span class="pos-tname">ELEVI</span><span class="pos-tprice">30,00 RON</span></button>
                <button class="pos-ticket" data-price="40" data-name="Grup pensionari (min. 10)"><span class="pos-tag">GRUP 10+</span><span class="pos-tname">PENSIONARI</span><span class="pos-tprice">40,00 RON</span></button>
                <button class="pos-ticket" data-price="25" data-name="Cadru didactic însoțitor"><span class="pos-tag">GRUP</span><span class="pos-tname">ÎNSOȚITOR</span><span class="pos-tprice">25,00 RON</span></button>
              </div>
            </div>
          </div>

          <div class="pos-catacc">
            <button class="pos-catacc-head" aria-expanded="false">
              <span>PACHETE (bilet + extra) <b>2</b></span><span class="pos-caret">▸</span>
            </button>
            <div class="pos-catacc-body">
              <div class="pos-tickets">
                <button class="pos-ticket" data-price="95" data-name="Bilet + program de sală"><span class="pos-tag">PACHET</span><span class="pos-tname">BILET + PROGRAM</span><span class="pos-tprice">95,00 RON</span></button>
                <button class="pos-ticket" data-price="120" data-name="Bilet + întâlnire cu actorii"><span class="pos-tag">PACHET</span><span class="pos-tname">BILET + MEET &amp; GREET</span><span class="pos-tprice">120,00 RON</span></button>
              </div>
            </div>
          </div>

          <div class="pos-catacc">
            <button class="pos-catacc-head" aria-expanded="false">
              <span>REDUCERI ȘI VOUCHERE <b>5</b></span><span class="pos-caret">▸</span>
            </button>
            <div class="pos-catacc-body">
              <div class="pos-tickets">
                <button class="pos-ticket" data-price="40" data-name="Card cultural (Edenred/Pluxee)"><span class="pos-tag">CARD CULTURAL</span><span class="pos-tname">TARIF CARD</span><span class="pos-tprice">40,00 RON</span></button>
                <button class="pos-ticket" data-price="56" data-name="Reducere abonat (−30%)"><span class="pos-tag">−30%</span><span class="pos-tname">ABONAT FIDEL</span><span class="pos-tprice">56,00 RON</span></button>
                <button class="pos-ticket" data-price="0" data-name="Voucher cadou (acoperă biletul)"><span class="pos-tag">VOUCHER</span><span class="pos-tname">CADOU</span><span class="pos-tprice">0,00 RON</span></button>
                <button class="pos-ticket" data-price="64" data-name="Last-minute (−20%)"><span class="pos-tag">−20%</span><span class="pos-tname">LAST-MINUTE</span><span class="pos-tprice">64,00 RON</span></button>
                <button class="pos-ticket" data-price="40" data-name="Ziua ta (−50%)"><span class="pos-tag">−50%</span><span class="pos-tname">ZIUA TA</span><span class="pos-tprice">40,00 RON</span></button>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT: cart -->
        <div class="pos-cart">
          <div class="pos-cart-head">
            <span>Coș</span>
            <button class="pos-empty" id="posEmpty">🗑 Golește coș</button>
          </div>
          <div class="pos-cart-items" id="posItems">
            <div class="pos-cart-empty" id="posEmptyMsg">Coș gol. Apasă pe un bilet ca să-l adaugi.</div>
          </div>

          <div class="pos-acc" id="accClient">
            <button class="pos-acc-head" aria-expanded="false" aria-controls="accClientBody">
              <span>👤 Date client <em>(opțional)</em></span><span class="pos-acc-caret">▸</span>
            </button>
            <div class="pos-acc-body" id="accClientBody">
              <input class="pos-input" type="text" placeholder="Nume" readonly>
              <input class="pos-input" type="text" placeholder="Email (pentru bilete pe mail)" readonly>
              <input class="pos-input" type="text" placeholder="Telefon" readonly>
              <textarea class="pos-input" placeholder="Informații suplimentare (opțional) — ex: solicitări speciale, grup, observații" rows="2" readonly></textarea>
            </div>
          </div>

          <div class="pos-acc" id="accFirm">
            <button class="pos-acc-head" aria-expanded="false" aria-controls="accFirmBody">
              <span>🏢 Date firmă <em>(opțional)</em> — factură pe persoană juridică</span><span class="pos-acc-caret">▸</span>
            </button>
            <div class="pos-acc-body" id="accFirmBody">
              <input class="pos-input" type="text" placeholder="Denumire firmă" readonly>
              <div class="pos-input-row">
                <input class="pos-input" type="text" placeholder="CUI / CIF (ex: RO12345678)" readonly>
                <button class="pos-anaf" tabindex="-1">🔍 ANAF</button>
              </div>
              <input class="pos-input" type="text" placeholder="Nr. Reg. Com." readonly>
              <input class="pos-input" type="text" placeholder="Sediu (adresă completă)" readonly>
            </div>
          </div>

          <div class="pos-totals">
            <div class="pos-subtotal"><span>Subtotal bilete</span><span id="posSub">0,00 RON</span></div>
            <div class="pos-subtotal"><span>Comision ticketing <b class="pos-fee-pct">1%</b></span><span id="posFee">0,00 RON</span></div>
            <div class="pos-total"><span>Total</span><span id="posTotal">0,00 RON</span></div>
          </div>
          <div class="pos-pay-label">METODĂ PLATĂ</div>
          <div class="pos-pay">
            <button class="pos-pay-btn active">💵 Cash</button>
            <button class="pos-pay-btn">💳 Card</button>
          </div>
          <button class="pos-finish" id="posFinish" disabled>Finalizează</button>
        </div>
      </div>
    </div>

    <div class="pos-notes">
      <div class="pos-note rv" data-d="3"><span class="pos-note-ic">🎭</span><div><b>Vânzare pe spectacol</b><p>Casierul alege reprezentația, apoi tipurile de bilet definite pentru ea: stal, balcon, reduceri, protocol, abonamente.</p></div></div>
      <div class="pos-note rv" data-d="4"><span class="pos-note-ic">🧾</span><div><b>Factură pe loc, inclusiv pentru firme</b><p>Date de client pentru biletul pe email; date de firmă cu verificare ANAF pentru factura pe persoană juridică.</p></div></div>
      <div class="pos-note rv" data-d="5"><span class="pos-note-ic">🖨</span><div><b>Imprimantă termică și închidere de casă</b><p>Bilet tipărit pe loc, cu raport de închidere la finalul turei — exact ce cere o casierie de instituție.</p></div></div>
    </div>
  </div>
</section>

<!-- SPEED -->
<section>
  <div class="wrap">
    <div class="eyebrow rv">Viteza sistemului</div>
    <h2 class="rv" data-d="1">Cumpărare în <span class="grad">sub 60 de secunde.</span><br>Rambursare transmisă instant.</h2>
    <p class="lede2 rv" data-d="2">Două momente decid dacă un spectator revine: cât de repede cumpără și cât de repede pornește banii înapoi când un spectacol se anulează. La un click, Tixello transmite rambursarea către procesator instant — timpul până când banii apar pe card ține apoi de bancă.</p>

    <div class="speed-grid">
      <div class="rv-l" data-d="2">
        <div class="ftag"><i></i>ACHIZIȚIE</div>
        <h3>De la căutare la bilet în buzunar</h3>
        <p>Patru pași, fără cont obligatoriu, fără formulare inutile. Biletul ajunge pe email și în Apple Wallet imediat după plată.</p>
        <div class="fig" style="margin-top:20px;position:static">
          <svg viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Cei patru pași ai achiziției unui bilet, cu timpii aferenți">
            <text x="20" y="18" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="9" class="fi">FLUX DE ACHIZIȚIE</text>

            <g class="pop sd1">
              <rect x="20" y="30" width="360" height="52" rx="10" fill="#12121F" stroke="#26263C"/>
              <circle cx="48" cy="56" r="14" fill="#7C5CFF" opacity=".18"/>
              <text x="48" y="61" font-size="13" text-anchor="middle">🔍</text>
              <text x="74" y="52" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Alege spectacolul</text>
              <text x="74" y="68" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10">Din Google sau direct de pe site-ul teatrului</text>
              <text x="366" y="60" fill="#22D3A6" font-family="JetBrains Mono,monospace" font-size="10" text-anchor="end">~10s</text>
            </g>
            <path d="M200 82 L200 96" stroke="#7C5CFF" stroke-width="2" class="dash sd2" style="--len:16"/>
            <path d="M195 92 l5 6 5-6" fill="#7C5CFF" class="fi sd2"/>

            <g class="pop sd3">
              <rect x="20" y="100" width="360" height="52" rx="10" fill="#12121F" stroke="#26263C"/>
              <circle cx="48" cy="126" r="14" fill="#7C5CFF" opacity=".18"/>
              <text x="48" y="131" font-size="13" text-anchor="middle">💺</text>
              <text x="74" y="122" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Alege locul pe hartă</text>
              <text x="74" y="138" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10">Vede sala reală, se blochează 10 minute</text>
              <text x="366" y="130" fill="#22D3A6" font-family="JetBrains Mono,monospace" font-size="10" text-anchor="end">~20s</text>
            </g>
            <path d="M200 152 L200 166" stroke="#7C5CFF" stroke-width="2" class="dash sd4" style="--len:16"/>
            <path d="M195 162 l5 6 5-6" fill="#7C5CFF" class="fi sd4"/>

            <g class="pop sd5">
              <rect x="20" y="170" width="360" height="52" rx="10" fill="#12121F" stroke="#26263C"/>
              <circle cx="48" cy="196" r="14" fill="#7C5CFF" opacity=".18"/>
              <text x="48" y="201" font-size="13" text-anchor="middle">💳</text>
              <text x="74" y="192" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Plătește</text>
              <text x="74" y="208" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10">Card, Apple Pay, Google Pay, card cultural, rate</text>
              <text x="366" y="200" fill="#22D3A6" font-family="JetBrains Mono,monospace" font-size="10" text-anchor="end">~20s</text>
            </g>
            <path d="M200 222 L200 236" stroke="#22D3A6" stroke-width="2" class="dash sd6" style="--len:16"/>
            <path d="M195 232 l5 6 5-6" fill="#22D3A6" class="fi sd6"/>

            <g class="pop sd7">
              <rect x="20" y="240" width="360" height="52" rx="10" fill="#12121F" stroke="#22D3A6" stroke-opacity=".45"/>
              <circle cx="48" cy="266" r="14" fill="#22D3A6" opacity=".2" class="anim-pulse"/>
              <path d="M42 266 l5 5 8-9" stroke="#22D3A6" stroke-width="2.4" fill="none" stroke-linecap="round"/>
              <text x="74" y="262" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">Biletul pe email + Wallet</text>
              <text x="74" y="278" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10">Instant, cu QR valid și factura atașată</text>
              <text x="366" y="270" fill="#22D3A6" font-family="JetBrains Mono,monospace" font-size="10" text-anchor="end">instant</text>
            </g>
          </svg>
          <div class="fcap">Sub un minut, de la căutare la bilet</div>
        </div>
      </div>

      <div class="rv-r" data-d="3">
        <div class="ftag"><i></i>RAMBURSĂRI</div>
        <h3>Anulare de spectacol, rezolvată în bloc</h3>
        <p>Când o reprezentație se anulează, operatorul nu procesează sute de returnări manual. Selectează reprezentația, confirmă, iar sistemul returnează automat pe același card.</p>
        <div class="fig" style="margin-top:20px;position:static">
          <svg viewBox="0 0 400 300" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Rambursare în bloc după anularea unei reprezentații">
            <text x="20" y="18" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="9" class="fi">ANULARE REPREZENTAȚIE · 412 BILETE</text>

            <g class="pop sd1">
              <rect x="20" y="30" width="360" height="46" rx="10" fill="#12121F" stroke="#FF6B6B" stroke-opacity=".35"/>
              <text x="40" y="57" font-size="14">⚠</text>
              <text x="64" y="49" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12.5" font-weight="700">„Hamlet" · 5 noiembrie — anulat</text>
              <text x="64" y="64" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="10">Un singur click în panou</text>
            </g>
            <path d="M200 76 L200 92" stroke="#FF6B6B" stroke-width="2" class="dash sd2" style="--len:18"/>
            <path d="M195 88 l5 6 5-6" fill="#FF6B6B" class="fi sd2"/>

            <g class="pop sd3">
              <rect x="20" y="96" width="360" height="66" rx="10" fill="#0B0B14" stroke="#26263C"/>
              <text x="36" y="118" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8.5">RETURNARE ÎN BLOC — PROGRES</text>
              <rect x="36" y="128" width="328" height="12" rx="6" fill="#181829"/>
              <rect x="36" y="128" width="328" height="12" rx="6" fill="#22D3A6" opacity=".8" class="gx sd4"/>
              <text x="36" y="156" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="11" font-weight="700" class="fi sd6">412 / 412 transmise instant la procesator</text>
            </g>
            <path d="M200 162 L200 178" stroke="#22D3A6" stroke-width="2" class="dash sd6" style="--len:18"/>
            <path d="M195 174 l5 6 5-6" fill="#22D3A6" class="fi sd6"/>

            <g class="pop sd7">
              <rect x="20" y="182" width="174" height="58" rx="10" fill="#12121F" stroke="#26263C"/>
              <text x="34" y="202" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8">TRANSMISĂ INSTANT</text>
              <text x="34" y="219" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="11.5" font-weight="700">Pe același card</text>
              <text x="34" y="232" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9">Stripe · Netopia · PayPal</text>
            </g>
            <g class="pop sd8">
              <rect x="206" y="182" width="174" height="58" rx="10" fill="#12121F" stroke="#26263C"/>
              <text x="220" y="202" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="8">NOTIFICARE</text>
              <text x="220" y="219" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="11.5" font-weight="700">Email automat</text>
              <text x="220" y="232" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9">Către toți spectatorii</text>
            </g>

            <g class="pop sd9">
              <rect x="20" y="252" width="360" height="40" rx="10" fill="#22D3A6" opacity=".1"/>
              <text x="40" y="276" font-size="13">⚡</text>
              <text x="64" y="269" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="11.5" font-weight="700">Alternativ: reprogramare, cu păstrarea locului</text>
              <text x="64" y="282" fill="#8A8AA0" font-family="Plus Jakarta Sans,sans-serif" font-size="9.5">Spectatorul își păstrează același loc la noua dată</text>
            </g>
          </svg>
          <div class="fcap">Sute de returnări, o singură operațiune</div>
        </div>
      </div>
    </div>

    <div class="speed-stats rv" data-d="2">
      <div class="sstat"><div class="v">&lt;60s</div><div class="k">Cumpărare completă</div></div>
      <div class="sstat"><div class="v">&lt;1s</div><div class="k">Validare QR la intrare</div></div>
      <div class="sstat"><div class="v">1 click</div><div class="k">Rambursare în bloc</div></div>
      <div class="sstat"><div class="v">24h</div><div class="k">Configurare inițială</div></div>
    </div>
  </div>
</section>

<!-- LIVE DEMO -->
<section>
  <div class="wrap">
    <div class="eyebrow rv">Vezi cu ochii tăi</div>
    <h2 class="rv" data-d="1">Un demo live te așteaptă la <span class="grad">teatru.tixello.ro</span></h2>
    <p class="lede2 rv" data-d="2">Nu trebuie să ne crezi pe cuvânt. Intră pe demo-ul public, cu o sală reală, spectacole și tot fluxul de cumpărare — exact ce va vedea spectatorul teatrului tău. Fără cont, fără instalare.</p>

    <a class="demo-window rv-s" data-d="2" href="https://teatru.tixello.ro" target="_blank" rel="noopener" aria-label="Deschide demo-ul live pe teatru.tixello.ro">
      <div class="demo-bar">
        <span class="demo-dots"><i></i><i></i><i></i></span>
        <span class="demo-url"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#22D3A6" stroke-width="2.4" stroke-linecap="round"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/></svg> teatru.tixello.ro</span>
        <span class="demo-open">Deschide ↗</span>
      </div>
      <div class="demo-view">
        <div class="demo-poster">
          <div class="demo-badge">DEMO LIVE</div>
          <div class="demo-show">
            <div class="demo-show-tag">STAGIUNEA 2026 · SALA MARE</div>
            <div class="demo-show-title">Alege-ți locul, cumpără biletul,<br>vezi tot fluxul în acțiune</div>
            <div class="demo-cta-row">
              <span class="demo-fakebtn">🎭 Vezi spectacolele</span>
              <span class="demo-fakebtn ghost">🗺 Deschide harta sălii</span>
            </div>
          </div>
        </div>
        <div class="demo-strip">
          <div class="demo-mini"><span>🗺</span> Hartă interactivă</div>
          <div class="demo-mini"><span>🎟</span> Cumpărare reală</div>
          <div class="demo-mini"><span>📱</span> Bilet cu QR</div>
          <div class="demo-mini"><span>🎫</span> Casierie POS</div>
        </div>
      </div>
    </a>

    <div class="demo-note rv" data-d="3">
      <span class="demo-note-ic">💡</span>
      <p>Vrei demo-ul pe <b>sala ta reală</b>, cu spectacolele tale? Îți configurez un mediu dedicat în 24 de ore — scrie-mi mai jos.</p>
    </div>
  </div>
</section>

<!-- COMPARE -->
<section class="alt">
  <div class="wrap">
    <div class="eyebrow rv">Nu Tixello da/nu — Tixello vs ce ai acum</div>
    <h2 class="rv" data-d="1">Cum arată diferența, <span class="grad">pe scurt.</span></h2>
    <p class="lede2 rv" data-d="2">Decizia reală nu e dacă să folosești un sistem, ci dacă cel de acum îți convine. Iată comparația directă.</p>
    <div class="cmp rv-s" data-d="2">
      <div class="cmp-row cmp-head">
        <div class="cmp-crit">Criteriu</div>
        <div class="cmp-old">Situația tipică acum</div>
        <div class="cmp-new">Cu Tixello</div>
      </div>
      <div class="cmp-row">
        <div class="cmp-crit">Comision</div>
        <div class="cmp-old">Adesea 5–10% din valoarea biletelor</div>
        <div class="cmp-new"><b>1%</b>, cel mai mic din piață</div>
      </div>
      <div class="cmp-row">
        <div class="cmp-crit">Când primești banii</div>
        <div class="cmp-old">La 3–30 de zile, după reținerea comisionului</div>
        <div class="cmp-new"><b>Instant</b>, direct în contul teatrului</div>
      </div>
      <div class="cmp-row">
        <div class="cmp-crit">Cine deține datele</div>
        <div class="cmp-old">Intermediarul; le vezi doar prin el</div>
        <div class="cmp-new"><b>Teatrul</b> — clienți, vânzări, rapoarte, toate ale tale</div>
      </div>
      <div class="cmp-row">
        <div class="cmp-crit">Site și pagină de vânzare</div>
        <div class="cmp-old">Cost separat cu o agenție, sau lipsă</div>
        <div class="cmp-new"><b>Gratuit</b>, construite și întreținute de noi</div>
      </div>
      <div class="cmp-row">
        <div class="cmp-crit">Specific de teatru</div>
        <div class="cmp-old">Sistem generic: fără abonamente, fără protocol</div>
        <div class="cmp-new"><b>Nativ</b>: hartă de sală, abonamente, invitații, ANAF</div>
      </div>
      <div class="cmp-row">
        <div class="cmp-crit">Costuri ascunse</div>
        <div class="cmp-old">Taxe de setup, abonament lunar, module extra</div>
        <div class="cmp-new"><b>Zero</b> — doar cei 1%</div>
      </div>
    </div>
  </div>
</section>

<!-- FAQ -->
<section>
  <div class="wrap">
    <div class="eyebrow rv">Întrebări frecvente</div>
    <h2 class="rv" data-d="1">Ce întreabă, de obicei, conducerea</h2>
    <p class="lede2 rv" data-d="2">Răspunsuri scurte la lucrurile care contează pentru o instituție publică.</p>
    <div class="faq rv" data-d="2">
      <div class="faq-item">
        <button class="faq-q" aria-expanded="false">Ce se întâmplă cu banii dacă Tixello dispare?<span class="faq-caret">▸</span></button>
        <div class="faq-a"><p>Banii nu trec niciodată prin Tixello — intră direct în contul teatrului, la fiecare vânzare. Nu există fonduri „blocate la noi" care s-ar putea pierde. Chiar și în cel mai rău scenariu, încasările tale sunt deja la tine.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-q" aria-expanded="false">Cine deține datele — clienți, vânzări, rapoarte?<span class="faq-caret">▸</span></button>
        <div class="faq-a"><p>Teatrul. Datele îți aparțin integral și pot fi exportate oricând (PDF, CSV). Nu te legăm de noi prin captivitatea datelor: dacă vrei să pleci, pleci cu tot ce e al tău.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-q" aria-expanded="false">Ce cost apare la volume mari de bilete?<span class="faq-caret">▸</span></button>
        <div class="faq-a"><p>Același 1%, indiferent de volum. Nu există praguri, taxe de trecere sau costuri care cresc pe măsură ce vinzi mai mult. Cu cât vinzi mai mult, cu atât rămâne mai mult la teatru față de un comision de 5–10%.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-q" aria-expanded="false">Cum se încadrează în achiziția publică?<span class="faq-caret">▸</span></button>
        <div class="faq-a"><p>Modelul de 1% comision, fără abonament și fără taxă de implementare, se poate justifica transparent ca procent din încasări. Îți punem la dispoziție documentația și o ofertă clară pentru dosarul de achiziție; formatul se adaptează procedurii instituției.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-q" aria-expanded="false">Cât durează trecerea și se oprește vânzarea?<span class="faq-caret">▸</span></button>
        <div class="faq-a"><p>Configurarea de bază durează 24 de ore. Rulăm noul sistem în paralel cu cel vechi, iar comutarea se face într-o fereastră fără spectacol — zero zile fără vânzare. Abonamentele în curs și datele istorice se preiau.</p></div>
      </div>
      <div class="faq-item">
        <button class="faq-q" aria-expanded="false">Cine răspunde dacă apare o problemă la spectacol?<span class="faq-caret">▸</span></button>
        <div class="faq-a"><p>Ai suport dedicat, inclusiv în intervalele de spectacol. În perioada de tranziție primești un canal direct, ca să existe mereu cineva cu care să vorbești imediat dacă apare ceva vineri seara la sală.</p></div>
      </div>
    </div>
  </div>
</section>

<!-- CTA MID -->
<section class="alt cta-band">
  <div class="wrap cta-inner">
    <div class="rv-l">
      <div class="eyebrow">Discutăm concret</div>
      <h2>Vrei să vezi sistemul<br><span class="grad">pe sala teatrului tău?</span></h2>
      <p class="lede2">Îți configurez o demonstrație cu harta reală a sălii voastre și cu un spectacol din stagiunea curentă. Durează 30 de minute.</p>
      <div class="cta-actions">
        <a class="btn btn-primary" href="mailto:andrei@tixello.ro?subject=Demo%20Tixello%20%E2%80%94%20teatru&amp;body=Bun%C4%83%20ziua%2C%20Andrei%2C%0A%0AAm%20parcurs%20prezentarea%20Tixello%20pentru%20teatre%20%C8%99i%20a%C8%99%20dori%20o%20demonstra%C8%9Bie.%0A%0AInstitu%C8%9Bia%3A%20%0APersoana%20de%20contact%3A%20%0ATelefon%3A%20%0A%0AV%C4%83%20mul%C8%9Bumesc.">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
          Trimite un email
        </a>
        <a class="btn btn-ghost" href="https://www.linkedin.com/in/nastase-andrei" target="_blank" rel="noopener">
          <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6z"/><rect x="2" y="9" width="4" height="12"/><circle cx="4" cy="4" r="2"/></svg>
          Scrie-mi pe LinkedIn
        </a>
      </div>
      <p class="cta-fine">Fără obligații și fără contract. Dacă nu se potrivește, îți spun direct.</p>
    </div>
    <div class="rv-r rep-card">
      <div class="rep-avatar">AN</div>
      <div class="rep-name">Andrei Năstase</div>
      <div class="rep-role">Fondator · Tixello</div>
      <div class="rep-links">
        <a href="mailto:andrei@tixello.ro">andrei@tixello.ro</a>
        <a href="https://www.linkedin.com/in/nastase-andrei" target="_blank" rel="noopener">linkedin.com/in/nastase-andrei</a>
        <a href="https://tixello.com" target="_blank" rel="noopener">tixello.com</a>
      </div>
    </div>
  </div>
</section>

<!-- MONEY FLOW -->
<section class="alt">
  <div class="wrap">
    <div class="eyebrow rv">Flux financiar</div>
    <h2 class="rv" data-d="1">Cum ajung banii la teatru</h2>
    <p class="lede2 rv" data-d="2">Modelul este construit ca instituția să rămână proprietara încasărilor. Tixello este furnizor de sistem, nu intermediar financiar.</p>

    <div class="rv-s" data-d="2" style="margin-top:32px;background:var(--card);border:1px solid var(--line);border-radius:16px;padding:26px">
      <svg viewBox="0 0 760 150" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Traseul banilor de la spectator la contul teatrului">
        <g class="pop sd1">
          <rect x="0" y="30" width="150" height="66" rx="11" fill="#12121F" stroke="#26263C"/>
          <text x="75" y="58" font-size="20" text-anchor="middle">🎟</text>
          <text x="75" y="80" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700" text-anchor="middle">Spectator</text>
        </g>
        <path d="M150 63 L228 63" stroke="#7C5CFF" stroke-width="2" class="dash sd2" style="--len:80"/>
        <path d="M222 58 l8 5 -8 5" fill="#7C5CFF" class="fi sd3"/>

        <g class="pop sd3">
          <rect x="236" y="30" width="150" height="66" rx="11" fill="#12121F" stroke="#22D3A6" stroke-opacity=".5"/>
          <text x="311" y="58" font-size="20" text-anchor="middle">🏛</text>
          <text x="311" y="80" fill="#22D3A6" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700" text-anchor="middle">Contul teatrului</text>
        </g>
        <text x="311" y="118" fill="#22D3A6" font-family="JetBrains Mono,monospace" font-size="9" text-anchor="middle" class="fi sd4">INSTANT · DIRECT</text>

        <path d="M386 63 L464 63" stroke="#26263C" stroke-width="2" stroke-dasharray="4 4" class="dash sd5" style="--len:80"/>

        <g class="pop sd5">
          <rect x="472" y="30" width="150" height="66" rx="11" fill="#12121F" stroke="#26263C"/>
          <text x="547" y="58" font-size="20" text-anchor="middle">📄</text>
          <text x="547" y="80" fill="#F4F4F8" font-family="Plus Jakarta Sans,sans-serif" font-size="12" font-weight="700" text-anchor="middle">Factură lunară 1%</text>
        </g>
        <path d="M622 63 L700 63" stroke="#7C5CFF" stroke-width="2" class="dash sd6" style="--len:80"/>
        <path d="M694 58 l8 5 -8 5" fill="#7C5CFF" class="fi sd7"/>
        <g class="pop sd7">
          <rect x="708" y="42" width="52" height="42" rx="10" fill="#7C5CFF" opacity=".16"/>
          <text x="734" y="69" fill="#A78BFA" font-family="Plus Jakarta Sans,sans-serif" font-size="14" font-weight="800" text-anchor="middle">tx</text>
        </g>

        <g class="fi sd8">
          <rect x="0" y="128" width="760" height="1" fill="#26263C"/>
          <text x="0" y="148" fill="#8A8AA0" font-family="JetBrains Mono,monospace" font-size="9">FONDURILE NU TREC PRIN TIXELLO — DOAR COMISIONUL SE FACTUREAZĂ</text>
        </g>
      </svg>
    </div>

    <div class="flow">
      <div class="step rv" data-d="1"><div class="s">PASUL 1</div><h4>Spectatorul plătește</h4><p>Online sau la ghișeu, cash sau card.</p></div>
      <div class="step rv" data-d="2"><div class="s">PASUL 2</div><h4>Banii intră în contul teatrului</h4><p>Instant, direct în contul societății. Fondurile nu trec prin Tixello.</p></div>
      <div class="step rv" data-d="3"><div class="s">PASUL 3</div><h4>O singură factură lunară</h4><p>Tixello emite lunar o factură unică, pentru comisionul de 1%.</p></div>
      <div class="step rv" data-d="4"><div class="s">PASUL 4</div><h4>Teatrul achită comisionul</h4><p>O plată pe lună, cu documentul justificativ aferent.</p></div>
    </div>

    <div class="callout rv" data-d="2">
      <strong>Comisionul de 1%</strong> poate fi <strong>inclus în prețul biletului</strong> — suportat de teatru, prețul afișat rămâne prețul final — sau <strong>adăugat peste prețul biletului</strong> la checkout, caz în care este suportat de spectator. Decizia aparține integral instituției și se poate schimba oricând.
    </div>
  </div>
</section>

<!-- TABLE -->
<section>
  <div class="wrap">
    <div class="eyebrow rv">Specific instituție publică</div>
    <h2 class="rv" data-d="1">De ce Tixello se potrivește unei instituții de stat</h2>
    <p class="lede2 rv" data-d="2">Rezumat pentru discuția cu conducerea și cu departamentul economic.</p>
    <div class="tw rv" data-d="2">
      <table>
        <thead><tr><th>Cerință a instituției publice</th><th>Cum răspunde Tixello</th></tr></thead>
        <tbody>
          <tr><td>Conformitate eFactura / ANAF</td><td>Trimitere automată în SPV (UBL 2.1 / CII), semnare electronică, recipise.</td></tr>
          <tr><td>Integrare cu contabilitatea</td><td>Conectori SmartBill, Oblio, Keez, FGO.</td></tr>
          <tr><td>Încasările rămân la instituție</td><td>Banii intră direct în contul teatrului. Tixello nu intermediază fondurile.</td></tr>
          <tr><td>Cost previzibil, justificabil</td><td>1% din valoarea biletelor vândute, o singură factură lunară.</td></tr>
          <tr><td>Transparența încasărilor</td><td>Rapoarte complete, export PDF și CSV, evidență vânzări față de protocol.</td></tr>
          <tr><td>Control asupra prețurilor și reducerilor</td><td>Categorii de preț, reduceri sociale, coduri — definite de teatru.</td></tr>
          <tr><td>Operare de către personal propriu</td><td>Panou intuitiv, roluri pe atribuții, jurnal de activitate.</td></tr>
          <tr><td>Protecția datelor (GDPR)</td><td>Cereri GDPR gestionate, consimțământ cookie, izolarea datelor, criptare.</td></tr>
          <tr><td>Securitate</td><td>Sistem auditat, credențiale criptate, acces pe roluri.</td></tr>
          <tr><td>Control al invitațiilor de protocol</td><td>Modul dedicat, nominal, urmăribil, cu detectare de abuz.</td></tr>
          <tr><td>Independență față de un intermediar</td><td>Casă de bilete și site propriu, sub brandul teatrului.</td></tr>
          <tr><td>Accesibilitate pentru public (Legea 448 / WCAG)</td><td>Interfață publică gândită accesibil, cu locuri accesibile marcate distinct; nivelul WCAG țintă se confirmă la implementare.</td></tr>
        </tbody>
      </table>
    </div>
    <div class="note rv" style="margin-top:22px">Un singur punct de configurat la punerea în funcțiune: integrarea cu casa de marcat fiscală a instituției pentru încasările cash la ghișeu, în funcție de modelul de casă folosit.</div>
  </div>
</section>

<!-- PART II -->
<div class="part">
  <div class="wrap">
    <div class="n rv">PARTEA II</div>
    <h2 class="rv" data-d="1">Toate celelalte funcționalități și integrări</h2>
    <p class="rv" data-d="2">42 de microservicii, peste 180 de funcționalități, peste 5.000 de integrări.</p>
  </div>
</div>

<section style="padding-top:20px">
  <div class="wrap">
    <div class="cols">
      <div class="block rv"><h4>💳 Plăți online</h4><ul><li>Netopia (mobilPay), EuPlătesc, PayU</li><li>Stripe pentru carduri internaționale</li><li>Apple Pay și Google Pay</li><li>Carduri culturale: Edenred, Pluxee, Up România</li><li>Plată în rate până la 3 luni</li><li>Buy now, pay later</li><li>Buy now, someone else pays</li><li>Mobile Wallet</li></ul></div>
      <div class="block rv"><h4>↩ Returnări și anulări</h4><ul><li>Returnare automată pe Stripe, Netopia, PayPal</li><li>Cereri cu articole individuale</li><li>Reprogramare sau anulare spectacol</li><li>Transfer și revânzare bilet</li><li>Liste de așteptare</li><li>Asigurare bilete</li></ul></div>
      <div class="block rv"><h4>🌐 Site public și whitelabel</h4><ul><li>Website Builder integrat</li><li>Domeniu propriu, automatizare DNS</li><li>Teme adaptate identității instituției</li><li>Widget de vânzare încorporabil</li><li>Modul de blog</li><li>Pagini optimizate SEO</li></ul></div>
      <div class="block rv"><h4>🎫 Bilete și acces</h4><ul><li>Bilete personalizate</li><li>Print-at-home cu QR valid</li><li>Bilet în Apple Wallet / Google Pay</li><li>Șabloane PDF personalizate</li><li>Aplicație mobilă de scanare</li><li>Locuri accesibile marcate</li></ul></div>
      <div class="block rv"><h4>📣 Comunicare cu publicul</h4><ul><li>Email tranzacțional cu șabloane</li><li>Newsletter și campanii</li><li>SMS și WhatsApp</li><li>Reminder automat cu 24h înainte</li><li>Automatizări CRM post-eveniment</li></ul></div>
      <div class="block rv"><h4>📊 Marketing și analiză</h4><ul><li>Tracking pixels: GA4, GTM, Meta, TikTok</li><li>Facebook Conversions API server-side</li><li>Google Ads și TikTok Ads</li><li>Raport ROAS</li><li>Sistem de afiliați</li><li>Recenzii după spectacol</li></ul></div>
      <div class="block rv"><h4>🎁 Fidelizare</h4><ul><li>Gamification: puncte, premii, clasament</li><li>Vouchere cadou</li><li>Reduceri last-minute automate</li><li>Group booking</li><li>Magazin online: merch, F&amp;B</li></ul></div>
      <div class="block rv"><h4>🔗 Integrări de business</h4><ul><li>Zapier și Airtable</li><li>Google Sheets și Google Workspace</li><li>HubSpot și Salesforce</li><li>Slack și Zoom</li><li>Conectori de contabilitate</li><li>API pentru dezvoltatori</li></ul></div>
      <div class="block rv"><h4>🔒 GDPR și securitate</h4><ul><li>Cereri GDPR gestionate</li><li>Consimțământ cookie cu istoric</li><li>Tracking doar cu opt-in</li><li>Credențiale criptate</li><li>Izolarea datelor pe instituție</li><li>Audituri de securitate</li></ul></div>
      <div class="block rv"><h4>🌍 Multi-limbă și creștere</h4><ul><li>Română și engleză, extensibil</li><li>Prețuri dinamice</li><li>Pachete flexibile</li><li>Cashless și brățări NFC</li><li>Split de plăți pentru co-producții</li></ul></div>
    </div>
  </div>
</section>

<!-- IMPL -->
<section class="alt">
  <div class="wrap">
    <div class="eyebrow rv">Implementare</div>
    <h2 class="rv" data-d="1">De la contract la go-live</h2>
    <p class="lede2 rv" data-d="2">Configurare în 24 de ore, cu suport dedicat pe toată durata implementării.</p>
    <ol class="impl">
      <li class="rv" data-d="1"><h4>Configurare instituție</h4><p>Date fiscale, serii de facturi, certificat eFactura, procesator de plată.</p></li>
      <li class="rv" data-d="2"><h4>Import sau desenare hartă sală</h4><p>Parter, balcon, loje; categorii de preț și locuri accesibile.</p></li>
      <li class="rv" data-d="3"><h4>Definire stagiune și abonamente</h4><p>Spectacolele stagiunii, tipurile de abonament, locurile rezervate.</p></li>
      <li class="rv" data-d="4"><h4>Introducerea artiștilor și a distribuției</h4><p>O singură dată, apoi reutilizabilă la fiecare producție.</p></li>
      <li class="rv" data-d="5"><h4>Configurare reduceri</h4><p>Elevi, studenți, pensionari, grupuri școlare.</p></li>
      <li class="rv" data-d="6"><h4>Publicare</h4><p>Pe site propriu, pe domeniul teatrului, și la ghișeu.</p></li>
      <li class="rv" data-d="7"><h4>Instruire personal</h4><p>Casierie, control acces la intrare, departament economic.</p></li>
      <li class="rv" data-d="8"><h4>Go-live</h4><p>Vânzare online și la ghișeu, cu validare QR la intrare.</p></li>
    </ol>
  </div>
</section>

<!-- MIGRATION -->
<section>
  <div class="wrap">
    <div class="eyebrow rv">Tranziția, fără riscuri</div>
    <h2 class="rv" data-d="1">Cum treci de la sistemul actual, <span class="grad">fără să oprești vânzarea.</span></h2>
    <p class="lede2 rv" data-d="2">Cea mai mare grijă a unui teatru care are deja un sistem nu e ce poate face platforma nouă — ci ce se întâmplă în timpul mutării. Răspunsul scurt: vânzarea nu se oprește nicio zi, iar nimic din ce ai construit nu se pierde.</p>

    <div class="mig-grid">
      <div class="mig-card rv" data-d="1">
        <div class="mig-ic">🎫</div>
        <h4>Abonamentele în curs rămân valabile</h4>
        <p>Abonamentele de stagiune deja vândute se importă cu tot cu locul fix. Abonatul își păstrează exact locul, la toate spectacolele rămase.</p>
      </div>
      <div class="mig-card rv" data-d="2">
        <div class="mig-ic">📦</div>
        <h4>Datele istorice se preiau</h4>
        <p>Clienți, istoric de vânzări, facturi emise. Se importă din exportul sistemului actual, ca să nu pornești de la zero.</p>
      </div>
      <div class="mig-card rv" data-d="3">
        <div class="mig-ic">🔀</div>
        <h4>Rulare în paralel la început</h4>
        <p>Punem sistemul nou lângă cel vechi. Vinzi în continuare cât timp verifici că totul e corect — comutarea se face doar când ești sigur.</p>
      </div>
      <div class="mig-card rv" data-d="4">
        <div class="mig-ic">🌙</div>
        <h4>Comutare între reprezentații</h4>
        <p>Trecerea propriu-zisă se programează într-o fereastră fără spectacol. Casa nu se închide în timpul unei vânzări active.</p>
      </div>
      <div class="mig-card rv" data-d="5">
        <div class="mig-ic">📞</div>
        <h4>Suport dedicat, inclusiv la spectacol</h4>
        <p>În perioada de tranziție ai un canal direct de suport. Dacă apare ceva vineri seara la sală, ai cu cine vorbi imediat.</p>
      </div>
      <div class="mig-card rv" data-d="6">
        <div class="mig-ic">↩</div>
        <h4>Plan de retragere clar</h4>
        <p>Datele îți aparțin și pot fi exportate oricând. Nu rămâi blocat: dacă vrei să pleci, pleci cu tot ce e al tău.</p>
      </div>
    </div>

    <div class="mig-timeline rv" data-d="2">
      <div class="mig-step"><span class="mig-dot"></span><b>Săptămâna 1</b><p>Configurare + import date, în paralel cu sistemul actual</p></div>
      <div class="mig-step"><span class="mig-dot"></span><b>Săptămâna 2</b><p>Verificare, instruire personal, vânzare de test</p></div>
      <div class="mig-step"><span class="mig-dot on"></span><b>Comutare</b><p>Într-o fereastră fără spectacol · zero zile fără vânzare</p></div>
    </div>
  </div>
</section>

<!-- CLOSER -->
<div class="closer">
  <div class="wrap">
    <h2 class="rv">Scena ta merită<br><span class="grad">parteneri de încredere.</span></h2>
    <div class="chain rv" data-d="1">
      Harta sălii → alegerea locului → plata online, la ghișeu sau în rate →<br>
      factură conformă ANAF → bilet cu QR → validare la intrare →<br>
      raport financiar → factură lunară de 1%
    </div>
    <p class="rv" data-d="2">Plus abonamentele de stagiune, invitațiile de protocol, reducerile sociale, gestiunea artiștilor și vizibilitatea organică — toate sub brandul și controlul instituției, cu banii direct în contul teatrului.</p>
    <div class="pills" style="margin-top:24px">
      <span class="pill rv" data-d="3">💰 Doar <b>1%</b> comision</span>
      <span class="pill rv" data-d="4">⚡ Configurare în 24h</span>
      <span class="pill rv" data-d="5">🤝 Suport dedicat</span>
      <span class="pill rv" data-d="6">📄 Fără taxe lunare</span>
    </div>

    <div class="cta-actions rv" data-d="4" style="margin-top:32px">
      <a class="btn btn-primary" href="mailto:andrei@tixello.ro?subject=Demo%20Tixello%20%E2%80%94%20teatru&amp;body=Bun%C4%83%20ziua%2C%20Andrei%2C%0A%0AAm%20parcurs%20prezentarea%20Tixello%20pentru%20teatre%20%C8%99i%20a%C8%99%20dori%20o%20demonstra%C8%9Bie.%0A%0AInstitu%C8%9Bia%3A%20%0APersoana%20de%20contact%3A%20%0ATelefon%3A%20%0A%0AV%C4%83%20mul%C8%9Bumesc.">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>
        Scrie-i lui Andrei
      </a>
      <a class="btn btn-ghost" href="https://teatru.tixello.ro" target="_blank" rel="noopener">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 0 20 15 15 0 0 1 0-20z"/></svg>
        Vezi demo live
      </a>
    </div>
  </div>
</div>

<footer>Tixello · Prezentare comercială · Verticala Teatru · tixello.com</footer>

<script>
(function(){
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // scroll reveal
  var els = document.querySelectorAll('.rv,.rv-l,.rv-r,.rv-s,.fig,.feat');
  if(reduce || !('IntersectionObserver' in window)){
    els.forEach(function(e){e.classList.add('in')});
  } else {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(en.isIntersecting){ en.target.classList.add('in'); io.unobserve(en.target); }
      });
    },{threshold:.16, rootMargin:'0px 0px -60px 0px'});
    els.forEach(function(e){ io.observe(e); });
  }

  // count-up stats
  var counters = document.querySelectorAll('[data-count]');
  function runCount(el){
    var target = parseFloat(el.getAttribute('data-count'));
    var suffix = el.getAttribute('data-suffix') || '';
    var grp = target >= 1000; // thousands separator for big numbers
    function show(n){ return (grp ? n.toLocaleString('ro-RO') : n) + suffix; }
    if(reduce){ el.textContent = show(target); return; }
    var start = null, dur = 1100;
    function tick(ts){
      if(!start) start = ts;
      var p = Math.min((ts-start)/dur,1);
      var eased = 1-Math.pow(1-p,3);
      el.textContent = show(Math.round(target*eased));
      if(p<1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  }
  if('IntersectionObserver' in window){
    var co = new IntersectionObserver(function(entries){
      entries.forEach(function(en){
        if(en.isIntersecting){ runCount(en.target); co.unobserve(en.target); }
      });
    },{threshold:.5});
    counters.forEach(function(c){ co.observe(c); });
  } else {
    counters.forEach(runCount);
  }

  // ===== FAQ accordions =====
  (function(){
    document.querySelectorAll('.faq-q').forEach(function(q){
      q.addEventListener('click', function(){
        var item=q.closest('.faq-item'), open=item.classList.toggle('open');
        q.setAttribute('aria-expanded', open?'true':'false');
        var caret=q.querySelector('.faq-caret'); if(caret) caret.textContent = open?'▾':'▸';
      });
    });
  })();

  // ===== feature list expand =====
  (function(){
    var btn=document.getElementById('featToggle'), more=document.getElementById('featMore');
    if(!btn||!more) return;
    btn.addEventListener('click', function(){
      var open = more.hasAttribute('hidden');
      if(open){
        more.removeAttribute('hidden');
        btn.setAttribute('aria-expanded','true');
        btn.querySelector('.feat-toggle-txt').textContent='Arată mai puțin';
        // reveal animations for the newly shown features
        more.querySelectorAll('.rv,.rv-l,.rv-r,.rv-s,.fig').forEach(function(e){ e.classList.add('in'); });
      } else {
        more.setAttribute('hidden','');
        btn.setAttribute('aria-expanded','false');
        btn.querySelector('.feat-toggle-txt').textContent='Vezi toate cele 14 capabilități';
        btn.scrollIntoView({block:'center', behavior:'smooth'});
      }
    });
  })();

  // ===== POS interactive cart =====
  (function(){
    var items = document.getElementById('posItems');
    if(!items) return;
    var emptyMsg = document.getElementById('posEmptyMsg'),
        subEl = document.getElementById('posSub'), feeEl = document.getElementById('posFee'),
        totalEl = document.getElementById('posTotal'),
        finish = document.getElementById('posFinish'), emptyBtn = document.getElementById('posEmpty');
    var cart = {}; // name -> {price, qty}
    var FEE = 0.01;

    function fmt(n){ return n.toLocaleString('ro-RO',{minimumFractionDigits:2,maximumFractionDigits:2})+' RON'; }
    function render(){
      var names = Object.keys(cart);
      if(!names.length){
        items.innerHTML=''; items.appendChild(emptyMsg); emptyMsg.style.display='';
        subEl.textContent='0,00 RON'; feeEl.textContent='0,00 RON'; totalEl.textContent='0,00 RON';
        finish.classList.remove('ready'); finish.disabled=true; return;
      }
      emptyMsg.style.display='none';
      var sum=0, html='';
      names.forEach(function(n){
        var it=cart[n]; sum += it.price*it.qty;
        html += '<div class="pos-line"><span class="pos-line-name">'+n+'</span>'+
          '<span class="pos-line-right"><span class="pos-qty">'+
          '<button data-act="dec" data-n="'+n+'">−</button><span>'+it.qty+'</span>'+
          '<button data-act="inc" data-n="'+n+'">+</button></span>'+
          '<span class="pos-line-price">'+fmt(it.price*it.qty)+'</span></span></div>';
      });
      items.innerHTML = html;
      var fee = sum*FEE, total = sum+fee;
      subEl.textContent=fmt(sum); feeEl.textContent=fmt(fee); totalEl.textContent=fmt(total);
      finish.classList.add('ready'); finish.disabled=false;
    }
    document.querySelectorAll('.pos-ticket').forEach(function(btn){
      btn.addEventListener('click', function(){
        var n=btn.getAttribute('data-name'), p=+btn.getAttribute('data-price');
        if(!cart[n]) cart[n]={price:p,qty:0};
        cart[n].qty++; render();
      });
    });
    items.addEventListener('click', function(e){
      var b=e.target.closest('button'); if(!b) return;
      var n=b.getAttribute('data-n'), act=b.getAttribute('data-act');
      if(!cart[n]) return;
      if(act==='inc') cart[n].qty++;
      else { cart[n].qty--; if(cart[n].qty<=0) delete cart[n]; }
      render();
    });
    emptyBtn.addEventListener('click', function(){ cart={}; render(); });
    document.querySelectorAll('.pos-pay-btn').forEach(function(b){
      b.addEventListener('click', function(){
        var sib=b.parentElement.querySelectorAll('button');
        sib.forEach(function(x){x.classList.remove('active')}); b.classList.add('active');
      });
    });
    finish.addEventListener('click', function(){
      if(finish.disabled) return;
      var orig=finish.textContent;
      finish.textContent='✓ Bilete emise'; finish.style.background='#1E9E6A';
      setTimeout(function(){ cart={}; render(); finish.textContent=orig; finish.style.background=''; }, 1400);
    });

    // accordions (Date client / Date firmă)
    document.querySelectorAll('.pos-acc-head').forEach(function(h){
      h.addEventListener('click', function(){
        var acc=h.closest('.pos-acc'), open=acc.classList.toggle('open');
        h.setAttribute('aria-expanded', open?'true':'false');
      });
    });

    // ticket-category accordions — exclusive: opening one closes the rest
    var catAccs = document.querySelectorAll('.pos-catacc');
    document.querySelectorAll('.pos-catacc-head').forEach(function(h){
      h.addEventListener('click', function(){
        var acc=h.closest('.pos-catacc'), willOpen=!acc.classList.contains('open');
        catAccs.forEach(function(a){
          a.classList.remove('open');
          var hh=a.querySelector('.pos-catacc-head'), cc=a.querySelector('.pos-caret');
          if(hh) hh.setAttribute('aria-expanded','false');
          if(cc) cc.textContent='▸';
        });
        if(willOpen){
          acc.classList.add('open');
          h.setAttribute('aria-expanded','true');
          var caret=h.querySelector('.pos-caret'); if(caret) caret.textContent='▾';
        }
      });
    });

    // event selector
    var sel=document.getElementById('posSelect');
    if(sel){
      var cur=document.getElementById('posSelCur');
      sel.addEventListener('click', function(e){
        if(e.target.closest('.pos-opt')) return;
        var open=sel.classList.toggle('open');
        sel.setAttribute('aria-expanded', open?'true':'false');
      });
      sel.querySelectorAll('.pos-opt').forEach(function(o){
        o.addEventListener('click', function(){
          sel.querySelectorAll('.pos-opt').forEach(function(x){x.classList.remove('active')});
          o.classList.add('active');
          cur.textContent = o.getAttribute('data-show')+' · '+o.getAttribute('data-when');
          sel.classList.remove('open'); sel.setAttribute('aria-expanded','false');
          cart={}; render(); // switching show clears the cart
        });
      });
      document.addEventListener('click', function(e){
        if(!sel.contains(e.target)){ sel.classList.remove('open'); sel.setAttribute('aria-expanded','false'); }
      });
    }
  })();

  // ===== savings calculator =====
  (function(){
    var sl = document.getElementById('salesSlider');
    if(!sl) return;
    var calcVal=document.getElementById('calcVal'),
        otherPct=document.getElementById('otherPct'), otherFee=document.getElementById('otherFee'),
        tixFee=document.getElementById('tixFee'), saveVal=document.getElementById('saveVal'),
        saveLabel=document.getElementById('saveLabel'),
        otherBar=document.getElementById('otherBar'), tixBar=document.getElementById('tixBar'),
        dotOther=document.getElementById('dotOther'), dotTix=document.getElementById('dotTix'),
        lockedVal=document.getElementById('lockedVal'),
        benefitSum=document.getElementById('benefitSum'), benefitGrid=document.getElementById('benefitGrid'),
        rateSlider=document.getElementById('rateSlider'), rateVal=document.getElementById('rateVal'),
        payerNote=document.getElementById('payerNote'), tglBtns=document.querySelectorAll('.calc-tgl');
    var TIX_RATE=0.01;
    var payer='theatre';  // who pays the CURRENT platform's commission
    function fmt(n){ return Math.round(n).toLocaleString('ro-RO')+' RON'; }

    // benefit tiers — each unlocks at a savings threshold, richest three shown
    var TIERS=[
      {min:0,     emoji:'🎟', t:'Recuzită și mici materiale', d:'Consumabile de scenă, mărunțișuri de producție.'},
      {min:3000,  emoji:'📢', t:'O campanie de promovare', d:'Public nou adus la câteva spectacole.'},
      {min:7000,  emoji:'👗', t:'Costume și decor', d:'O producție arată vizibil mai bogat.'},
      {min:12000, emoji:'🪑', t:'Recuzită nouă completă', d:'Dotare de scenă pentru un titlu întreg.'},
      {min:20000, emoji:'🎭', t:'O producție nouă mică', d:'Un spectacol de studio, montat de la zero.'},
      {min:32000, emoji:'💶', t:'Măriri de salariu pentru echipă', d:'Recunoașterea oamenilor care țin teatrul.'},
      {min:50000, emoji:'🧑‍🎨', t:'O angajare nouă', d:'Încă un om în echipă, pe tot anul.'},
      {min:80000, emoji:'🏛', t:'O producție mare de stagiune', d:'Titlu de sală mare, cu distribuție amplă.'}
    ];
    function renderBenefits(save){
      var afford=TIERS.filter(function(x){ return save>=x.min; });
      var top=afford.slice(-3).reverse();
      if(!top.length) top=[TIERS[0]];
      benefitGrid.innerHTML=top.map(function(x,i){
        var tag = i===0 ? '<span class="betag">CU ECONOMIA TA</span>' : '';
        return '<div class="benefit-card'+(i===0?' hi':'')+'">'+tag+
          '<div class="bemoji">'+x.emoji+'</div><h4>'+x.t+'</h4><p>'+x.d+'</p></div>';
      }).join('');
    }

    function compute(){
      var v=+sl.value;
      var rate=(+rateSlider.value)/100;
      var pct=((v-sl.min)/(sl.max-sl.min))*100;
      sl.style.setProperty('--pct', pct.toFixed(1)+'%');
      rateSlider.style.setProperty('--rpct', ((+rateSlider.value)/12*100).toFixed(1)+'%');
      calcVal.textContent = v.toLocaleString('ro-RO')+' RON';
      rateVal.textContent = (+rateSlider.value)+'%';
      otherPct.textContent = (+rateSlider.value)+'%';

      var oFee=v*rate, tFee=v*TIX_RATE, diff=oFee-tFee;
      otherFee.textContent=fmt(oFee); tixFee.textContent=fmt(tFee);
      otherBar.style.width='100%';
      tixBar.style.width = rate>0 ? (TIX_RATE/rate*100).toFixed(1)+'%' : '4%';

      var locked = v*0.054;
      lockedVal.textContent='~'+fmt(locked);

      var save = diff>0 ? diff : 0;
      if(payer==='theatre'){
        saveLabel.textContent='Economisești pe stagiune';
        saveVal.textContent='+'+fmt(save);
        saveVal.style.color='';
        payerNote.className='calc-note-payer';
        payerNote.innerHTML='Plătești acum comisionul din bugetul teatrului. Cu Tixello, diferența de <b>'+fmt(save)+'</b> rămâne la instituție.';
      } else {
        // buyer pays current commission -> theatre doesn't save on fees;
        // benefit is cheaper tickets for the public (demand) + Tixello's 1% is far lower
        saveLabel.textContent='Bilete mai ieftine pentru public cu';
        saveVal.textContent='−'+fmt(save);
        payerNote.className='calc-note-payer warn';
        payerNote.innerHTML='Acum comisionul e adăugat pe bilet, deci îl plătește spectatorul. Cu Tixello la 1%, biletul se ieftinește cu <b>'+fmt(save)+'</b> pe stagiune — preț mai mic, cerere mai mare. Dacă alegi să incluzi 1% în preț, câștigul trece la teatru.';
      }
      benefitSum.textContent=fmt(save);
      renderBenefits(save);
    }
    sl.addEventListener('input', compute);
    rateSlider.addEventListener('input', compute);
    tglBtns.forEach(function(btn){
      btn.addEventListener('click', function(){
        tglBtns.forEach(function(x){x.classList.remove('active')});
        btn.classList.add('active');
        payer=btn.getAttribute('data-payer');
        compute();
      });
    });
    compute();

    // animate the money-speed dots when the calc scrolls into view
    function runDots(){
      dotOther.style.left='72%';           // other platforms: money lands late (3–30 days)
      dotTix.style.left='0%';              // Tixello: instant -> at "azi", the very start
      dotTix.classList.add('pulsing');
    }
    if(reduce){ dotOther.style.left='72%'; dotTix.style.left='0%'; }
    else if('IntersectionObserver' in window){
      var io=new IntersectionObserver(function(en){
        en.forEach(function(e){ if(e.isIntersecting){ setTimeout(runDots,250); io.disconnect(); } });
      },{threshold:.4});
      io.observe(sl.closest('.calc'));
    } else { runDots(); }
  })();

  // ===== HERO: live sales simulation (single rAF clock) =====
  (function(){
    var map = document.getElementById('liveMap');
    var feed = document.getElementById('livefeed');
    if(!map || !feed) return;

    var seats = Array.prototype.slice.call(map.querySelectorAll('rect[data-row]'));
    if(!seats.length) return;

    var cSold = document.getElementById('cSold'),
        cCart = document.getElementById('cCart'),
        cOcc  = document.getElementById('cOcc');

    var FREE='#22D3A6', CART='#FFB020', SOLD='#3A3A55', SUB='#7C5CFF';
    var names=['Andrei M.','Elena P.','Mihai D.','Ioana R.','Cristina V.','Radu S.','Alina T.',
               'Bogdan C.','Maria I.','Vlad N.','Gabriela F.','Ștefan L.','Daniela B.','Cosmin A.'];
    var cities=['Ploiești','Brașov','București','Cluj','Sibiu','Iași','Timișoara','Constanța'];
    function pick(a){ return a[(Math.random()*a.length)|0]; }

    // state per seat: free|cart|sold|sub ; plus a shimmer phase for ambient motion
    var st = new Array(seats.length);
    var baseOp = new Array(seats.length);
    for(var i=0;i<seats.length;i++){
      st[i]='free';
      baseOp[i] = seats[i].getAttribute('opacity')==='.42' ? 0.42 : 0.55;
    }
    var freeIdx = function(){ var r=[]; for(var i=0;i<seats.length;i++) if(st[i]==='free') r.push(i); return r; };

    var sold=0, cart=0;

    function paint(i, color, op){
      var r = seats[i];
      r.setAttribute('fill', color);
      r.setAttribute('opacity', op);
    }
    function render(){
      if(cSold) cSold.textContent = sold.toLocaleString('ro-RO');
      if(cCart) cCart.textContent = cart;
      if(cOcc)  cOcc.textContent = Math.round((sold/seats.length)*100)+'%';
    }

    // seed ~32% sold
    (function seed(){
      var f = freeIdx();
      for(var i=f.length-1;i>0;i--){ var j=(Math.random()*(i+1))|0; var t=f[i]; f[i]=f[j]; f[j]=t; }
      var n = (seats.length*0.32)|0;
      for(var k=0;k<n;k++){ st[f[k]]='sold'; paint(f[k],SOLD,'1'); sold++; }
      render();
    })();

    // ---- toast pool: reuse 4 fixed nodes, never create/destroy in the loop ----
    var POOL = 4, pool = [], live = [];
    for(var t=0;t<POOL;t++){
      var el=document.createElement('div');
      el.className='toast'; el.style.display='none';
      var dot=document.createElement('span'); dot.className='tdot';
      var body=document.createElement('div'); body.className='tbody';
      var m=document.createElement('div'); m.className='tmain';
      var s=document.createElement('div'); s.className='tsub';
      body.appendChild(m); body.appendChild(s);
      el.appendChild(dot); el.appendChild(body);
      feed.appendChild(el);
      pool.push({el:el,m:m,s:s,born:0});
    }
    function showToast(kind, main, sub, now){
      var slot = pool.pop();
      if(!slot){ // recycle oldest
        slot = live.shift();
      }
      slot.el.className = 'toast '+kind;
      slot.m.textContent = main;
      slot.s.textContent = sub;
      slot.el.style.display = '';
      slot.el.style.animation = 'none';
      // force reflow then re-add the entrance animation
      void slot.el.offsetWidth;
      slot.el.style.animation = '';
      slot.born = now;
      feed.insertBefore(slot.el, feed.firstChild);
      live.push(slot);
    }
    function reap(now){
      for(var k=live.length-1;k>=0;k--){
        if(now - live[k].born > 6000){
          var slot = live.splice(k,1)[0];
          slot.el.style.display='none';
          pool.push(slot);
        }
      }
    }

    function label(i){ return 'rând '+seats[i].getAttribute('data-row')+' · loc '+seats[i].getAttribute('data-seat'); }

    // adjacent free group in same row
    function group(){
      var f = freeIdx(); if(!f.length) return [];
      var a = pick(f), row = seats[a].getAttribute('data-row'), num = +seats[a].getAttribute('data-seat');
      var g=[a], want=1+((Math.random()*3)|0);
      for(var d=1; g.length<want && d<5; d++){
        var nx=-1;
        for(var i=0;i<seats.length;i++){
          if(st[i]==='free' && seats[i].getAttribute('data-row')===row && +seats[i].getAttribute('data-seat')===num+d){ nx=i; break; }
        }
        if(nx>=0) g.push(nx); else break;
      }
      return g;
    }

    // pending resolutions: {seats, kind, at, who}
    var pending = [];

    function addCart(now){
      var g = group(); if(!g.length) return;
      for(var i=0;i<g.length;i++){ st[g[i]]='cart'; paint(g[i],CART,'1'); }
      cart += g.length; render();
      var who = pick(names);
      showToast('cart', who+' a adăugat în coș',
                g.length+(g.length===1?' bilet · ':' bilete · ')+label(g[0]), now);
      pending.push({g:g, who:who, at: now + 2600 + Math.random()*2000, paid: Math.random()<0.78});
    }
    function addSub(now){
      var f=freeIdx(); if(f.length<2) return;
      var i=pick(f); st[i]='sub'; paint(i,SUB,'1'); sold++; render();
      showToast('sub', pick(names)+' a cumpărat abonament', 'Stagiune completă · loc fix '+label(i), now);
    }
    function resolve(now){
      for(var k=pending.length-1;k>=0;k--){
        var p=pending[k];
        if(now < p.at) continue;
        pending.splice(k,1);
        if(p.paid){
          for(var i=0;i<p.g.length;i++){ st[p.g[i]]='sold'; paint(p.g[i],SOLD,'1'); }
          cart -= p.g.length; sold += p.g.length; render();
          showToast('paid', p.who+' a achitat',
                    (p.g.length*((45+Math.random()*70)|0))+' RON · '+pick(cities), now);
        } else {
          for(var j=0;j<p.g.length;j++){ st[p.g[j]]='free'; paint(p.g[j],FREE,'0.55'); }
          cart -= p.g.length; render();
          showToast('cart','Rezervare expirată','Locurile s-au eliberat automat', now);
        }
      }
    }
    function relief(){
      if(sold/seats.length <= 0.85) return;
      var soldList=[]; for(var i=0;i<seats.length;i++) if(st[i]==='sold') soldList.push(i);
      for(var n=0;n<12 && soldList.length;n++){
        var idx=(Math.random()*soldList.length)|0, i=soldList.splice(idx,1)[0];
        st[i]='free'; paint(i,FREE,'0.55'); sold--;
      }
      render();
    }

    // ---- ambient shimmer: free seats gently breathe, continuously ----
    var freePhase = new Float32Array(seats.length);
    for(var i=0;i<seats.length;i++) freePhase[i]=Math.random()*Math.PI*2;

    var running=false, raf=0;
    var nextEvent=0, lastShimmer=0;

    function frame(now){
      if(!running) return;
      // 1) ambient shimmer every ~120ms (cheap, only free seats)
      if(now - lastShimmer > 120){
        lastShimmer = now;
        for(var i=0;i<seats.length;i++){
          if(st[i]==='free'){
            var o = baseOp[i] + 0.18*Math.sin(now*0.0016 + freePhase[i]);
            seats[i].setAttribute('opacity', o.toFixed(2));
          }
        }
      }
      // 2) discrete events on their own schedule
      if(now >= nextEvent){
        (Math.random()<0.72) ? addCart(now) : addSub(now);
        relief();
        nextEvent = now + 1800 + Math.random()*1800;
      }
      resolve(now);
      reap(now);
      raf = requestAnimationFrame(frame);
    }

    function start(){
      if(running) return;
      running=true;
      nextEvent = performance.now() + 600;
      raf = requestAnimationFrame(frame);
    }
    function stop(){ running=false; if(raf) cancelAnimationFrame(raf); }

    if(reduce){ showToast('paid','Vânzări în timp real','Simulare dezactivată', 0); return; }

    var userPaused=false, onScreen=false;
    // manual toggle — lets a conservative viewer calm the page
    var toggle=document.getElementById('simToggle'), simLabel=document.getElementById('simLabel');
    if(toggle){
      toggle.addEventListener('click', function(){
        userPaused=!userPaused;
        if(userPaused){ stop(); toggle.classList.add('paused'); toggle.setAttribute('aria-pressed','false'); simLabel.textContent='Pornește simularea'; }
        else { toggle.classList.remove('paused'); toggle.setAttribute('aria-pressed','true'); simLabel.textContent='Simulare live'; if(onScreen && !document.hidden) start(); }
      });
    }

    // pause when tab hidden or hero off-screen -> no runaway timers
    document.addEventListener('visibilitychange', function(){
      if(document.hidden) stop(); else if(onScreen && !userPaused) start();
    });
    if('IntersectionObserver' in window){
      var io=new IntersectionObserver(function(en){
        en.forEach(function(e){ onScreen=e.isIntersecting;
          if(onScreen && !document.hidden && !userPaused) start(); else stop(); });
      },{threshold:.12});
      io.observe(map);
    } else { onScreen=true; start(); }
  })();

  // progress bar
  var prog = document.getElementById('prog');
  function onScroll(){
    var h = document.documentElement.scrollHeight - window.innerHeight;
    var p = h>0 ? (window.scrollY/h)*100 : 0;
    prog.style.width = p + '%';
  }
  window.addEventListener('scroll', onScroll, {passive:true});
  onScroll();
})();
</script>
</body>
</html>
