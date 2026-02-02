<?php

namespace App\Filament\Marketplace\Resources\PayoutResource\Pages;

use App\Filament\Marketplace\Resources\PayoutResource;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewPayout extends ViewRecord
{
    protected static string $resource = PayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canBeApproved())
                ->action(function () {
                    $admin = Auth::guard('marketplace_admin')->user();
                    $this->record->approve($admin->id);
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('process')
                ->label('Mark Processing')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->canBeProcessed())
                ->action(function () {
                    $admin = Auth::guard('marketplace_admin')->user();
                    $this->record->markAsProcessing($admin->id);
                    $this->refreshFormData(['status']);
                }),

            Actions\Action::make('complete')
                ->label('Complete Payout')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->canBeCompleted())
                ->form([
                    Forms\Components\TextInput::make('payment_reference')
                        ->label('Payment Reference')
                        ->required()
                        ->helperText('Bank transfer reference or transaction ID'),
                    Forms\Components\Textarea::make('payment_notes')
                        ->label('Notes')
                        ->rows(2),
                ])
                ->action(function (array $data) {
                    $this->record->complete($data['payment_reference'], $data['payment_notes'] ?? null);
                    $this->refreshFormData(['status', 'payment_reference', 'payment_notes', 'completed_at']);
                }),

            Actions\Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => $this->record->canBeRejected())
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3)
                        ->helperText('Explain why this payout request is being rejected'),
                ])
                ->action(function (array $data) {
                    $admin = Auth::guard('marketplace_admin')->user();
                    $this->record->reject($admin->id, $data['reason']);
                    $this->refreshFormData(['status', 'rejection_reason', 'rejected_at']);
                }),

            Actions\Action::make('add_note')
                ->label('Add Admin Note')
                ->icon('heroicon-o-pencil-square')
                ->form([
                    Forms\Components\Textarea::make('admin_notes')
                        ->label('Admin Notes')
                        ->default(fn () => $this->record->admin_notes)
                        ->rows(3),
                ])
                ->action(function (array $data) {
                    $this->record->update(['admin_notes' => $data['admin_notes']]);
                    $this->refreshFormData(['admin_notes']);
                }),
        ];
    }
}
