<?php

namespace App\Services;

use App\Models\Tenant;

class ThemeService
{
    /**
     * Available fonts for the theme editor
     */
    public const AVAILABLE_FONTS = [
        // Sans-serif
        'inter' => ['name' => 'Inter', 'category' => 'sans-serif', 'weights' => [400, 500, 600, 700, 800]],
        'poppins' => ['name' => 'Poppins', 'category' => 'sans-serif', 'weights' => [400, 500, 600, 700]],
        'open-sans' => ['name' => 'Open Sans', 'category' => 'sans-serif', 'weights' => [400, 600, 700]],
        'roboto' => ['name' => 'Roboto', 'category' => 'sans-serif', 'weights' => [400, 500, 700]],
        'montserrat' => ['name' => 'Montserrat', 'category' => 'sans-serif', 'weights' => [400, 500, 600, 700]],
        'lato' => ['name' => 'Lato', 'category' => 'sans-serif', 'weights' => [400, 700]],
        // Serif
        'playfair-display' => ['name' => 'Playfair Display', 'category' => 'serif', 'weights' => [400, 700]],
        'merriweather' => ['name' => 'Merriweather', 'category' => 'serif', 'weights' => [400, 700]],
        'lora' => ['name' => 'Lora', 'category' => 'serif', 'weights' => [400, 600, 700]],
        // Display
        'oswald' => ['name' => 'Oswald', 'category' => 'sans-serif', 'weights' => [400, 500, 600, 700]],
        'raleway' => ['name' => 'Raleway', 'category' => 'sans-serif', 'weights' => [400, 500, 600, 700]],
        'bebas-neue' => ['name' => 'Bebas Neue', 'category' => 'sans-serif', 'weights' => [400]],
    ];

    /**
     * Default theme configuration
     */
    public static function getDefaultTheme(): array
    {
        return [
            'colors' => [
                'primary' => '#3B82F6',
                'primaryDark' => '#2563EB',
                'secondary' => '#1E40AF',
                'secondaryDark' => '#1E3A8A',
                'accent' => '#F59E0B',
                'background' => '#FFFFFF',
                'backgroundAlt' => '#F9FAFB',
                'surface' => '#FFFFFF',
                'text' => '#111827',
                'textMuted' => '#6B7280',
                'textOnPrimary' => '#FFFFFF',
                'border' => '#E5E7EB',
                'success' => '#10B981',
                'warning' => '#F59E0B',
                'error' => '#EF4444',
            ],
            'typography' => [
                'fontFamily' => 'inter',
                'fontFamilyHeading' => 'inter',
                'baseFontSize' => '16px',
                'lineHeight' => '1.6',
                'headings' => [
                    'h1' => ['size' => '3rem', 'weight' => '800', 'lineHeight' => '1.2'],
                    'h2' => ['size' => '2.25rem', 'weight' => '700', 'lineHeight' => '1.3'],
                    'h3' => ['size' => '1.5rem', 'weight' => '600', 'lineHeight' => '1.4'],
                    'h4' => ['size' => '1.25rem', 'weight' => '600', 'lineHeight' => '1.4'],
                ],
            ],
            'spacing' => [
                'containerMaxWidth' => '1280px',
                'sectionPadding' => '4rem',
                'cardPadding' => '1.5rem',
            ],
            'borders' => [
                'radius' => '0.5rem',
                'radiusLarge' => '1rem',
                'radiusButton' => '0.5rem',
            ],
            'shadows' => [
                'card' => '0 1px 3px rgba(0,0,0,0.1)',
                'cardHover' => '0 10px 40px rgba(0,0,0,0.15)',
                'button' => '0 1px 2px rgba(0,0,0,0.05)',
            ],
            'header' => [
                'style' => 'light',
                'sticky' => true,
                'height' => '72px',
            ],
            'buttons' => [
                'paddingX' => '1.5rem',
                'paddingY' => '0.75rem',
                'fontWeight' => '600',
            ],
        ];
    }

    /**
     * Get theme for a tenant with defaults applied
     */
    public static function getTheme(Tenant $tenant): array
    {
        $settings = $tenant->settings ?? [];
        $theme = $settings['theme'] ?? [];

        return self::mergeWithDefaults($theme);
    }

    /**
     * Merge partial theme with defaults
     */
    public static function mergeWithDefaults(array $theme): array
    {
        $defaults = self::getDefaultTheme();

        return array_replace_recursive($defaults, $theme);
    }

    /**
     * Update tenant theme
     */
    public static function updateTheme(Tenant $tenant, array $theme): void
    {
        $settings = $tenant->settings ?? [];
        $settings['theme'] = self::mergeWithDefaults($theme);
        $tenant->update(['settings' => $settings]);
    }

    /**
     * Get available fonts for the editor
     */
    public static function getAvailableFonts(): array
    {
        return self::AVAILABLE_FONTS;
    }

    /**
     * Get font options for Filament select
     */
    public static function getFontOptions(): array
    {
        $options = [];
        foreach (self::AVAILABLE_FONTS as $key => $font) {
            $options[$key] = $font['name'];
        }
        return $options;
    }

    /**
     * Validate theme configuration
     */
    public static function validate(array $theme): array
    {
        $errors = [];

        // Validate colors
        if (isset($theme['colors'])) {
            foreach ($theme['colors'] as $key => $color) {
                if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                    $errors["colors.{$key}"] = "Invalid hex color format";
                }
            }
        }

