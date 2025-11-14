<?php

namespace App\Filament\Resources\Taxonomies;

use App\Filament\Resources\Taxonomies\ArtistTypeResource\Pages;
use App\Models\ArtistType;
use Filament\Forms;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;

class ArtistTypeResource extends Resource
{
    protected static ?string $model = ArtistType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Taxonomies';
    protected static ?string $navigationLabel = 'Artist Types';
    protected static ?int $navigationSort = 31;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Artist Type')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()->maxLength(190)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, $set) => $set('slug', \Illuminate\Support\Str::slug((string) $state))),
                    Forms\Components\TextInput::make('slug')->maxLength(190),
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent')
                        ->relationship('parent', 'name')
                        ->searchable()->preload(),
                    Forms\Components\Textarea::make('description')->rows(3),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name')
                    ->label('Artist Type')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
            Tables\Columns\TextColumn::make('slug')->sortable()->toggleable(),
            Tables\Columns\TextColumn::make('parent.name')->label('Parent')->toggleable(),
            Tables\Columns\TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
        ])
        ->actions([])
        ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListArtistTypes::route('/'),
            'create' => Pages\CreateArtistType::route('/create'),
            'edit'   => Pages\EditArtistType::route('/{record}/edit'),
        ];
    }
}
