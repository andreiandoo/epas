<?php

namespace App\Filament\Resources\Docs\DocResource\Pages;

use App\Filament\Resources\Docs\DocResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditDoc extends EditRecord
{
    protected static string $resource = DocResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('preview')
                ->icon('heroicon-o-eye')
                ->url(fn () => route('docs.show', $this->record->slug))
                ->openUrlInNewTab()
                ->visible(fn () => $this->record->is_public && $this->record->status === 'published'),
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        // Create version snapshot before saving changes
        if ($this->record->isDirty('content')) {
            $this->record->createVersion(auth()->id());
        }
    }
}
