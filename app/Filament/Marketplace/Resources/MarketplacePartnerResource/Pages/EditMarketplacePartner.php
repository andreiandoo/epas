<?php

namespace App\Filament\Marketplace\Resources\MarketplacePartnerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplacePartnerResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplacePartner extends EditRecord
{
    protected static string $resource = MarketplacePartnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
