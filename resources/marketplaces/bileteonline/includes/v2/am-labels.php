<?php
/**
 * bilete.online v2: Romanian labels for the activities module values (locations, products, lodging).
 * The keys are the ones the core accepts (OrganizerCatalog constants); the location page, the experience
 * page and the operator editors all read them from here.
 */

const AM_FACILITIES = [
    'parking' => 'Parcare', 'toilets' => 'Toalete', 'restaurant' => 'Restaurant', 'accessible' => 'Acces persoane cu dizabilități',
    'playground' => 'Loc de joacă', 'wifi' => 'Wi-Fi', 'pets' => 'Animale acceptate', 'lodging' => 'Cazare',
    'camping' => 'Camping', 'rentals' => 'Închirieri', 'guide' => 'Ghid', 'shop' => 'Magazin', 'card' => 'Plată cu cardul',
];

const AM_LODGING_FACILITIES = [
    'wifi' => 'Wi-Fi', 'parking' => 'Parcare', 'breakfast' => 'Mic dejun', 'restaurant' => 'Restaurant', 'kitchen' => 'Bucătărie',
    'ac' => 'Aer condiționat', 'heating' => 'Încălzire', 'private_bathroom' => 'Baie proprie', 'tv' => 'TV',
    'pets' => 'Animale acceptate', 'pool' => 'Piscină', 'spa' => 'Spa', 'terrace' => 'Terasă', 'bbq' => 'Grătar',
    'playground' => 'Loc de joacă', 'accessible' => 'Acces persoane cu dizabilități',
];

const AM_LODGING_TYPES = [
    'pensiune' => 'Pensiune', 'hotel' => 'Hotel', 'cabana' => 'Cabană', 'vila' => 'Vilă', 'apartamente' => 'Apartamente',
    'camping' => 'Camping', 'glamping' => 'Glamping', 'altele' => 'Cazare',
];

const AM_LINK_PLATFORMS = [
    'booking' => 'Booking.com', 'airbnb' => 'Airbnb', 'travelminit' => 'Travelminit', 'website' => 'Site-ul oficial', 'other' => 'Rezervă cazarea',
];

const AM_DAYS = ['mon' => 'luni', 'tue' => 'marți', 'wed' => 'miercuri', 'thu' => 'joi', 'fri' => 'vineri', 'sat' => 'sâmbătă', 'sun' => 'duminică'];

const AM_MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];

const AM_PRODUCT_TYPES = ['access' => 'Bilet de acces', 'experience' => 'Experiență', 'package' => 'Pachet'];

/** "04-01" → "1 aprilie". */
function am_month_day(?string $md): string
{
    if (!is_string($md) || !preg_match('/^(\d{2})-(\d{2})$/', $md, $m)) {
        return '';
    }
    return (int) $m[2] . ' ' . (AM_MONTHS[(int) $m[1] - 1] ?? '');
}

/** "2026-12-25" → "25 decembrie 2026". */
function am_date(?string $ymd): string
{
    if (!is_string($ymd) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $ymd, $m)) {
        return '';
    }
    return (int) $m[3] . ' ' . (AM_MONTHS[(int) $m[2] - 1] ?? '') . ' ' . $m[1];
}

/**
 * A week of {day: {open, close}} as grouped rows: [["luni – vineri", "09:00–19:00"], ["sâmbătă", "închis"]].
 * Consecutive days with the same hours share a row; a week with one set of hours is "zilnic".
 */
function am_week_rows($schedule): array
{
    $schedule = is_array($schedule) ? $schedule : [];
    $keys = array_keys(AM_DAYS);
    $hours = [];
    foreach ($keys as $d) {
        $h = $schedule[$d] ?? null;
        $hours[$d] = (is_array($h) && !empty($h['open']) && !empty($h['close']))
            ? substr($h['open'], 0, 5) . '–' . substr($h['close'], 0, 5)
            : 'închis';
    }
    if (count(array_unique($hours)) === 1) {
        return [['zilnic', reset($hours)]];
    }
    $rows = [];
    $start = 0;
    for ($i = 1; $i <= 7; $i++) {
        if ($i === 7 || $hours[$keys[$i]] !== $hours[$keys[$start]]) {
            $label = AM_DAYS[$keys[$start]] . ($i - 1 > $start ? ' – ' . AM_DAYS[$keys[$i - 1]] : '');
            $rows[] = [$label, $hours[$keys[$start]]];
            $start = $i;
        }
    }
    return $rows;
}

/** Rich text from the core (already purified there): a small allow-list again, no handlers, no script URLs. */
function am_rich(?string $html): string
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }
    if (strip_tags($html) === $html) {
        return '<p>' . nl2br(htmlspecialchars($html, ENT_QUOTES, 'UTF-8')) . '</p>';
    }
    $html = preg_replace('#<(script|style|iframe|object|embed)\b[^>]*>.*?</\1\s*>#is', '', $html);
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><h3><h4><a><blockquote>');
    $html = preg_replace('#\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#\s(?:style|class|id)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
    $html = preg_replace('#href\s*=\s*(["\']?)\s*(?:javascript|data|vbscript):[^"\'>\s]*\1#i', 'href="#"', $html);
    return preg_replace('#<a\s#i', '<a rel="nofollow noopener" target="_blank" ', $html);
}

/** Money from cents: 4100 → "41 lei", 4150 → "41,50 lei". */
function am_lei(?int $cents): string
{
    $v = ((int) $cents) / 100;
    return number_format($v, fmod($v, 1.0) ? 2 : 0, ',', '.') . ' lei';
}
