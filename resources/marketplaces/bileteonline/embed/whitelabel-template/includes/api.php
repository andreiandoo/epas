<?php
/**
 * API helper — proxies requests to Tixello Core.
 */

function api_get(string $endpoint, array $params = []): array
{
    $url = API_BASE_URL . $endpoint;
    if (!empty($params)) $url .= '?' . http_build_query($params);
    return api_request('GET', $url);
}

function api_post(string $endpoint, array $data = []): array
{
    return api_request('POST', API_BASE_URL . $endpoint, $data);
}

function api_request(string $method, string $url, ?array $data = null): array
{
    $ch = curl_init();
    $headers = ['Accept: application/json', 'Content-Type: application/json', 'X-API-Key: ' . API_KEY];

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        if ($data !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) return ['success' => false, 'data' => []];

    $decoded = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) return ['success' => false, 'data' => []];
    if ($httpCode >= 400) return ['success' => false, 'error' => $decoded['message'] ?? 'API error', 'data' => []];

    return ['success' => true, 'data' => $decoded['data'] ?? $decoded];
}

function api_cached(string $key, callable $callback, int $ttl = 300): array
{
    $cacheDir = sys_get_temp_dir() . '/wl_cache';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
    $cacheFile = $cacheDir . '/' . md5($key) . '.json';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) return $cached;
    }

    $result = $callback();
    @file_put_contents($cacheFile, json_encode($result));
    return $result;
}
