<?php

namespace App\Filament\Resources\Tenants\Pages;

use App\Filament\Resources\Tenants\TenantResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Actions;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('reset_owner_password')
                ->label('Reset Owner Password')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->visible(fn () => $this->record->owner !== null)
                ->requiresConfirmation()
                ->form([
                    \Filament\Forms\Components\TextInput::make('new_password')
                        ->label('New Password')
                        ->password()
                        ->required()
                        ->minLength(8)
                        ->revealable(),
                    \Filament\Forms\Components\TextInput::make('new_password_confirmation')
                        ->label('Confirm New Password')
                        ->password()
                        ->required()
                        ->same('new_password')
                        ->revealable(),
                ])
                ->action(function (array $data) {
                    $owner = $this->record->owner;
                    if ($owner) {
                        $owner->password = Hash::make($data['new_password']);
                        $owner->save();

                        Notification::make()
                            ->title('Password reset successfully')
                            ->success()
                            ->body("Password for {$owner->name} has been updated.")
                            ->send();
                    }
                }),
        ];
    }
}
