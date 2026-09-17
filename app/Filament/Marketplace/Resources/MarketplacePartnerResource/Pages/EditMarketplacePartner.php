<?php

namespace App\Filament\Marketplace\Resources\MarketplacePartnerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplacePartnerResource;
use App\Models\MarketplacePartnerDelivery;
use App\Services\Partners\PartnerWebhooks;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMarketplacePartner extends EditRecord
{
    protected static string $resource = MarketplacePartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('pingWebhook')
                ->label('Trimite test')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->visible(fn () => !empty($this->record->webhook_url) && !empty($this->record->outbound_secret))
                ->action(function () {
                    $delivery = app(PartnerWebhooks::class)->ping($this->record);

                    $sent = $delivery->status === MarketplacePartnerDelivery::STATUS_SENT;
                    Notification::make()
                        ->title($sent ? 'Partenerul a răspuns ' . $delivery->http_status : 'Testul a eșuat')
                        ->body($sent ? null : ($delivery->error ?? 'Fără răspuns'))
                        ->status($sent ? 'success' : 'danger')
                        ->send();
                }),
            Actions\Action::make('regenerateApiKey')
                ->label('Generează cheie nouă')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('Cheia actuală nu va mai funcționa. Partenerul trebuie să primească noua cheie.')
                ->action(function () {
                    MarketplacePartnerResource::notifyNewKey($this->record, $this->record->regenerateApiKey());
                }),
            Actions\DeleteAction::make(),
        ];
    }
}
