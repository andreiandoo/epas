<?php

namespace App\Filament\Organizer\Widgets;

use App\Models\Event;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingEventsTable extends BaseWidget
{
    protected static ?string $heading = 'Upcoming Events';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        $organizer = auth('organizer')->user()?->organizer;

        return $table
            ->query(
                Event::query()
                    ->where('organizer_id', $organizer?->id)
                    ->where('start_date', '>=', now())
                    ->orderBy('start_date')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Event')
                    ->limit(30)
                    ->searchable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('Date')
                    ->dateTime('M j, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'gray',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('tickets_count')
                    ->label('Tickets')
                    ->counts('tickets')
                    ->suffix(' sold'),
            ])
            ->paginated([5])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->url(fn (Event $record) => route('filament.organizer.resources.events.view', ['record' => $record]))
                    ->icon('heroicon-o-eye'),
            ]);
    }
}
