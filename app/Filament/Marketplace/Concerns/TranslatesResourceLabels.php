<?php

namespace App\Filament\Marketplace\Concerns;

/**
 * Numele resursei (singular/plural/meniu) trec prin __() în limba operatorului.
 * Cheia = textul din cod; traducerile stau în resources/lang/{locale}.json.
 */
trait TranslatesResourceLabels
{
    public static function getModelLabel(): string
    {
        return __(parent::getModelLabel());
    }

    public static function getPluralModelLabel(): string
    {
        $explicit = static::$pluralModelLabel ?? static::getPluralLabel();
        if (filled($explicit)) {
            return __($explicit);
        }

        // Pluralise the untranslated (English) label, then translate it. Filament's
        // default pluralises the already-translated label ("Locație" -> "Locațies").
        $raw = static::$modelLabel ?? static::getLabel() ?? \Filament\Support\get_model_label(static::getModel());
        $plural = \Illuminate\Support\Str::plural($raw);
        foreach ([$plural, \Illuminate\Support\Str::ucfirst($plural)] as $key) {
            if (($t = __($key)) !== $key) {
                return $t;
            }
        }

        return $plural;
    }

    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }
}
