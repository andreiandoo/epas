<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\KbArticleResource\Pages;
use App\Models\KnowledgeBase\KbArticle;
use App\Models\KnowledgeBase\KbCategory;
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

class KbArticleResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = KbArticle::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationLabel = 'Knowledge Base';

    protected static \UnitEnum|string|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'Article';

    protected static ?string $pluralModelLabel = 'Knowledge Base';

    protected static ?string $slug = 'kb-articles';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('knowledge-base');
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                // Two-column layout: Left (2/3) and Right (1/3)
                SC\Grid::make(3)
                    ->schema([
                        // LEFT COLUMN (2/3 width)
                        SC\Grid::make(1)
                            ->columnSpan(2)
                            ->schema([
                                // Type selection at the top
                                SC\Section::make('Article Type')
                                    ->schema([
                                        Forms\Components\Radio::make('type')
                                            ->label('')
                                            ->options([
                                                'article' => 'Article - Full article with title and content',
                                                'faq' => 'FAQ - Question and answer format',
                                            ])
                                            ->default('article')
                                            ->required()
                                            ->live()
                                            ->inline(),
                                    ]),

                                // Article Content Section (for type = article)
                                SC\Section::make('Article Content')
                                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('type') === 'article')
                                    ->schema([
                                        Forms\Components\TextInput::make("title.{$marketplaceLanguage}")
                                            ->label('Title')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) use ($marketplaceLanguage) {
                                                if ($state) {
                                                    $set('slug', Str::slug($state));
                                                    if (!$get("meta_title.{$marketplaceLanguage}")) {
                                                        $set("meta_title.{$marketplaceLanguage}", Str::limit($state, 60));
                                                    }
                                                }
                                            }),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->rule('alpha_dash'),

                                        Forms\Components\RichEditor::make("content.{$marketplaceLanguage}")
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

                                // FAQ Content Section (for type = faq)
                                SC\Section::make('FAQ Content')
                                    ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('type') === 'faq')
                                    ->schema([
                                        Forms\Components\TextInput::make("question.{$marketplaceLanguage}")
                                            ->label('Question')
                                            ->required()
                                            ->maxLength(500)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                                if ($state) {
                                                    $set('slug', Str::slug(Str::limit($state, 50)));
                                                }
                                            })
                                            ->columnSpanFull(),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('Slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->rule('alpha_dash'),

                                        Forms\Components\RichEditor::make("content.{$marketplaceLanguage}")
                                            ->label('Answer')
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
                            ]),

                        // RIGHT COLUMN (1/3 width)
                        SC\Grid::make(1)
                            ->columnSpan(1)
                            ->schema([
                                // Organization Section
                                SC\Section::make('Organization')
                                    ->schema([
                                        Forms\Components\Select::make('kb_category_id')
                                            ->label('Category')
                                            ->options(function () use ($marketplace, $marketplaceLanguage) {
                                                return KbCategory::where('marketplace_client_id', $marketplace?->id)
                                                    ->orderBy('sort_order')
                                                    ->get()
                                                    ->mapWithKeys(function ($cat) use ($marketplaceLanguage) {
                                                        $name = $cat->name[$marketplaceLanguage] ?? $cat->name['en'] ?? $cat->name[array_key_first($cat->name ?? [])] ?? 'Unnamed';
                                                        return [$cat->id => $name];
                                                    });
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->required(),

                                        Forms\Components\TextInput::make('sort_order')
                                            ->label('Sort Order')
                                            ->numeric()
                                            ->default(0),

                                        Forms\Components\Toggle::make('is_visible')
                                            ->label('Visible')
                                            ->default(true),

                                        Forms\Components\Toggle::make('is_featured')
                                            ->label('Featured')
                                            ->helperText('Show on homepage/featured section'),

                                        Forms\Components\Toggle::make('is_popular')
                                            ->label('Popular')
                                            ->helperText('Mark as popular topic'),
                                    ]),

                                // Appearance Section
                                SC\Section::make('Appearance')
                                    ->collapsed()
                                    ->schema([
                                        Forms\Components\Select::make('icon')
                                            ->label('Icon')
                                            ->options([
                                                'heroicon-o-ticket' => 'Ticket',
                                                'heroicon-o-calendar' => 'Calendar',
                                                'heroicon-o-user' => 'User',
                                                'heroicon-o-cog-6-tooth' => 'Settings',
                                                'heroicon-o-credit-card' => 'Credit Card',
                                                'heroicon-o-document-text' => 'Document',
                                                'heroicon-o-question-mark-circle' => 'Question',
                                                'heroicon-o-information-circle' => 'Information',
                                                'heroicon-o-bell' => 'Bell',
                                                'heroicon-o-envelope' => 'Envelope',
                                                'heroicon-o-shield-check' => 'Shield',
                                                'heroicon-o-lock-closed' => 'Lock',
                                            ])
                                            ->searchable()
                                            ->placeholder('Select an icon'),

                                        Forms\Components\TagsInput::make('tags')
                                            ->label('Tags')
                                            ->placeholder('Add tags...'),
                                    ]),

                                // Stats Section (only on edit)
                                SC\Section::make('Statistics')
                                    ->collapsed()
                                    ->visible(fn ($record) => $record !== null)
                                    ->schema([
                                        Forms\Components\Placeholder::make('view_count_display')
                                            ->label('Views')
                                            ->content(fn ($record) => $record?->view_count ?? 0),

                                        Forms\Components\Placeholder::make('helpfulness_display')
                                            ->label('Helpfulness')
                                            ->content(function ($record) {
                                                if (!$record) return '-';
                                                $score = $record->helpfulness_score;
                                                if ($score === null) return 'No votes yet';
                                                return "{$score}% ({$record->helpful_count} helpful, {$record->not_helpful_count} not helpful)";
                                            }),
                                    ]),
                            ]),
                    ]),
            ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'article' => 'info',
                        'faq' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => strtoupper($state)),

                Tables\Columns\TextColumn::make("title.{$marketplaceLanguage}")
                    ->label('Title/Question')
                    ->searchable()
                    ->sortable()
                    ->limit(50)
                    ->formatStateUsing(function ($state, $record) use ($marketplaceLanguage) {
                        if ($record->type === 'faq') {
                            return $record->question[$marketplaceLanguage] ?? $record->question['en'] ?? '-';
                        }
                        return $state ?? '-';
                    }),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(function ($state) use ($marketplaceLanguage) {
                        if (is_array($state)) {
                            return $state[$marketplaceLanguage] ?? $state['en'] ?? '-';
                        }
                        return $state ?? '-';
                    }),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_popular')
                    ->label('Popular')
                    ->boolean(),

                Tables\Columns\TextColumn::make('view_count')
                    ->label('Views')
                    ->sortable(),

                Tables\Columns\TextColumn::make('helpfulness_score')
                    ->label('Helpful %')
                    ->formatStateUsing(fn ($state) => $state !== null ? "{$state}%" : '-')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'article' => 'Article',
                        'faq' => 'FAQ',
                    ]),
                Tables\Filters\SelectFilter::make('kb_category_id')
                    ->label('Category')
                    ->options(function () use ($marketplace, $marketplaceLanguage) {
                        return KbCategory::where('marketplace_client_id', $marketplace?->id)
                            ->get()
                            ->mapWithKeys(function ($cat) use ($marketplaceLanguage) {
                                $name = $cat->name[$marketplaceLanguage] ?? $cat->name['en'] ?? 'Unnamed';
                                return [$cat->id => $name];
                            });
                    }),
                Tables\Filters\TernaryFilter::make('is_visible')
                    ->label('Visible'),
                Tables\Filters\TernaryFilter::make('is_featured')
                    ->label('Featured'),
                Tables\Filters\TernaryFilter::make('is_popular')
                    ->label('Popular'),
            ])
            ->recordActions([
                EditAction::make(),
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
            'index' => Pages\ListKbArticles::route('/'),
            'create' => Pages\CreateKbArticle::route('/create'),
            'edit' => Pages\EditKbArticle::route('/{record}/edit'),
        ];
    }
}
