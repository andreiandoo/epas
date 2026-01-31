<?php
/**
 * EPAS Changelog Integration pentru WordPress (tixello.com)
 *
 * INSTALARE:
 * 1. Copiază acest fișier în tema ta WordPress sau ca plugin
 * 2. Configurează API_URL și API_KEY
 * 3. Folosește shortcode [epas_changelog] sau funcția direct
 *
 * SHORTCODES:
 * [epas_changelog]                    - Afișează toate intrările
 * [epas_changelog days="7"]           - Ultimele 7 zile
 * [epas_changelog module="seating"]   - Doar modulul Seating
 * [epas_changelog type="feat"]        - Doar features
 * [epas_changelog group_by="module"]  - Grupat pe module
 *
 * FUNCȚII:
 * epas_get_changelog($params)         - Returnează array cu intrări
 * epas_get_changelog_summary()        - Returnează statistici
 * epas_render_changelog($params)      - Returnează HTML
 */

if (!defined('ABSPATH')) {
    // Allow standalone usage outside WordPress
    define('ABSPATH', dirname(__FILE__) . '/');
}

class EPAS_Changelog {

    // =========================================================================
    // CONFIGURARE - Modifică aceste valori
    // =========================================================================

    private const API_URL = 'https://api.epas.ro/api/v1/public/changelog';
    private const API_KEY = 'your-api-key-here'; // Obține din admin panel
    private const CACHE_TTL = 300; // Cache 5 minute (în secunde)

    // =========================================================================
    // CORE METHODS
    // =========================================================================

