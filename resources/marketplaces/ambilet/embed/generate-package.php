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
$orgName = $org['name'] ?? 'Organizator';
$logo = $widgetConfig['logo'] ?? $org['avatar'] ?? '';
$bgImage = $widgetConfig['bg_image'] ?? '';
$accent = $widgetConfig['accent'] ?? '#D4A843';
$heroImage = $widgetConfig['hero_image'] ?? $org['cover_image'] ?? '';
$theme = $widgetConfig['theme'] ?? 'dark';

// Replacement map for template placeholders
$replacements = [
    '{{API_BASE_URL}}' => API_BASE_URL,
    '{{STORAGE_URL}}' => STORAGE_URL,
    '{{API_KEY}}' => API_KEY,
    '{{ORG_SLUG}}' => $organizerSlug,
    '{{ORG_NAME}}' => $orgName,
    '{{SITE_NAME}}' => $orgName . ' — Bilete',
    '{{MARKETPLACE_NAME}}' => SITE_NAME,
    '{{MARKETPLACE_URL}}' => SITE_URL,
    '{{LOGO_URL}}' => $logo,
    '{{BG_IMAGE_URL}}' => $bgImage,
    '{{HERO_IMAGE_URL}}' => $heroImage,
    '{{ACCENT_COLOR}}' => $accent,
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
$zipFilename = 'bilete-' . $organizerSlug . '.zip';
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
$readme = "# {$orgName} — Site Bilete\n\n";
$readme .= "## Instalare\n\n";
$readme .= "1. Incarca continutul acestei arhive pe serverul tau web (Apache + PHP 7.4+)\n";
$readme .= "2. Asigura-te ca mod_rewrite este activat\n";
$readme .= "3. Seteaza DocumentRoot la directorul unde ai extras fisierele\n";
$readme .= "4. Acceseaza site-ul in browser\n\n";
$readme .= "## Cerinte server\n\n";
$readme .= "- Apache cu mod_rewrite\n";
$readme .= "- PHP 7.4+ cu extensia cURL\n";
$readme .= "- Certificat SSL (HTTPS) recomandat\n\n";
$readme .= "## Structura\n\n";
$readme .= "- `index.php` — Lista evenimente\n";
$readme .= "- `event.php` — Detalii eveniment + bilete\n";
$readme .= "- `checkout.php` — Cos + finalizare comanda\n";
$readme .= "- `thank-you.php` — Confirmare comanda\n";
$readme .= "- `terms.php` — Termeni si conditii\n";
$readme .= "- `privacy.php` — Politica confidentialitate\n";
$readme .= "- `api/proxy.php` — Proxy API (tine cheia API server-side)\n";
$readme .= "- `includes/config.php` — Configurare (NU modifica API_KEY!)\n\n";
$readme .= "## Personalizare\n\n";
$readme .= "- Logo: modifica LOGO_URL in includes/config.php\n";
$readme .= "- Culori: modifica ACCENT_COLOR si THEME in includes/config.php\n";
$readme .= "- Fundal: modifica BG_IMAGE_URL in includes/config.php\n";
$readme .= "- Stiluri: editeaza assets/css/style.css\n\n";
$readme .= "Generat automat de " . SITE_NAME . " (" . SITE_URL . ")\n";

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
