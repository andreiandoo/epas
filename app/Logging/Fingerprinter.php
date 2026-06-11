<?php

namespace App\Logging;

/**
 * Generates a stable fingerprint for log records so functionally identical
 * errors that differ only in dynamic IDs/UUIDs/emails/URLs collapse to a
 * single bucket in the dashboard.
 */
class Fingerprinter
{
    private const PLACEHOLDERS = [
        // UUID v1-v5 (and v7 used by HasUuids)
        '/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i' => '<UUID>',
        // Email addresses
        '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/' => '<EMAIL>',
        // URLs (http/https)
        '/https?:\/\/[^\s"\'<>]+/i' => '<URL>',
        // IPv4 addresses
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '<IP>',
        // Long hex strings (tokens, hashes)
        '/\b[A-Fa-f0-9]{32,}\b/' => '<HEX>',
        // Bcrypt hashes ($2y$..)
        '/\$2[aby]\$\d{2}\$[\.\/A-Za-z0-9]+/' => '<BCRYPT>',
        // Quoted single-quoted PG values that are usually IDs/strings inside SQL
        "/'[^']{20,}'/" => "'<VAL>'",
        // Long numeric IDs (>=4 digits) — order IDs, event IDs, etc.
        '/\b\d{4,}\b/' => '<ID>',
        // Trailing decimals from floats in error messages
        '/\d+\.\d+/' => '<NUM>',
    ];

    public static function compute(string $message, ?string $exceptionClass = null, ?string $file = null): string
    {
        $normalized = self::normalize($message);

        return sha1(implode('|', [
            $normalized,
            $exceptionClass ?? '',
            $file ?? '',
        ]));
    }

    public static function normalize(string $message): string
    {
        $out = $message;
        foreach (self::PLACEHOLDERS as $pattern => $replacement) {
            $out = preg_replace($pattern, $replacement, $out) ?? $out;
        }
        // Collapse whitespace
        $out = preg_replace('/\s+/', ' ', $out) ?? $out;
        return trim($out);
    }
}
