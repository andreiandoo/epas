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
                ->url(fn () => "/marketplace/ticket-customizer/{$this->record->id}/editor")
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * Preserve layers, assets, and other visual editor data when saving
     * the form. The Filament form only has template_data.meta.* fields,
     * so without this merge the layers/assets would be wiped on save.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['template_data']) && $this->record) {
            $existing = $this->record->template_data ?? [];
            $formMeta = $data['template_data']['meta'] ?? [];

            // Merge: keep existing data, overlay form meta fields on top
            $data['template_data'] = array_merge($existing, [
                'meta' => array_merge($existing['meta'] ?? [], $formMeta),
            ]);
        }

        return $data;
    }
}
