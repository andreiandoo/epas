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
            // Multi-page: toggle-ul 'template_data.page_2.enabled' din form trebuie
            // pastrat, dar layers/meta pentru pagina 2 vin exclusiv din Visual Editor
            // — nu suprascriem din formul asta. Asa ca facem array_merge_recursive doar
            // pe cheia 'enabled' cu subtree-ul page_2 existent.
            $formPage2Enabled = $data['template_data']['page_2']['enabled'] ?? null;
            $existingPage2 = $existing['page_2'] ?? null;

            $merged = array_merge($existing, [
                'meta' => array_merge($existing['meta'] ?? [], $formMeta),
            ]);
            if ($formPage2Enabled !== null) {
                $merged['page_2'] = array_merge(is_array($existingPage2) ? $existingPage2 : [], [
                    'enabled' => (bool) $formPage2Enabled,
                ]);
                // Cand dezactivezi, pastram meta+layers-ul paginii 2 in caz ca reactivi
                // (nu pierzi munca; le stergi manual daca vrei).
            }
            $data['template_data'] = $merged;
        }

        return $data;
    }
}
