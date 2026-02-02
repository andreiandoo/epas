<?php

namespace App\Filament\Resources\CoreCustomerResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'Event Timeline';

    protected static ?string $recordTitleAttribute = 'event_type';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('event_type')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('M j, Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'purchase' => 'success',
                        'add_to_cart' => 'warning',
                        'begin_checkout' => 'info',
                        'page_view' => 'gray',
                        'view_item' => 'gray',
                        'sign_up', 'login' => 'primary',
                        default => 'gray',
                    })
                    ->icon(fn ($state) => match ($state) {
                        'purchase' => 'heroicon-m-shopping-cart',
                        'add_to_cart' => 'heroicon-m-shopping-bag',
                        'begin_checkout' => 'heroicon-m-credit-card',
                        'page_view' => 'heroicon-m-eye',
                        'view_item' => 'heroicon-m-cursor-arrow-rays',
                        'sign_up' => 'heroicon-m-user-plus',
                        'login' => 'heroicon-m-arrow-right-on-rectangle',
                        default => 'heroicon-m-bolt',
                    }),

                Tables\Columns\TextColumn::make('conversion_value')
                    ->label('Value')
                    ->money('USD')
                    ->placeholder('-')
                    ->visible(fn ($record) => $record && $record->conversion_value > 0),

                Tables\Columns\TextColumn::make('page_url')
                    ->label('Page')
                    ->limit(40)
                    ->tooltip(fn ($state) => $state)
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('utm_source')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('device_type')
                    ->label('Device')
                    ->badge()
                    ->color('gray')
                    ->icon(fn ($state) => match ($state) {
                        'desktop' => 'heroicon-m-computer-desktop',
                        'mobile' => 'heroicon-m-device-phone-mobile',
                        'tablet' => 'heroicon-m-device-tablet',
                        default => 'heroicon-m-question-mark-circle',
                    }),

                Tables\Columns\TextColumn::make('location')
                    ->label('Location')
                    ->getStateUsing(fn ($record) => implode(', ', array_filter([
                        $record->city,
                        $record->country_code,
                    ])))
                    ->placeholder('-'),

                Tables\Columns\IconColumn::make('attribution')
                    ->label('Attribution')
                    ->getStateUsing(fn ($record) => $record->gclid ? 'google' : ($record->fbclid ? 'facebook' : ($record->ttclid ? 'tiktok' : null)))
                    ->icon(fn ($state) => match ($state) {
                        'google' => 'heroicon-m-magnifying-glass',
                        'facebook' => 'heroicon-m-chat-bubble-left-right',
                        'tiktok' => 'heroicon-m-musical-note',
                        default => null,
                    })
                    ->color(fn ($state) => match ($state) {
                        'google' => 'danger',
                        'facebook' => 'info',
                        'tiktok' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'page_view' => 'Page View',
                        'view_item' => 'View Item',
                        'add_to_cart' => 'Add to Cart',
                        'begin_checkout' => 'Begin Checkout',
                        'purchase' => 'Purchase',
                        'sign_up' => 'Sign Up',
                        'login' => 'Login',
                    ])
                    ->multiple(),

                Tables\Filters\Filter::make('purchases_only')
                    ->label('Purchases Only')
                    ->query(fn ($query) => $query->where('event_type', 'purchase')),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50])
            ->poll('30s');
    }
}
