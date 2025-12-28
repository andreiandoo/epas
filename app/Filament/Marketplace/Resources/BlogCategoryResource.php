<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\BlogCategoryResource\Pages;
use App\Models\Blog\BlogCategory;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Str;

class BlogCategoryResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = BlogCategory::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-folder';

    protected static ?string $navigationLabel = 'Blog Categories';

    protected static ?string $navigationParentItem = 'Blog';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Category';

    protected static ?string $pluralModelLabel = 'Blog Categories';

    protected static ?string $slug = 'blog-categories';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('blog');
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make('Category Details')
                    ->schema([
                        Forms\Components\TextInput::make("name.{$marketplaceLanguage}")
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

                        Forms\Components\Textarea::make("description.{$marketplaceLanguage}")
                            ->label('Description')
                            ->rows(3),

                        Forms\Components\Select::make('parent_id')
                            ->label('Parent Category')
                            ->options(function () {
                                $marketplace = static::getMarketplaceClient();
                                $lang = $marketplace->language ?? $marketplace->locale ?? 'en';
                                return BlogCategory::where('marketplace_client_id', $marketplace?->id)
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
                        Forms\Components\TextInput::make('image_url')
                            ->label('Image URL')
                            ->url()
                            ->maxLength(500),

                        Forms\Components\TextInput::make('icon')
                            ->label('Icon')
                            ->placeholder('heroicon-o-folder')
                            ->maxLength(100),

                        Forms\Components\ColorPicker::make('color')
                            ->label('Color'),
                    ])->columns(3),

                SC\Section::make('SEO')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make("meta_title.{$marketplaceLanguage}")
                            ->label('Meta Title')
                            ->maxLength(70),

                        Forms\Components\Textarea::make("meta_description.{$marketplaceLanguage}")
                            ->label('Meta Description')
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
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable(),

                Tables\Columns\TextColumn::make('parent.name')
                    ->label('Parent')
                    ->formatStateUsing(function ($state) use ($marketplaceLanguage) {
                        if (is_array($state)) {
                            return $state[$marketplaceLanguage] ?? $state['en'] ?? '-';
                        }
                        return $state ?? '-';
                    }),

                Tables\Columns\TextColumn::make('article_count')
                    ->label('Articles')
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
            ])
            ->actions([
                EditAction::make(),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogCategories::route('/'),
            'create' => Pages\CreateBlogCategory::route('/create'),
            'edit' => Pages\EditBlogCategory::route('/{record}/edit'),
        ];
    }
}
