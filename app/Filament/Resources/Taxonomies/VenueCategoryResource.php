<?php

namespace App\Filament\Resources\Taxonomies;

use App\Filament\Resources\Taxonomies\VenueCategoryResource\Pages;
use App\Filament\Forms\Components\TranslatableField;
use App\Models\VenueCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms;
use BackedEnum;
use UnitEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;

class VenueCategoryResource extends Resource
{
    protected static ?string $model = VenueCategory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';
    protected static UnitEnum|string|null $navigationGroup = 'Taxonomies';
    protected static ?string $navigationLabel = 'Venue Categories';
    protected static ?int $navigationSort = 24;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Venue Category')
                ->schema([
                    TranslatableField::make('name', 'Name')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('slug')
                        ->maxLength(100)
                        ->unique(ignoreRecord: true)
                        ->helperText('Auto-generated from name if left empty'),
                    Forms\Components\TextInput::make('icon')
                        ->label('Icon (Emoji)')
                        ->maxLength(10)
                        ->placeholder('e.g. 🎭')
                        ->helperText('Enter an emoji icon for this category'),
                    Forms\Components\TextInput::make('sort_order')
                        ->numeric()
                        ->default(0),
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
                ->label('Category')
                ->sortable()
                ->searchable()
                ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
            Tables\Columns\TextColumn::make('name.ro')
                ->label('Name (RO)')
                ->sortable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('slug')
                ->sortable()
                ->toggleable(),
            Tables\Columns\TextColumn::make('venue_types_count')
                ->label('Types')
                ->counts('venueTypes')
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
        ->actions([
            DeleteAction::make(),
        ])
        ->bulkActions([
            BulkActionGroup::make([
                DeleteBulkAction::make(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVenueCategories::route('/'),
            'create' => Pages\CreateVenueCategory::route('/create'),
            'edit' => Pages\EditVenueCategory::route('/{record}/edit'),
        ];
    }
}
