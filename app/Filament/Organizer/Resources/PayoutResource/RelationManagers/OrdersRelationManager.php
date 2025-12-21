<?php

namespace App\Filament\Organizer\Resources\PayoutResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Orders Included';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Order #')
                    ->prefix('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Customer')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('total')
                    ->label('Order Total')
                    ->money('RON'),

                Tables\Columns\TextColumn::make('organizer_revenue')
                    ->label('Your Revenue')
                    ->money('RON')
                    ->color('success'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'paid', 'completed' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }
}