    /**
     * Fetch changelog entries from API
     */
    public static function fetch(array $params = []): array {
        $cacheKey = 'epas_changelog_' . md5(json_encode($params));

        // Check cache (WordPress transients)
        if (function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $url = self::API_URL . '?' . http_build_query($params);

        $response = self::makeRequest($url);

        if ($response && $response['success']) {
            $data = $response['data'] ?? [];

            // Cache response
            if (function_exists('set_transient')) {
                set_transient($cacheKey, $data, self::CACHE_TTL);
            }

            return $data;
        }

        return [];
    }

    /**
     * Fetch changelog grouped by field
     */
    public static function fetchGrouped(string $groupBy = 'module', int $days = 30): array {
        $cacheKey = 'epas_changelog_grouped_' . $groupBy . '_' . $days;

        if (function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $url = self::API_URL . '/grouped?' . http_build_query([
            'group_by' => $groupBy,
            'days' => $days,
        ]);

        $response = self::makeRequest($url);

        if ($response && $response['success']) {
            $data = $response['data'] ?? [];

            if (function_exists('set_transient')) {
                set_transient($cacheKey, $data, self::CACHE_TTL);
            }

            return $data;
        }

        return [];
    }

    /**
     * Fetch changelog summary/stats
     */
    public static function fetchSummary(int $days = 30): array {
        $cacheKey = 'epas_changelog_summary_' . $days;

        if (function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $url = self::API_URL . '/summary?days=' . $days;

        $response = self::makeRequest($url);

        if ($response && $response['success']) {
            $data = $response['data'] ?? [];

            if (function_exists('set_transient')) {
                set_transient($cacheKey, $data, self::CACHE_TTL);
            }

            return $data;
        }

        return [];
    }

    /**
     * Fetch available modules
     */
    public static function fetchModules(): array {
        $cacheKey = 'epas_changelog_modules';

        if (function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $url = self::API_URL . '/modules';

        $response = self::makeRequest($url);

        if ($response && $response['success']) {
            $data = $response['data'] ?? [];

            if (function_exists('set_transient')) {
                set_transient($cacheKey, $data, 3600); // Cache 1 hour
            }

            return $data;
        }

        return [];
    }

    // =========================================================================
    // RENDERING METHODS
    // =========================================================================

    /**
     * Render changelog as HTML
     */
    public static function render(array $params = []): string {
        $groupBy = $params['group_by'] ?? null;

        if ($groupBy) {
            $data = self::fetchGrouped($groupBy, $params['days'] ?? 30);
            return self::renderGrouped($data, $groupBy);
        }

        $entries = self::fetch($params);
        return self::renderList($entries);
    }

    /**
     * Render as simple list
     */
    public static function renderList(array $entries): string {
        if (empty($entries)) {
            return '<div class="epas-changelog-empty">Nu există intrări în changelog.</div>';
        }

        $html = '<div class="epas-changelog">';

        foreach ($entries as $entry) {
            $html .= self::renderEntry($entry);
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render grouped changelog
     */
    public static function renderGrouped(array $groups, string $groupBy): string {
        if (empty($groups)) {
            return '<div class="epas-changelog-empty">Nu există intrări în changelog.</div>';
        }

        $html = '<div class="epas-changelog epas-changelog-grouped">';

        foreach ($groups as $group) {
            $label = $group['label'] ?? $group['module'] ?? $group['type'] ?? $group['date'] ?? 'Unknown';
            $count = $group['count'] ?? count($group['entries'] ?? []);

            $html .= '<div class="epas-changelog-group">';
            $html .= '<h3 class="epas-changelog-group-title">';
            $html .= '<span class="group-label">' . esc_html($label) . '</span>';
            $html .= '<span class="group-count">' . $count . '</span>';
            $html .= '</h3>';

            $html .= '<div class="epas-changelog-group-entries">';
            foreach ($group['entries'] ?? [] as $entry) {
                $html .= self::renderEntry($entry);
            }
            $html .= '</div>';

            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Render single entry
     */
    public static function renderEntry(array $entry): string {
        $typeClass = 'type-' . ($entry['type'] ?? 'other');
        $typeLabel = $entry['type_label'] ?? ucfirst($entry['type'] ?? 'Other');
        $moduleLabel = $entry['module_label'] ?? ucfirst($entry['module'] ?? 'General');
        $isBreaking = $entry['is_breaking'] ?? false;

        $html = '<div class="epas-changelog-entry ' . $typeClass . '">';

        // Header
        $html .= '<div class="entry-header">';
        $html .= '<span class="entry-type">' . esc_html($typeLabel) . '</span>';
        $html .= '<span class="entry-module">' . esc_html($moduleLabel) . '</span>';
        if ($isBreaking) {
            $html .= '<span class="entry-breaking">⚠️ Breaking</span>';
        }
        $html .= '<span class="entry-date">' . esc_html($entry['committed_at_human'] ?? '') . '</span>';
        $html .= '</div>';

        // Message
        $html .= '<div class="entry-message">' . esc_html($entry['message'] ?? '') . '</div>';

        // Hash
        $html .= '<div class="entry-hash">';
        $html .= '<code>' . esc_html($entry['hash'] ?? '') . '</code>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Render summary stats
     */
    public static function renderSummary(int $days = 30): string {
        $summary = self::fetchSummary($days);

        if (empty($summary)) {
            return '';
        }

        $html = '<div class="epas-changelog-summary">';

        // Stats cards
        $html .= '<div class="summary-stats">';

        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-value">' . ($summary['total_commits'] ?? 0) . '</div>';
        $html .= '<div class="stat-label">Total Commits</div>';
        $html .= '</div>';

        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-value">' . number_format($summary['lines_added'] ?? 0) . '</div>';
        $html .= '<div class="stat-label">Linii Adăugate</div>';
        $html .= '</div>';

        $html .= '<div class="stat-card">';
        $html .= '<div class="stat-value">' . count($summary['by_module'] ?? []) . '</div>';
        $html .= '<div class="stat-label">Module Active</div>';
        $html .= '</div>';

        $html .= '</div>';

        // By module breakdown
        if (!empty($summary['by_module'])) {
            $html .= '<div class="summary-modules">';
            $html .= '<h4>Activitate per Modul</h4>';
            $html .= '<div class="module-bars">';

            $maxCount = max(array_column($summary['by_module'], 'count'));

            foreach ($summary['by_module'] as $module) {
                $percentage = $maxCount > 0 ? ($module['count'] / $maxCount) * 100 : 0;

                $html .= '<div class="module-bar">';
                $html .= '<span class="module-name">' . esc_html($module['label']) . '</span>';
                $html .= '<div class="module-bar-bg"><div class="module-bar-fill" style="width: ' . $percentage . '%"></div></div>';
                $html .= '<span class="module-count">' . $module['count'] . '</span>';
                $html .= '</div>';
            }

            $html .= '</div>';
            $html .= '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Make HTTP request to API
     */
    private static function makeRequest(string $url): ?array {
        $args = [
            'headers' => [
                'Accept' => 'application/json',
                'X-API-Key' => self::API_KEY,
            ],
            'timeout' => 10,
        ];

        // WordPress HTTP API
        if (function_exists('wp_remote_get')) {
            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                error_log('EPAS Changelog API Error: ' . $response->get_error_message());
                return null;
            }

            $body = wp_remote_retrieve_body($response);
            return json_decode($body, true);
        }

        // Fallback to cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'X-API-Key: ' . self::API_KEY,
            ],
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('EPAS Changelog API Error: ' . $error);
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Get inline CSS styles
     */
    public static function getStyles(): string {
        return '
        <style>
        .epas-changelog {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 900px;
            margin: 0 auto;
        }

        .epas-changelog-entry {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            transition: box-shadow 0.2s;
        }

        .epas-changelog-entry:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .epas-changelog-entry.type-feat { border-left: 4px solid #10b981; }
        .epas-changelog-entry.type-fix { border-left: 4px solid #ef4444; }
        .epas-changelog-entry.type-refactor { border-left: 4px solid #f59e0b; }
        .epas-changelog-entry.type-docs { border-left: 4px solid #3b82f6; }
        .epas-changelog-entry.type-other { border-left: 4px solid #6b7280; }

        .entry-header {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
            margin-bottom: 8px;
        }

        .entry-type, .entry-module {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .entry-type {
            background: #f3f4f6;
            color: #374151;
        }

        .type-feat .entry-type { background: #d1fae5; color: #065f46; }
        .type-fix .entry-type { background: #fee2e2; color: #991b1b; }
        .type-refactor .entry-type { background: #fef3c7; color: #92400e; }
        .type-docs .entry-type { background: #dbeafe; color: #1e40af; }

        .entry-module {
            background: #ede9fe;
            color: #5b21b6;
        }

        .entry-breaking {
            font-size: 12px;
            color: #dc2626;
            font-weight: 600;
        }

        .entry-date {
            font-size: 12px;
            color: #9ca3af;
            margin-left: auto;
        }

        .entry-message {
            font-size: 15px;
            color: #1f2937;
            line-height: 1.5;
        }

        .entry-hash {
            margin-top: 8px;
        }

        .entry-hash code {
            font-size: 12px;
            background: #f3f4f6;
            padding: 2px 6px;
            border-radius: 4px;
            color: #6b7280;
            font-family: monospace;
        }

        .epas-changelog-group {
            margin-bottom: 24px;
        }

        .epas-changelog-group-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            background: #f9fafb;
            border-radius: 8px;
            margin-bottom: 12px;
            font-size: 16px;
            font-weight: 600;
            color: #374151;
        }

        .group-count {
            background: #3b82f6;
            color: #fff;
            font-size: 12px;
            padding: 2px 8px;
            border-radius: 12px;
        }

        /* Summary Styles */
        .epas-changelog-summary {
            margin-bottom: 32px;
        }

        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }

        .summary-modules h4 {
            font-size: 16px;
            color: #374151;
            margin-bottom: 16px;
        }

        .module-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .module-name {
            width: 150px;
            font-size: 14px;
            color: #4b5563;
        }

        .module-bar-bg {
            flex: 1;
            height: 8px;
            background: #e5e7eb;
            border-radius: 4px;
            overflow: hidden;
        }

        .module-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #3b82f6, #8b5cf6);
            border-radius: 4px;
            transition: width 0.3s;
        }

        .module-count {
            width: 40px;
            text-align: right;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
        }

        @media (max-width: 640px) {
            .entry-header { flex-direction: column; align-items: flex-start; }
            .entry-date { margin-left: 0; margin-top: 8px; }
            .module-name { width: 100px; font-size: 12px; }
        }
        </style>';
    }
}

// =========================================================================
// WORDPRESS SHORTCODES
// =========================================================================

if (function_exists('add_shortcode')) {

    /**
     * [epas_changelog] - Main changelog shortcode
     */
    add_shortcode('epas_changelog', function($atts) {
        $atts = shortcode_atts([
            'days' => 30,
            'module' => '',
            'type' => '',
            'group_by' => '',
            'per_page' => 20,
            'show_summary' => 'false',
        ], $atts);

        $params = array_filter([
            'days' => (int) $atts['days'],
            'module' => $atts['module'],
            'type' => $atts['type'],
            'per_page' => (int) $atts['per_page'],
            'group_by' => $atts['group_by'],
        ]);

        $html = EPAS_Changelog::getStyles();

        if ($atts['show_summary'] === 'true') {
            $html .= EPAS_Changelog::renderSummary($atts['days']);
        }

        $html .= EPAS_Changelog::render($params);

        return $html;
    });

    /**
     * [epas_changelog_summary] - Summary stats shortcode
     */
    add_shortcode('epas_changelog_summary', function($atts) {
        $atts = shortcode_atts([
            'days' => 30,
        ], $atts);

        $html = EPAS_Changelog::getStyles();
        $html .= EPAS_Changelog::renderSummary((int) $atts['days']);

        return $html;
    });

    /**
     * [epas_changelog_modules] - Module list shortcode
     */
    add_shortcode('epas_changelog_modules', function($atts) {
        $modules = EPAS_Changelog::fetchModules();

        if (empty($modules)) {
            return '<p>Nu s-au găsit module.</p>';
        }

        $html = EPAS_Changelog::getStyles();
        $html .= '<div class="epas-modules-list">';

        foreach ($modules as $module) {
            $html .= '<a href="?module=' . esc_attr($module['id']) . '" class="module-link">';
            $html .= '<span class="module-label">' . esc_html($module['label']) . '</span>';
            $html .= '<span class="module-count">' . $module['count'] . '</span>';
            $html .= '</a>';
        }

        $html .= '</div>';

        return $html;
    });
}

// =========================================================================
// HELPER FUNCTIONS (for use in templates)
// =========================================================================

if (!function_exists('epas_get_changelog')) {
    function epas_get_changelog(array $params = []): array {
        return EPAS_Changelog::fetch($params);
    }
}

if (!function_exists('epas_get_changelog_summary')) {
    function epas_get_changelog_summary(int $days = 30): array {
        return EPAS_Changelog::fetchSummary($days);
    }
}

if (!function_exists('epas_render_changelog')) {
    function epas_render_changelog(array $params = []): string {
        return EPAS_Changelog::getStyles() . EPAS_Changelog::render($params);
    }
}

// =========================================================================
// USAGE EXAMPLE (standalone)
// =========================================================================

/*
// Direct usage in any PHP file:

require_once 'wordpress-changelog-integration.php';

// Get raw data
$entries = EPAS_Changelog::fetch(['days' => 7, 'module' => 'seating']);

// Get summary
$summary = EPAS_Changelog::fetchSummary(30);

// Get grouped by module
$grouped = EPAS_Changelog::fetchGrouped('module', 30);

// Render HTML
echo EPAS_Changelog::getStyles();
echo EPAS_Changelog::render(['days' => 30, 'group_by' => 'module']);
*/
