<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;

class RecentEventsTable extends BaseWidget
{
    protected static ?string $heading = 'Recent Events';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Event::query()
                    ->whereIn('id', Cache::remember('widget.recent_events.' . now()->format('Y-m-d-H'), 300, function () {
                        return Event::query()->latest()->limit(10)->pluck('id')->toArray();
                    }))
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Event')
                    ->getStateUsing(fn ($record) => $record->getTranslation('title', 'en') ?: $record->getTranslation('title', 'ro') ?: collect($record->title)->first())
                    ->limit(30),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'published',
                        'warning' => 'draft',
                        'danger' => 'cancelled',
                        'info' => 'postponed',
                    ]),
            ])
            ->searchable(false)
            ->paginated(false);
    }
}
