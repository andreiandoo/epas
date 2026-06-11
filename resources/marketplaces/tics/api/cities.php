<?php
/**
 * Server-side proxy: locations/cities
 *
 * Fetches cities from the backend API using cURL (avoids browser CORS restrictions).
 * Called by the country selector in the header.
 *
 * Query params:
 *   country  – ISO 2-letter code (RO, MD, HU, BG)
 *   per_page – max results (default 50, max 200)
 *   sort     – "events" (default) or "name"
 */

require_once dirname(__DIR__) . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5-minute browser cache

// Validate inputs
$country = strtoupper(preg_replace('/[^A-Za-z]/', '', $_GET['country'] ?? ''));
$sort    = in_array($_GET['sort'] ?? '', ['events', 'name']) ? $_GET['sort'] : 'events';
$perPage = min(max((int) ($_GET['per_page'] ?? 50), 1), 200);

$params = ['per_page' => $perPage, 'sort' => $sort];
if ($country !== '') {
    $params['country'] = $country;
}

echo json_encode(callApi('locations/cities', $params));
