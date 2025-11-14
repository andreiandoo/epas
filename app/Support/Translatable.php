<?php

namespace App\Support;

trait Translatable
{
    protected function getTranslatableAttributes(): array
    {
        return (property_exists($this, 'translatable') && is_array($this->translatable))
            ? $this->translatable
            : [];
    }

    public function getTranslation(string $attribute, ?string $locale = null, bool $useFallback = true, ?string $fallbackLocale = null)
    {
        $this->assertTranslatableAttribute($attribute);

        $locale = $locale ?: app()->getLocale();
        $value  = $this->getAttribute($attribute);

        if (is_null($value) || is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            if (array_key_exists($locale, $value) && $value[$locale] !== null && $value[$locale] !== '') {
                return $value[$locale];
            }

            if ($useFallback) {
                $fallbackLocale = $fallbackLocale
                    ?: (config('locales.fallback') ?? config('app.fallback_locale', 'en'));

                if (array_key_exists($fallbackLocale, $value) && $value[$fallbackLocale] !== null && $value[$fallbackLocale] !== '') {
                    return $value[$fallbackLocale];
                }

                foreach ($value as $v) {
                    if ($v !== null && $v !== '') {
                        return $v;
                    }
                }
            }
        }

        return null;
    }

    public function setTranslation(string $attribute, string $locale, $translatedValue): static
    {
        $this->assertTranslatableAttribute($attribute);

        $current = (array) ($this->getAttribute($attribute) ?? []);
        $current[$locale] = $translatedValue;

        $this->setAttribute($attribute, $current);

        return $this;
    }

    public function forgetTranslation(string $attribute, string $locale): static
    {
        $this->assertTranslatableAttribute($attribute);

        $current = (array) ($this->getAttribute($attribute) ?? []);
        unset($current[$locale]);

        $this->setAttribute($attribute, $current);

        return $this;
    }

    public function hasTranslation(string $attribute, string $locale): bool
    {
        $this->assertTranslatableAttribute($attribute);

        $current = (array) ($this->getAttribute($attribute) ?? []);
        return array_key_exists($locale, $current) && $current[$locale] !== null && $current[$locale] !== '';
    }

    protected function assertTranslatableAttribute(string $attribute): void
    {
        $attrs = $this->getTranslatableAttributes();

        if (! in_array($attribute, $attrs, true)) {
            throw new \InvalidArgumentException("Attribute [$attribute] is not marked as translatable.");
        }
    }
}
