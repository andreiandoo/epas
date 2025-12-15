<?php

namespace App\Filament\Resources\Taxonomies;

use App\Filament\Resources\Taxonomies\VenueTypeResource\Pages;
use App\Filament\Forms\Components\TranslatableField;
use App\Models\VenueType;
use Filament\Forms;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;

class VenueTypeResource extends Resource
{
    protected static ?string $model = VenueType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-building-office-2';
    protected static \UnitEnum|string|null $navigationGroup = 'Taxonomies';
    protected static ?string $navigationLabel = 'Venue Types';
    protected static ?int $navigationSort = 25;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Venue Type')
                ->schema([
                    TranslatableField::make('name', 'Name')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('slug')
                        ->maxLength(190)
                        ->helperText('Auto-generated from name if left empty'),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icon (Emoji)')
                        ->maxLength(10)
                        ->placeholder('e.g. 🏟️')
                        ->helperText('Enter an emoji icon for this venue type'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
                    TranslatableField::textarea('description', 'Description', 3)
                        ->columnSpanFull(),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('icon')
                ->label('')
                ->width(40),
            Tables\Columns\TextColumn::make('name.en')
                ->label('Venue Type')
                ->sortable()
                ->searchable()
                ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
            Tables\Columns\TextColumn::make('slug')
                ->sortable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('venues_count')
                ->label('Venues')
                ->counts('venues')
                ->sortable(),
            Tables\Columns\TextColumn::make('sort_order')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            Tables\Columns\TextColumn::make('created_at')
                ->dateTime()
                ->toggleable(isToggledHiddenByDefault: true),
        ])
        ->defaultSort('sort_order')
        ->reorderable('sort_order')
        ->actions([])
        ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVenueTypes::route('/'),
            'create' => Pages\CreateVenueType::route('/create'),
            'edit' => Pages\EditVenueType::route('/{record}/edit'),
        ];
    }
}
