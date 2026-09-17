<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\MarketplacePartnerResource\Pages;
use App\Models\MarketplacePartner;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

/**
 * Media partners (microservice media-partners) and their keys to /api/partner/v1.
 */
class MarketplacePartnerResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplacePartner::class;
    protected static ?string $slug = 'media-partners';
    protected static ?string $navigationLabel = 'Parteneri media';
    protected static ?string $modelLabel = 'partener media';
    protected static ?string $pluralModelLabel = 'parteneri media';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-newspaper';
    protected static \UnitEnum|string|null $navigationGroup = 'Tools';

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice(MarketplacePartner::MICROSERVICE);
    }

    public static function canAccess(): bool
    {
        return static::shouldRegisterNavigation();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('marketplace_client_id', static::getMarketplaceClientId());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Partener')->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nume')
                    ->required()
                    ->maxLength(191),

                Forms\Components\TextInput::make('slug')
                    ->label('Identificator')
                    ->required()
                    ->maxLength(64)
                    ->regex('/^[a-z0-9_-]+$/')
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule
                        ->where('marketplace_client_id', static::getMarketplaceClientId()))
                    ->helperText('Litere mici, fără spații (ex. maximumrock). Implicit devine utm_source.'),

                Forms\Components\Select::make('status')
                    ->label('Stare')
                    ->options(['active' => 'Activ', 'inactive' => 'Inactiv'])
                    ->default('active')
                    ->required()
                    ->native(false),
            ])->columns(3),

            Section::make('Acces API')->schema([
                Forms\Components\CheckboxList::make('scopes')
                    ->label('Drepturi')
                    ->options(MarketplacePartner::SCOPES)
                    ->default(array_keys(MarketplacePartner::SCOPES))
                    ->columns(2)
                    ->columnSpanFull(),

                Forms\Components\TagsInput::make('allowed_ips')
                    ->label('IP-uri permise')
                    ->placeholder('IP sau interval CIDR')
                    ->helperText('Gol = orice IP.'),

                Forms\Components\TextInput::make('rate_limit_per_minute')
                    ->label('Cereri pe minut')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(10000)
                    ->default(120)
                    ->required(),

                Forms\Components\Placeholder::make('api_key_info')
                    ->label('Cheie API')
                    ->content(fn (?MarketplacePartner $record) => $record?->api_key_prefix
                        ? $record->api_key_prefix . '…'
                        : 'Se generează la salvare și se afișează o singură dată.'),

                Forms\Components\Placeholder::make('last_used_info')
                    ->label('Ultima folosire')
                    ->content(fn (?MarketplacePartner $record) => $record?->last_used_at
                        ? $record->last_used_at->timezone('Europe/Bucharest')->format('d.m.Y H:i') . ' · ' . $record->last_used_ip
                        : 'Niciodată')
                    ->visibleOn('edit'),

                Forms\Components\Placeholder::make('base_url_info')
                    ->label('Adresa API')
                    ->content(url('/api/partner/v1')),
            ])->columns(2),

            Section::make('Notificări (webhook)')
                ->description('La fiecare eveniment nou, modificat, anulat sau șters, partenerul primește un POST semnat și citește apoi evenimentul prin API.')
                ->schema([
                    Forms\Components\TextInput::make('webhook_url')
                        ->label('URL webhook')
                        ->url()
                        ->rule('starts_with:https://')
                        ->maxLength(2048)
                        ->placeholder('https://…')
                        ->helperText('Gol = fără notificări; partenerul se sincronizează singur.'),

                    Forms\Components\TextInput::make('outbound_secret')
                        ->label('Secret de semnare')
                        ->password()
                        ->revealable()
                        ->maxLength(255)
                        ->helperText('Secret comun (HMAC-SHA256) pentru cererile trimise partenerului. Trimite-l pe un canal sigur.')
                        ->suffixAction(
                            \Filament\Actions\Action::make('generateOutboundSecret')
                                ->icon('heroicon-m-arrow-path')
                                ->label('Generează')
                                ->action(fn (Set $set) => $set('outbound_secret', Str::random(48)))
                        ),
                ])->columns(2),

            Section::make('Reclame')
                ->description('Reclamele create în „Reclame parteneri” sunt publicate pe site-ul partenerului prin API-ul lui, semnate cu secretul de mai sus.')
                ->schema([
                    Forms\Components\TextInput::make('ads_api_url')
                        ->label('Adresa API pentru reclame')
                        ->url()
                        ->rule('starts_with:https://')
                        ->maxLength(2048)
                        ->placeholder('https://www.maximumrock.ro/wp-json/maximumrock/v1')
                        ->helperText('Fără /ads la final. Gol = partenerul nu primește reclame.'),

                    Forms\Components\TextInput::make('settings.ads.header_prefix')
                        ->label('Prefix antete semnătură')
                        ->placeholder('X-Ambilet')
                        ->regex('/^X-[A-Za-z0-9-]{1,30}$/')
                        ->maxLength(32)
                        ->helperText('Cum își numește partenerul antetele, ex. X-MR → X-MR-Timestamp și X-MR-Signature.'),
                ])->columns(2),

            Section::make('Articole')
                ->description('Articolele trimise de partener apar pe pagina artistului, cu link către site-ul partenerului.')
                ->schema([
                    Forms\Components\TextInput::make('settings.articles.domain')
                        ->label('Domeniul site-ului partenerului')
                        ->placeholder('maximumrock.ro')
                        ->maxLength(191)
                        ->helperText('Sunt acceptate doar articole cu link pe acest domeniu (și subdomeniile lui). Gol = niciun articol acceptat.'),
                ]),

            Section::make('Linkuri de bilete')
                ->description('Parametrii UTM adăugați linkurilor către evenimente pe care le primește partenerul.')
                ->schema([
                    Forms\Components\TextInput::make('settings.utm.source')
                        ->label('utm_source')
                        ->placeholder('implicit: identificatorul')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('settings.utm.medium')
                        ->label('utm_medium')
                        ->placeholder('implicit: referral')
                        ->maxLength(100),

                    Forms\Components\TextInput::make('settings.utm.campaign')
                        ->label('utm_campaign')
                        ->maxLength(100),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nume')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('slug')->label('Identificator'),
                Tables\Columns\TextColumn::make('status')
                    ->label('Stare')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => $state === 'active' ? 'Activ' : 'Inactiv')
                    ->color(fn (string $state) => $state === 'active' ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('scopes')->label('Drepturi')->badge(),
                Tables\Columns\TextColumn::make('api_key_prefix')
                    ->label('Cheie')
                    ->formatStateUsing(fn (?string $state) => $state ? $state . '…' : '—'),
                Tables\Columns\TextColumn::make('last_used_at')->label('Ultima folosire')->since()->placeholder('Niciodată'),
            ])
            ->defaultSort('name')
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    /**
     * Show a freshly generated key once, as a notification that stays until closed.
     */
    public static function notifyNewKey(MarketplacePartner $partner, string $plainKey): void
    {
        Notification::make()
            ->title('Cheia API pentru ' . $partner->name)
            ->body(new HtmlString(
                'Copiaz-o acum: nu mai poate fi afișată.<br>'
                . '<code style="user-select:all;word-break:break-all">' . e($plainKey) . '</code>'
            ))
            ->warning()
            ->persistent()
            ->send();
    }

    public static function getRelations(): array
    {
        return [
            MarketplacePartnerResource\RelationManagers\DeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketplacePartners::route('/'),
            'create' => Pages\CreateMarketplacePartner::route('/create'),
            'edit' => Pages\EditMarketplacePartner::route('/{record}/edit'),
        ];
    }
}
