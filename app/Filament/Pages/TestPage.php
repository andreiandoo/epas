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

    // FORCE access to always be true
    public static function canAccess(): bool
    {
        \Illuminate\Support\Facades\Log::info('=== TestPage::canAccess() called ===');
        return true;
    }

    public function mount(): void
    {
        \Illuminate\Support\Facades\Log::info('=== TestPage::mount() called ===');
    }
}
