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

    protected static ?string $navigationLabel = 'Blog Articles';

    protected static ?string $navigationParentItem = 'Pages';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Article';

    protected static ?string $pluralModelLabel = 'Blog Articles';

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

                SC\Section::make('Article Content')
                    ->schema([
                        Forms\Components\TextInput::make("title.{$tenantLanguage}")
                            ->label('Title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) $set('slug', Str::slug($state));
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->rule('alpha_dash'),

                        Forms\Components\TextInput::make("subtitle.{$tenantLanguage}")
                            ->label('Subtitle')
                            ->maxLength(255),

                        Forms\Components\Textarea::make("excerpt.{$tenantLanguage}")
                            ->label('Excerpt')
                            ->rows(2)
                            ->helperText('Short summary for previews'),

                        Forms\Components\RichEditor::make("content.{$tenantLanguage}")
                            ->label('Content')
                            ->required()
                            ->columnSpanFull(),
                    ])->columns(2),

                SC\Section::make('Organization')
                    ->schema([
                        Forms\Components\Select::make('category_id')
                            ->label('Category')
                            ->options(function () {
                                $tenant = auth()->user()->tenant;
                                return BlogCategory::where('tenant_id', $tenant?->id)
                                    ->get()
                                    ->mapWithKeys(function ($cat) {
                                        $lang = $tenant->language ?? $tenant->locale ?? 'en';
                                        $name = $cat->name[$lang] ?? $cat->name['en'] ?? 'Unnamed';
                                        return [$cat->id => $name];
                                    });
                            })
                            ->searchable(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'scheduled' => 'Scheduled',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Publish Date'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Article'),
                    ])->columns(2),

                SC\Section::make('Featured Image')
                    ->schema([
                        Forms\Components\TextInput::make('featured_image_url')
                            ->label('Image URL')
                            ->url()
                            ->maxLength(500),

                        Forms\Components\TextInput::make('featured_image_alt')
                            ->label('Alt Text')
                            ->maxLength(255),
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
                    ->label('Views')
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published')
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
                        'published' => 'Published',
                        'scheduled' => 'Scheduled',
                        'archived' => 'Archived',
                    ]),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
            ])
            ->actions([
                EditAction::make(),
                Actions\Action::make('publish')
                    ->label('Publish')
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
