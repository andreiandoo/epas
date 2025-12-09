<?php

namespace App\Filament\Tenant\Pages;

use BackedEnum;
use Filament\Pages\Page;

class GroupBookingPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Group Booking';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'group-booking';
    protected string $view = 'filament.tenant.pages.group-booking';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;

        if (!$tenant) {
            return false;
        }

        return $tenant->microservices()
            ->where('microservices.slug', 'group-booking')
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function getTitle(): string
    {
        return 'Group Booking';
    }
}
