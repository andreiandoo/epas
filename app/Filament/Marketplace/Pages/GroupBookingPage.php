<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class GroupBookingPage extends Page
{
    use HasMarketplaceContext;

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
        return static::marketplaceHasMicroservice('group-booking');
    }

}
