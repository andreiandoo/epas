<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PriceTierResource\Pages;
use App\Models\Seating\PriceTier;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Resources\Resource;
use Filament\Tables;
use Illuminate\Database\Eloquent\Builder;
use BackedEnum;

class PriceTierResource extends Resource 
{
    protected static ?string $model = PriceTier::class;
    
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-currency-dollar';
    protected static \UnitEnum|string|null $navigationGroup = 'Venues & Mapping';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Price Tiers';
    protected static ?string $modelLabel = 'Price Tier';
    protected static ?string $pluralModelLabel = 'Price Tiers';

    //protected static ?string $navigationParentItem = 'Venues';

    // protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    // protected static ?string $navigationLabel = 'Price Tiers';

    // protected static ?string $navigationGroup = 'Venues';

    // protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Section::make('Basic Information')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Display name for this pricing tier (e.g., "Standard", "VIP", "Balcony")')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('tier_code')
                            ->label('Tier Code')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->alphaNum()
                            ->helperText('Unique identifier (e.g., STD, VIP, BAL)')
                            ->columnSpan(1),

                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->prefix('$')
                            ->helperText('Enter price with up to 2 decimals (e.g., 50.00)')
                            ->columnSpan(1),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Display Color')
                            ->helperText('Color used in seating maps for visual distinction')
                            ->default('#3b82f6')
                            ->columnSpan(1),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(500)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                SC\Section::make('Additional Settings')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive tiers cannot be assigned to new seats'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first in lists'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('tier_code')
                    ->label('Code')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->placeholder('All tiers')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPriceTiers::route('/'),
            'create' => Pages\CreatePriceTier::route('/create'),
            'edit' => Pages\EditPriceTier::route('/{record}/edit'),
        ];
    }
}
