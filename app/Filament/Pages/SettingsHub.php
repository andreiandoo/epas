<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class SettingsHub extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static UnitEnum|string|null $navigationGroup = 'Core';

    protected static ?int $navigationSort = 90;

    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'settings-hub';

    protected string $view = 'filament.pages.navigation-hub';
}
