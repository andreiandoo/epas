<?php

namespace App\Filament\Resources\Shorts;

use App\Filament\Resources\Shorts\ShortCollectionResource\Pages\CreateShortCollection;
use App\Filament\Resources\Shorts\ShortCollectionResource\Pages\EditShortCollection;
use App\Filament\Resources\Shorts\ShortCollectionResource\Pages\ListShortCollections;
use App\Models\Short;
use App\Models\ShortCollection;
use BackedEnum;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Editorial curation of shorts into named rails (B7).
 *
 * Core admin only — a collection spans tenants by design ("Weekend in
 * Bucharest" is not one organiser's playlist).
 */
class ShortCollectionResource extends Resource
{
    protected static ?string $model = ShortCollection::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static UnitEnum|string|null $navigationGroup = 'Core';

    protected static ?string $navigationLabel = 'Short collections';


    /* Submeniu sub Shorts: cele patru resurse sunt un singur feature,

       iar in bara laterala aratau ca patru lucruri fara legatura. */

    protected static ?string $navigationParentItem = 'Shorts';

    protected static ?string $modelLabel = 'Collection';

    protected static ?string $pluralModelLabel = 'Short collections';

    protected static ?int $navigationSort = 52;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Collection')
                ->schema([
                    Forms\Components\TextInput::make('title')
                        ->required()
                        ->maxLength(120),

                    Forms\Components\TextInput::make('slug')
                        ->maxLength(140)
                        ->unique(ignoreRecord: true)
                        ->helperText('Left empty, it is derived from the title.'),

                    Forms\Components\Textarea::make('description')
                        ->rows(2)
                        ->columnSpanFull(),

                    Forms\Components\FileUpload::make('cover_path')
                        ->label('Cover')
                        ->image()
                        ->disk('public')
                        ->directory('shorts/collections')
                        ->columnSpanFull(),

                    Forms\Components\TextInput::make('marketplace_client_id')
                        ->label('Marketplace client ID')
                        ->numeric()
                        ->helperText('Empty = editorial, shown on every marketplace.'),

                    Forms\Components\Toggle::make('is_active')->default(true),

                    Forms\Components\TextInput::make('sort')->numeric()->default(0),
                ])
                ->columns(2),

            SC\Section::make('Shorts')
                ->description('Drag to reorder — the feed plays them in this order.')
                ->schema([
                    Forms\Components\Select::make('shorts')
                        ->relationship('shorts', 'title')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->getOptionLabelFromRecordUsing(
                            fn (Short $record) => $record->title ?: 'Short #'.$record->id
                        ),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_path')
                    ->label('Cover')
                    ->disk('public')
                    ->height(44),

                Tables\Columns\TextColumn::make('title')->searchable()->weight('bold'),

                Tables\Columns\TextColumn::make('slug')->badge()->color('gray')->toggleable(),

                Tables\Columns\TextColumn::make('shorts_count')
                    ->label('Shorts')
                    ->counts('shorts'),

                Tables\Columns\IconColumn::make('is_active')->label('Active')->boolean(),

                Tables\Columns\TextColumn::make('sort')->label('Order')->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([DeleteBulkAction::make()])
            ->defaultSort('sort');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShortCollections::route('/'),
            'create' => CreateShortCollection::route('/create'),
            'edit' => EditShortCollection::route('/{record}/edit'),
        ];
    }
}
