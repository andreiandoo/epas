<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;

class CrmPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'CRM';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 8;
    protected static ?string $slug = 'crm';
    protected string $view = 'filament.marketplace.pages.crm';

    public function getHeading(): string
    {
        return '';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // CRM is tenant-specific, not applicable to marketplace panel
        return false;
    }

}
