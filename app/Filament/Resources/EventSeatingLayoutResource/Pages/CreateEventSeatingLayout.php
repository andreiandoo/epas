<?php

namespace App\Filament\Resources\EventSeatingLayoutResource\Pages;

use App\Filament\Resources\EventSeatingLayoutResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventSeatingLayout extends CreateRecord
{
    protected static string $resource = EventSeatingLayoutResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Auto-generate geometry snapshot from base layout
        $baseLayout = \App\Models\Seating\SeatingLayout::find($data['layout_id'] ?? null);

        if ($baseLayout) {
            $geometryService = app(\App\Services\Seating\GeometryStorage::class);
            $data['json_geometry'] = $geometryService->generateGeometrySnapshot($baseLayout);
        } else {
            // If no base layout, provide empty geometry to satisfy NOT NULL constraint
            $data['json_geometry'] = json_encode([]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        // Automatically initialize event seats after creation
        $record = $this->getRecord();
        $inventoryRepo = app(\App\Repositories\SeatInventoryRepository::class);
        $inventoryRepo->initializeEventSeats($record->id);

        \Filament\Notifications\Notification::make()
            ->success()
            ->title('Seats initialized')
            ->body('Event seating layout created and seats initialized successfully.')
            ->send();
    }
}
