<?php

namespace App\Filament\Marketplace\Resources\OrderResource\Pages;

use App\Filament\Marketplace\Resources\OrderResource;
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
                            'marketplace_client_id' => $this->record->tenant_id,
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

            Actions\EditAction::make(),
        ];
    }
}
