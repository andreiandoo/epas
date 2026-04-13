<?php
/**
 * Whitelabel head — header, styles, cookie consent.
 */
$accentColor = ACCENT_COLOR ?: '#6366f1';
$isDark = THEME === 'dark';
$textColor = $isDark ? '#e2e8f0' : '#1e293b';
$cardBg = $isDark ? '#1e293b' : '#ffffff';
$borderColor = $isDark ? '#334155' : '#e2e8f0';
$mutedColor = $isDark ? '#94a3b8' : '#64748b';
$bgColor = $isDark ? '#0f172a' : '#f8fafc';
$bp = BASE_PATH; // shorthand for templates
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? ORG_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $bp ?>/assets/css/style.css">
    <style>
        :root { --accent: <?= $accentColor ?>; --bg: <?= $bgColor ?>; --text: <?= $textColor ?>; --card: <?= $cardBg ?>; --border: <?= $borderColor ?>; --muted: <?= $mutedColor ?>; }
        body { background: var(--bg); color: var(--text); }
        <?php if (BG_IMAGE_URL): ?>
        body { background-image: url('<?= htmlspecialchars(BG_IMAGE_URL) ?>'); background-size:cover; background-position:center; background-attachment:fixed; }
        .wl-main { background: <?= $isDark ? 'rgba(15,23,42,0.9)' : 'rgba(248,250,252,0.92)' ?>; }
        <?php endif; ?>
    </style>
</head>
<body>
    <header class="wl-header">
        <div class="wl-container wl-header-inner">
            <a href="<?= $bp ?>/" class="wl-logo-link">
                <?php if (LOGO_URL): ?>
                <img src="<?= htmlspecialchars(LOGO_URL) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>" class="wl-logo">
                <?php else: ?>
                <span class="wl-org-name"><?= htmlspecialchars(ORG_NAME) ?></span>
                <?php endif; ?>
            </a>
            <a href="<?= $bp ?>/checkout" class="wl-cart-link" id="wl-cart-link">
                <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                <span id="wl-cart-count" class="wl-cart-badge" style="display:none;">0</span>
            </a>
        </div>
    </header>

    <div id="wl-cookie-banner" class="wl-cookie-banner" style="display:none;">
        <div class="wl-container" style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <p style="margin:0;font-size:13px;color:var(--muted);flex:1;">
                Acest site folosește cookie-uri. <a href="<?= $bp ?>/privacy" target="_blank">Confidențialitate</a>
            </p>
            <button onclick="document.getElementById('wl-cookie-banner').style.display='none';document.cookie='wl_cookies=1;path=/;max-age=31536000'" class="wl-btn" style="padding:8px 20px;font-size:13px;">Accept</button>
        </div>
    </div>
    <script>if(!document.cookie.includes('wl_cookies=1'))document.getElementById('wl-cookie-banner').style.display='';</script>

    <main class="wl-main">
        <div class="wl-container wl-content">
