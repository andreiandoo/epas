<?php

namespace App\Filament\Resources\ChatConversationResource\Pages;

use App\Filament\Resources\ChatConversationResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;

class ViewChatConversation extends ViewRecord
{
    protected static string $resource = ChatConversationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resolve')
                ->label('Mark Resolved')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => $this->record->status !== 'resolved')
                ->requiresConfirmation()
                ->action(fn () => $this->record->markResolved()),

            Action::make('escalate')
                ->label('Escalate')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->visible(fn () => $this->record->status === 'open')
                ->requiresConfirmation()
                ->action(fn () => $this->record->markEscalated()),
        ];
    }
}
