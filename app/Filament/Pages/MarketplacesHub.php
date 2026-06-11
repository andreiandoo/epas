<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class MarketplacesHub extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Marketplaces';

    protected static UnitEnum|string|null $navigationGroup = 'Tix Users';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Marketplaces';

    protected static ?string $slug = 'marketplaces-hub';

    protected string $view = 'filament.pages.navigation-hub';
}
