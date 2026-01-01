<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class IntelligenceMonitor extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tv';

    protected static ?string $navigationLabel = 'Live Monitor';

    protected static ?string $title = 'Intelligence Monitor';

    protected static ?string $slug = 'intelligence-monitor';

    protected static \UnitEnum|string|null $navigationGroup = 'Platform Marketing';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.intelligence-monitor';

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }
}
