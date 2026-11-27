<?php

namespace App\Filament\Marketplace\Concerns;

use Illuminate\Contracts\Support\Htmlable;

/**
 * Titlul paginii și eticheta din meniu trec prin __() în limba operatorului.
 */
trait TranslatesPageLabels
{
    public function getTitle(): string | Htmlable
    {
        $title = parent::getTitle();

        return is_string($title) ? __($title) : $title;
    }

    public static function getNavigationLabel(): string
    {
        return __(parent::getNavigationLabel());
    }
}
