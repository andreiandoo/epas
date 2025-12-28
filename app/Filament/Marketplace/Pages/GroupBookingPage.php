<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;

class GroupBookingPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Group Booking';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'group-booking';
    protected string $view = 'filament.marketplace.pages.group-booking';

    public function getHeading(): string
    {
        return '';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Group booking is tenant-specific, not applicable to marketplace panel
        return false;
    }

}
