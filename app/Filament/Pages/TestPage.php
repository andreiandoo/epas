<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class TestPage extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.test-page'; // NOT static!

    protected static ?string $title = 'Test Page';

    public static function canAccess(): bool
    {
        return true;
    }
}
