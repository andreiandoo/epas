<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class TaxonomiesHub extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Taxonomies';

    protected static UnitEnum|string|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 80;

    protected static ?string $title = 'Taxonomies';

    protected static ?string $slug = 'taxonomies-hub';

    protected string $view = 'filament.pages.navigation-hub';
}
