<?php
/**
 * TICS.ro - Organizer Portal Head Component
 *
 * Variables:
 * - $pageTitle (required): Page title
 * - $pageDescription (optional): Meta description
 * - $bodyClass (optional): Additional body classes
 */

// Include config for helper functions
require_once __DIR__ . '/config.php';

$pageTitle = isset($pageTitle) ? $pageTitle : 'Dashboard';
$pageDescription = isset($pageDescription) ? $pageDescription : 'Portal organizatori TICS';
$bodyClass = isset($bodyClass) ? $bodyClass : 'bg-gray-50';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — TICS Organizer</title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/css/organizer.css') ?>">
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
<body class="<?= htmlspecialchars($bodyClass) ?> min-h-screen">
