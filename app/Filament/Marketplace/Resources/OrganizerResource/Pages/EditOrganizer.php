<?php

namespace App\Filament\Marketplace\Resources\OrganizerResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerResource;
use App\Filament\Marketplace\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrganizer extends EditRecord
{
    protected static string $resource = OrganizerResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            // Primary actions: ALWAYS first, aligned LEFT
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),

            // Secondary actions: pushed to the RIGHT via margin-left:auto on first one
            Actions\Action::make('login_as')
                ->label('Login as Organizer')
                ->icon('heroicon-o-arrow-right-on-rectangle')
                ->color('warning')
                ->extraAttributes(['style' => 'margin-left: auto;'])
                ->url(fn () => url('/marketplace/organizers/' . $record->id . '/login-as'), shouldOpenInNewTab: true),

            Actions\Action::make('view_events')
                ->label('View Events')
                ->icon('heroicon-o-calendar')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('index', ['organizer' => $record->id])),

            Actions\Action::make('create_event')
                ->label('Create Event')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->url(fn () => EventResource::getUrl('create', ['organizer' => $record->id])),

            Actions\Action::make('view_contract')
                ->label('Vezi Contract')
                ->icon('heroicon-o-document-text')
                ->color('primary')
                ->visible(fn () => \App\Models\OrganizerDocument::where('marketplace_organizer_id', $record->id)
                    ->where('document_type', 'organizer_contract')
                    ->exists())
                ->url(fn () => \App\Models\OrganizerDocument::where('marketplace_organizer_id', $record->id)
                    ->where('document_type', 'organizer_contract')
                    ->latest('issued_at')
                    ->first()?->download_url, shouldOpenInNewTab: true),

            Actions\Action::make('view_balance')
                ->label('View Balance')
                ->icon('heroicon-o-wallet')
                ->color('warning')
                ->url(fn () => url('/marketplace/organizers/' . $record->id . '/balance')),

            Actions\Action::make('create_payout')
                ->label('Create Payout')
                ->icon('heroicon-o-banknotes')
                ->color('info')
                ->visible(fn () => $record->available_balance > 0)
                ->url(fn () => url('/marketplace/organizers/' . $record->id . '/balance')),

            Actions\Action::make('suspend')
                ->label('Suspend Organizer')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn () => $record->status === 'active')
                ->action(function () use ($record) {
                    $record->update(['status' => 'suspended']);
                    \Filament\Notifications\Notification::make()->title('Organizer suspended')->success()->send();
                }),

            Actions\Action::make('reactivate')
                ->label('Reactivate')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->visible(fn () => $record->status === 'suspended')
                ->action(function () use ($record) {
                    $record->update(['status' => 'active']);
                    \Filament\Notifications\Notification::make()->title('Organizer reactivated')->success()->send();
                }),
        ];
    }
}
