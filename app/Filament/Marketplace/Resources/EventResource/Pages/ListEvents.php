<?php

namespace App\Filament\Marketplace\Resources\EventResource\Pages;

use App\Filament\Marketplace\Resources\EventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    protected static string $resource = EventResource::class;

    public function mount(): void
    {
        parent::mount();

        // Check for organizer query parameter and apply filter
        $organizerId = request()->query('organizer');
        if ($organizerId) {
            $this->tableFilters['marketplace_organizer_id']['value'] = $organizerId;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
