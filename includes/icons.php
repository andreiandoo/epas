<?php
/**
 * Iconite SVG comune (in locul emoticoanelor).
 * Sursa unica: assets/js/utils/icons.js (blocul JSON dintre markere).
 *
 *   <?= amb_icon('ticket', 'w-4 h-4') ?>
 *   <?= amb_icon('flag-ro', 'w-5 h-5 rounded-sm') ?>
 */

if (!function_exists('amb_icon_data')) {
    function amb_icon_data(): array
    {
        static $data = null;
        if ($data !== null) {
            return $data;
        }
        $data = ['icons' => [], 'flags' => [], 'emoji' => []];
        $src = @file_get_contents(dirname(__DIR__) . '/assets/js/utils/icons.js');
        if ($src !== false
            && ($a = strpos($src, '/*AMB_ICONS_JSON*/')) !== false
            && ($b = strpos($src, '/*END_AMB_ICONS_JSON*/')) !== false) {
            $a += strlen('/*AMB_ICONS_JSON*/');
            $decoded = json_decode(substr($src, $a, $b - $a), true);
            if (is_array($decoded)) {
                $data = array_merge($data, $decoded);
            }
        }
        return $data;
    }
}

if (!function_exists('amb_icon')) {
    function amb_icon(string $name, string $class = 'w-4 h-4'): string
    {
        $d = amb_icon_data();
        $name = str_replace("\u{FE0F}", '', $name);
        $attrs = ' class="amb-icon ' . htmlspecialchars($class, ENT_QUOTES) . '" aria-hidden="true" focusable="false"';

        if (strpos($name, 'flag-') === 0 && isset($d['flags'][substr($name, 5)])) {
            return '<svg viewBox="0 0 24 24" preserveAspectRatio="none"' . $attrs . '>' . $d['flags'][substr($name, 5)] . '</svg>';
        }
        $inner = $d['icons'][$name] ?? null;
        if ($inner === null && isset($d['emoji'][$name])) {
            return amb_icon($d['emoji'][$name], $class);
        }
        $inner = $inner ?? ($d['icons']['ticket'] ?? '');

        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"' . $attrs . '>' . $inner . '</svg>';
    }
}
