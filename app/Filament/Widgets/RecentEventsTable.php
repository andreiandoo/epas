<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentEventsTable extends BaseWidget
{
    protected static ?string $heading = 'Recent Events';
    protected static ?int $sort = 4;
    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Event::query()->latest()->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Event')
                    ->limit(30)
                    ->searchable(),
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
            ->paginated(false);
    }
}
