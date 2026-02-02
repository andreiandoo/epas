<?php

namespace App\Filament\Marketplace\Resources\EventSeatingLayoutResource\Pages;

use App\Filament\Marketplace\Resources\EventSeatingLayoutResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEventSeatingLayout extends EditRecord
{
    protected static string $resource = EventSeatingLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Auto-set published_at when status changes to published
        if (($data['status'] ?? null) === 'published' && !$this->getRecord()->published_at) {
            $data['published_at'] = now();
        }

        // Auto-set archived_at when status changes to archived
        if (($data['status'] ?? null) === 'archived' && !$this->getRecord()->archived_at) {
            $data['archived_at'] = now();
        }

        return $data;
    }
}
