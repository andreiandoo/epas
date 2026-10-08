<?php
/**
 * Generate a whitelabel ZIP package for an organizer.
 * Called from the organizer widgets page.
 *
 * GET params: organizer (slug), passed via proxy with auth.
 */
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/api.php';

// Get organizer slug from session or request
$organizerSlug = $_GET['organizer'] ?? '';
if (!$organizerSlug) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing organizer slug']);
    exit;
}

// Fetch organizer data to get settings
$orgData = api_get('/marketplace-events/organizers/' . urlencode($organizerSlug));
$org = $orgData['data'] ?? null;

if (!$org) {
    http_response_code(404);
    echo json_encode(['error' => 'Organizer not found']);
    exit;
}

// Read widget config — API returns it at top level or in settings
$widgetConfig = $org['widget_config'] ?? $org['settings']['widget_config'] ?? [];
$orgName = $org['name'] ?? 'Operator';
$logo = $widgetConfig['logo'] ?? $org['avatar'] ?? '';
$bgImage = $widgetConfig['bg_image'] ?? '';
$accent = $widgetConfig['accent'] ?? '#D4A843';
$heroImage = $widgetConfig['hero_image'] ?? $org['cover_image'] ?? '';
$homeTitle = $widgetConfig['home_title'] ?? '';
$homeSubtitle = $widgetConfig['home_subtitle'] ?? '';
$orgAddress = $widgetConfig['address'] ?? '';
$orgPhone = $widgetConfig['phone'] ?? '';
$widgetTerms = $org['widget_terms'] ?? $org['settings']['widget_terms'] ?? '';
$widgetPrivacy = $org['widget_privacy'] ?? $org['settings']['widget_privacy'] ?? '';
$theme = $widgetConfig['theme'] ?? 'dark';

// Replacement map for template placeholders
$replacements = [
    '{{API_BASE_URL}}' => API_BASE_URL,
    '{{STORAGE_URL}}' => STORAGE_URL,
    '{{API_KEY}}' => API_KEY,
    '{{ORG_SLUG}}' => $organizerSlug,
    '{{ORG_NAME}}' => $orgName,
    '{{SITE_NAME}}' => $orgName . ' · Tickets',
    '{{MARKETPLACE_NAME}}' => SITE_NAME,
    '{{MARKETPLACE_URL}}' => SITE_URL,
    '{{LOGO_URL}}' => $logo,
    '{{BG_IMAGE_URL}}' => $bgImage,
    '{{HERO_IMAGE_URL}}' => $heroImage,
    '{{HOME_TITLE}}' => $homeTitle,
    '{{HOME_SUBTITLE}}' => $homeSubtitle,
    '{{ORG_ADDRESS}}' => $orgAddress,
    '{{ORG_PHONE}}' => $orgPhone,
    '{{ACCENT_COLOR}}' => $accent,
    '{{WIDGET_TERMS}}' => $widgetTerms,
    '{{WIDGET_PRIVACY}}' => $widgetPrivacy,
    '{{THEME}}' => $theme,
];

// Template directory
$templateDir = __DIR__ . '/whitelabel-template';

if (!is_dir($templateDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Template not found']);
    exit;
}

// Create ZIP
$zipFilename = 'tickets-' . $organizerSlug . '.zip';
$zipPath = sys_get_temp_dir() . '/' . $zipFilename;

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    http_response_code(500);
    echo json_encode(['error' => 'Cannot create ZIP']);
    exit;
}

// Recursively add template files to ZIP, replacing placeholders
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($templateDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($iterator as $file) {
    $filePath = $file->getRealPath();
    $relativePath = substr($filePath, strlen($templateDir) + 1);
    // Normalize path separators
    $relativePath = str_replace('\\', '/', $relativePath);

    $content = file_get_contents($filePath);

    // Apply replacements to PHP, JS, CSS, and HTML files
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    if (in_array($ext, ['php', 'js', 'css', 'html', 'htaccess', ''])) {
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    $zip->addFromString($relativePath, $content);
}

// Add a README
// A document for the operator's developer, not page text: written in English, outside the language catalogue.
$readme = "# {$orgName}: ticket site\n\n";
$readme .= "## Installation\n\n";
$readme .= "1. Upload the contents of this archive to your web server (Apache + PHP 7.4+)\n";
$readme .= "2. Make sure mod_rewrite is enabled\n";
$readme .= "3. Set DocumentRoot to the folder where you extracted the files\n";
$readme .= "4. Open the site in a browser\n\n";
$readme .= "## Server requirements\n\n";
$readme .= "- Apache with mod_rewrite\n";
$readme .= "- PHP 7.4+ with the cURL extension\n";
$readme .= "- An SSL certificate (HTTPS) is recommended\n\n";
$readme .= "## Structure\n\n";
$readme .= "- `index.php`: event list\n";
$readme .= "- `event.php`: event details + tickets\n";
$readme .= "- `checkout.php`: basket + checkout\n";
$readme .= "- `thank-you.php`: order confirmation\n";
$readme .= "- `terms.php`: terms and conditions\n";
$readme .= "- `privacy.php`: privacy policy\n";
$readme .= "- `api/proxy.php`: API proxy (keeps the API key on the server)\n";
$readme .= "- `includes/config.php`: configuration (do not change API_KEY)\n\n";
$readme .= "## Customisation\n\n";
$readme .= "- Logo: change LOGO_URL in includes/config.php\n";
$readme .= "- Colours: change ACCENT_COLOR and THEME in includes/config.php\n";
$readme .= "- Background: change BG_IMAGE_URL in includes/config.php\n";
$readme .= "- Styles: edit assets/css/style.css\n\n";
$readme .= "Generated automatically by " . SITE_NAME . " (" . SITE_URL . ")\n";

$zip->addFromString('README.md', $readme);

$zip->close();

// Send ZIP as download
header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-cache');
readfile($zipPath);
unlink($zipPath);
exit;
