<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\MarketplaceVenueCategoryResource\Pages;
use App\Models\MarketplaceVenueCategory;
use App\Models\Venue;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Str;

class MarketplaceVenueCategoryResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceVenueCategory::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';
    protected static ?string $navigationLabel = 'Categorii Locații';
    protected static ?string $modelLabel = 'Categorie Locație';
    protected static ?string $pluralModelLabel = 'Categorii Locații';
    protected static ?string $navigationParentItem = 'Venues';
    protected static ?int $navigationSort = 4;

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')
                ->default($marketplace?->id),

            // Main Info
            SC\Section::make('Informații categorie')
                ->schema([
                    SC\Tabs::make('Name Translations')
                        ->tabs([
                            SC\Tabs\Tab::make('Română')
                                ->schema([
                                    Forms\Components\TextInput::make('name.ro')
                                        ->label('Nume categorie (RO)')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                            if ($state) $set('slug', Str::slug($state));
                                        }),
                                    Forms\Components\Textarea::make('description.ro')
                                        ->label('Descriere (RO)')
                                        ->rows(3),
                                ]),
                            SC\Tabs\Tab::make('English')
                                ->schema([
                                    Forms\Components\TextInput::make('name.en')
                                        ->label('Category name (EN)')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('description.en')
                                        ->label('Description (EN)')
                                        ->rows(3),
                                ]),
                        ])->columnSpanFull(),

                    Forms\Components\TextInput::make('slug')
                        ->label('Slug')
                        ->required()
                        ->maxLength(190)
                        ->unique(ignoreRecord: true)
                        ->rule('alpha_dash')
                        ->placeholder('auto-generated'),
                ])->columns(1),

            // Appearance
            SC\Section::make('Aspect')
                ->schema([
                    Forms\Components\TextInput::make('icon')
                        ->label('Icon (emoji)')
                        ->placeholder('🎭')
                        ->maxLength(10),
                    Forms\Components\ColorPicker::make('color')
                        ->label('Culoare'),
                    Forms\Components\FileUpload::make('image_url')
                        ->label('Imagine')
                        ->image()
                        ->disk('public')
                        ->directory('venue-categories')
                        ->columnSpanFull(),
                ])->columns(2),

            // Settings
            SC\Section::make('Setări')
                ->schema([
                    Forms\Components\TextInput::make('sort_order')
                        ->label('Ordine afișare')
                        ->numeric()
                        ->default(0)
                        ->minValue(0),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Activă')
                        ->default(true),
                    Forms\Components\Toggle::make('is_featured')
                        ->label('Promovată')
                        ->default(false),
                ])->columns(3),

            // Venues in this category
            SC\Section::make('Locații în această categorie')
                ->description('Selectează locațiile care aparțin acestei categorii')
                ->schema([
                    Forms\Components\Select::make('venues')
                        ->label('Locații')
                        ->relationship(
                            'venues',
                            'name',
                            fn (Builder $query) => $query->where('marketplace_client_id', static::getMarketplaceClient()?->id)
                        )
                        ->getOptionLabelFromRecordUsing(fn ($record) => ($record->getTranslation('name', 'ro') ?? $record->getTranslation('name', 'en')) . ' - ' . $record->city)
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->columnSpanFull(),
                ])
                ->visible(fn ($record) => $record !== null), // Only show when editing
        ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon')
                    ->label('')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make("name.{$lang}")
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\ColorColumn::make('color')
                    ->label('Culoare'),
                Tables\Columns\TextColumn::make('venues_count')
                    ->label('Locații')
                    ->counts('venues')
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activă')
                    ->boolean(),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Promovată')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Promovate'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketplaceVenueCategories::route('/'),
            'create' => Pages\CreateMarketplaceVenueCategory::route('/create'),
            'edit' => Pages\EditMarketplaceVenueCategory::route('/{record}/edit'),
        ];
    }
}
