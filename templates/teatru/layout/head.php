<?php
/**
 * <head> pentru template-ul teatru. Variabile opționale setate de pagină:
 *   $pageTitle, $pageDescription, $pageExtraStyles (CSS), $pageExtraHead (HTML)
 */
$pageTitle       = $pageTitle       ?? SITE_NAME;
$pageDescription = $pageDescription ?? (site('branding.description') ?: 'Bilete și abonamente — ' . SITE_NAME);
?>
<!DOCTYPE html>
<html lang="<?= ts_locale() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDescription) ?>">
    <?= ts_seo_head(['title' => $pageTitle, 'description' => $pageDescription, 'image' => $pageImage ?? null, 'schema' => $pageSchema ?? []]) ?>
    <link rel="icon" href="<?= e(site('branding.favicon') ?: '/favicon.svg') ?>">
    <title><?= e($pageTitle) ?></title>
    <?= ts_head_assets() ?>
    <style>
        .curtain-texture::before { content:""; position:absolute; inset:0; background: repeating-linear-gradient(90deg,transparent,transparent 2px,rgb(var(--c-accent) / .03) 2px,rgb(var(--c-accent) / .03) 4px); pointer-events:none; }
        .spotlight { background: radial-gradient(ellipse at top, rgb(var(--c-primary) / .06), transparent 60%); }
        .timeline-item { position: relative; padding-left: 2rem; border-left: 1px solid rgb(var(--c-primary) / .2); }
        .timeline-item::before { content: ""; position: absolute; left: -6px; top: 4px; width: 11px; height: 11px; border-radius: 9999px; background: rgb(var(--c-primary)); }
        .input-field { background: rgb(var(--c-canvas)); border: 1px solid rgb(var(--c-primary) / .2); transition: all .2s ease; }
        .input-field:focus { border-color: rgb(var(--c-primary)); outline: none; }
        <?= $pageExtraStyles ?? '' ?>
    </style>
    <?= $pageExtraHead ?? '' ?>
</head>
