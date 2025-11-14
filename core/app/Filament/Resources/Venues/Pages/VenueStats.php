<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Filament\Resources\Venues\VenueResource;
use App\Models\Venue;
use Filament\Resources\Pages\Page;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Actions\Action;

class VenueStats extends Page
{
    use InteractsWithRecord;

    protected static string $resource = VenueResource::class;
    protected string $view = 'filament.resources.venues.pages.venue-stats';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorize('view', $this->record);
    }

    public function getHeading(): string
    {
        return $this->record->name . ' — Statistics';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Venue')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => VenueResource::getUrl('view', ['record' => $this->record])),
        ];
    }
}
