<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ShopCategoryResource\Pages;
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
use Illuminate\Support\Str;

class ShopCategoryResource extends Resource
{
    protected static ?string $model = ShopCategory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Categories';

    protected static ?string $navigationParentItem = 'Shop';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Shop Categories';

    protected static ?string $slug = 'shop-categories';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) return false;

        return $tenant->microservices()
            ->where('slug', 'shop')
            ->wherePivot('status', 'active')
            ->exists();
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Category Details')
                    ->schema([
                        Forms\Components\TextInput::make("name.{$tenantLanguage}")
                            ->label('Category Name')
                            ->required()
                            ->maxLength(190)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) $set('slug', Str::slug($state));
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\Textarea::make("description.{$tenantLanguage}")
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Category')
                            ->options(function () {
                                $tenant = auth()->user()->tenant;
                                $lang = $tenant->language ?? $tenant->locale ?? 'en';
                                return ShopCategory::where('tenant_id', $tenant?->id)
                                    ->whereNull('parent_id')
                                    ->get()
                                    ->mapWithKeys(fn ($cat) => [$cat->id => $cat->name[$lang] ?? $cat->name['en'] ?? 'Unnamed']);
                            })
                            ->searchable()
                            ->placeholder('None (Top-level category)'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),

                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),
                    ])->columns(2),

                SC\Section::make('Appearance')
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label('Category Image')
                            ->image()
                            ->disk('public')
                            ->directory('shop/categories')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(2048)
                            ->columnSpanFull(),

                        Forms\Components\Select::make('icon')
                            ->label('Icon')
                            ->options([
                                'heroicon-o-shopping-bag' => '🛍️ Shopping Bag',
                                'heroicon-o-shopping-cart' => '🛒 Shopping Cart',
                                'heroicon-o-gift' => '🎁 Gift',
                                'heroicon-o-heart' => '❤️ Heart',
                                'heroicon-o-star' => '⭐ Star',
                                'heroicon-o-sparkles' => '✨ Sparkles',
                                'heroicon-o-tag' => '🏷️ Tag',
                                'heroicon-o-cube' => '📦 Cube/Box',
                                'heroicon-o-truck' => '🚚 Truck/Delivery',
                                'heroicon-o-ticket' => '🎫 Ticket',
                                'heroicon-o-musical-note' => '🎵 Music',
                                'heroicon-o-film' => '🎬 Film',
                                'heroicon-o-camera' => '📷 Camera',
                                'heroicon-o-device-phone-mobile' => '📱 Phone',
                                'heroicon-o-computer-desktop' => '💻 Computer',
                                'heroicon-o-tv' => '📺 TV',
                                'heroicon-o-book-open' => '📖 Book',
                                'heroicon-o-academic-cap' => '🎓 Academic',
                                'heroicon-o-beaker' => '🧪 Science',
                                'heroicon-o-puzzle-piece' => '🧩 Puzzle',
                                'heroicon-o-face-smile' => '😊 Smile',
                                'heroicon-o-hand-thumb-up' => '👍 Thumbs Up',
                                'heroicon-o-fire' => '🔥 Fire/Hot',
                                'heroicon-o-bolt' => '⚡ Bolt/Electric',
                                'heroicon-o-sun' => '☀️ Sun',
                                'heroicon-o-moon' => '🌙 Moon',
                                'heroicon-o-cloud' => '☁️ Cloud',
                                'heroicon-o-globe-alt' => '🌍 Globe',
                                'heroicon-o-home' => '🏠 Home',
                                'heroicon-o-building-storefront' => '🏪 Store',
                            ])
                            ->searchable()
                            ->placeholder('Select an icon'),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Color'),
                    ])->columns(2),

                SC\Section::make('SEO')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make("meta_title.{$tenantLanguage}")
                            ->label('Meta Title')
                            ->maxLength(70),

                        Forms\Components\Textarea::make("meta_description.{$tenantLanguage}")
                            ->label('Meta Description')
                            ->rows(2)
                            ->maxLength(160),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("name.{$tenantLanguage}")
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->formatStateUsing(function ($state) use ($tenantLanguage) {
                        if (is_array($state)) {
                            return $state[$tenantLanguage] ?? $state['en'] ?? '-';
                        }
                        return $state ?? '-';
                    }),

                Tables\Columns\TextColumn::make('products_count')
                    ->label('Products')
                    ->counts('products')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),
                Tables\Filters\SelectFilter::make('parent_id')
                    ->label('Parent Category')
                    ->options(function () {
                        $tenant = auth()->user()->tenant;
                        $lang = $tenant->language ?? $tenant->locale ?? 'en';
                        return ShopCategory::where('tenant_id', $tenant?->id)
                            ->whereNull('parent_id')
                            ->get()
                            ->mapWithKeys(fn ($cat) => [$cat->id => $cat->name[$lang] ?? $cat->name['en'] ?? 'Unnamed']);
                    })
                    ->placeholder('All'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
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
