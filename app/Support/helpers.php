<?php

if (! function_exists('tfield')) {
    /**
     * Safely resolve a translatable JSON field to a string using current or fallback locale.
     *
     * @param  mixed        $value   Array<string,string>|string|null
     * @param  string|null  $locale
     * @param  string|null  $fallback
     * @return string|null
     */
    function tfield($value, ?string $locale = null, ?string $fallback = null): ?string
    {
        if (is_array($value)) {
            $locale   = $locale ?: app()->getLocale();
            $fallback = $fallback ?: (config('locales.fallback') ?? config('app.fallback_locale', 'en'));

            if (isset($value[$locale]) && $value[$locale] !== '') {
                return $value[$locale];
            }
            if (isset($value[$fallback]) && $value[$fallback] !== '') {
                return $value[$fallback];
            }
            foreach ($value as $v) {
                if ($v !== null && $v !== '') {
                    return $v;
                }
            }
            return null;
        }

        return $value; // scalar or null
    }
}

/**
 * Check if a feature is enabled
 *
 * @param string $featureKey Feature key (e.g., 'microservices.whatsapp.enabled')
 * @param string|null $tenantId Tenant ID for tenant-specific checks
 * @param array $context Additional context for evaluation
 * @return bool
 */
if (!function_exists('feature_enabled')) {
    function feature_enabled(string $featureKey, ?string $tenantId = null, array $context = []): bool
    {
        $service = app(\App\Services\FeatureFlags\FeatureFlagService::class);
        return $service->isEnabled($featureKey, $tenantId, $context);
    }
}

/**
 * Check if a feature is disabled
 *
 * @param string $featureKey Feature key
 * @param string|null $tenantId Tenant ID
 * @param array $context Additional context
 * @return bool
 */
if (!function_exists('feature_disabled')) {
    function feature_disabled(string $featureKey, ?string $tenantId = null, array $context = []): bool
    {
        return !feature_enabled($featureKey, $tenantId, $context);
    }
}

/**
 * Convert a 2-letter country code to a flag emoji
 *
 * @param string $countryCode ISO 3166-1 alpha-2 country code (e.g., 'US', 'GB')
 * @return string Flag emoji or empty string
 */
if (!function_exists('country_flag')) {
    function country_flag(string $countryCode): string
    {
        if (strlen($countryCode) !== 2) {
            return '';
        }

        $countryCode = strtoupper($countryCode);

        // Convert country code to regional indicator symbols
        // A = 127462, B = 127463, etc.
        $firstLetter = ord($countryCode[0]) - ord('A') + 127462;
        $secondLetter = ord($countryCode[1]) - ord('A') + 127462;

        return mb_chr($firstLetter) . mb_chr($secondLetter);
    }
}
