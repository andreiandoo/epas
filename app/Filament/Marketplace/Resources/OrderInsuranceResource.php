<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\OrderInsuranceResource\Pages;
use App\Models\Order;
use App\Support\MarketplaceTz;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderInsuranceResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Order::class;
    protected static ?string $slug = 'orders-insurance';
    protected static ?string $navigationLabel = 'Asigurări';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'id';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplace?->id)
            ->whereRaw("(meta->>'insurance_amount' IS NOT NULL AND (meta->>'insurance_amount')::numeric > 0) OR (meta->>'ticket_insurance')::boolean = true");
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
                Tables\Columns\TextColumn::make('insurance_amount')
                    ->label('Asigurare')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(fn ($record) => number_format((float) ($record->meta['insurance_amount'] ?? 0), 2) . ' RON')
                    ->sortable(query: fn ($query, $direction) => $query->orderByRaw("(meta->>'insurance_amount')::numeric $direction NULLS LAST")),
                Tables\Columns\TextColumn::make('meta.payment_method')
                    ->label('Plată')
                    ->badge()
                    ->getStateUsing(fn ($record) => $record->meta['payment_method'] ?? '-'),
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
        return ['index' => Pages\ListOrderInsurance::route('/')];
    }
}
