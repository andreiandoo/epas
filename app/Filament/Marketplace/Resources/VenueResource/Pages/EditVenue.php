<?php

namespace App\Filament\Marketplace\Resources\VenueResource\Pages;

use App\Filament\Marketplace\Resources\VenueResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        // No delete action for marketplace users - they cannot delete venues
        return [
            Action::make('make_partner')
                ->label('Setează ca partener')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn () => ! $this->record->is_partner)
                ->requiresConfirmation()
                ->modalHeading('Setează locația ca partener')
                ->modalDescription('Locația va fi marcată ca parteneră și va apărea la filtrul „Doar partenere”.')
                ->action(fn () => $this->makePartner()),
        ];
    }

    protected function makePartner(): void
    {
        $this->record->forceFill(['is_partner' => true])->save();

        // Keep the per-marketplace pivot flag in sync
        $marketplaceId = static::getResource()::getMarketplaceClient()?->id;
        if ($marketplaceId) {
            $this->record->marketplaceClients()->syncWithoutDetaching([
                $marketplaceId => ['is_partner' => true],
            ]);
        }

        Notification::make()
            ->title('Locația este acum parteneră')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        // Stay on the edit page after save instead of bouncing back to the list.
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
