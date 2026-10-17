<?php

namespace App\Filament\Tenant\Resources\NewsletterResource\Pages;

use App\Filament\Tenant\Resources\NewsletterResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNewsletter extends EditRecord
{
    protected static string $resource = NewsletterResource::class;

    protected function getHeaderActions(): array
    {
        return array_merge(NewsletterResource::sendActions(), [
            Actions\DeleteAction::make()->visible(fn ($record) => $record->status === 'draft'),
        ]);
    }

    // O campanie trimisă rămâne doar de citit
    protected function getFormActions(): array
    {
        return $this->getRecord()->status === 'draft' ? parent::getFormActions() : [];
    }
}
