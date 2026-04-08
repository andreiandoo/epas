<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\VanityUrlResource\Pages;
use App\Models\Artist;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceVanityUrl;
use App\Models\Venue;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get as SGet;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class VanityUrlResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceVanityUrl::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-link';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Vanity URLs';

    protected static ?string $modelLabel = 'Vanity URL';

    protected static ?string $pluralModelLabel = 'Vanity URLs';

    protected static ?string $slug = 'vanity-urls';

    /**
     * Reserved top-level paths that cannot be used as vanity slugs.
     * These collide with existing routes in resources/marketplaces/ambilet/.htaccess.
     */
    public const RESERVED_PATHS = [
        // Filament panels
        'admin', 'tenant', 'marketplace',
        // API
        'api',
        // Account routes
        'cont', 'autentificare', 'inregistrare', 'parola-uitata', 'resetare-parola',
        'verify-email', 'email-confirmat',
        // Organizer routes
        'organizator', 'organizatori',
        // Entity routes
        'bilete', 'artist', 'artisti', 'locatie', 'locatii', 'gen', 'regiune', 'blog',
        'ajutor', 'intrebari',
        // Listings
        'evenimente', 'evenimente-trecute', 'calendar', 'cauta',
        // Cart / checkout
        'cos', 'finalizare', 'multumim',
        // Static pages
        'despre', 'contact', 'parteneri', 'termeni', 'confidentialitate', 'cookies',
        'gdpr', 'card-cadou', 'ghid-organizator', 'politica-retur', 'accesabilitate',
        'press-kit', 'pentru-organizatori',
        // Special routes
        'view', 'vanity', 'storage', 'public', 'css', 'js', 'images', 'assets',
        'favicon.ico', 'robots.txt', 'sitemap.xml',
    ];

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Vanity URL')
                    ->icon('heroicon-o-link')
                    ->description('Configurează un URL scurt care răspunde direct pe domeniul marketplace-ului. Pagina țintă va fi randată direct pe URL-ul scurt (fără redirect).')
                    ->schema([
                        Forms\Components\TextInput::make('slug')
                            ->label('Slug (cale URL)')
                            ->required()
                            ->maxLength(100)
                            ->prefix('https://bilete.online/')
                            ->placeholder('teatrulmicilorvisatori')
                            ->helperText('Doar litere mici, cifre și liniuțe. NU folosi paths rezervate (artist, bilete, organizator, etc.).')
                            ->extraInputAttributes(['style' => 'text-transform: lowercase'])
                            ->dehydrateStateUsing(fn ($state) => strtolower(trim($state)))
                            ->rules([
                                'regex:/^[a-z][a-z0-9-]{0,99}$/',
                                fn () => function (string $attribute, $value, \Closure $fail) {
                                    $value = strtolower(trim($value));
                                    if (in_array($value, \App\Filament\Marketplace\Resources\VanityUrlResource::RESERVED_PATHS, true)) {
                                        $fail("Slug-ul „{$value}\" este rezervat și nu poate fi folosit.");
                                    }
                                },
                            ])
                            ->validationMessages([
                                'regex' => 'Slug-ul poate conține doar litere mici (a-z), cifre și liniuțe. Trebuie să înceapă cu o literă.',
                            ])
                            ->unique(
                                table: 'marketplace_vanity_urls',
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: function (\Illuminate\Validation\Rules\Unique $rule) {
                                    $marketplace = static::getMarketplaceClient();
                                    return $rule->where('marketplace_client_id', $marketplace?->id);
                                },
                            ),

                        Forms\Components\Select::make('target_type')
                            ->label('Tip țintă')
                            ->options(MarketplaceVanityUrl::TYPES)
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (\Filament\Schemas\Components\Utilities\Set $set) => $set('target_id', null)),

                        Forms\Components\Select::make('target_id')
                            ->label('Selectează ținta')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->visible(fn (SGet $get) => $get('target_type') && $get('target_type') !== MarketplaceVanityUrl::TYPE_EXTERNAL_URL)
                            ->options(function (SGet $get) {
                                $marketplace = static::getMarketplaceClient();
                                return match ($get('target_type')) {
                                    MarketplaceVanityUrl::TYPE_ARTIST => Artist::whereHas(
                                        'marketplaceClients',
                                        fn ($q) => $q->where('marketplace_artist_partners.marketplace_client_id', $marketplace?->id)
                                    )->orderBy('name')->limit(50)->pluck('name', 'id'),
                                    MarketplaceVanityUrl::TYPE_EVENT => Event::where('marketplace_client_id', $marketplace?->id)
                                        ->orderByDesc('id')->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn ($e) => [
                                            $e->id => is_array($e->title)
                                                ? ($e->title['ro'] ?? $e->title['en'] ?? reset($e->title) ?: '#' . $e->id)
                                                : ($e->title ?? '#' . $e->id),
                                        ]),
                                    MarketplaceVanityUrl::TYPE_VENUE => Venue::orderBy('id')->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn ($v) => [$v->id => is_array($v->name) ? (reset($v->name) ?: '#' . $v->id) : ($v->name ?? '#' . $v->id)]),
                                    MarketplaceVanityUrl::TYPE_ORGANIZER => MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
                                        ->orderBy('name')->limit(50)->pluck('name', 'id'),
                                    default => [],
                                };
                            })
                            ->getSearchResultsUsing(function (string $search, SGet $get) {
                                $marketplace = static::getMarketplaceClient();
                                return match ($get('target_type')) {
                                    MarketplaceVanityUrl::TYPE_ARTIST => Artist::whereHas(
                                            'marketplaceClients',
                                            fn ($q) => $q->where('marketplace_artist_partners.marketplace_client_id', $marketplace?->id)
                                        )
                                        ->where('name', 'ilike', "%{$search}%")
                                        ->limit(50)
                                        ->pluck('name', 'id'),
                                    MarketplaceVanityUrl::TYPE_EVENT => Event::where('marketplace_client_id', $marketplace?->id)
                                        ->whereRaw("LOWER(title::text) LIKE ?", ['%' . strtolower($search) . '%'])
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn ($e) => [
                                            $e->id => is_array($e->title)
                                                ? ($e->title['ro'] ?? $e->title['en'] ?? reset($e->title) ?: '#' . $e->id)
                                                : ($e->title ?? '#' . $e->id),
                                        ]),
                                    MarketplaceVanityUrl::TYPE_VENUE => Venue::whereRaw("LOWER(name::text) LIKE ?", ['%' . strtolower($search) . '%'])
                                        ->limit(50)
                                        ->get()
                                        ->mapWithKeys(fn ($v) => [$v->id => is_array($v->name) ? (reset($v->name) ?: '#' . $v->id) : ($v->name ?? '#' . $v->id)]),
                                    MarketplaceVanityUrl::TYPE_ORGANIZER => MarketplaceOrganizer::where('marketplace_client_id', $marketplace?->id)
                                        ->where('name', 'ilike', "%{$search}%")
                                        ->limit(50)
                                        ->pluck('name', 'id'),
                                    default => [],
                                };
                            })
                            ->getOptionLabelUsing(function ($value, SGet $get) {
                                return match ($get('target_type')) {
                                    MarketplaceVanityUrl::TYPE_ARTIST => Artist::find($value)?->name,
                                    MarketplaceVanityUrl::TYPE_EVENT => (function ($id) {
                                        $e = Event::find($id);
                                        if (!$e) return null;
                                        return is_array($e->title)
                                            ? ($e->title['ro'] ?? $e->title['en'] ?? reset($e->title) ?: '#' . $e->id)
                                            : ($e->title ?? '#' . $e->id);
                                    })($value),
                                    MarketplaceVanityUrl::TYPE_VENUE => (function ($id) {
                                        $v = Venue::find($id);
                                        if (!$v) return null;
                                        return is_array($v->name) ? (reset($v->name) ?: '#' . $v->id) : ($v->name ?? '#' . $v->id);
                                    })($value),
                                    MarketplaceVanityUrl::TYPE_ORGANIZER => MarketplaceOrganizer::find($value)?->name,
                                    default => null,
                                };
                            }),

                        Forms\Components\TextInput::make('target_url')
                            ->label('URL extern')
                            ->url()
                            ->maxLength(500)
                            ->placeholder('https://example.com/promo')
                            ->visible(fn (SGet $get) => $get('target_type') === MarketplaceVanityUrl::TYPE_EXTERNAL_URL)
                            ->required(fn (SGet $get) => $get('target_type') === MarketplaceVanityUrl::TYPE_EXTERNAL_URL),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Activ')
                            ->default(true),

                        Forms\Components\Textarea::make('notes')
                            ->label('Note interne')
                            ->rows(2),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->prefix('/')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\BadgeColumn::make('target_type')
                    ->label('Tip')
                    ->colors([
                        'primary' => MarketplaceVanityUrl::TYPE_ARTIST,
                        'success' => MarketplaceVanityUrl::TYPE_EVENT,
                        'warning' => MarketplaceVanityUrl::TYPE_VENUE,
                        'info' => MarketplaceVanityUrl::TYPE_ORGANIZER,
                        'gray' => MarketplaceVanityUrl::TYPE_EXTERNAL_URL,
                    ])
                    ->formatStateUsing(fn ($state) => MarketplaceVanityUrl::TYPES[$state] ?? $state),

                Tables\Columns\TextColumn::make('target_label')
                    ->label('Țintă')
                    ->getStateUsing(fn (MarketplaceVanityUrl $record) => $record->getTargetLabel() ?? '—')
                    ->limit(60)
                    ->wrap(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Activ')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('clicks_count')
                    ->label('Click-uri')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_accessed_at')
                    ->label('Ultim accesat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modificat')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Activ'),
                Tables\Filters\SelectFilter::make('target_type')
                    ->label('Tip')
                    ->options(MarketplaceVanityUrl::TYPES),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVanityUrls::route('/'),
            'create' => Pages\CreateVanityUrl::route('/create'),
            'edit' => Pages\EditVanityUrl::route('/{record}/edit'),
        ];
    }
}
