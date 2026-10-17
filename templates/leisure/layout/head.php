<?php
/**
 * <head> pentru template-ul leisure (Nordvale). Variabile opționale:
 *   $pageTitle, $pageDescription, $pageExtraStyles (CSS), $pageExtraHead (HTML), $bodyAttrs
 */
require_once TEMPLATE_DIR . '/lib.php';
$pageTitle       = $pageTitle       ?? SITE_NAME;
$pageDescription = $pageDescription ?? (site('branding.description') ?: 'Bilete și rezervări online — ' . SITE_NAME);
// O pagină din secțiuni nu-și aduce stilurile proprii (paginile fixe au $pageExtraStyles)
$GLOBALS['lvSections'] = !isset($pageExtraStyles) && !defined('TS_SHARED_PAGE') && !empty($activeNav ?? null);
?>
<!DOCTYPE html>
<html lang="ro" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?php if (SITE_PREVIEW): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
    <link rel="icon" href="<?= e(site('branding.favicon') ?: '/favicon.svg') ?>">
    <title><?= e($pageTitle) ?></title>
    <style><?= lv_palette_css() ?></style>
    <?php // înaintea lui Alpine: paginile își construiesc componenta din LVBooking ?>
    <script defer src="<?= e(ts_asset('booking.js')) ?>"></script>
    <?= ts_head_assets() ?>
    <?php if (!empty($pageExtraStyles)): ?><style><?= $pageExtraStyles ?></style><?php endif; ?>
    <?= $pageExtraHead ?? '' ?>
    <?php if ($GLOBALS['lvSections']): // paginile din secțiuni (Acasă …) au stilurile și animațiile prototipului ?>
    <link rel="stylesheet" href="<?= e(ts_asset('home.css')) ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/animejs@3.2.2/lib/anime.min.js"></script>
    <script>
        document.documentElement.classList.add('reveal-ready');
        window.__introSafety = window.setTimeout(function () {
            var i = document.querySelector('[data-intro]'); if (i) { i.classList.add('is-hidden'); }
            document.documentElement.classList.remove('reveal-ready');
        }, 4800);
    </script>
    <?php endif; ?>
</head>
