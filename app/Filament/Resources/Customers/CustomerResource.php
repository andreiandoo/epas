<?php

namespace App\Filament\Resources\Customers;

use App\Models\Customer;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Colors\Color;
use App\Filament\Resources\Customers\Pages\ViewCustomerStats;
use Filament\Schemas\Components as SC;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    // Filament v4 typing
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Core';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('tenant_id')
                ->label('Tenant')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->required(),

            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make('first_name')->maxLength(120),
                Forms\Components\TextInput::make('last_name')->maxLength(120),
            ]),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(190),
            Forms\Components\TextInput::make('phone')->maxLength(60),
            Forms\Components\Select::make('primary_tenant_id')
                ->label('Primary Tenant')
                ->relationship('primaryTenant', 'name')
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('tenants')
                ->label('Member of Tenants')
                ->multiple()
                ->relationship('tenants', 'name')
                ->preload()
                ->helperText('Tenants where this customer has a relationship (e.g., orders).'),
            Forms\Components\KeyValue::make('meta')
                ->keyLabel('Key')->valueLabel('Value')
                ->columnSpanFull()
                ->addable()->deletable()->reorderable(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->url(fn (\App\Models\Customer $record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')   // necesită ->orders() pe modelul Customer
                    ->sortable(),

                Tables\Columns\TextColumn::make('primaryTenant.name')
                    ->label('Primary Tenant')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('view_orders')
                    ->label('View Orders')
                    ->state('Open')
                    ->url(fn ($record) => route('filament.admin.resources.orders.index') . '?tableSearch=' . urlencode($record->email))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('stats_link')
                    ->label('Stats')
                    ->state('Open')
                    ->url(fn ($record) => static::getUrl('stats', ['record' => $record]))
                    ->openUrlInNewTab(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])      // lăsăm gol ca să evităm dependențe de Actions classes
            ->bulkActions([]);
    }


    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
            'stats'  => Pages\ViewCustomerStats::route('/{record}/stats'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount('orders')
            ->with('primaryTenant'); // pt. afișare rapidă
    }
}
