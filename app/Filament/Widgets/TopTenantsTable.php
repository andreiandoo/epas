<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TopTenantsTable extends BaseWidget
{
    protected static ?string $heading = 'Top Tenants';
    protected static ?int $sort = 5;
    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Tenant::query()
                    ->withCount('events')
                    ->orderByDesc('events_count')
                    ->limit(10)
            )
            ->columns([
                Tables\Columns\TextColumn::make('public_name')
                    ->label('Tenant')
                    ->default(fn ($record) => $record->name)
                    ->limit(25)
                    ->searchable(),
                Tables\Columns\TextColumn::make('events_count')
                    ->label('Events')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'suspended',
                    ]),
            ])
            ->paginated(false);
    }
}
