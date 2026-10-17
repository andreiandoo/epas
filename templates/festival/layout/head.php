<?php
/**
 * <head> pentru template-ul festival. Opționale: $pageTitle, $pageDescription,
 * $pageImage, $pageExtraHead, $pageExtraStyles. Animațiile: modulul comun
 * ts-motion.js (Lenis + GSAP, din manifest) + assets/fx.js (numărătoare, galerie
 * orizontală, banda de artiști, scântei în hero). Niciuna pe paginile comune.
 */
require_once TEMPLATE_DIR . '/lib.php';
$pageTitle       = $pageTitle       ?? SITE_NAME;
$pageDescription = $pageDescription ?? (site('branding.description') ?: t('Bilete, lineup și program — {name}.', ['name' => SITE_NAME]));
$pageImage       = $pageImage       ?? (fest_event()['hero_image_url'] ?? null);
// Festivalul ca eveniment (Festival), cu artiștii din lineup, pe paginile proprii ale template-ului
if (!isset($pageSchema) && !defined('TS_SHARED_PAGE') && ($fe = fest_event())) {
    $names = [];
    foreach ((array) (fest_lineup()['slots'] ?? []) as $sl) {
        if (!empty($sl['name'])) { $names[$sl['name']] = ['name' => $sl['name'], 'type' => 'PerformingGroup']; }
    }
    $pageSchema = [ts_event_schema($fe, ['type' => 'Festival', 'url' => '/', 'performers' => array_values(array_slice($names, 0, 40))])];
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
    <?php // înaintea lui Alpine: componentele paginii (festTickets, lineupPage …) sunt definite aici ?>
    <?php if (!defined('TS_SHARED_PAGE')): ?><script defer src="<?= e(ts_asset('fx.js')) ?>"></script><?php endif; ?>
    <?= ts_head_assets() ?>
    <link rel="stylesheet" href="<?= e(ts_asset('site.css')) ?>">
    <?php if (!empty($pageExtraStyles)): ?><style><?= $pageExtraStyles ?></style><?php endif; ?>
    <?= $pageExtraHead ?? '' ?>
</head>
