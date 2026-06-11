<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class OperationalHub extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Operational';

    protected static UnitEnum|string|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 80;

    protected static ?string $title = 'Operational';

    protected static ?string $slug = 'operational-hub';

    protected string $view = 'filament.pages.navigation-hub';
}
