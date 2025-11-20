<?php

namespace App\Filament\Resources\Taxonomies;

use App\Filament\Resources\Taxonomies\EventTagResource\Pages;
use App\Models\EventTag;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class EventTagResource extends Resource
{
    protected static ?string $model = EventTag::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hashtag';
    protected static \UnitEnum|string|null $navigationGroup = 'Taxonomies';
    protected static ?int $navigationSort = 13;
    protected static ?string $modelLabel = 'Event Tag';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Tag')
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

                    Forms\Components\Textarea::make('description')->rows(3),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tag')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
                Tables\Columns\TextColumn::make('slug'),
                Tables\Columns\TextColumn::make('created_at')->since(),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEventTags::route('/'),
            'create' => Pages\CreateEventTag::route('/create'),
            'edit'   => Pages\EditEventTag::route('/{record}/edit'),
        ];
    }
}
