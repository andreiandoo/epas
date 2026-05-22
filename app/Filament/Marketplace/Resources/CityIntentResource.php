<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\CityIntentResource\Pages;
use App\Models\MarketplaceCityIntent;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CityIntentResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceCityIntent::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'City Intents';

    // Lives alongside Cities/Counties/Regions under the "Locații" parent item.
    protected static ?string $navigationParentItem = 'Locații';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'City Intent';

    protected static ?string $pluralModelLabel = 'City Intents';

    protected static ?string $slug = 'city-intents';

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplaceClientId);
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema
            ->schema([
                Forms\Components\Hidden::make('marketplace_client_id')
                    ->default($marketplace?->id),

                SC\Section::make('Identificare')
                    ->schema([
                        SC\Tabs::make('Label')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')->schema([
                                    Forms\Components\TextInput::make('name.ro')
                                        ->label('Etichetă scurtă (RO)')
                                        ->required()
                                        ->maxLength(120)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                                            if ($state && !$get('slug')) {
                                                $set('slug', 'activitati-' . Str::slug($state));
                                            }
                                        }),
                                ]),
                                SC\Tabs\Tab::make('English')->schema([
                                    Forms\Components\TextInput::make('name.en')
                                        ->label('Short label (EN)')
                                        ->maxLength(120),
                                ]),
                            ])->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug URL')
                            ->helperText('Convenție: începe cu "activitati-" ca routing-ul să-l distingă de categorii/orașe. URL final: /{oras}/{slug}.')
                            ->required()
                            ->maxLength(120)
                            ->rule('alpha_dash')
                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) use ($marketplace) {
                                return $rule->where('marketplace_client_id', $marketplace?->id);
                            }),

                        Forms\Components\TextInput::make('icon')
                            ->label('Icon / emoji')
                            ->placeholder('🎯 sau heroicon-o-sparkles')
                            ->maxLength(80),

                        Forms\Components\Select::make('accent_color')
                            ->label('Culoare accent')
                            ->options([
                                'vermilion' => 'Vermilion (roșu)',
                                'forest' => 'Forest (verde)',
                                'ochre' => 'Ochre (galben-portocaliu)',
                                'sky' => 'Sky (albastru)',
                            ])
                            ->default('vermilion'),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Ordine afișare')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                SC\Section::make('SEO — template-uri')
                    ->description('Suportă placeholderii: {intent_label}, {city_name}, {result_count}')
                    ->schema([
                        SC\Tabs::make('SEO templates')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')->schema([
                                    Forms\Components\TextInput::make('title_template.ro')
                                        ->label('Title template (<title>)')
                                        ->placeholder('Activități {intent_label} în {city_name} · bilete.online')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('h1_template.ro')
                                        ->label('H1 template (opțional)')
                                        ->placeholder('Activități {intent_label} în {city_name}')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('meta_description_template.ro')
                                        ->label('Meta description template')
                                        ->rows(2)
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('intro_copy.ro')
                                        ->label('Intro (apare deasupra rezultatelor)')
                                        ->rows(2),
                                    Forms\Components\Textarea::make('seo_copy.ro')
                                        ->label('SEO copy lung (apare sub rezultate)')
                                        ->rows(6),
                                ]),
                                SC\Tabs\Tab::make('English')->schema([
                                    Forms\Components\TextInput::make('title_template.en')
                                        ->label('Title template')
                                        ->maxLength(255),
                                    Forms\Components\TextInput::make('h1_template.en')
                                        ->label('H1 template')
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('meta_description_template.en')
                                        ->label('Meta description template')
                                        ->rows(2)
                                        ->maxLength(255),
                                    Forms\Components\Textarea::make('intro_copy.en')
                                        ->label('Intro')
                                        ->rows(2),
                                    Forms\Components\Textarea::make('seo_copy.en')
                                        ->label('SEO copy')
                                        ->rows(6),
                                ]),
                            ])->columnSpanFull(),
                    ]),

                SC\Section::make('Regulă de filtrare (DSL JSON)')
                    ->description(
                        'Editor JSON. Tipuri de regulă: in_city, event_attr, category_slug, cheapest_price_max/min/eq, ' .
                        'has_session_today/_tomorrow/_this_weekend, tag, age_includes. Combinatori: all, any, not. ' .
                        'Vezi IntentFilterResolver.php pentru exemple complete.'
                    )
                    ->schema([
                        Forms\Components\Textarea::make('filter_rule_json')
                            ->label('filter_rule_json')
                            ->rows(12)
                            ->required()
                            ->rule('json')
                            ->dehydrateStateUsing(fn ($state) => is_string($state) && trim($state) !== '' ? json_decode($state, true) : null)
                            ->afterStateHydrated(fn ($component, $state) => $component->state(is_array($state) ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ($state ?? '')))
                            ->columnSpanFull(),
                    ]),

                SC\Section::make('Setări vizibilitate')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Activă')
                            ->default(true),

                        Forms\Components\TextInput::make('min_results_for_index')
                            ->label('Minim rezultate pentru indexare')
                            ->helperText('Sub acest prag, paginile primesc <meta robots="noindex"> ca să evite penalizarea de thin content.')
                            ->numeric()
                            ->default(3)
                            ->minValue(0),

                        Forms\Components\FileUpload::make('cover_image_url')
                            ->label('Imagine cover (opțional, pentru OG share)')
                            ->image()
                            ->disk('public')
                            ->directory('intents')
                            ->visibility('public'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("name.{$lang}")
                    ->label('Etichetă')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('icon')
                    ->label('Icon'),

                Tables\Columns\TextColumn::make('accent_color')
                    ->label('Accent')
                    ->badge(),

                Tables\Columns\TextColumn::make('min_results_for_index')
                    ->label('Min. rezultate')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activă')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Ordine')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activă'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCityIntents::route('/'),
            'create' => Pages\CreateCityIntent::route('/create'),
            'edit' => Pages\EditCityIntent::route('/{record}/edit'),
        ];
    }
}