        // Validate font family
        if (isset($theme['typography']['fontFamily'])) {
            if (!array_key_exists($theme['typography']['fontFamily'], self::AVAILABLE_FONTS)) {
                $errors['typography.fontFamily'] = "Unknown font family";
            }
        }

        return $errors;
    }

    /**
     * Generate CSS variables from theme
     */
    public static function generateCssVariables(array $theme): string
    {
        $theme = self::mergeWithDefaults($theme);
        $css = ":root {\n";

        // Colors
        foreach ($theme['colors'] as $key => $value) {
            $cssKey = self::camelToKebab($key);
            $css .= "  --theme-{$cssKey}: {$value};\n";
        }

        // Typography
        $fontFamily = self::AVAILABLE_FONTS[$theme['typography']['fontFamily']]['name'] ?? 'Inter';
        $css .= "  --theme-font-family: '{$fontFamily}', system-ui, sans-serif;\n";

        $headingFont = self::AVAILABLE_FONTS[$theme['typography']['fontFamilyHeading']]['name'] ?? $fontFamily;
        $css .= "  --theme-font-family-heading: '{$headingFont}', system-ui, sans-serif;\n";

        $css .= "  --theme-base-font-size: {$theme['typography']['baseFontSize']};\n";
        $css .= "  --theme-line-height: {$theme['typography']['lineHeight']};\n";

        foreach ($theme['typography']['headings'] as $heading => $props) {
            $css .= "  --theme-{$heading}-size: {$props['size']};\n";
            $css .= "  --theme-{$heading}-weight: {$props['weight']};\n";
            $css .= "  --theme-{$heading}-line-height: {$props['lineHeight']};\n";
        }

        // Spacing
        foreach ($theme['spacing'] as $key => $value) {
            $cssKey = self::camelToKebab($key);
            $css .= "  --theme-{$cssKey}: {$value};\n";
        }

        // Borders
        foreach ($theme['borders'] as $key => $value) {
            $cssKey = self::camelToKebab($key);
            $css .= "  --theme-border-{$cssKey}: {$value};\n";
        }

        // Shadows
        foreach ($theme['shadows'] as $key => $value) {
            $cssKey = self::camelToKebab($key);
            $css .= "  --theme-shadow-{$cssKey}: {$value};\n";
        }

        // Header
        $css .= "  --theme-header-height: {$theme['header']['height']};\n";

        // Buttons
        $css .= "  --theme-button-padding-x: {$theme['buttons']['paddingX']};\n";
        $css .= "  --theme-button-padding-y: {$theme['buttons']['paddingY']};\n";
        $css .= "  --theme-button-font-weight: {$theme['buttons']['fontWeight']};\n";

        $css .= "}\n";

        return $css;
    }

    /**
     * Convert camelCase to kebab-case
     */
    private static function camelToKebab(string $string): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $string));
    }

    /**
     * Darken a hex color by percentage
     */
    public static function darkenColor(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = max(0, (int) ($r * (1 - $percent / 100)));
        $g = max(0, (int) ($g * (1 - $percent / 100)));
        $b = max(0, (int) ($b * (1 - $percent / 100)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Lighten a hex color by percentage
     */
    public static function lightenColor(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        $r = min(255, (int) ($r + (255 - $r) * $percent / 100));
        $g = min(255, (int) ($g + (255 - $g) * $percent / 100));
        $b = min(255, (int) ($b + (255 - $b) * $percent / 100));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }

    /**
     * Get full configuration for API response
     */
    public static function getFullConfig(Tenant $tenant): array
    {
        $settings = $tenant->settings ?? [];
        $theme = self::getTheme($tenant);

        return [
            'theme' => $theme,
            'branding' => [
                'logo' => $settings['branding']['logo'] ?? null,
                'favicon' => $settings['branding']['favicon'] ?? null,
            ],
            'site' => [
                'title' => $settings['site_title'] ?? $tenant->public_name ?? $tenant->name,
                'description' => $settings['site_description'] ?? '',
                'tagline' => $settings['site_tagline'] ?? '',
                'language' => $tenant->locale ?? 'en',
                'template' => $settings['site_template'] ?? 'default',
            ],
            'social' => $settings['social'] ?? [
                'facebook' => null,
                'instagram' => null,
                'twitter' => null,
                'youtube' => null,
                'tiktok' => null,
                'linkedin' => null,
            ],
            'fonts' => self::getRequiredFonts($theme),
        ];
    }

    /**
     * Get list of fonts required by the theme
     */
    private static function getRequiredFonts(array $theme): array
    {
        $fonts = [];
        $fontFamily = $theme['typography']['fontFamily'] ?? 'inter';
        $fontFamilyHeading = $theme['typography']['fontFamilyHeading'] ?? $fontFamily;

        if (isset(self::AVAILABLE_FONTS[$fontFamily])) {
            $fonts[$fontFamily] = self::AVAILABLE_FONTS[$fontFamily];
        }

        if ($fontFamilyHeading !== $fontFamily && isset(self::AVAILABLE_FONTS[$fontFamilyHeading])) {
            $fonts[$fontFamilyHeading] = self::AVAILABLE_FONTS[$fontFamilyHeading];
        }

        return $fonts;
    }
}
