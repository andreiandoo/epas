<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Order Details')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->disabled(),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'email')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('total')
                            ->numeric()
                            ->prefix('€')
                            ->disabled(),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                        'gray' => 'refunded',
                    ]),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
