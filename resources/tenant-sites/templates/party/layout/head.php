<?php
/**
 * <head> pentru template-ul party. Opționale: $pageTitle, $pageDescription, $pageImage,
 * $pageSchema, $pageExtraHead, $pageExtraStyles. Animațiile: modulul comun ts-motion.js
 * (Lenis + GSAP, din manifest) + assets/fx.js (globul disco three.js, confetti PixiJS,
 * galeria fixată, lumina foto). Niciuna
 * pe paginile comune (coș, plată, cont).
 */
require_once TEMPLATE_DIR . '/lib.php';
$pageTitle       = $pageTitle       ?? SITE_NAME;
$pageDescription = $pageDescription ?? (site('branding.description') ?: t('Petrecerile {name}: ediții tematice în mai multe orașe, bilete online.', ['name' => SITE_NAME]));
$pageImage       = $pageImage       ?? null;
// Pe prima pagină: edițiile următoare, pentru motoarele de căutare
if (!isset($pageSchema) && ts_current_path() === '/') {
    $ptNext = array_slice(pt_upcoming(), 0, 10);
    $pageSchema = $ptNext ? [[
        '@type' => 'ItemList', 'name' => t('Petreceri') . ' — ' . SITE_NAME,
        'itemListElement' => array_map(fn ($ev, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => ts_abs_url(ts_link('event', ['slug' => $ev['slug']])), 'name' => pt_show_name($ev)], $ptNext, array_keys($ptNext)),
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
    <?php // înaintea lui Alpine: componentele paginii (partyTickets …) sunt definite aici ?>
    <?php if (!defined('TS_SHARED_PAGE')): ?><script defer src="<?= e(ts_asset('fx.js')) ?>"></script><?php endif; ?>
    <?= ts_head_assets() ?>
    <link rel="stylesheet" href="<?= e(ts_asset('site.css')) ?>">
    <?php if (!empty($pageExtraStyles)): ?><style><?= $pageExtraStyles ?></style><?php endif; ?>
    <?= $pageExtraHead ?? '' ?>
</head>
