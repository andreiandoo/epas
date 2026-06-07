<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\CityResource\Pages;
use App\Models\MarketplaceCity;
use App\Models\MarketplaceCounty;
use App\Models\MarketplaceRegion;
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

class CityResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceCity::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Cities';

    protected static ?string $navigationParentItem = 'Locații';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'City';

    protected static ?string $pluralModelLabel = 'Cities';

    protected static ?string $slug = 'cities';

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

                SC\Section::make('City Details')
                    ->schema([
                        SC\Tabs::make('Name Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\TextInput::make('name.ro')
                                            ->label('Nume oras (RO)')
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
                                            ->label('City name (EN)')
                                            ->maxLength(190),
                                    ]),
                            ])->columnSpanFull(),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(190)
                            ->rule('alpha_dash'),

                        Forms\Components\Select::make('county_id')
                            ->label('County (Județ)')
                            ->options(function () {
                                $marketplace = static::getMarketplaceClient();
                                $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                                return MarketplaceCounty::where('marketplace_client_id', $marketplace?->id)
                                    ->with('region')
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn ($c) => [
                                        $c->id => $c->code . ' - ' . ($c->name[$lang] ?? $c->name['ro'] ?? 'Unnamed')
                                            . ($c->region ? ' (' . ($c->region->name[$lang] ?? $c->region->name['ro'] ?? '') . ')' : '')
                                    ]);
                            })
                            ->searchable()
                            ->required()
                            ->placeholder('Select a county')
                            ->live()
                            ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set) {
                                if ($state) {
                                    $county = MarketplaceCounty::find($state);
                                    if ($county) {
                                        $set('region_id', $county->region_id);
                                    }
                                }
                            }),

                        Forms\Components\Select::make('region_id')
                            ->label('Region')
                            ->options(function () {
                                $marketplace = static::getMarketplaceClient();
                                $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                                return MarketplaceRegion::where('marketplace_client_id', $marketplace?->id)
                                    ->orderBy('sort_order')
                                    ->get()
                                    ->mapWithKeys(fn ($r) => [$r->id => $r->name[$lang] ?? $r->name['en'] ?? 'Unnamed']);
                            })
                            ->searchable()
                            ->disabled()
                            ->dehydrated()
                            ->helperText('Auto-selected based on county'),

                        Forms\Components\Select::make('country')
                            ->label('Country')
                            ->options([
                                'RO' => 'Romania',
                                'MD' => 'Moldova',
                                'HU' => 'Hungary',
                                'BG' => 'Bulgaria',
                            ])
                            ->default('RO')
                            ->required(),

                        Forms\Components\TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0),
                    ])->columns(2),

                SC\Section::make('Location')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('latitude')
                            ->label('Latitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->placeholder('44.4268'),

                        Forms\Components\TextInput::make('longitude')
                            ->label('Longitude')
                            ->numeric()
                            ->step('0.0000001')
                            ->placeholder('26.1025'),

                        Forms\Components\TextInput::make('timezone')
                            ->label('Timezone')
                            ->placeholder('Europe/Bucharest')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('population')
                            ->label('Population')
                            ->numeric()
                            ->minValue(0),
                    ])->columns(2),

                // Affiliate widgets — GetYourGuide. The numeric city id is
                // visible in the GYG URL after a city search (e.g.
                // https://www.getyourguide.com/bucharest-l124688/). The
                // helper link below opens the GYG search pre-filled with
                // the city name so the operator can grab the id with two
                // clicks instead of remembering the URL pattern.
                SC\Section::make('Widget GetYourGuide')
                    ->description('ID-ul numeric al orașului pe GetYourGuide. Folosit pentru widget-ul de activități afișat pe pagina publică a orașului.')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('getyourguide_city_id')
                            ->label('GetYourGuide City ID')
                            ->placeholder('ex. 124688')
                            ->maxLength(40)
                            ->helperText(new \Illuminate\Support\HtmlString(
                                'Caută orașul pe GetYourGuide → ID-ul e numărul după <code>-l</code> în URL (ex. <code>...-l<strong>124688</strong>/</code>).'
                            ))
                            ->hintAction(
                                \Filament\Forms\Components\Actions\Action::make('searchOnGyg')
                                    ->label('Caută pe GetYourGuide ↗')
                                    ->icon('heroicon-o-magnifying-glass')
                                    ->url(function ($get) {
                                        $name = $get('name');
                                        if (is_array($name)) {
                                            $name = $name['ro'] ?? $name['en'] ?? reset($name) ?? '';
                                        }
                                        $q = urlencode((string) ($name ?: ''));
                                        // Force English locale on the search side — GYG
                                        // returns more reliable city matches that way; the
                                        // widget itself still renders in ro-RO regardless.
                                        return "https://www.getyourguide.com/s/?q={$q}&searchSource=3";
                                    }, shouldOpenInNewTab: true)
                            ),
                    ])->columns(1),

                SC\Section::make('Appearance')
                    ->collapsed()
                    ->schema([
                        Forms\Components\FileUpload::make('image_url')
                            ->label('City Image')
                            ->image()
                            ->disk('public')
                            ->directory('cities')
                            ->visibility('public'),

                        Forms\Components\FileUpload::make('cover_image_url')
                            ->label('Cover Image')
                            ->image()
                            ->disk('public')
                            ->directory('cities/covers')
                            ->visibility('public'),

                        Forms\Components\TextInput::make('icon')
                            ->label('Icon')
                            ->placeholder('heroicon-o-building-library')
                            ->maxLength(100),

                        Forms\Components\Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured'),

                        Forms\Components\Toggle::make('is_capital')
                            ->label('Capital of Region'),
                    ])->columns(3),

                SC\Section::make('Description')
                    ->collapsed()
                    ->schema([
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
                    ]),

                SC\Section::make('SEO Body — "Tot ce trebuie să știi"')
                    ->description('Titlu + corp rich-text afișat în ghidul local al orașului.')
                    ->collapsed()
                    ->schema([
                        SC\Tabs::make('SEO Body Translations')
                            ->tabs([
                                SC\Tabs\Tab::make('Română')
                                    ->schema([
                                        Forms\Components\TextInput::make('seo_body_title.ro')
                                            ->label('Titlu (RO)')
                                            ->placeholder('Ce să faci în București: idei pentru weekend, familie sau o zi liberă')
                                            ->maxLength(190),
                                        Forms\Components\RichEditor::make('seo_body.ro')
                                            ->label('Corp text (RO)')
                                            ->placeholder('Despre oraș — recomandări, ce să faci, ghid local. Poți folosi titluri, liste, link-uri, bold.')
                                            ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                                            ->columnSpanFull(),
                                    ]),
                                SC\Tabs\Tab::make('English')
                                    ->schema([
                                        Forms\Components\TextInput::make('seo_body_title.en')
                                            ->label('Title (EN)')
                                            ->maxLength(190),
                                        Forms\Components\RichEditor::make('seo_body.en')
                                            ->label('Body (EN)')
                                            ->toolbarButtons(['bold', 'italic', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'blockquote', 'undo', 'redo'])
                                            ->columnSpanFull(),
                                    ]),
                            ])->columnSpanFull(),
                    ])->columns(1),

                SC\Section::make('Întrebări frecvente (FAQs)')
                    ->description('Lista de FAQs afișată pe pagina orașului + inclusă ca FAQPage JSON-LD pentru rich SERP. Doar limba primară (RO).')
                    ->collapsed()
                    ->schema([
                        Forms\Components\Repeater::make('faqs')
                            ->label(false)
                            ->schema([
                                Forms\Components\TextInput::make('q')
                                    ->label('Întrebare')
                                    ->required()
                                    ->maxLength(200),
                                Forms\Components\Textarea::make('a')
                                    ->label('Răspuns')
                                    ->required()
                                    ->rows(3),
                            ])
                            ->columns(1)
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->itemLabel(fn (array $state): ?string => $state['q'] ?? null)
                            ->addActionLabel('Adaugă întrebare')
                            ->columnSpanFull(),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        $marketplace = static::getMarketplaceClient();
        $marketplaceLanguage = $marketplace->language ?? $marketplace->locale ?? 'ro';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("name.{$marketplaceLanguage}")
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('county.code')
                    ->label('County')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('region.name')
                    ->label('Region')
                    ->getStateUsing(function ($record) use ($marketplaceLanguage) {
                        $name = $record->region?->name;
                        if (is_array($name)) {
                            return $name[$marketplaceLanguage] ?? $name['ro'] ?? '-';
                        }
                        return $name ?? '-';
                    }),

                Tables\Columns\TextColumn::make('country')
                    ->label('Country')
                    ->badge(),

                Tables\Columns\TextColumn::make('event_count')
                    ->label('Events')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_visible')
                    ->label('Visible')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_capital')
                    ->label('Capital')
                    ->boolean(),

                Tables\Columns\TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('region_id')
                    ->label('Region')
                    ->options(function () {
                        $marketplace = static::getMarketplaceClient();
                        $lang = $marketplace->language ?? $marketplace->locale ?? 'ro';
                        return MarketplaceRegion::where('marketplace_client_id', $marketplace?->id)
                            ->orderBy('sort_order')
                            ->get()
                            ->mapWithKeys(fn ($r) => [$r->id => $r->name[$lang] ?? $r->name['en'] ?? 'Unnamed']);
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
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
