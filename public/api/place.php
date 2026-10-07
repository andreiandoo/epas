<?php
/**
 * What a place says about itself, in a sentence or two — for the open card of a stop in the planner.
 *
 *   GET /api/place.php?id=castelul-peles-sinaia            an attraction, by slug
 *   GET /api/place.php?id=b:muzeul-de-culori&kind=location  something bookable: kind = location | activity
 *   -> { ok: true, text: "…" }      text is empty when the catalogue has nothing of its own to say
 *
 * The pin dataset the planner runs on carries no descriptions (it would triple its size for text
 * nobody reads until a card is opened), so the card asks here, once, when it opens. The answer comes
 * out of the same cached API responses the place's own page uses.
 *
 * Most of the attraction catalogue was imported with a placeholder description; that is not passed
 * on. An empty answer is the honest one, and the card then simply shows no text.
 */

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/api.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

const PLACE_MAX = 300;

function placeOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** A field that may be plain text, HTML, or a map of translations. */
function placeText($v): string
{
    if (is_array($v)) {
        $v = $v['en'] ?? (reset($v) ?: '');
    }
    $t = html_entity_decode(strip_tags((string) $v), ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return trim((string) preg_replace('/\s+/u', ' ', $t));
}

/** Cut at the end of a sentence where there is one, at a word otherwise. */
function placeExcerpt(string $t): string
{
    if (mb_strlen($t) <= PLACE_MAX) {
        return $t;
    }
    $cut = mb_substr($t, 0, PLACE_MAX);
    $dot = max((int) mb_strrpos($cut, '. '), (int) mb_strrpos($cut, '! '), (int) mb_strrpos($cut, '? '));
    if ($dot > PLACE_MAX * 0.5) {
        return mb_substr($cut, 0, $dot + 1);
    }
    $sp = mb_strrpos($cut, ' ');

    return rtrim(mb_substr($cut, 0, $sp ?: PLACE_MAX), ' ,;:–-') . '…';
}

$id = (string) ($_GET['id'] ?? '');
$bookable = strncmp($id, 'b:', 2) === 0;
$slug = $bookable ? substr($id, 2) : $id;
if (!preg_match('/^[a-z0-9][a-z0-9-]{1,190}$/', $slug)) {
    placeOut(['ok' => false, 'error' => 'bad id'], 400);
}

$text = '';
if ($bookable) {
    $isLocation = ($_GET['kind'] ?? '') === 'location';
    $resp = $isLocation
        ? api_cached("am_location_{$slug}", fn () => api_get('/activities-module/locations/' . $slug), 60)
        : api_cached("am_product_{$slug}", fn () => api_get('/activities-module/products/' . $slug), 60);
    $d = is_array($resp['data'] ?? null) ? $resp['data'] : [];
    foreach (['short_description', 'subtitle', 'description'] as $f) {
        $text = placeText($d[$f] ?? '');
        if ($text !== '') {
            break;
        }
    }
} else {
    $resp = api_cached("attraction_{$slug}", fn () => api_get('/attractions/' . $slug), 300);
    $a = is_array($resp['data']['attraction'] ?? null) ? $resp['data']['attraction'] : [];
    $desc = placeText($a['description'] ?? '');
    // the importer's placeholder says so itself; it is not a description
    if ($desc !== '' && mb_strpos($desc, 'orientare rapidă') === false) {
        $text = $desc;
    } else {
        // the subtitle, unless it is only "<type> în <town>, <county>" — the card already shows that
        $sub = placeText($a['subtitle'] ?? '');
        $type = placeText($a['type']['name'] ?? '');
        if ($sub !== '' && ($type === '' || mb_stripos($sub, $type . ' în ') !== 0)) {
            $text = $sub;
        }
    }
}

// A label is not a description: "biserica din Brașov" says nothing the card does not already show.
if (mb_strlen($text) < 40) {
    $text = '';
}

header('Cache-Control: public, max-age=3600');
placeOut(['ok' => true, 'text' => placeExcerpt($text)]);
