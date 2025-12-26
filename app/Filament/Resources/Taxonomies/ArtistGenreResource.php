<?php

namespace App\Filament\Resources\Taxonomies;

use App\Filament\Resources\Taxonomies\ArtistGenreResource\Pages;
use App\Filament\Forms\Components\TranslatableField;
use App\Models\ArtistGenre;
use Filament\Forms;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;

class ArtistGenreResource extends Resource
{
    protected static ?string $model = ArtistGenre::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-musical-note';
    protected static \UnitEnum|string|null $navigationGroup = 'Taxonomies';
    protected static \BackedEnum|string|null $navigationLabel = 'Artist Genres';
    protected static ?int $navigationSort = 32;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Artist Genre')
                ->schema([
                    TranslatableField::make('name', 'Name')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('slug')->maxLength(190),
                    Forms\Components\Select::make('parent_id')
                        ->label('Parent')
                        ->relationship('parent', 'name->en')
                        ->searchable()->preload(),
                    TranslatableField::textarea('description', 'Description', 3)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name.en')
                    ->label('Artist Genre')
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
            'index'  => Pages\ListArtistGenres::route('/'),
            'create' => Pages\CreateArtistGenre::route('/create'),
            'edit'   => Pages\EditArtistGenre::route('/{record}/edit'),
        ];
    }
}
