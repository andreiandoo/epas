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
                    // See switch-client route for context. We write the guard
                    // session key by hand to avoid Session::migrate(true)
                    // (triggered by auth->login()) which regenerates the
                    // session ID and destroys the old row, breaking the
                    // browser's cookie.
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

                    $guard = auth('marketplace_admin');
                    $guardSessionKey = $guard instanceof \Illuminate\Auth\SessionGuard
                        ? $guard->getName()
                        : 'login_marketplace_admin_' . sha1(\Illuminate\Auth\SessionGuard::class);

                    session()->put($guardSessionKey, $admin->getAuthIdentifier());
                    session([
                        'super_admin_marketplace_client_id' => (int) $this->record->id,
                        'marketplace_is_super_admin' => true,
                        'marketplace_super_admin_user_id' => $user->id,
                    ]);
                    $guard->setUser($admin);
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
