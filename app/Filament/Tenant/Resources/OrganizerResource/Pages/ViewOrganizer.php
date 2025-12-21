<?php

namespace App\Filament\Tenant\Resources\OrganizerResource\Pages;

use App\Filament\Tenant\Resources\OrganizerResource;
use App\Services\Marketplace\OrganizerRegistrationService;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ViewRecord;

class ViewOrganizer extends ViewRecord
{
    protected static string $resource = OrganizerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
            Actions\Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn () => $this->record->isPendingApproval())
                ->action(function () {
                    $this->record->approve(auth()->id());
                    $this->refreshFormData(['status', 'is_verified', 'verified_at']);
                })
                ->requiresConfirmation()
                ->modalHeading('Approve Organizer')
                ->modalDescription('This organizer will be able to create events and sell tickets.'),
            Actions\Action::make('suspend')
                ->label('Suspend')
                ->icon('heroicon-o-pause')
                ->color('danger')
                ->visible(fn () => $this->record->isActive())
                ->form([
                    Forms\Components\Textarea::make('reason')
                        ->label('Suspension Reason')
                        ->required(),
                ])
                ->action(function (array $data) {
                    app(OrganizerRegistrationService::class)->suspend($this->record, $data['reason']);
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation(),
            Actions\Action::make('reactivate')
                ->label('Reactivate')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(fn () => $this->record->isSuspended())
                ->action(function () {
                    $this->record->reactivate();
                    $this->refreshFormData(['status']);
                })
                ->requiresConfirmation(),
            Actions\Action::make('refresh_stats')
                ->label('Refresh Statistics')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $this->record->refreshStatistics();
                    $this->refreshFormData(['total_events', 'total_orders', 'total_revenue', 'pending_payout']);
                }),
        ];
    }
}
