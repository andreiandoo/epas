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
                    // Delegates to switchSuperAdminToMarketplace() (defined in
                    // routes/web.php). See that function for why we avoid
                    // auth->login() — TL;DR Session::migrate(true) breaks the
                    // browser cookie under our session config.
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

                    switchSuperAdminToMarketplace($admin, (int) $this->record->id, $user);

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
