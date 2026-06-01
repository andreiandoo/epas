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
                    // Same approach as /marketplace/switch-client: resolve and
                    // login the target admin in this request, so the redirect
                    // already has the correct guard state.
                    $user = auth('web')->user();
                    $admin = \App\Models\MarketplaceAdmin::where('marketplace_client_id', $this->record->id)
                        ->where(function ($q) use ($user) {
                            $q->where('email', $user->email)->orWhere('role', 'super_admin');
                        })
                        ->first();
                    if (!$admin) {
                        $admin = \App\Models\MarketplaceAdmin::create([
                            'marketplace_client_id' => $this->record->id,
                            'email' => $user->email,
                            'password' => bcrypt(uniqid('system_', true)),
                            'name' => $user->name . ' (System)',
                            'role' => 'super_admin',
                            'status' => 'active',
                            'email_verified_at' => now(),
                        ]);
                    }

                    auth('marketplace_admin')->logout();
                    auth('marketplace_admin')->login($admin);
                    session([
                        'super_admin_marketplace_client_id' => (int) $this->record->id,
                        'marketplace_is_super_admin' => true,
                        'marketplace_super_admin_user_id' => $user->id,
                    ]);
                    session()->save();

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
