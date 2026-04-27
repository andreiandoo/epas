<?php

namespace App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\MarketplaceCustomerResource;
use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Hash;

class EditMarketplaceCustomer extends EditRecord
{
    use HasMarketplaceContext;

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
                    // Route via the active marketplace's transport so the
                    // password-change notice arrives from the marketplace's
                    // own domain — never from the system localhost mailer.
                    $marketplace = static::getMarketplaceClient()
                        ?? $customer->marketplaceClient;
                    if ($marketplace) {
                        $body = '<p>Bună ' . e($name) . ',</p>'
                            . '<p>Parola contului tău a fost modificată de un administrator.</p>'
                            . '<p>Dacă nu ai solicitat această modificare, te rugăm să contactezi echipa de suport.</p>'
                            . '<p>Cu respect,<br>Echipa de suport</p>';
                        BaseController::sendViaMarketplace(
                            $marketplace,
                            $email,
                            $name,
                            'Parola contului tău a fost modificată',
                            $body,
                            ['template_slug' => 'admin_customer_password_changed']
                        );
                    } else {
                        \Log::warning('Skipping password-change email — no marketplace context', [
                            'customer_id' => $customer->id,
                        ]);
                    }
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
