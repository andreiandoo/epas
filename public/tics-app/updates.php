<?php

/**
 * Endpoint OTA self-hosted pentru aplicatia mobila Tixello.
 * Inlocuieste Capgo Cloud (care nu are tier gratuit) — plugin-ul
 * @capgo/capacitor-updater e MPL-2.0 si accepta orice `updateUrl`.
 *
 * DE CE E FISIER STANDALONE, NU O RUTA LARAVEL:
 * o ruta noua ar impune `route:clear && route:cache` la fiecare deploy pe
 * core.tixello.com (si implicit `artisan down`, din cauza cursei TOCTOU cu
 * scheduler-ul). Asa, publicarea unui bundle nou ramane un simplu `git pull`.
 *
 * CONTRACT (documentat de plugin):
 *   Request  POST JSON: { platform, device_id, app_id, version_name, version_build, ... }
 *            `version_name` = versiunea bundle-ului activ pe telefon, sau "builtin".
 *   Response 200 JSON cu update:      { "version": "0.1.1", "url": "...zip", "checksum": "..." }
 *            200 JSON fara update:    { "message": "No update available" }
 *
 * Sursa adevarului = manifest.json de langa acest fisier. Ca sa publici un
 * bundle nou rulezi `tics-app/mobile/publish-bundle.ps1`, care scrie zip-ul,
 * calculeaza sha256 si rescrie manifestul.
 *
 * ESCAPE HATCH: daca update-urile esueaza cu eroare de checksum (versiuni
 * diferite de plugin calculeaza altfel hash-ul), sterge cheia "checksum" din
 * manifest.json — e optionala. Nu necesita rebuild de APK.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Robots-Tag: noindex, nofollow');

const MANIFEST = __DIR__ . '/manifest.json';

/** Raspuns "nu exista update" — plugin-ul il trateaza ca no-op. */
function noUpdate(string $reason): void
{
    echo json_encode(['message' => 'No update available', 'reason' => $reason]);
    exit;
}

if (! is_readable(MANIFEST)) {
    http_response_code(200);
    noUpdate('manifest lipseste');
}

$manifest = json_decode((string) file_get_contents(MANIFEST), true);
if (! is_array($manifest) || empty($manifest['version']) || empty($manifest['url'])) {
    noUpdate('manifest invalid');
}

// Plugin-ul trimite JSON pe POST; acceptam si GET ca sa putem inspecta din browser.
$raw = file_get_contents('php://input');
$req = json_decode((string) $raw, true);
if (! is_array($req)) {
    $req = $_GET;
}

$current = isset($req['version_name']) ? trim((string) $req['version_name']) : 'builtin';
if ($current === '') {
    $current = 'builtin';
}

// Optional: blocheaza bundle-urile pentru versiuni native prea vechi. Setezi
// "min_version_build" in manifest cand un bundle depinde de un plugin nativ nou.
if (! empty($manifest['min_version_build']) && ! empty($req['version_build'])) {
    if (version_compare((string) $req['version_build'], (string) $manifest['min_version_build'], '<')) {
        noUpdate('APK prea vechi pentru acest bundle');
    }
}

// "builtin" = ruleaza assets-urile din APK, deci orice bundle publicat e mai nou.
if ($current !== 'builtin' && version_compare($current, (string) $manifest['version'], '>=')) {
    noUpdate('deja la zi');
}

$response = [
    'version' => (string) $manifest['version'],
    'url'     => (string) $manifest['url'],
];

// Optionala: verificarea de integritate. Vezi ESCAPE HATCH din antet.
if (! empty($manifest['checksum'])) {
    $response['checksum'] = (string) $manifest['checksum'];
}

echo json_encode($response, JSON_UNESCAPED_SLASHES);
