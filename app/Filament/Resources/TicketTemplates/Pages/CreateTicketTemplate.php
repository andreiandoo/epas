<?php

namespace App\Filament\Resources\TicketTemplates\Pages;

use App\Filament\Resources\TicketTemplates\TicketTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketTemplate extends CreateRecord
{
    protected static string $resource = TicketTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Initialize template_data if not set
        if (!isset($data['template_data'])) {
            $data['template_data'] = [
                'meta' => [
                    'dpi' => 300,
                    'size_mm' => [
                        'w' => 80,
                        'h' => 200,
                    ],
                    'orientation' => 'portrait',
                    'bleed_mm' => 3,
                    'safe_area_mm' => 5,
                ],
                'assets' => [],
                'layers' => [],
            ];
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
