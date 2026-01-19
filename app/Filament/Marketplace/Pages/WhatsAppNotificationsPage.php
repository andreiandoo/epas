<?php

namespace App\Filament\Marketplace\Pages;

use BackedEnum;
use Filament\Pages\Page;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class WhatsAppNotificationsPage extends Page
{
    use HasMarketplaceContext;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'WhatsApp Notifications';
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'whatsapp-notifications';
    protected string $view = 'filament.marketplace.pages.whatsapp-notifications';

    public function getHeading(): string
    {
        return '';
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('whatsapp-notifications');
    }

}
