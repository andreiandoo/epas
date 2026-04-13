<?php
/**
 * Embed head — minimal layout for iframe pages.
 * Variables available: $organizerSlug, $returnUrl, $theme, $accent, $orgData, $embedDomains
 */

// Security: set frame-ancestors dynamically
// Convert embed_domains to CSP frame-ancestors format
// Input: "https://bilete.hailateatru.ro" → "https://bilete.hailateatru.ro"
// Input: "*.hailateatru.ro" → "https://*.hailateatru.ro"
$cspAncestors = [];
foreach ($embedDomains as $domain) {
    $host = parse_url($domain, PHP_URL_HOST) ?: $domain;
    $scheme = parse_url($domain, PHP_URL_SCHEME) ?: 'https';
    $cspAncestors[] = $scheme . '://' . $host;
}
$frameAncestors = !empty($cspAncestors) ? implode(' ', $cspAncestors) : "'none'";
header("Content-Security-Policy: frame-ancestors {$frameAncestors}");
header('X-Frame-Options: SAMEORIGIN');

$orgName = $orgData['data']['name'] ?? 'Organizator';
$accentColor = $accent ?: '#6366f1';
$isDark = $theme === 'dark';
$bgColor = $isDark ? '#0f172a' : '#f8fafc';
$textColor = $isDark ? '#e2e8f0' : '#1e293b';
$cardBg = $isDark ? '#1e293b' : '#ffffff';
$borderColor = $isDark ? '#334155' : '#e2e8f0';
$mutedColor = $isDark ? '#94a3b8' : '#64748b';
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
            background: <?= $bgColor ?>;
            color: <?= $textColor ?>;
            overflow-x: hidden;
        }
        a { color: <?= htmlspecialchars($accentColor) ?>; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .embed-card {
            background: <?= $cardBg ?>;
            border: 1px solid <?= $borderColor ?>;
            border-radius: 12px;
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }
        .embed-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transform: translateY(-2px);
        }
        .embed-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 10px 24px;
            background: <?= htmlspecialchars($accentColor) ?>;
            color: #fff;
            border: none; border-radius: 10px;
            font-weight: 600; font-size: 14px;
            cursor: pointer; transition: opacity 0.2s;
        }
        .embed-btn:hover { opacity: 0.9; text-decoration: none; color: #fff; }
        .embed-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .skeleton { background: linear-gradient(90deg, <?= $borderColor ?> 25%, <?= $isDark ? '#475569' : '#f1f5f9' ?> 50%, <?= $borderColor ?> 75%); background-size: 200% 100%; animation: shimmer 1.5s infinite; }
        @keyframes shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
    </style>
</head>
<body>
    <div id="embed-app" style="padding: 16px; max-width: 1200px; margin: 0 auto;">
