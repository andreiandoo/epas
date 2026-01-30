<?php
/**
 * API Helper Functions
 * Provides functions to interact with the EPAS Core API
 */

// Prevent direct access
if (!defined('API_BASE_URL') || !defined('API_KEY')) {
    die('Configuration not loaded');
}

/**
 * Make a GET request to the API
 *
 * @param string $endpoint The API endpoint (e.g., '/kb/categories')
 * @param array $params Optional query parameters
 * @return array Response data with 'success' and 'data' keys
 */
function api_get(string $endpoint, array $params = []): array
{
    $url = API_BASE_URL . $endpoint;

    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }

    return api_request('GET', $url);
}

/**
 * Make a POST request to the API
 *
 * @param string $endpoint The API endpoint
 * @param array $data The data to send
 * @return array Response data with 'success' and 'data' keys
 */
function api_post(string $endpoint, array $data = []): array
{
    $url = API_BASE_URL . $endpoint;
    return api_request('POST', $url, $data);
}

/**
 * Make an API request using cURL
 *
 * @param string $method HTTP method (GET, POST, etc.)
 * @param string $url Full URL to request
 * @param array|null $data Optional data for POST requests
 * @return array Response data
 */
function api_request(string $method, string $url, ?array $data = null): array
{
    $ch = curl_init();

    $headers = [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-API-Key: ' . API_KEY,
    ];

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
    ]);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Handle cURL errors
    if ($error) {
        error_log("API Request Error: {$error} for URL: {$url}");
        return [
            'success' => false,
            'error' => 'Connection error',
            'data' => [],
        ];
    }

    // Parse response
    $decoded = json_decode($response, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("API Response Parse Error: " . json_last_error_msg() . " for URL: {$url}");
        return [
            'success' => false,
            'error' => 'Invalid response',
            'data' => [],
        ];
    }

    // Check for API errors
    if ($httpCode >= 400) {
        error_log("API Error ({$httpCode}): " . ($decoded['message'] ?? 'Unknown error') . " for URL: {$url}");
        return [
            'success' => false,
            'error' => $decoded['message'] ?? 'API error',
            'data' => [],
        ];
    }

    return [
        'success' => true,
        'data' => $decoded['data'] ?? $decoded,
    ];
}

/**
 * Cache wrapper for API requests
 * Uses file-based caching for simple deployments
 *
 * @param string $key Cache key
 * @param callable $callback Function to call if cache miss
 * @param int $ttl Cache TTL in seconds (default: 5 minutes)
 * @return mixed Cached or fresh data
 */
function api_cached(string $key, callable $callback, int $ttl = 300)
{
    $cacheDir = sys_get_temp_dir() . '/ambilet_cache';
    $cacheFile = $cacheDir . '/' . md5($key) . '.json';

    // Create cache directory if needed
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0755, true);
    }

    // Check cache
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached && isset($cached['expires']) && $cached['expires'] > time()) {
            return $cached['data'];
        }
    }

    // Fetch fresh data
    $data = $callback();

    // Save to cache
    $cacheData = [
        'expires' => time() + $ttl,
        'data' => $data,
    ];
    @file_put_contents($cacheFile, json_encode($cacheData));

    return $data;
}

/**
 * Get KB categories with caching
 *
 * @return array Categories array
 */
function get_kb_categories(): array
{
    return api_cached('kb_categories', function () {
        $response = api_get('/kb/categories');
        return $response['data']['categories'] ?? [];
    }, 300); // 5 minute cache
}

/**
 * Get KB category by slug with its articles
 *
 * @param string $slug Category slug
 * @return array|null Category data with articles or null if not found
 */
function get_kb_category(string $slug): ?array
{
    $response = api_get('/kb/categories/' . urlencode($slug));
    return $response['success'] ? $response['data'] : null;
}

/**
 * Get KB article by slug
 *
 * @param string $slug Article slug
 * @return array|null Article data or null if not found
 */
function get_kb_article(string $slug): ?array
{
    $response = api_get('/kb/articles/' . urlencode($slug));
    return $response['success'] ? ($response['data']['article'] ?? null) : null;
}

/**
 * Search KB articles
 *
 * @param string $query Search query
 * @param int $limit Maximum results
 * @return array Search results
 */
function search_kb_articles(string $query, int $limit = 10): array
{
    $response = api_get('/kb/articles/search', [
        'q' => $query,
        'limit' => $limit,
    ]);
    return $response['data']['results'] ?? [];
}

/**
 * Get popular KB articles
 *
 * @param int $limit Maximum results
 * @return array Popular articles
 */
function get_popular_articles(int $limit = 7): array
{
    return api_cached('kb_popular_' . $limit, function () use ($limit) {
        $response = api_get('/kb/articles/popular', ['limit' => $limit]);
        return $response['data']['articles'] ?? [];
    }, 300);
}

/**
 * Get featured KB articles
 *
 * @param int $limit Maximum results
 * @return array Featured articles
 */
function get_featured_articles(int $limit = 5): array
{
    return api_cached('kb_featured_' . $limit, function () use ($limit) {
        $response = api_get('/kb/articles/featured', ['limit' => $limit]);
        return $response['data']['articles'] ?? [];
    }, 300);
}

/**
 * Record article view
 *
 * @param int $articleId Article ID
 * @return bool Success status
 */
function record_article_view(int $articleId): bool
{
    $response = api_post('/kb/articles/' . $articleId . '/view');
    return $response['success'];
}

/**
 * Submit article helpfulness vote
 *
 * @param int $articleId Article ID
 * @param bool $helpful Whether the article was helpful
 * @return bool Success status
 */
function vote_article_helpful(int $articleId, bool $helpful): bool
{
    $response = api_post('/kb/articles/' . $articleId . '/vote', [
        'helpful' => $helpful,
    ]);
    return $response['success'];
}
