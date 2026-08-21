<?php

namespace App\Filament\Marketplace\Resources\MarketplaceOrganizerTeamMemberResource\Pages;

use App\Filament\Marketplace\Resources\MarketplaceOrganizerTeamMemberResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMarketplaceOrganizerTeamMember extends EditRecord
{
    protected static string $resource = MarketplaceOrganizerTeamMemberResource::class;

    public function getTitle(): string
    {
        return 'Editare ' . ($this->record?->name ?? 'membru echipă');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back_to_organizer')
                ->label('← Înapoi la echipa organizatorului')
                ->color('gray')
                ->url(fn () => "/marketplace/organizers/{$this->record?->marketplace_organizer_id}?tab=echipa"),
            Actions\DeleteAction::make()
                ->label('Șterge membru')
                ->requiresConfirmation(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return "/marketplace/organizers/{$this->record?->marketplace_organizer_id}?tab=echipa";
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Membru actualizat')
            ->body('Modificările au fost salvate.');
    }
}
