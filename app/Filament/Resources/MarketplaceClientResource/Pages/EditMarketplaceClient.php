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
            Actions\Action::make('login_to_marketplace')
                ->label('Login to Marketplace')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('success')
                ->visible(fn () => $this->record->status === 'active' && auth()->user()?->isSuperAdmin())
                ->action(function () {
                    // Set session for the target marketplace client
                    session(['super_admin_marketplace_client_id' => $this->record->id]);
                    // Clear any existing marketplace admin session to force re-login
                    auth('marketplace_admin')->logout();
                    session()->forget('marketplace_is_super_admin');

                    // Redirect to marketplace panel
                    return redirect('/marketplace');
                }),
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
