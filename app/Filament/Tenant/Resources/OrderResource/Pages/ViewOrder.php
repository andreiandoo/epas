<?php

namespace App\Filament\Tenant\Resources\OrderResource\Pages;

use App\Filament\Tenant\Resources\OrderResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('change_status')
                ->label('Change Status')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->form([
                    \Filament\Forms\Components\Select::make('status')
                        ->label('New Status')
                        ->options([
                            'pending' => 'Pending',
                            'paid' => 'Paid',
                            'confirmed' => 'Confirmed',
                            'cancelled' => 'Cancelled',
                            'refunded' => 'Refunded',
                        ])
                        ->default(fn () => $this->record->status)
                        ->required(),
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Reason for change (optional)')
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $oldStatus = $this->record->status;
                    $newStatus = $data['status'];

                    if ($oldStatus === $newStatus) {
                        Notification::make()
                            ->warning()
                            ->title('No change')
                            ->body('Status is already ' . $newStatus)
                            ->send();
                        return;
                    }

                    $this->record->update(['status' => $newStatus]);

                    // Log the change
                    activity('tenant')
                        ->performedOn($this->record)
                        ->withProperties([
                            'tenant_id' => $this->record->tenant_id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'reason' => $data['reason'] ?? null,
                        ])
                        ->log("Order status changed from {$oldStatus} to {$newStatus}");

                    Notification::make()
                        ->success()
                        ->title('Status updated')
                        ->body("Order status changed from {$oldStatus} to {$newStatus}")
                        ->send();
                }),

            Actions\Action::make('download_tickets')
                ->label('Descarcă biletele')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => (bool) \App\Http\Controllers\Api\TenantClient\DemoStorefrontController::ticketsPdfUrl($this->record))
                ->url(fn () => \App\Http\Controllers\Api\TenantClient\DemoStorefrontController::ticketsPdfUrl($this->record), shouldOpenInNewTab: true),

            Actions\Action::make('resend_tickets')
                ->label('Retrimite biletele pe email')
                ->icon('heroicon-o-envelope')
                ->color('gray')
                ->visible(fn () => in_array($this->record->status, ['paid', 'confirmed', 'completed'], true) && filled($this->record->customer_email))
                ->requiresConfirmation()
                ->modalHeading('Retrimite biletele')
                ->modalDescription(fn () => 'Emailul cu biletele în PDF pleacă din nou la ' . $this->record->customer_email . '.')
                ->action(function () {
                    $sent = \App\Http\Controllers\Api\TenantClient\DemoStorefrontController::sendOrderEmail($this->record->fresh(), true);
                    $note = \Filament\Notifications\Notification::make()
                        ->title($sent ? 'Biletele au fost retrimise' : 'Emailul nu a putut fi trimis')
                        ->body($sent ? $this->record->customer_email : 'Verifică adresa clientului și setările de email.');
                    ($sent ? $note->success() : $note->danger())->send();
                }),

            Actions\EditAction::make(),
        ];
    }
}
