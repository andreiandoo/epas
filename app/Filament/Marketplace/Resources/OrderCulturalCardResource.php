<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrderCulturalCardResource\Pages;
use App\Models\Order;
use App\Support\MarketplaceTz;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderCulturalCardResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Order::class;
    protected static ?string $slug = 'orders-cultural-card';
    protected static ?string $navigationLabel = 'Comenzi CC';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'id';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id)
            ->whereRaw("(meta->>'payment_method') = 'card_cultural' OR (meta->>'cultural_card_surcharge' IS NOT NULL AND (meta->>'cultural_card_surcharge')::numeric > 0)");
    }

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) return null;
        return (string) static::getEloquentQuery()->count();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Nr. Comandă')
                    ->formatStateUsing(fn ($state, $record) => '#' . str_pad($state, 6, '0', STR_PAD_LEFT) . ($record->order_number ? " ({$record->order_number})" : ''))
                    ->searchable(query: fn ($query, $search) => $query->where(fn ($q) => $q->where('id', 'like', "%{$search}%")->orWhere('order_number', 'like', "%{$search}%")))
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Client')
                    ->description(fn ($record) => $record->customer_email)
                    ->searchable(['customer_name', 'customer_email']),
                Tables\Columns\TextColumn::make('event_names')
                    ->label('Eveniment')
                    ->getStateUsing(function ($record) {
                        $names = $record->tickets->pluck('event')->filter()->unique('id')->take(2)
                            ->map(fn ($e) => $e->getTranslation('title', app()->getLocale()) ?? $e->title)
                            ->implode(', ');
                        $c = $record->tickets->pluck('event_id')->unique()->count();
                        return $names . ($c > 2 ? ' +' . ($c - 2) : '');
                    })
                    ->wrap()
                    ->limit(45),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total comandă')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2) . ' ' . ($record->currency ?? 'RON'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('cultural_card_surcharge')
                    ->label('Surcharge CC')
                    ->badge()
                    ->color('info')
                    ->getStateUsing(fn ($record) => number_format((float) ($record->meta['cultural_card_surcharge'] ?? 0), 2) . ' RON')
                    ->tooltip('Surcharge Netopia pentru plata cu card cultural — revine AmBilet, nu organizatorului')
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw("(meta->>'cultural_card_surcharge')::numeric $direction NULLS LAST")),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => fn ($state) => in_array($state, ['completed', 'paid', 'confirmed']),
                        'danger' => fn ($state) => in_array($state, ['cancelled', 'failed']),
                        'gray' => fn ($state) => in_array($state, ['refunded', 'expired']),
                        'info' => 'partially_refunded',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'În așteptare', 'paid' => 'Plătită', 'confirmed' => 'Confirmată', 'completed' => 'Finalizată',
                        'cancelled' => 'Anulată', 'refunded' => 'Rambursată', 'partially_refunded' => 'Rambursată parțial',
                        'failed' => 'Eșuată', 'expired' => 'Expirată', default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d M Y H:i', timezone: MarketplaceTz::tz())
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'paid' => 'Plătită', 'confirmed' => 'Confirmată', 'completed' => 'Finalizată',
                        'cancelled' => 'Anulată', 'refunded' => 'Rambursată', 'partially_refunded' => 'Rambursată parțial',
                    ]),
            ])
            ->recordUrl(fn ($record) => url("/marketplace/orders/{$record->id}"));
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOrderCulturalCard::route('/')];
    }
}
