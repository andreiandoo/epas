<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\OrderCulturalCardResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class OrderCulturalCardResource extends OrderResource
{
    protected static ?string $slug = 'orders-cultural-card';
    protected static ?string $navigationLabel = 'Comenzi CC';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereRaw("(meta->>'payment_method') = 'card_cultural' OR (meta->>'cultural_card_surcharge' IS NOT NULL AND (meta->>'cultural_card_surcharge')::numeric > 0)");
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOrderCulturalCard::route('/')];
    }
}
