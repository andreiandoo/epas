<?php
/**
 * TICS.ro - User Account Head Component
 *
 * Variables:
 * - $pageTitle (required): Page title
 * - $pageDescription (optional): Meta description
 */

// Include config for helper functions (asset, cityUrl, etc.)
require_once __DIR__ . '/config.php';

$pageTitle = isset($pageTitle) ? $pageTitle : 'Contul meu';
$pageDescription = isset($pageDescription) ? $pageDescription : 'Gestioneaza contul tau TICS.ro';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — TICS.ro</title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/custom.css">
    <link rel="stylesheet" href="/assets/css/user.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'sans': ['Inter', 'system-ui', 'sans-serif'],
                    },
                }
            }
        }
    </script>
</head>
