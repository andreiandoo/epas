<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class EditEvent extends EditRecord
{
    use HasMarketplaceContext;

    protected static string $resource = EventResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Redirect hosted events to view-guest page (can't edit events you don't own)
        // Marketplace events use marketplace_client_id, not tenant_id
        $marketplace = static::getMarketplaceClient();
        if ($this->record->marketplace_client_id !== $marketplace?->id) {
            redirect(EventResource::getUrl('view-guest', ['record' => $this->record]));
        }
    }

    protected function getHeaderActions(): array
    {
        $marketplace = static::getMarketplaceClient();

        // Check if invitations microservice is active
        $hasInvitations = $marketplace?->microservices()
            ->where('microservices.slug', 'invitations')
            ->wherePivot('is_active', true)
            ->exists() ?? false;

        $actions = [];

        // Statistics button - always visible
        $actions[] = Actions\Action::make('statistics')
            ->label('Statistics')
            ->icon('heroicon-o-chart-bar')
            ->color('info')
            ->url(fn () => EventResource::getUrl('statistics', ['record' => $this->record]));

        if ($hasInvitations) {
            $actions[] = Actions\Action::make('invitations')
                ->label('Create Invitations')
                ->icon('heroicon-o-envelope')
                ->color('warning')
                ->url(fn () => route('filament.marketplace.pages.invitations') . '?event=' . $this->record->id);
        }

        $actions[] = Actions\DeleteAction::make();

        return $actions;
    }
}
