<?php

namespace App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceCustomerResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class EditMarketplaceCustomer extends EditRecord
{
    protected static string $resource = MarketplaceCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewProfile')
                ->label('Profil Client')
                ->icon('heroicon-o-user-circle')
                ->color('info')
                ->url(fn () => static::getResource()::getUrl('view', ['record' => $this->record])),
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Handle password change
        if (!empty($data['new_password'])) {
            $this->record->password = Hash::make($data['new_password']);
            $this->record->save();

            // Send notification email
            $customer = $this->record;
            $email = $customer->email;
            $name = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')) ?: $email;

            if ($email) {
                try {
                    Mail::raw(
                        "Bună {$name},\n\n" .
                        "Parola contului tău a fost modificată de un administrator.\n\n" .
                        "Dacă nu ai solicitat această modificare, te rugăm să contactezi echipa de suport.\n\n" .
                        "Cu respect,\nEchipa de suport",
                        function ($message) use ($email, $name) {
                            $message->to($email, $name)
                                ->subject('Parola contului tău a fost modificată');
                        }
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to send password change email to customer', [
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            Notification::make()
                ->title('Parola a fost schimbată')
                ->body("Clientul {$name} a fost notificat pe email.")
                ->success()
                ->send();
        }

        unset($data['new_password'], $data['new_password_confirmation']);

        return $data;
    }
}
