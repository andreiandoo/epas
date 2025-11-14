<?php

namespace App\Filament\Resources\EventSeatingLayoutResource\Pages;

use App\Filament\Resources\EventSeatingLayoutResource;
use App\Models\Seating\EventSeatingLayout;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditEventSeatingLayout extends EditRecord
{
    protected static string $resource = EventSeatingLayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('snapshot')
                ->label('Generate Snapshot')
                ->icon('heroicon-o-camera')
                ->color('info')
                ->requiresConfirmation()
                ->modalDescription('Generate a fresh geometry snapshot from the base layout. This will update the JSONB geometry data.')
                ->action(function (): void {
                    $geometryService = app(\App\Services\Seating\GeometryStorage::class);
                    $geometry = $geometryService->generateGeometrySnapshot($this->record->baseLayout);
                    $this->record->update(['geometry' => $geometry]);

                    Notification::make()
                        ->success()
                        ->title('Snapshot generated')
                        ->body('Geometry data updated successfully.')
                        ->send();
                }),

            Actions\Action::make('initializeSeats')
                ->label('Initialize Seats')
                ->icon('heroicon-o-squares-plus')
                ->color('success')
                ->requiresConfirmation()
                ->modalDescription('Create EventSeat records from the layout geometry. Run this once after attaching a layout.')
                ->visible(fn () => $this->record->eventSeats()->count() === 0)
                ->action(function (): void {
                    $inventoryRepo = app(\App\Repositories\SeatInventoryRepository::class);
                    $count = $inventoryRepo->initializeEventSeats($this->record->id);

                    Notification::make()
                        ->success()
                        ->title('Seats initialized')
                        ->body("{$count} seat records created successfully.")
                        ->send();
                }),

            Actions\Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status === 'draft')
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'published',
                        'published_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Layout published')
                        ->body('This seating layout is now visible to the public.')
                        ->send();
                }),

            Actions\Action::make('archive')
                ->label('Archive')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn () => $this->record->status !== 'archived')
                ->action(function (): void {
                    $this->record->update([
                        'status' => 'archived',
                        'archived_at' => now(),
                    ]);

                    Notification::make()
                        ->success()
                        ->title('Layout archived')
                        ->send();
                }),

            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Auto-update published_at when status changes to published
        if ($data['status'] === 'published' && $this->getRecord()->status !== 'published') {
            $data['published_at'] = now();
        }

        // Auto-update archived_at when status changes to archived
        if ($data['status'] === 'archived' && $this->getRecord()->status !== 'archived') {
            $data['archived_at'] = now();
        }

        return $data;
    }
}
