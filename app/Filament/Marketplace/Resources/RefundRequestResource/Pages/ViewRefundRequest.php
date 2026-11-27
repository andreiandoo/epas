<?php

namespace App\Filament\Marketplace\Resources\RefundRequestResource\Pages;

use App\Filament\Marketplace\Resources\RefundRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Notifications\Notification;

class ViewRefundRequest extends ViewRecord
{
    use \App\Filament\Marketplace\Concerns\TranslatesPageLabels;

    protected static string $resource = RefundRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\TextInput::make('approved_amount')
                        ->label(__('Approved Amount'))
                        ->numeric()
                        ->required()
                        ->default(fn () => $this->record->requested_amount)
                        ->prefix('RON'),
                    \Filament\Forms\Components\Textarea::make('notes')
                        ->label(__('Admin Notes')),
                ])
                ->visible(fn () => in_array($this->record->status, ['pending', 'under_review']))
                ->action(function (array $data) {
                    $this->record->approve($data['approved_amount'], $data['notes']);
                    Notification::make()
                        ->title(__('Refund Approved'))
                        ->success()
                        ->send();
                    $this->refreshFormData(['status', 'approved_amount', 'admin_notes']);
                }),

            Actions\Action::make('reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->form([
                    \Filament\Forms\Components\Textarea::make('reason')
                        ->label(__('Rejection Reason'))
                        ->required(),
                ])
                ->visible(fn () => in_array($this->record->status, ['pending', 'under_review']))
                ->requiresConfirmation()
                ->action(function (array $data) {
                    $this->record->reject($data['reason']);
                    Notification::make()
                        ->title(__('Refund Rejected'))
                        ->warning()
                        ->send();
                    $this->refreshFormData(['status', 'admin_notes']);
                }),

            Actions\Action::make('process_refund')
                ->label(__('Process Refund'))
                ->icon('heroicon-o-banknotes')
                ->color('success')
                ->form([
                    \Filament\Forms\Components\Select::make('method')
                        ->label(__('Refund Method'))
                        ->options([
                            'auto' => __('Automatic (via payment provider)'),
                            'manual' => __('Manual (mark as completed)'),
                        ])
                        ->required()
                        ->default('auto'),
                    \Filament\Forms\Components\TextInput::make('reference')
                        ->label(__('Reference Number'))
                        ->helperText(__('Required for manual refunds')),
                ])
                ->visible(fn () => $this->record->status === 'approved')
                ->action(function (array $data) {
                    if ($data['method'] === 'auto') {
                        $success = $this->record->attemptAutoRefund();
                        if (!$success) {
                            Notification::make()
                                ->title(__('Auto Refund Failed'))
                                ->body($this->record->auto_refund_error)
                                ->danger()
                                ->send();
                            return;
                        }
                    } else {
                        $this->record->markRefunded($data['reference'], auth()->id());
                    }

                    Notification::make()
                        ->title(__('Refund Processed'))
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'refund_reference', 'processed_at']);
                }),
        ];
    }
}
