<?php

namespace App\Filament\Tenant\Resources\ShortResource\Pages;

use App\Filament\Tenant\Resources\ShortResource;
use App\Models\Short;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditShort extends EditRecord
{
    protected static string $resource = ShortResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Editing a published short sends it back to review: the content that was
     * approved is not the content that would now be served.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->status === Short::STATUS_PUBLISHED) {
            $data['status'] = Short::STATUS_PENDING_REVIEW;
        }

        return $data;
    }
}
