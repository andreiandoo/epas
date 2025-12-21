<?php

namespace App\Filament\Organizer\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentOrdersTable extends BaseWidget
{
    protected static ?string $heading = 'Recent Orders';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 1;

    public function table(Table $table): Table
    {
        $organizer = auth('organizer')->user()?->organizer;

        return $table
            ->query(
                Order::query()
                    ->where('organizer_id', $organizer?->id)
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Order #')
                    ->prefix('#'),

                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Customer')
                    ->limit(20),

                Tables\Columns\TextColumn::make('organizer_revenue')
                    ->label('Revenue')
                    ->money('RON'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid', 'completed' => 'success',
                        'pending' => 'warning',
                        'cancelled', 'refunded' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M j, H:i')
                    ->sortable(),
            ])
            ->paginated([5])
            ->defaultSort('created_at', 'desc');
    }
}
