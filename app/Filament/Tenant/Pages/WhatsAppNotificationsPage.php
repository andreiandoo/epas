<?php

namespace App\Filament\Tenant\Pages;

use BackedEnum;
use Filament\Pages\Page;

class WhatsAppNotificationsPage extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'WhatsApp Notifications';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'whatsapp-notifications';
    protected string $view = 'filament.tenant.pages.whatsapp-notifications';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;

        if (!$tenant) {
            return false;
        }

        // Check for whatsapp or whatsapp-cloud microservice
        return $tenant->microservices()
            ->whereIn('microservices.slug', ['whatsapp', 'whatsapp-notifications', 'whatsapp-cloud'])
            ->wherePivot('is_active', true)
            ->exists();
    }

    public function getTitle(): string
    {
        return 'WhatsApp Notifications';
    }
}
