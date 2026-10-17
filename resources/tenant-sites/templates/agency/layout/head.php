<?php
/**
 * <head> pentru template-ul agency. Opționale: $pageTitle, $pageDescription, $pageImage,
 * $pageSchema, $pageExtraHead, $pageExtraStyles. Animațiile: modulul comun ts-motion.js
 * (Lenis + GSAP, din manifest) + assets/fx.js (rosterul, rândurile evenimentelor). Niciuna
 * pe paginile comune (coș, plată, cont).
 */
require_once TEMPLATE_DIR . '/lib.php';
$pageTitle       = $pageTitle       ?? SITE_NAME;
$pageDescription = $pageDescription ?? (site('branding.description') ?: t('Artiștii, concertele și booking-ul {name}.', ['name' => SITE_NAME]));
$pageImage       = $pageImage       ?? null;
// Pe prima pagină: rosterul agenției (lista artiștilor), pentru motoarele de căutare
if (!isset($pageSchema) && ts_current_path() === '/') {
    $agRoster = ag_roster();
    $pageSchema = $agRoster ? [[
        '@type' => 'ItemList', 'name' => t('Artiști') . ' — ' . SITE_NAME,
        'itemListElement' => array_map(fn ($a, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ts_abs_url(ag_artist_url($a)), 'name' => $a['name']], $agRoster, array_keys($agRoster)),
    ]] : [];
}
?>
<!DOCTYPE html>
<html lang="<?= ts_locale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?= ts_seo_head(['title' => $pageTitle, 'description' => $pageDescription, 'image' => $pageImage ?? null, 'schema' => $pageSchema ?? []]) ?>
    <link rel="icon" href="<?= e(site('branding.favicon') ?: '/favicon.svg') ?>">
    <?php // înaintea lui Alpine: componentele paginii (agencyTickets …) sunt definite aici ?>
    <?php if (!defined('TS_SHARED_PAGE')): ?><script defer src="<?= e(ts_asset('fx.js')) ?>"></script><?php endif; ?>
    <?= ts_head_assets() ?>
    <link rel="stylesheet" href="<?= e(ts_asset('site.css')) ?>">
    <?php if (!empty($pageExtraStyles)): ?><style><?= $pageExtraStyles ?></style><?php endif; ?>
    <?= $pageExtraHead ?? '' ?>
</head>
