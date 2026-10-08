<?php

namespace App\Filament\Resources\MarketplaceClientResource\Pages;

use App\Filament\Resources\MarketplaceClientResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMarketplaceClient extends ViewRecord
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
                    // Delegates to \App\Support\SuperAdminMarketplaceSwitcher::switchTo().
                    // See that method for why we avoid
                    // auth->login() — TL;DR Session::migrate(true) breaks the
                    // browser cookie under our session config.
                    $user = auth('web')->user();
                    $admin = \App\Models\MarketplaceAdmin::resolveForCoreSuperAdmin((int) $this->record->id, $user);
                    abort_if(!$admin, 403, 'Contul tău de pe acest marketplace este dezactivat.');

                    \App\Support\SuperAdminMarketplaceSwitcher::switchTo($admin, (int) $this->record->id, $user);

                    return redirect('/marketplace');
                }),
            Actions\EditAction::make(),
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
