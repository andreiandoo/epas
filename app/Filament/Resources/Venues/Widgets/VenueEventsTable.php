<?php

namespace App\Filament\Resources\Venues\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Event;
use App\Models\Venue;

class VenueEventsTable extends BaseWidget
{
    protected static ?string $heading = 'Events at this venue';

    protected function venueId(): int
    {
        $key = request()->route('record');
        $venue = Venue::query()
            ->when(is_numeric($key), fn($q) => $q->where('id', $key), fn($q) => $q->where('slug', $key))
            ->firstOrFail();

        return (int) $venue->id;
    }

    protected function getTableQuery(): Builder
    {
        $venueId = $this->venueId();

        return Event::query()
            ->where('venue_id', $venueId)
            ->select(['id','title','status','event_date','start_time','tenant_id','venue_id'])
            ->orderByDesc('event_date');
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('title')
                ->label('Event')
                ->limit(50)
                ->formatStateUsing(function ($state, $record) {
                    if (is_array($state)) {
                        return $state['en'] ?? $state['ro'] ?? reset($state);
                    }
                    return $state;
                })
                ->url(fn($record) => \App\Filament\Resources\Events\EventResource::getUrl('edit', ['record' => $record])),
            Tables\Columns\TextColumn::make('status')->badge(),
            Tables\Columns\TextColumn::make('event_date')
                ->label('Date')
                ->date('Y-m-d')
                ->description(fn ($record) => $record->start_time ?? ''),
        ];
    }

    protected int|string|array $columnSpan = 'full';
}
