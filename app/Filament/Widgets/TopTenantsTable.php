<?php

namespace App\Filament\Widgets;

use App\Models\Tenant;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Cache;

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
                    ->whereIn('id', Cache::remember('widget.top_tenants.' . now()->format('Y-m-d-H'), 300, function () {
                        return Tenant::query()
                            ->withCount('events')
                            ->orderByDesc('events_count')
                            ->limit(10)
                            ->pluck('id')
                            ->toArray();
                    }))
                    ->withCount('events')
                    ->orderByDesc('events_count')
            )
            ->columns([
                Tables\Columns\TextColumn::make('public_name')
                    ->label('Tenant')
                    ->default(fn ($record) => $record->name)
                    ->limit(25),
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
            ->searchable(false)
            ->paginated(false);
    }
}
