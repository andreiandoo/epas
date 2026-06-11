<?php

namespace App\Filament\Vendor\Resources\SaleResource\Pages;

use App\Filament\Vendor\Resources\SaleResource;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Infolists\Components\Section::make('Sale Details')->schema([
                Infolists\Components\TextEntry::make('sale_number')
                    ->label('Sale Number'),
                Infolists\Components\TextEntry::make('sold_at')
                    ->label('Date')
                    ->dateTime('d M Y H:i:s'),
                Infolists\Components\TextEntry::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),
                Infolists\Components\TextEntry::make('employee.name')
                    ->label('Employee'),
            ])->columns(4),

            Infolists\Components\Section::make('Amounts')->schema([
                Infolists\Components\TextEntry::make('subtotal_cents')
                    ->label('Subtotal')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON'),
                Infolists\Components\TextEntry::make('tax_cents')
                    ->label('Tax')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON'),
                Infolists\Components\TextEntry::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON')
                    ->weight('bold'),
                Infolists\Components\TextEntry::make('tip_cents')
                    ->label('Tip')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON'),
                Infolists\Components\TextEntry::make('commission_cents')
                    ->label('Commission')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON'),
            ])->columns(5),

            Infolists\Components\Section::make('Items')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->schema([
                            Infolists\Components\TextEntry::make('product_name')->label('Product'),
                            Infolists\Components\TextEntry::make('quantity')->label('Qty'),
                            Infolists\Components\TextEntry::make('unit_price_cents')
                                ->label('Unit Price')
                                ->formatStateUsing(fn ($state) => number_format($state / 100, 2)),
                            Infolists\Components\TextEntry::make('total_cents')
                                ->label('Total')
                                ->formatStateUsing(fn ($state) => number_format($state / 100, 2)),
                        ])->columns(4),
                ]),
        ]);
    }
}
