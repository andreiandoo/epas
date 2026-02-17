<?php

namespace App\Filament\Resources\Taxonomies;

use App\Filament\Resources\Taxonomies\EventGenreResource\Pages;
use App\Filament\Forms\Components\TranslatableField;
use App\Models\EventGenre;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class EventGenreResource extends Resource
{
    protected static ?string $model = EventGenre::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-sparkles';
    protected static UnitEnum|string|null $navigationGroup = 'Taxonomies';
    protected static ?int $navigationSort = 11;
    protected static ?string $modelLabel = 'Event Genre';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Genre')
                ->schema([
                    TranslatableField::make('name', 'Name')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('slug')
                        ->maxLength(190)
                        ->rule('alpha_dash')
                        ->helperText('Leave empty to auto-generate.')
                        ->unique(ignoreRecord: true),

                    Forms\Components\Select::make('parent_id')
                        ->label('Parent')
                        ->relationship('parent', 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record) =>
                            $record->getTranslation('name', 'en')
                                ?: $record->getTranslation('name', 'ro')
                                ?: $record->slug
                                ?? ('ID: ' . $record->id)
                        )
                        ->getSearchResultsUsing(function (string $search) {
                            return EventGenre::whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                                ->orWhere('slug', 'like', '%' . strtolower($search) . '%')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($record) => [
                                    $record->id => $record->getTranslation('name', 'en')
                                        ?: $record->getTranslation('name', 'ro')
                                        ?: $record->slug
                                        ?? ('ID: ' . $record->id),
                                ]);
                        })
                        ->searchable()
                        ->preload(),

                    TranslatableField::textarea('description', 'Description', 3)
                        ->columnSpanFull(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name.en')
                    ->label('Genre')
                    ->sortable()
                    ->searchable(query: fn (Builder $query, string $search) =>
                        $query->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($search) . '%'])
                    )
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
