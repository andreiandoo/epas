<?php

namespace App\Filament\Resources\Taxonomies;

use App\Filament\Resources\Taxonomies\EventGenreResource\Pages;
use App\Models\EventGenre;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EventGenreResource extends Resource
{
    protected static ?string $model = EventGenre::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';
    protected static \UnitEnum|string|null $navigationGroup = 'Taxonomies';
    protected static ?int $navigationSort = 11;
    protected static ?string $modelLabel = 'Event Genre';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Genre')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->required()->maxLength(190)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                            if ($state && ! $set('slug')) {
                                $set('slug', Str::slug($state));
                            }
                        }),

                    Forms\Components\TextInput::make('slug')
                        ->maxLength(190)
                        ->rule('alpha_dash')
                        ->helperText('Leave empty to auto-generate.')
                        ->unique(ignoreRecord: true),

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
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Genre')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('parent.name')->label('Parent'),
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')->relationship('parent','name')->label('Parent'),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEventGenres::route('/'),
            'create' => Pages\CreateEventGenre::route('/create'),
            'edit'   => Pages\EditEventGenre::route('/{record}/edit'),
        ];
    }
}
