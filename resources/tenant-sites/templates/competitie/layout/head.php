<?php
/**
 * <head> pentru template-ul competiție. Variabile opționale setate de pagină:
 *   $pageTitle, $pageDescription, $pageImage (Open Graph), $pageExtraHead (HTML), $pageExtraStyles (CSS)
 * Template-ul are motorul lui de animații (assets/fx.js: Lenis + GSAP + Motion), nu pe cel comun.
 */
$pageTitle       = $pageTitle       ?? SITE_NAME;
$pageDescription = $pageDescription ?? (site('branding.description') ?: 'Bilete online la competițiile ' . SITE_NAME . '.');
$pageImage       = $pageImage       ?? (site('branding.logo') ?: null);
?>
<!DOCTYPE html>
<html lang="<?= ts_locale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if (SITE_PREVIEW): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <?php if ($pageImage): ?><meta property="og:image" content="<?= e($pageImage) ?>"><?php endif; ?>
    <link rel="icon" href="<?= e(site('branding.favicon') ?: '/favicon.svg') ?>">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <?= ts_head_assets() ?>
    <link rel="stylesheet" href="<?= e(ts_asset('site.css')) ?>">
    <?php if (!defined('TS_SHARED_PAGE')): ?>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/lenis@1.1.14/dist/lenis.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/motion@11.11.13/dist/motion.js"></script>
    <script defer src="<?= e(ts_asset('fx.js')) ?>"></script>
    <?php endif; ?>
    <?php if (!empty($pageExtraStyles)): ?><style><?= $pageExtraStyles ?></style><?php endif; ?>
    <?= $pageExtraHead ?? '' ?>
</head>
