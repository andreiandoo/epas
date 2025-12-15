<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\ShopProductResource\Pages;
use App\Filament\Tenant\Resources\ShopProductResource\RelationManagers;
use App\Models\Shop\ShopProduct;
use App\Models\Shop\ShopCategory;
use App\Models\Shop\ShopAttribute;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ShopProductResource extends Resource
{
    protected static ?string $model = ShopProduct::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $navigationLabel = 'Shop';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Product';

    protected static ?string $pluralModelLabel = 'Products';

    protected static ?string $slug = 'shop-products';

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
            ->wherePivot('is_active', true)
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

                SC\Tabs::make('Product')
                    ->tabs([
                        SC\Tabs\Tab::make('Basic Information')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                SC\Section::make('Product Details')
                                    ->schema([
                                        Forms\Components\TextInput::make("title.{$tenantLanguage}")
                                            ->label('Product Title')
                                            ->required()
                                            ->maxLength(190)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, $get) {
                                                if ($state && !$get('slug')) {
                                                    $set('slug', Str::slug($state));
                                                }
                                            }),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(190)
                                            ->rule('alpha_dash'),

                                        Forms\Components\Select::make('category_id')
                                            ->label('Category')
                                            ->options(function () {
                                                $tenant = auth()->user()->tenant;
                                                $lang = $tenant->language ?? $tenant->locale ?? 'en';
                                                return ShopCategory::where('tenant_id', $tenant?->id)
                                                    ->where('is_visible', true)
                                                    ->get()
                                                    ->mapWithKeys(fn ($cat) => [$cat->id => $cat->name[$lang] ?? $cat->name['en'] ?? 'Unnamed']);
                                            })
                                            ->searchable()
                                            ->placeholder('Select category'),

                                        Forms\Components\Select::make('type')
                                            ->options([
                                                'physical' => 'Physical Product',
                                                'digital' => 'Digital Product',
                                            ])
                                            ->default('physical')
                                            ->required()
                                            ->live(),

                                        Forms\Components\TextInput::make('sku')
                                            ->label('SKU')
                                            ->maxLength(100)
                                            ->placeholder('Leave empty for auto-generate'),

                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'active' => 'Active',
                                                'out_of_stock' => 'Out of Stock',
                                                'discontinued' => 'Discontinued',
                                            ])
                                            ->default('draft')
                                            ->required(),
                                    ])->columns(3),

                                SC\Section::make('Description')
                                    ->schema([
                                        Forms\Components\Textarea::make("short_description.{$tenantLanguage}")
                                            ->label('Short Description')
                                            ->rows(2)
                                            ->maxLength(500),

                                        Forms\Components\RichEditor::make("description.{$tenantLanguage}")
                                            ->label('Full Description')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        SC\Tabs\Tab::make('Pricing')
                            ->icon('heroicon-o-currency-euro')
                            ->schema([
                                SC\Section::make('Prices')
                                    ->schema([
                                        Forms\Components\TextInput::make('price')
                                            ->label('Price')
                                            ->required()
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->prefix(fn ($get) => $get('currency') ?? 'RON')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Enter price (e.g., 19.99)'),

                                        Forms\Components\TextInput::make('sale_price')
                                            ->label('Sale Price')
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->prefix(fn ($get) => $get('currency') ?? 'RON')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Leave empty if not on sale'),

                                        Forms\Components\TextInput::make('cost')
                                            ->label('Cost')
                                            ->numeric()
                                            ->step(0.01)
                                            ->minValue(0)
                                            ->prefix(fn ($get) => $get('currency') ?? 'RON')
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Your cost for profit calculation'),

                                        Forms\Components\Select::make('currency')
                                            ->options([
                                                'RON' => 'RON - Romanian Leu',
                                                'EUR' => 'EUR - Euro',
                                                'USD' => 'USD - US Dollar',
                                            ])
                                            ->default('RON')
                                            ->required()
                                            ->live(),
                                    ])->columns(4),

                                SC\Section::make('Tax Settings')
                                    ->schema([
                                        Forms\Components\TextInput::make('tax_rate')
                                            ->label('Tax Rate (%)')
                                            ->numeric()
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->suffix('%')
                                            ->placeholder('Use store default'),

                                        Forms\Components\Select::make('tax_mode')
                                            ->label('Tax Mode')
                                            ->options([
                                                'included' => 'Tax Included in Price',
                                                'added_on_top' => 'Tax Added on Top',
                                            ])
                                            ->placeholder('Use store default'),
                                    ])->columns(2),
                            ]),

                        SC\Tabs\Tab::make('Inventory')
                            ->icon('heroicon-o-archive-box')
                            ->schema([
                                SC\Section::make('Stock Management')
                                    ->schema([
                                        Forms\Components\Toggle::make('track_inventory')
                                            ->label('Track Inventory')
                                            ->default(true)
                                            ->live(),

                                        Forms\Components\TextInput::make('stock_quantity')
                                            ->label('Stock Quantity')
                                            ->numeric()
                                            ->default(0)
                                            ->visible(fn ($get) => $get('track_inventory')),

                                        Forms\Components\TextInput::make('low_stock_threshold')
                                            ->label('Low Stock Alert')
                                            ->numeric()
                                            ->default(5)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Alert when stock falls below this number')
                                            ->visible(fn ($get) => $get('track_inventory')),
                                    ])->columns(3),

                                SC\Section::make('Physical Product Details')
                                    ->visible(fn ($get) => $get('type') === 'physical')
                                    ->schema([
                                        Forms\Components\TextInput::make('weight_grams')
                                            ->label('Weight (grams)')
                                            ->numeric()
                                            ->suffix('g'),

                                        SC\Grid::make(3)
                                            ->schema([
                                                Forms\Components\TextInput::make('dimensions.length')
                                                    ->label('Length (cm)')
                                                    ->numeric(),
                                                Forms\Components\TextInput::make('dimensions.width')
                                                    ->label('Width (cm)')
                                                    ->numeric(),
                                                Forms\Components\TextInput::make('dimensions.height')
                                                    ->label('Height (cm)')
                                                    ->numeric(),
                                            ]),
                                    ])->columns(2),

                                SC\Section::make('Digital Product Details')
                                    ->visible(fn ($get) => $get('type') === 'digital')
                                    ->schema([
                                        Forms\Components\TextInput::make('digital_file_url')
                                            ->label('File URL')
                                            ->url()
                                            ->maxLength(500)
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'URL to the downloadable file'),

                                        Forms\Components\TextInput::make('digital_download_limit')
                                            ->label('Download Limit')
                                            ->numeric()
                                            ->placeholder('Unlimited'),

                                        Forms\Components\TextInput::make('digital_download_expiry_days')
                                            ->label('Download Expiry (days)')
                                            ->numeric()
                                            ->placeholder('Never expires'),
                                    ])->columns(3),
                            ]),

                        SC\Tabs\Tab::make('Images')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                SC\Section::make('Product Images')
                                    ->schema([
                                        Forms\Components\FileUpload::make('image_url')
                                            ->label('Main Product Image')
                                            ->image()
                                            ->imageEditor()
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('1:1')
                                            ->imageResizeTargetWidth('800')
                                            ->imageResizeTargetHeight('800')
                                            ->disk('public')
                                            ->directory('shop-products')
                                            ->visibility('public')
                                            ->maxSize(5120)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->helperText('Drag & drop or click to upload. Recommended: 800x800px, max 5MB'),

                                        Forms\Components\FileUpload::make('gallery')
                                            ->label('Gallery Images')
                                            ->image()
                                            ->multiple()
                                            ->reorderable()
                                            ->imageEditor()
                                            ->imageResizeMode('cover')
                                            ->imageResizeTargetWidth('800')
                                            ->imageResizeTargetHeight('800')
                                            ->disk('public')
                                            ->directory('shop-products/gallery')
                                            ->visibility('public')
                                            ->maxSize(5120)
                                            ->maxFiles(10)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->helperText('Drag & drop multiple images. Max 10 images, 5MB each'),
                                    ]),
                            ]),

                        SC\Tabs\Tab::make('Variants')
                            ->icon('heroicon-o-swatch')
                            ->schema([
                                SC\Section::make('Product Attributes')
                                    ->description('Select which attributes this product uses for variants')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('attributes')
                                            ->relationship('attributes', 'slug')
                                            ->options(function () {
                                                $tenant = auth()->user()->tenant;
                                                $lang = $tenant->language ?? $tenant->locale ?? 'en';
                                                return ShopAttribute::where('tenant_id', $tenant?->id)
                                                    ->get()
                                                    ->mapWithKeys(fn ($attr) => [$attr->id => $attr->name[$lang] ?? $attr->slug]);
                                            })
                                            ->columns(3),
                                    ]),
                            ]),

                        SC\Tabs\Tab::make('Options')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                SC\Section::make('Visibility & Features')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_visible')
                                            ->label('Visible on Store')
                                            ->default(true),

                                        Forms\Components\Toggle::make('is_featured')
                                            ->label('Featured Product')
                                            ->default(false),

                                        Forms\Components\Toggle::make('reviews_enabled')
                                            ->label('Enable Reviews')
                                            ->default(true),
                                    ])->columns(3),

                                SC\Section::make('Related Products')
                                    ->schema([
                                        Forms\Components\Select::make('related_product_ids')
                                            ->label('Related Products')
                                            ->multiple()
                                            ->options(function ($record) {
                                                $tenant = auth()->user()->tenant;
                                                $lang = $tenant->language ?? $tenant->locale ?? 'en';
                                                return ShopProduct::where('tenant_id', $tenant?->id)
                                                    ->when($record, fn ($q) => $q->where('id', '!=', $record->id))
                                                    ->get()
                                                    ->mapWithKeys(fn ($p) => [$p->id => $p->title[$lang] ?? $p->slug]);
                                            })
                                            ->searchable()
                                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Leave empty to auto-suggest from same category'),
                                    ]),

                                SC\Section::make('SEO')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\TextInput::make('seo.meta_title')
                                            ->label('Meta Title')
                                            ->maxLength(70),

                                        Forms\Components\Textarea::make('seo.meta_description')
                                            ->label('Meta Description')
                                            ->rows(2)
                                            ->maxLength(160),

                                        Forms\Components\TagsInput::make('seo.keywords')
                                            ->label('Keywords'),
                                    ]),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_url')
                    ->label('Image')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://placehold.co/100x100/EEE/31343C?text=No+Image'),

                Tables\Columns\TextColumn::make("title.{$tenantLanguage}")
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(function ($state) use ($tenantLanguage) {
                        if (is_array($state)) {
                            return $state[$tenantLanguage] ?? $state['en'] ?? '-';
                        }
                        return $state ?? '-';
                    })
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'primary' => 'physical',
                        'success' => 'digital',
                    ]),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->formatStateUsing(fn ($state, $record) => number_format($state, 2) . ' ' . $record->currency)
                    ->sortable(),

                Tables\Columns\TextColumn::make('sale_price')
                    ->label('Sale')
                    ->formatStateUsing(fn ($state, $record) => $state ? number_format($state, 2) . ' ' . $record->currency : '-')
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('stock_quantity')
                    ->label('Stock')
                    ->formatStateUsing(function ($state, $record) {
                        if (!$record->track_inventory) {
                            return '∞';
                        }
                        return $state ?? 0;
                    })
                    ->color(fn ($state, $record) => match (true) {
                        !$record->track_inventory => 'gray',
                        $state <= 0 => 'danger',
                        $state <= $record->low_stock_threshold => 'warning',
                        default => 'success',
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'warning' => 'out_of_stock',
                        'danger' => 'discontinued',
                    ]),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'out_of_stock' => 'Out of Stock',
                        'discontinued' => 'Discontinued',
                    ]),
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'physical' => 'Physical',
                        'digital' => 'Digital',
                    ]),
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Category')
                    ->options(function () {
                        $tenant = auth()->user()->tenant;
                        $lang = $tenant->language ?? $tenant->locale ?? 'en';
                        return ShopCategory::where('tenant_id', $tenant?->id)
                            ->get()
                            ->mapWithKeys(fn ($cat) => [$cat->id => $cat->name[$lang] ?? $cat->name['en'] ?? 'Unnamed']);
                    })
                    ->searchable(),
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
                Tables\Filters\Filter::make('low_stock')
                    ->label('Low Stock')
                    ->query(fn (Builder $query): Builder => $query->lowStock()),
                Tables\Filters\Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder => $query->outOfStock()),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Actions\Action::make('toggle_visibility')
                    ->label(fn ($record) => $record->is_visible ? 'Hide' : 'Show')
                    ->icon(fn ($record) => $record->is_visible ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color('gray')
                    ->action(fn ($record) => $record->update(['is_visible' => !$record->is_visible])),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'active']))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('hide')
                        ->label('Hide Selected')
                        ->icon('heroicon-o-eye-slash')
                        ->color('gray')
                        ->action(fn (Collection $records) => $records->each->update(['is_visible' => false]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('show')
                        ->label('Show Selected')
                        ->icon('heroicon-o-eye')
                        ->color('primary')
                        ->action(fn (Collection $records) => $records->each->update(['is_visible' => true]))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VariantsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShopProducts::route('/'),
            'create' => Pages\CreateShopProduct::route('/create'),
            'view' => Pages\ViewShopProduct::route('/{record}'),
            'edit' => Pages\EditShopProduct::route('/{record}/edit'),
        ];
    }
}
