<?php

namespace App\Filament\Resources\Taxonomies;

use App\Filament\Resources\Taxonomies\EventTypeResource\Pages;
use App\Filament\Forms\Components\TranslatableField;
use App\Models\EventType;
use Filament\Forms;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;

class EventTypeResource extends Resource
{
    protected static ?string $model = EventType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static \UnitEnum|string|null $navigationGroup = 'Taxonomies';
    protected static ?string $navigationLabel = 'Event Types';
    protected static ?int    $navigationSort  = 20;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Event Type')
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

                    Forms\Components\Select::make('allowedEventGenres')
                        ->label('Allowed Genres')
                        ->relationship('allowedEventGenres', 'slug')
                        ->getOptionLabelFromRecordUsing(fn ($record) => $record->name['en'] ?? $record->slug)
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->columnSpanFull()
                        ->helperText('Select which genres are available for this event type'),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('name.en')
                    ->label('Event Type')
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
            'index'  => Pages\ListEventTypes::route('/'),
            'create' => Pages\CreateEventType::route('/create'),
            'edit'   => Pages\EditEventType::route('/{record}/edit'),
        ];
    }
}
