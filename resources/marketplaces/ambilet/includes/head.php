<?php
/**
 * Common head section for all pages
 *
 * Variables to set before including:
 * - $pageTitle (required): Page title
 * - $pageDescription (optional): Meta description
 * - $bodyClass (optional): Additional body classes
 */

if (!isset($pageTitle)) $pageTitle = SITE_NAME;
if (!isset($pageDescription)) $pageDescription = 'Cumpara bilete online pentru concerte, festivaluri, teatru, sport si multe altele.';
?>
<!DOCTYPE html>
<html lang="<?= SITE_LOCALE ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= SITE_NAME ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">

    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= SITE_NAME ?>">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { 'sans': ['Plus Jakarta Sans', 'sans-serif'] },
                    colors: {
                        'primary': '<?= $THEME['primary'] ?>',
                        'primary-dark': '<?= $THEME['primary_dark'] ?>',
                        'primary-light': '<?= $THEME['primary_light'] ?>',
                        'secondary': '<?= $THEME['secondary'] ?>',
                        'accent': '<?= $THEME['accent'] ?>',
                        'surface': '<?= $THEME['surface'] ?>',
                        'muted': '<?= $THEME['muted'] ?>',
                        'border': '<?= $THEME['border'] ?>',
                        'success': '<?= $THEME['success'] ?>',
                        'warning': '<?= $THEME['warning'] ?>',
                        'error': '<?= $THEME['error'] ?>',
                    }
                }
            }
        }
    </script>

    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= asset('assets/css/custom.css') ?>">

    <!-- Page-specific head content -->
    <?php if (isset($headExtra)) echo $headExtra; ?>
</head>
<body class="<?= isset($bodyClass) ? htmlspecialchars($bodyClass) : 'bg-white' ?>">
