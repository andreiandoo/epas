<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\BlogArticleResource\Pages;
use App\Models\Blog\BlogArticle;
use App\Models\Blog\BlogCategory;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class BlogArticleResource extends Resource
{
    protected static ?string $model = BlogArticle::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-newspaper';

    protected static ?string $navigationLabel = 'Blog';

    public static function getNavigationLabel(): string
    {
        return __('Blog');
    }

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Article';

    public static function getModelLabel(): string
    {
        return __('Article');
    }

    protected static ?string $pluralModelLabel = 'Blog Articles';

    public static function getPluralModelLabel(): string
    {
        return __('Blog Articles');
    }

    protected static ?string $slug = 'blog-articles';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Only show if tenant has blog microservice active
        $tenant = auth()->user()->tenant;
        if (!$tenant) return false;

        return $tenant->microservices()
            ->where('slug', 'blog')
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

                // Two-column layout: Left (2/3) and Right (1/3)
                SC\Grid::make(3)
                    ->schema([
                        // LEFT COLUMN (2/3 width)
                        SC\Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                // Article Content Section
                                SC\Section::make(__('Article Content'))
                                    ->schema([
                                        Forms\Components\TextInput::make("title.{$tenantLanguage}")
                                            ->label('Title')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) use ($tenantLanguage) {
                                                if ($state) {
                                                    $set('slug', Str::slug($state));
                                                    // Auto-populate SEO if empty
                                                    if (!$get("meta_title.{$tenantLanguage}")) {
                                                        $set("meta_title.{$tenantLanguage}", Str::limit($state, 60));
                                                    }
                                                    if (!$get("og_title.{$tenantLanguage}")) {
                                                        $set("og_title.{$tenantLanguage}", Str::limit($state, 60));
                                                    }
                                                }
                                            }),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->rule('alpha_dash'),

                                        Forms\Components\TextInput::make("subtitle.{$tenantLanguage}")
                                            ->label(__('Subtitle'))
                                            ->maxLength(255)
                                            ->columnSpanFull(),

                                        Forms\Components\Textarea::make("excerpt.{$tenantLanguage}")
                                            ->label(__('Excerpt'))
                                            ->rows(2)
                                            ->helperText(__('Short summary for previews and SEO'))
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) use ($tenantLanguage) {
                                                if ($state) {
                                                    // Auto-populate meta description if empty
                                                    if (!$get("meta_description.{$tenantLanguage}")) {
                                                        $set("meta_description.{$tenantLanguage}", Str::limit($state, 155));
                                                    }
                                                    if (!$get("og_description.{$tenantLanguage}")) {
                                                        $set("og_description.{$tenantLanguage}", Str::limit($state, 155));
                                                    }
                                                }
                                            })
                                            ->columnSpanFull(),

                                        Forms\Components\RichEditor::make("content.{$tenantLanguage}")
                                            ->label('Content')
                                            ->required()
                                            ->toolbarButtons([
                                                'blockquote',
                                                'bold',
                                                'bulletList',
                                                'codeBlock',
                                                'h2',
                                                'h3',
                                                'italic',
                                                'link',
                                                'orderedList',
                                                'redo',
                                                'strike',
                                                'underline',
                                                'undo',
                                            ])
                                            ->columnSpanFull(),
                                    ])->columns(2),

                                // SEO Section
                                SC\Section::make(__('SEO & Meta Tags'))
                                    ->description(__('Search engine optimization settings'))
                                    ->schema([
                                        SC\Tabs::make('SEO Tabs')
                                            ->tabs([
                                                SC\Tabs\Tab::make(__('Basic SEO'))
                                                    ->schema([
                                                        Forms\Components\TextInput::make("meta_title.{$tenantLanguage}")
                                                            ->label('Meta Title')
                                                            ->maxLength(60)
                                                            ->helperText(__('Recommended: 50-60 characters. Auto-fills from title.')),

                                                        Forms\Components\Textarea::make("meta_description.{$tenantLanguage}")
                                                            ->label('Meta Description')
                                                            ->rows(2)
                                                            ->maxLength(160)
                                                            ->helperText(__('Recommended: 150-160 characters. Auto-fills from excerpt.')),

                                                        Forms\Components\TextInput::make('canonical_url')
                                                            ->label(__('Canonical URL'))
                                                            ->url()
                                                            ->maxLength(500)
                                                            ->helperText(__('Leave empty to use the article URL')),

                                                        Forms\Components\Toggle::make('no_index')
                                                            ->label(__('Hide from Search Engines'))
                                                            ->helperText(__('When enabled, search engines will not index this article')),
                                                    ])->columns(1),

                                                SC\Tabs\Tab::make('Open Graph')
                                                    ->schema([
                                                        Forms\Components\TextInput::make("og_title.{$tenantLanguage}")
                                                            ->label(__('OG Title'))
                                                            ->maxLength(60)
                                                            ->helperText(__('Title for social media sharing')),

                                                        Forms\Components\Textarea::make("og_description.{$tenantLanguage}")
                                                            ->label(__('OG Description'))
                                                            ->rows(2)
                                                            ->maxLength(200)
                                                            ->helperText(__('Description for social media sharing')),

                                                        Forms\Components\FileUpload::make('og_image_url')
                                                            ->label(__('OG Image'))
                                                            ->image()
                                                            ->disk('public')
                                                            ->directory('blog-og-images')
                                                            ->imageResizeMode('cover')
                                                            ->imageCropAspectRatio('1.91:1')
                                                            ->imageResizeTargetWidth('1200')
                                                            ->imageResizeTargetHeight('630')
                                                            ->helperText(__('Image for social sharing (1200x630px recommended)')),

                                                        Forms\Components\Select::make('twitter_card')
                                                            ->label(__('Twitter Card Type'))
                                                            ->options([
                                                                'summary' => __('Summary'),
                                                                'summary_large_image' => __('Summary with Large Image'),
                                                            ])
                                                            ->default('summary_large_image'),
                                                    ])->columns(1),

                                                SC\Tabs\Tab::make('Schema.org')
                                                    ->schema([
                                                        Forms\Components\Select::make('schema_markup.type')
                                                            ->label(__('Schema Type'))
                                                            ->options([
                                                                'Article' => __('Article'),
                                                                'BlogPosting' => __('Blog Posting'),
                                                                'NewsArticle' => __('News Article'),
                                                                'TechArticle' => __('Tech Article'),
                                                            ])
                                                            ->default('BlogPosting'),

                                                        Forms\Components\TextInput::make('schema_markup.author_name')
                                                            ->label(__('Author Name'))
                                                            ->maxLength(100),

                                                        Forms\Components\TextInput::make('schema_markup.author_url')
                                                            ->label(__('Author URL'))
                                                            ->url()
                                                            ->maxLength(255),

                                                        Forms\Components\TextInput::make('schema_markup.publisher_name')
                                                            ->label(__('Publisher Name'))
                                                            ->maxLength(100)
                                                            ->default(fn () => $tenant->public_name ?? $tenant->name ?? ''),

                                                        Forms\Components\TextInput::make('schema_markup.publisher_logo')
                                                            ->label(__('Publisher Logo URL'))
                                                            ->url()
                                                            ->maxLength(500),
                                                    ])->columns(1),

                                                SC\Tabs\Tab::make(__('Advanced'))
                                                    ->schema([
                                                        Forms\Components\Select::make('language')
                                                            ->label(__('Content Language'))
                                                            ->options([
                                                                'en' => 'English',
                                                                'ro' => __('Romanian'),
                                                                'de' => __('German'),
                                                                'fr' => __('French'),
                                                                'es' => __('Spanish'),
                                                                'it' => __('Italian'),
                                                            ])
                                                            ->default($tenantLanguage),

                                                        Forms\Components\TextInput::make('reading_time_minutes')
                                                            ->label(__('Reading Time (minutes)'))
                                                            ->numeric()
                                                            ->minValue(1)
                                                            ->helperText(__('Leave empty for auto-calculation')),
                                                    ])->columns(1),
                                            ])
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // RIGHT COLUMN (1/3 width)
                        SC\Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                // Organization Section
                                SC\Section::make(__('Organization'))
                                    ->schema([
                                        Forms\Components\Select::make('category_id')
                                            ->label('Category')
                                            ->options(function () use ($tenant, $tenantLanguage) {
                                                return BlogCategory::where('tenant_id', $tenant?->id)
                                                    ->get()
                                                    ->mapWithKeys(function ($cat) use ($tenantLanguage) {
                                                        $name = $cat->name[$tenantLanguage] ?? $cat->name['en'] ?? $cat->name[array_key_first($cat->name ?? [])] ?? 'Unnamed';
                                                        return [$cat->id => $name];
                                                    });
                                            })
                                            ->searchable()
                                            ->preload(),

                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'draft' => 'Draft',
                                                'published' => __('Published'),
                                                'scheduled' => __('Scheduled'),
                                                'archived' => 'Archived',
                                            ])
                                            ->default('draft')
                                            ->required(),

                                        Forms\Components\DateTimePicker::make('published_at')
                                            ->label(__('Publish Date'))
                                            ->helperText(__('Leave empty to publish immediately')),

                                        Forms\Components\Toggle::make('is_featured')
                                            ->label(__('Featured Article'))
                                            ->helperText(__('Show on homepage/featured section')),
                                    ]),

                                // Featured Image Section
                                SC\Section::make(__('Featured Image'))
                                    ->schema([
                                        Forms\Components\FileUpload::make('featured_image_url')
                                            ->label('Image')
                                            ->image()
                                            ->disk('public')
                                            ->directory('blog-images')
                                            ->imageEditor()
                                            ->imageResizeMode('cover')
                                            ->imageCropAspectRatio('16:9')
                                            ->imageResizeTargetWidth('1200')
                                            ->imageResizeTargetHeight('630')
                                            ->helperText(__('Drag & drop or click to upload. Recommended: 1200x630px'))
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('featured_image_alt')
                                            ->label(__('Alt Text'))
                                            ->maxLength(255)
                                            ->helperText(__('Describe the image for accessibility')),
                                    ]),
                            ]),
                    ]),
            ]) ->columns(1);
    }

    public static function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image_url')
                    ->label('Image')
                    ->circular(false)
                    ->size(50),

                Tables\Columns\TextColumn::make("title.{$tenantLanguage}")
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(function ($state) use ($tenantLanguage) {
                        if (is_array($state)) {
                            return $state[$tenantLanguage] ?? $state['en'] ?? '-';
                        }
                        return $state ?? '-';
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'published',
                        'warning' => 'scheduled',
                        'danger' => 'archived',
                    ]),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\TextColumn::make('view_count')
                    ->label(__('Views'))
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label(__('Published'))
                    ->dateTime('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => __('Published'),
                        'scheduled' => __('Scheduled'),
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                EditAction::make(),
                Actions\Action::make('publish')
                    ->label(__('Publish'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (BlogArticle $record) => $record->status !== 'published')
                    ->action(fn (BlogArticle $record) => $record->publish()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListBlogArticles::route('/'),
            'create' => Pages\CreateBlogArticle::route('/create'),
            'edit' => Pages\EditBlogArticle::route('/{record}/edit'),
        ];
    }
}
