<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\ShopCategoryResource\Pages;
use App\Models\Shop\ShopCategory;
use BackedEnum;
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
use Illuminate\Support\Str;

class ShopCategoryResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = ShopCategory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $navigationParentItem = 'Shop';

    protected static \UnitEnum|string|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Shop Categories';

    protected static ?string $slug = 'shop-categories';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('shop');
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make(__('Category Details'))
                    ->schema([
                        Forms\Components\TextInput::make("name.{$marketplaceLanguage}")
                            ->label(__('Category Name'))
                            ->required()
                            ->maxLength(190)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) $set('slug', Str::slug($state));
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\Textarea::make("description.{$marketplaceLanguage}")
                            ->label(__('Description'))
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('parent_id')
                            ->label(__('Parent Category'))
                            ->options(function () {
                                $marketplace = static::getMarketplaceClient();
                                $lang = $marketplace->language ?? $marketplace->locale ?? 'en';
                                return ShopCategory::where('marketplace_client_id', $marketplace?->id)
                                    ->whereNull('parent_id')
                                    ->get()
                                    ->mapWithKeys(fn ($cat) => [$cat->id => $cat->name[$lang] ?? $cat->name['en'] ?? 'Unnamed']);
                            })
                            ->searchable()
                            ->placeholder(__('None (Top-level category)')),

                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('Sort Order'))
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_visible')
                            ->label(__('Visible'))
                            ->default(true),
                    ])->columns(2),

                SC\Section::make(__('Appearance'))
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label(__('Category Image'))
                            ->image()
                            ->disk('public')
                            ->directory('shop/categories')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('icon')
                            ->label(__('Icon'))
                            ->options([
                                'heroicon-o-shopping-bag' => __('🛍️ Shopping Bag'),
                                'heroicon-o-shopping-cart' => __('🛒 Shopping Cart'),
                                'heroicon-o-gift' => __('🎁 Gift'),
                                'heroicon-o-heart' => __('❤️ Heart'),
                                'heroicon-o-star' => __('⭐ Star'),
                                'heroicon-o-sparkles' => __('✨ Sparkles'),
                                'heroicon-o-tag' => __('🏷️ Tag'),
                                'heroicon-o-cube' => __('📦 Cube/Box'),
                                'heroicon-o-truck' => __('🚚 Truck/Delivery'),
                                'heroicon-o-ticket' => __('🎫 Ticket'),
                                'heroicon-o-musical-note' => __('🎵 Music'),
                                'heroicon-o-film' => __('🎬 Film'),
                                'heroicon-o-camera' => __('📷 Camera'),
                                'heroicon-o-device-phone-mobile' => __('📱 Phone'),
                                'heroicon-o-computer-desktop' => __('💻 Computer'),
                                'heroicon-o-tv' => __('📺 TV'),
                                'heroicon-o-book-open' => __('📖 Book'),
                                'heroicon-o-academic-cap' => __('🎓 Academic'),
                                'heroicon-o-beaker' => __('🧪 Science'),
                                'heroicon-o-puzzle-piece' => __('🧩 Puzzle'),
                                'heroicon-o-face-smile' => __('😊 Smile'),
                                'heroicon-o-hand-thumb-up' => __('👍 Thumbs Up'),
                                'heroicon-o-fire' => __('🔥 Fire/Hot'),
                                'heroicon-o-bolt' => __('⚡ Bolt/Electric'),
                                'heroicon-o-sun' => __('☀️ Sun'),
                                'heroicon-o-moon' => __('🌙 Moon'),
                                'heroicon-o-cloud' => __('☁️ Cloud'),
                                'heroicon-o-globe-alt' => __('🌍 Globe'),
                                'heroicon-o-home' => __('🏠 Home'),
                                'heroicon-o-building-storefront' => __('🏪 Store'),
                            ])
                            ->searchable()
                            ->placeholder(__('Select an icon')),

                        Forms\Components\ColorPicker::make('color')
                            ->label(__('Color')),
                    ])->columns(2),

                SC\Section::make(__('SEO'))
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make("meta_title.{$marketplaceLanguage}")
                            ->label(__('Meta Title'))
                            ->maxLength(70),

                        Forms\Components\Textarea::make("meta_description.{$marketplaceLanguage}")
                            ->label(__('Meta Description'))
                            ->rows(2)
                            ->maxLength(160),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label(__('Name'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label(__('Parent'))
                    ->formatStateUsing(function ($state) use ($marketplaceLanguage) {
                        if (is_array($state)) {
                            return $state[$marketplaceLanguage] ?? $state['en'] ?? '-';
                        }
                        return $state ?? '-';
                    }),

                Tables\Columns\TextColumn::make('products_count')
                    ->label(__('Products'))
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label(__('Visible'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Order'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label(__('Visible')),
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label(__('Parent Category'))
                    ->options(function () {
                        $marketplace = static::getMarketplaceClient();
                        $lang = $marketplace->language ?? $marketplace->locale ?? 'en';
                        return ShopCategory::where('marketplace_client_id', $marketplace?->id)
                            ->whereNull('parent_id')
                            ->get()
                            ->mapWithKeys(fn ($cat) => [$cat->id => $cat->name[$lang] ?? $cat->name['en'] ?? 'Unnamed']);
                    })
                    ->placeholder(__('All')),
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
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopCategories::route('/'),
            'create' => Pages\CreateShopCategory::route('/create'),
            'edit' => Pages\EditShopCategory::route('/{record}/edit'),
        ];
    }
}
