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
