<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\EventCategoryResource\Pages;
use App\Models\MarketplaceEventCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Models\EventType;
use Illuminate\Support\Str;

class EventCategoryResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceEventCategory::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Event Categories';

    protected static ?string $navigationParentItem = 'Events';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Event Category';

    protected static ?string $pluralModelLabel = 'Event Categories';

    protected static ?string $slug = 'event-categories';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make('Category Details')
                    ->schema([
                        SC\Tabs::make('Name Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.ro')
                                            ->label('Nume categorie (RO)')
                                            ->required()
                                            ->maxLength(190)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                                if ($state && !$get('slug')) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),
                                    ]),
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.en')
                                            ->label('Category name (EN)')
                                            ->maxLength(190),
                                    ]),
                            ])->columnSpanFull(),

                        SC\Tabs::make('Description Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\Textarea::make('description.ro')
                                            ->label('Descriere (RO)')
                                            ->rows(3),
                                    ]),
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\Textarea::make('description.en')
                                            ->label('Description (EN)')
                                            ->rows(3),
                                    ]),
                            ])->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Category')
                            ->options(function () {
                                $marketplace = static::getMarketplaceClient();
                                $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                                return MarketplaceEventCategory::where('marketplace_client_id', $marketplace?->id)
                                    ->whereNull('parent_id')
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? 'Unnamed']);
                            })
                            ->searchable()
                            ->placeholder('None (Top-level category)'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),

                        Forms\Components\TextInput::make('icon_emoji')
                            ->label('Emoji Icon')
                            ->placeholder('🎵')
                            ->maxLength(10),

                        Forms\Components\TextInput::make('icon')
                            ->label('Heroicon')
                            ->placeholder('heroicon-o-musical-note')
                            ->maxLength(100),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Color'),

                        Forms\Components\FileUpload::make('image_url')
                            ->label('Category Image')
                            ->image()
                            ->disk('public')
                            ->directory('event-categories')
                            ->visibility('public'),

                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured'),

                        Forms\Components\Select::make('event_type_ids')
                            ->label('Linked Event Types')
                            ->helperText('When this category is selected on an event, these event types will be auto-filled')
                            ->multiple()
                            ->options(function () {
                                return EventType::query()
                                    ->whereNotNull('parent_id')
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn ($type) => [
                                        $type->id => ($type->parent ? $type->parent->getTranslation('name', 'ro') . ' > ' : '')
                                            . $type->getTranslation('name', 'ro')
                                    ]);
                            })
                            ->searchable()
                            ->preload()
                            ->columnSpanFull(),

                        SC\Tabs::make('SEO Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\TextInput::make('meta_title.ro')
                                            ->label('Meta Title (RO)')
                                            ->maxLength(70),
                                        Forms\Components\Textarea::make('meta_description.ro')
                                            ->label('Meta Description (RO)')
                                            ->rows(2)
                                            ->maxLength(160),
                                    ]),
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\TextInput::make('meta_title.en')
                                            ->label('Meta Title (EN)')
                                            ->maxLength(70),
                                        Forms\Components\Textarea::make('meta_description.en')
                                            ->label('Meta Description (EN)')
                                            ->rows(2)
                                            ->maxLength(160),
                                    ]),
                            ])->columnSpanFull(),
                    ])->columns(3),
            ]) ->columns(1);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('icon_emoji')
                    ->label('')
                    ->alignCenter()
                    ->width(50),

                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->formatStateUsing(function ($state) use ($marketplaceLanguage) {
                        if (is_array($state)) {
                            return $state[$marketplaceLanguage] ?? $state['en'] ?? '-';
                        }
                        return $state ?? '-';
                    }),

                Tables\Columns\TextColumn::make('event_count')
                    ->label('Events')
                    ->sortable(),

                Tables\Columns\ColorColumn::make('color')
                    ->label('Color'),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->options(function () {
                        $marketplace = static::getMarketplaceClient();
                        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                        return MarketplaceEventCategory::where('marketplace_client_id', $marketplace?->id)
                            ->whereNull('parent_id')
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($c) => [$c->id => $c->name[$lang] ?? $c->name['en'] ?? 'Unnamed']);
                    }),
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventCategories::route('/'),
            'create' => Pages\CreateEventCategory::route('/create'),
            'edit' => Pages\EditEventCategory::route('/{record}/edit'),
        ];
    }
}
