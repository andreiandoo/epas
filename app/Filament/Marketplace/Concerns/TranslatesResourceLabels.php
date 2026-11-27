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
        return __(parent::getPluralModelLabel());
    }

    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }
}
