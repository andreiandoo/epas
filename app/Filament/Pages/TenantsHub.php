<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class TenantsHub extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationLabel = 'Tenants';

    protected static UnitEnum|string|null $navigationGroup = 'Tix Users';

    protected static ?int $navigationSort = 20;

    protected static ?string $title = 'Tenants';

    protected static ?string $slug = 'tenants-hub';

    protected string $view = 'filament.pages.navigation-hub';
}
