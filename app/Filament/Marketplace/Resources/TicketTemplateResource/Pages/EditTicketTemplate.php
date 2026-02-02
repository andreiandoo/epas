<?php

namespace App\Filament\Marketplace\Resources\TicketTemplateResource\Pages;

use App\Filament\Marketplace\Resources\TicketTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditTicketTemplate extends EditRecord
{
    protected static string $resource = TicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('visual_editor')
                ->label('Open Visual Editor')
                ->icon('heroicon-o-pencil-square')
                ->color('warning')
                ->url(fn () => "/tenant/ticket-customizer/{$this->record->id}/editor")
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
