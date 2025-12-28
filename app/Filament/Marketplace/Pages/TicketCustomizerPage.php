<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;

class TicketCustomizerPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Ticket Customizer';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 5;
    protected static ?string $slug = 'ticket-customizer';
    protected string $view = 'filament.marketplace.pages.ticket-customizer';
    protected static bool $shouldRegisterNavigation = true;

    public function getHeading(): string
    {
        return '';
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;

        if (!$tenant) {
            return false;
        }

        return $tenant->microservices()
            ->where('microservices.slug', 'ticket-customizer')
            ->wherePivot('is_active', true)
            ->exists();
    }

}
