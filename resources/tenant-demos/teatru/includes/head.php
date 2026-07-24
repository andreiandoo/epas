<?php
/**
 * Unified <head> for the teatru skin.
 * Set before including:
 *   $pageTitle       — <title> text
 *   $pageDescription — meta description (optional)
 *   $pageExtraStyles — extra CSS injected in <style> (optional, raw CSS string)
 *   $pageExtraHead   — extra raw HTML injected at end of <head> (optional)
 */
$pageTitle       = $pageTitle       ?? (defined('SITE_NAME') ? SITE_NAME : 'Teatrul Național');
$pageDescription = $pageDescription ?? 'Bilete și abonamente — ' . (defined('SITE_NAME') ? SITE_NAME : 'Teatrul Național');
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($pageDescription, ENT_QUOTES) ?>">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,500;0,600;0,700;1,400;1,500&family=Lato:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'display': ['Playfair Display', 'serif'],
                        'body': ['Lato', 'sans-serif']
                    },
                    colors: {
                        'midnight': '#0A0A0A',
                        'charcoal': '#1A1A1A',
                        'burgundy': '#722F37',
                        'burgundy-light': '#8B3A44',
                        'burgundy-dark': '#5C262E',
                        'gold': '#D4AF37',
                        'gold-light': '#E5C76B',
                        'ivory': '#FFFEF2',
                        'ivory-dark': '#F0EDE0',
                        'warm-gray': '#9A9590'
                    }
                }
            }
        }
    </script>
    <style>
        ::selection { background: #D4AF37; color: #0A0A0A; }
        body { background: #0A0A0A; color: #FFFEF2; font-family: 'Lato', sans-serif; }
        .font-display { font-family: 'Playfair Display', serif; }
        .text-gold-gradient { background: linear-gradient(135deg, #D4AF37 0%, #E5C76B 50%, #D4AF37 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
        .btn-gold { background: linear-gradient(135deg, #D4AF37 0%, #E5C76B 50%, #D4AF37 100%); color: #0A0A0A; font-weight: 600; transition: all 0.3s ease; }
        .btn-gold:hover { transform: translateY(-2px); box-shadow: 0 8px 30px -10px rgba(212, 175, 55, 0.5); }
        .btn-burgundy { background: #722F37; color: #FFFEF2; transition: all 0.3s ease; }
        .btn-burgundy:hover { background: #8B3A44; transform: translateY(-2px); }
        .btn-outline { border: 1px solid #D4AF37; color: #D4AF37; transition: all 0.3s ease; }
        .btn-outline:hover { background: #D4AF37; color: #0A0A0A; }
        .show-card { background: linear-gradient(180deg, #1A1A1A 0%, #0A0A0A 100%); border: 1px solid rgba(212, 175, 55, 0.1); transition: all 0.4s ease; }
        .show-card:hover { border-color: rgba(212, 175, 55, 0.3); transform: translateY(-4px); }
        .ornament { display: inline-block; width: 60px; height: 1px; background: linear-gradient(90deg, transparent, #D4AF37, transparent); }
        .divider-ornate { display: flex; align-items: center; gap: 1rem; }
        .divider-ornate::before, .divider-ornate::after { content: ''; flex: 1; height: 1px; background: linear-gradient(90deg, transparent, rgba(212,175,55,0.3), transparent); }
        <?= $pageExtraStyles ?? '' ?>
    </style>
    <?= $pageExtraHead ?? '' ?>
</head>
