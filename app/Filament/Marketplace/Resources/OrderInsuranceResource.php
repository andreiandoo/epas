<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\OrderInsuranceResource\Pages;
use Illuminate\Database\Eloquent\Builder;

class OrderInsuranceResource extends OrderResource
{
    protected static ?string $slug = 'orders-insurance';
    protected static ?string $navigationLabel = 'Asigurări';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where(function ($q) {
                $q->whereRaw("(meta->>'insurance_amount' IS NOT NULL AND (meta->>'insurance_amount')::numeric > 0)")
                  ->orWhereRaw("(meta->>'ticket_insurance')::boolean = true");
            });
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListOrderInsurance::route('/')];
    }
}
