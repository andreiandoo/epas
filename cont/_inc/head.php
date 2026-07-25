<?php
/**
 * Head + body open pentru zona de cont client (design nou, self-contained).
 * Variabile opționale: $pageTitle, $navActive (cheia item-ului activ din sidebar).
 */
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/api.php';
$pageTitle = $pageTitle ?? ('Contul meu — ' . SITE_NAME);
$navActive = $navActive ?? '';
?><!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#090807">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:ital,wght@0,500;0,600;0,700;1,500&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
<script>
// Scala completă de opacitate (0–100) ca modificatorii /N (ex. border-white/8, text-paper/48)
// să se genereze pe Play CDN — altfel border-ul cade pe currentColor (≈ alb).
(function(){var op={};for(var i=0;i<=100;i++){op[i]=(i/100).toString();}
tailwind.config={theme:{extend:{
  fontFamily:{display:['Playfair Display','serif'],body:['DM Sans','sans-serif']},
  colors:{ink:'#090807',coal:'#151311',panel:'#1D1A17',wine:'#6F1D2C','wine-dark':'#49111C',brass:'#C9A75D','brass-light':'#E3C77D',paper:'#F4EFE6',muted:'#A9A096',drama:'#8B2234',revue:'#B77B24',imaginario:'#467A74'},
  opacity:op,
  borderColor:{DEFAULT:'rgba(244,239,230,0.1)'},
  divideColor:{DEFAULT:'rgba(244,239,230,0.1)'}
}}};})();
</script>
<style>
[x-cloak]{display:none!important}html{scroll-behavior:smooth}body{background:#090807;color:#F4EFE6;font-family:'DM Sans',sans-serif}::selection{background:#C9A75D;color:#090807}:focus-visible{outline:2px solid #E3C77D;outline-offset:3px}.font-display{font-family:'Playfair Display',serif}.grain:after{content:'';position:absolute;inset:0;pointer-events:none;opacity:.055;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.55'/%3E%3C/svg%3E");mix-blend-mode:soft-light}.btn-primary{background:linear-gradient(135deg,#E3C77D,#C9A75D 58%,#B98D3C);color:#090807;transition:.25s}.btn-primary:hover{transform:translateY(-2px);filter:brightness(1.05);box-shadow:0 18px 45px -24px rgba(227,199,125,.9)}.btn-secondary{border:1px solid rgba(244,239,230,.2);transition:.25s}.btn-secondary:hover{background:rgba(244,239,230,.07);border-color:rgba(244,239,230,.45)}.card{border:1px solid rgba(244,239,230,.1);background:rgba(29,26,23,.72);border-radius:1rem}.card-hover{transition:transform .3s,border-color .3s,background .3s}.card-hover:hover{transform:translateY(-3px);border-color:rgba(201,167,93,.35)}.input{width:100%;border:1px solid rgba(244,239,230,.12);background:#151311;border-radius:.8rem;padding:.78rem 1rem;color:#F4EFE6;outline:none}.input:focus{border-color:rgba(201,167,93,.75);box-shadow:0 0 0 3px rgba(201,167,93,.08)}.label{display:block;margin-bottom:.45rem;font-size:.78rem;font-weight:700;color:rgba(244,239,230,.65)}.badge{display:inline-flex;align-items:center;gap:.4rem;border-radius:999px;padding:.32rem .65rem;font-size:.7rem;font-weight:700}.table-wrap{overflow:auto}.table-wrap table{min-width:720px;width:100%}.table-wrap th{text-align:left;font-size:.68rem;letter-spacing:.12em;color:rgba(244,239,230,.35);padding:.9rem 1rem}.table-wrap td{padding:1rem;border-top:1px solid rgba(244,239,230,.08);font-size:.86rem}.switch{position:relative;width:46px;height:26px;border-radius:999px;background:#34302c;transition:.2s;cursor:pointer}.switch:after{content:'';position:absolute;width:20px;height:20px;left:3px;top:3px;border-radius:50%;background:#F4EFE6;transition:.2s}.switch.on{background:#C9A75D}.switch.on:after{transform:translateX(20px);background:#090807}.ticket-cut:before,.ticket-cut:after{content:'';position:absolute;right:-13px;width:26px;height:26px;border-radius:50%;background:#090807}.ticket-cut:before{top:-13px}.ticket-cut:after{bottom:-13px}@media print{header,aside,.no-print,.mobile-account-nav{display:none!important}main{padding-top:0!important}.print-card{border:1px solid #aaa!important;color:#111!important;background:#fff!important}}@media(prefers-reduced-motion:reduce){*{animation-duration:.01ms!important;transition-duration:.01ms!important}}
<?= $contExtraStyles ?? '' ?>
</style>
</head>
<body class="antialiased" x-data="accountShell()" x-init="init()" @keydown.escape.window="close()">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded bg-brass focus:px-4 focus:py-3 focus:text-ink">Sari la conținut</a>
