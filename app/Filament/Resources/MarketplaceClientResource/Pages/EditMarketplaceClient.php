<?php

namespace App\Filament\Resources\MarketplaceClientResource\Pages;

use App\Filament\Resources\MarketplaceClientResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceClient extends EditRecord
{
    protected static string $resource = MarketplaceClientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\Action::make('regenerate_api_key')
                ->label('Regenerate API Key')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate API Credentials')
                ->modalDescription('This will invalidate the current API key. The client will need to update their integration.')
                ->action(function () {
                    $this->record->regenerateApiCredentials();
                    $this->refreshFormData(['api_key', 'api_secret']);

                    \Filament\Notifications\Notification::make()
                        ->title('API Credentials Regenerated')
                        ->body("New API Key: {$this->record->api_key}")
                        ->success()
                        ->persistent()
                        ->send();
                }),
        ];
    }
}
