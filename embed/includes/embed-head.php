<?php
/**
 * Embed head — whitelabel layout for iframe pages.
 * Variables available: $organizerSlug, $returnUrl, $theme, $accent, $orgData, $embedDomains
 */

// Security: set frame-ancestors dynamically
$cspAncestors = [SITE_URL];
foreach ($embedDomains as $domain) {
    $host = parse_url($domain, PHP_URL_HOST) ?: $domain;
    $scheme = parse_url($domain, PHP_URL_SCHEME) ?: 'https';
    $entry = $scheme . '://' . $host;
    if (!in_array($entry, $cspAncestors)) {
        $cspAncestors[] = $entry;
    }
}
header("Content-Security-Policy: frame-ancestors " . implode(' ', $cspAncestors));
header('X-Frame-Options: SAMEORIGIN');

$orgName = $orgData['data']['name'] ?? 'Organizator';
$accentColor = $accent ?: '#6366f1';
// $embedLogo and $embedBgImage come from embed-init.php (persisted in cookie)
$isDark = $theme === 'dark';
$bgColor = $isDark ? '#0f172a' : '#f8fafc';
$textColor = $isDark ? '#e2e8f0' : '#1e293b';
$cardBg = $isDark ? '#1e293b' : '#ffffff';
$borderColor = $isDark ? '#334155' : '#e2e8f0';
$mutedColor = $isDark ? '#94a3b8' : '#64748b';
$headerBg = $isDark ? '#1e293b' : '#ffffff';
$baseUrl = '/embed/' . htmlspecialchars($organizerSlug);
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? $orgName) ?></title>
    <base target="_self">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '<?= htmlspecialchars($accentColor) ?>',
                        surface: '<?= $bgColor ?>',
                        secondary: '<?= $textColor ?>',
                        card: '<?= $cardBg ?>',
                        border: '<?= $borderColor ?>',
                        muted: '<?= $mutedColor ?>',
                    },
                    fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
                }
            }
        }
    </script>
    <style>
        body {
            margin: 0; padding: 0;
            font-family: 'Inter', system-ui, sans-serif;
            background: transparent;
            <?php if ($embedBgImage): ?>
            background-image: url('<?= htmlspecialchars($embedBgImage) ?>');
            background-size: cover; background-position: center;
            background-attachment: fixed; background-repeat: no-repeat;
            <?php endif; ?>
            color: <?= $textColor ?>;
            overflow-x: hidden;
        }
        a { color: <?= htmlspecialchars($accentColor) ?>; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .embed-card {
            background: <?= $cardBg ?>; border: 1px solid <?= $borderColor ?>;
            border-radius: 12px; overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .embed-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .embed-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 24px; background: <?= htmlspecialchars($accentColor) ?>; color: #fff;
            border: none; border-radius: 10px; font-weight: 600; font-size: 14px;
            cursor: pointer; transition: opacity 0.2s;
        }
        .embed-btn:hover { opacity: 0.9; text-decoration: none; color: #fff; }
        .embed-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .skeleton { background: linear-gradient(90deg, <?= $borderColor ?> 25%, <?= $isDark ? '#475569' : '#f1f5f9' ?> 50%, <?= $borderColor ?> 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
</head>
<body>
    <!-- Header -->
    <header style="position:sticky;top:0;z-index:50;background:<?= $headerBg ?>;border-bottom:1px solid <?= $borderColor ?>;padding:12px 16px;">
        <div style="display:flex;align-items:center;justify-content:space-between;">
            <a href="<?= $baseUrl ?>" style="display:flex;align-items:center;gap:10px;text-decoration:none;">
                <?php if ($embedLogo): ?>
                <img src="<?= htmlspecialchars($embedLogo) ?>" alt="<?= htmlspecialchars($orgName) ?>" style="max-height:40px;">
                <?php endif; ?>
                <span style="font-weight:700;font-size:16px;color:<?= $textColor ?>;"><?= htmlspecialchars($orgName) ?></span>
            </a>
            <a href="<?= $baseUrl ?>/cos" style="display:flex;align-items:center;gap:4px;font-size:13px;font-weight:500;color:<?= $mutedColor ?>;text-decoration:none;" id="embed-header-cart">
                <svg style="width:20px;height:20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                <span id="embed-cart-count" style="display:none;background:<?= htmlspecialchars($accentColor) ?>;color:#fff;font-size:11px;font-weight:700;padding:1px 6px;border-radius:10px;">0</span>
            </a>
        </div>
    </header>

    <!-- Cookie consent banner -->
    <div id="embed-cookie-banner" style="display:none;position:fixed;bottom:0;left:0;right:0;z-index:100;background:<?= $headerBg ?>;border-top:1px solid <?= $borderColor ?>;padding:14px 16px;box-shadow:0 -2px 12px rgba(0,0,0,0.1);">
        <div style="max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
            <p style="margin:0;font-size:13px;color:<?= $mutedColor ?>;flex:1;min-width:200px;">
                Acest site folosește cookie-uri pentru a îmbunătăți experiența ta. Prin continuarea navigării, ești de acord cu utilizarea cookie-urilor.
                <a href="<?= SITE_URL ?>/privacy" target="_blank" style="font-weight:500;">Politica de confidențialitate</a>
            </p>
            <button onclick="acceptCookies()" style="padding:8px 20px;background:<?= htmlspecialchars($accentColor) ?>;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">Accept</button>
        </div>
    </div>
    <script>
        function acceptCookies() {
            document.getElementById('embed-cookie-banner').style.display = 'none';
            document.cookie = 'embed_cookies_accepted=1;path=/;max-age=31536000;SameSite=Lax';
        }
        if (!document.cookie.includes('embed_cookies_accepted=1')) {
            document.getElementById('embed-cookie-banner').style.display = '';
        }
    </script>

    <!-- Main content -->
    <main class="embed-content" style="flex:1;padding:20px 16px;">
        <div id="embed-app">
