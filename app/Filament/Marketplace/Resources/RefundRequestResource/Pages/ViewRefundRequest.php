<?php

namespace App\Filament\Marketplace\Resources\RefundRequestResource\Pages;

use App\Filament\Marketplace\Resources\RefundRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewRefundRequest extends ViewRecord
{
    protected static string $resource = RefundRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\TextInput::make('approved_amount')
                        ->label('Approved Amount')
                        ->numeric()
                        ->required()
                        ->default(fn () => $this->record->requested_amount)
                        ->prefix('RON'),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label('Admin Notes'),
                ])
                ->visible(fn () => in_array($this->record->status, ['pending', 'under_review']))
                ->action(function (array $data) {
                    $this->record->approve($data['approved_amount'], $data['notes']);
                    Notification::make()
                        ->title('Refund Approved')
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'approved_amount', 'admin_notes']);
                }),

            Actions\Action::make('reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label('Rejection Reason')
                        ->required(),
                ])
                ->visible(fn () => in_array($this->record->status, ['pending', 'under_review']))
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $this->record->reject($data['reason']);
                    Notification::make()
                        ->title('Refund Rejected')
                        ->warning()
                        ->send();
                    $this->refreshFormData(['status', 'admin_notes']);
                }),

            Actions\Action::make('process_refund')
                ->label('Process Refund')
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Select::make('method')
                        ->label('Refund Method')
                        ->options([
                            'auto' => 'Automatic (via payment provider)',
                            'manual' => 'Manual (mark as completed)',
                        ])
                        ->required()
                        ->default('auto'),
                    \Filament\Forms\Components\TextInput::make('reference')
                        ->label('Reference Number')
                        ->helperText('Required for manual refunds'),
                ])
                ->visible(fn () => $this->record->status === 'approved')
                ->action(function (array $data) {
                    if ($data['method'] === 'auto') {
                        $success = $this->record->attemptAutoRefund();
                        if (!$success) {
                            Notification::make()
                                ->title('Auto Refund Failed')
                                ->body($this->record->auto_refund_error)
                                ->danger()
                                ->send();
                            return;
                        }
                    } else {
                        $this->record->markRefunded($data['reference'], auth()->id());
                    }

                    Notification::make()
                        ->title('Refund Processed')
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'refund_reference', 'processed_at']);
                }),
        ];
    }
}
