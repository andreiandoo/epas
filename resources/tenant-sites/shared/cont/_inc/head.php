<?php
/**
 * Head + body open pentru zona de cont client (comună tuturor template-urilor;
 * diferă doar culorile și fonturile, din temă).
 * Variabile opționale: $pageTitle, $navActive (cheia item-ului activ din sidebar), $contExtraStyles.
 */
$pageTitle = $pageTitle ?? (t('Contul meu') . ' — ' . SITE_NAME);
$navActive = $navActive ?? '';
?><!DOCTYPE html>
<html lang="<?= ts_locale() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= e($pageTitle) ?></title>
<link rel="icon" href="<?= e(site('branding.favicon') ?: '/favicon.svg') ?>">
<?= ts_head_assets() ?>
<style>
html{scroll-behavior:smooth}:focus-visible{outline:2px solid rgb(var(--c-primary-light));outline-offset:3px}
.grain:after{content:'';position:absolute;inset:0;pointer-events:none;opacity:.055;background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 180 180' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.55'/%3E%3C/svg%3E");mix-blend-mode:soft-light}
.btn-primary{background:linear-gradient(135deg,rgb(var(--c-primary-light)),rgb(var(--c-primary)) 58%,rgb(var(--c-primary)));color:rgb(var(--c-on-primary));transition:.25s}
.btn-primary:hover{transform:translateY(-2px);filter:brightness(1.05);box-shadow:0 18px 45px -24px rgb(var(--c-primary-light) / .9)}
.btn-secondary{border:1px solid rgb(var(--c-fg) / .2);transition:.25s}.btn-secondary:hover{background:rgb(var(--c-fg) / .07);border-color:rgb(var(--c-fg) / .45)}
.card{border:1px solid rgb(var(--c-fg) / .1);background:rgb(var(--c-panel) / .72);border-radius:1rem}
.card-hover{transition:transform .3s,border-color .3s,background .3s}.card-hover:hover{transform:translateY(-3px);border-color:rgb(var(--c-primary) / .35)}
.input{width:100%;border:1px solid rgb(var(--c-fg) / .12);background:rgb(var(--c-surface));border-radius:.8rem;padding:.78rem 1rem;color:rgb(var(--c-fg));outline:none}
.input:focus{border-color:rgb(var(--c-primary) / .75);box-shadow:0 0 0 3px rgb(var(--c-primary) / .08)}
.label{display:block;margin-bottom:.45rem;font-size:.78rem;font-weight:700;color:rgb(var(--c-fg) / .65)}
.badge{display:inline-flex;align-items:center;gap:.4rem;border-radius:999px;padding:.32rem .65rem;font-size:.7rem;font-weight:700}
.table-wrap{overflow:auto}.table-wrap table{min-width:720px;width:100%}
.table-wrap th{text-align:left;font-size:.68rem;letter-spacing:.12em;color:rgb(var(--c-fg) / .35);padding:.9rem 1rem}
.table-wrap td{padding:1rem;border-top:1px solid rgb(var(--c-fg) / .08);font-size:.86rem}
.switch{position:relative;width:46px;height:26px;border-radius:999px;background:rgb(var(--c-fg) / .18);transition:.2s;cursor:pointer}
.switch:after{content:'';position:absolute;width:20px;height:20px;left:3px;top:3px;border-radius:50%;background:rgb(var(--c-fg));transition:.2s}
.switch.on{background:rgb(var(--c-primary))}.switch.on:after{transform:translateX(20px);background:rgb(var(--c-canvas))}
.ticket-cut:before,.ticket-cut:after{content:'';position:absolute;right:-13px;width:26px;height:26px;border-radius:50%;background:rgb(var(--c-canvas))}
.ticket-cut:before{top:-13px}.ticket-cut:after{bottom:-13px}
@media print{header,aside,.no-print,.mobile-account-nav{display:none!important}main{padding-top:0!important}.print-card{border:1px solid #aaa!important;color:#111!important;background:#fff!important}}
@media(prefers-reduced-motion:reduce){*{animation-duration:.01ms!important;transition-duration:.01ms!important}}
<?= $contExtraStyles ?? '' ?>
</style>
</head>
<body class="antialiased" x-data="accountShell()" x-init="init()" @keydown.escape.window="close()">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded bg-primary focus:px-4 focus:py-3 focus:text-canvas"><?= te('Sari la conținut') ?></a>
