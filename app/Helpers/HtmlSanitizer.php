<?php

namespace App\Helpers;

/**
 * SECURITY FIX: HTML Sanitizer for email templates
 *
 * Allows safe HTML tags for email formatting while preventing XSS attacks.
 * This is a lightweight sanitizer - for production, consider using HTMLPurifier.
 */
class HtmlSanitizer
{
    /**
     * Allowed HTML tags for email templates
     */
    protected static array $allowedTags = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u',
        'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'a', 'span', 'div',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'hr', 'blockquote',
    ];

    /**
     * Allowed attributes for specific tags
     */
    protected static array $allowedAttributes = [
        'a' => ['href', 'title', 'target'],
        'span' => ['style'],
        'div' => ['style'],
        'p' => ['style'],
        'td' => ['style', 'colspan', 'rowspan'],
        'th' => ['style', 'colspan', 'rowspan'],
        'table' => ['style', 'border', 'cellpadding', 'cellspacing'],
    ];

    /**
     * Dangerous patterns to remove
     */
    protected static array $dangerousPatterns = [
        // Script tags
        '/<script\b[^>]*>(.*?)<\/script>/is',
        // Event handlers
        '/\s+on\w+\s*=\s*["\'][^"\']*["\']/i',
        '/\s+on\w+\s*=\s*[^\s>]+/i',
        // JavaScript URLs
        '/javascript\s*:/i',
        '/vbscript\s*:/i',
        '/data\s*:/i',
        // Expression/binding
        '/expression\s*\(/i',
        '/binding\s*:/i',
        // Style with dangerous content
        '/-moz-binding/i',
        '/behavior\s*:/i',
    ];

    /**
     * Sanitize HTML content for safe email rendering
     *
     * @param string|null $html The HTML content to sanitize
     * @return string The sanitized HTML
     */
    public static function sanitize(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Remove dangerous patterns first
        foreach (self::$dangerousPatterns as $pattern) {
            $html = preg_replace($pattern, '', $html);
        }

        // Strip tags to only allowed ones
        $allowedTagsString = '<' . implode('><', self::$allowedTags) . '>';
        $html = strip_tags($html, $allowedTagsString);

        // Clean up attributes
        $html = self::cleanAttributes($html);

        return $html;
    }

    /**
     * Remove dangerous attributes from HTML
     */
    protected static function cleanAttributes(string $html): string
    {
        // Match all tags with attributes
        return preg_replace_callback(
            '/<(\w+)(\s+[^>]*)?\/?>/i',
            function ($matches) {
                $tag = strtolower($matches[1]);
                $attributes = $matches[2] ?? '';

                if (empty($attributes)) {
                    return $matches[0];
                }

                // Get allowed attributes for this tag
                $allowed = self::$allowedAttributes[$tag] ?? [];

                // Parse and filter attributes
                $cleanedAttrs = '';
                preg_match_all('/\s+(\w+)\s*=\s*["\']([^"\']*)["\']/', $attributes, $attrMatches, PREG_SET_ORDER);

                foreach ($attrMatches as $attr) {
                    $attrName = strtolower($attr[1]);
                    $attrValue = $attr[2];

                    // Only keep allowed attributes
                    if (!in_array($attrName, $allowed)) {
                        continue;
                    }

                    // For href, validate it's a safe URL
                    if ($attrName === 'href') {
                        $attrValue = self::sanitizeUrl($attrValue);
                        if (empty($attrValue)) {
                            continue;
                        }
                    }

                    // For style, remove dangerous CSS
                    if ($attrName === 'style') {
                        $attrValue = self::sanitizeStyle($attrValue);
                    }

                    $cleanedAttrs .= ' ' . $attrName . '="' . htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8') . '"';
                }

                // Reconstruct the tag
                $selfClosing = substr($matches[0], -2) === '/>' ? ' /' : '';
                return '<' . $tag . $cleanedAttrs . $selfClosing . '>';
            },
            $html
        );
    }

    /**
     * Sanitize a URL to prevent javascript: and other dangerous schemes
     */
    protected static function sanitizeUrl(string $url): string
    {
        $url = trim($url);

        // Allow only safe URL schemes
        $safeSchemes = ['http', 'https', 'mailto', 'tel'];

        // Parse the URL
        $parsed = parse_url($url);

        // If it has a scheme, check if it's safe
        if (isset($parsed['scheme'])) {
            $scheme = strtolower($parsed['scheme']);
            if (!in_array($scheme, $safeSchemes)) {
                return '';
            }
        }

        // Check for javascript/vbscript in URL
        if (preg_match('/(javascript|vbscript|data):/i', $url)) {
            return '';
        }

        return $url;
    }

    /**
     * Sanitize CSS style attribute
     */
    protected static function sanitizeStyle(string $style): string
    {
        // Remove dangerous CSS
        $dangerousCss = [
            '/expression\s*\(/i',
            '/javascript\s*:/i',
            '/vbscript\s*:/i',
            '/-moz-binding/i',
            '/behavior\s*:/i',
            '/url\s*\(/i',  // Prevent external resources in CSS
        ];

        foreach ($dangerousCss as $pattern) {
            $style = preg_replace($pattern, '', $style);
        }

        return $style;
    }

    /**
     * Escape HTML for display (convert special chars)
     *
     * @param string|null $text Plain text to escape
     * @return string Escaped text safe for HTML display
     */
    public static function escape(?string $text): string
    {
        return htmlspecialchars($text ?? '', ENT_QUOTES, 'UTF-8');
    }

    /**
     * Convert plain text to HTML-safe with line breaks
     *
     * @param string|null $text Plain text with newlines
     * @return string HTML with <br> tags
     */
    public static function nl2br(?string $text): string
    {
        return nl2br(self::escape($text));
    }
}
