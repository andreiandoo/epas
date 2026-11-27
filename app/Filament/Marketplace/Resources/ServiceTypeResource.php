<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\ServiceTypeResource\Pages;
use App\Models\ServiceType;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;

class ServiceTypeResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = ServiceType::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-currency-dollar';

    protected static \UnitEnum|string|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Service Pricing';

    protected static ?string $modelLabel = 'Service Pricing';

    protected static ?string $pluralModelLabel = 'Service Pricing';

    protected static ?string $slug = 'service-pricing';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();

        // Ensure service types exist for this marketplace
        if ($marketplace) {
            ServiceType::getOrCreateForMarketplace($marketplace->id);

            // Auto-create Extended Artist service entry if microserviciul e activ
            // pentru acest marketplace. In acest fel apare automat in lista cu
            // audience='artist' fara ca admin sa-l creeze manual.
            $extendedArtistActive = \Illuminate\Support\Facades\DB::table('marketplace_client_microservices as mcm')
                ->join('microservices as m', 'm.id', '=', 'mcm.microservice_id')
                ->where('mcm.marketplace_client_id', $marketplace->id)
                ->where('m.slug', 'extended-artist')
                ->where('mcm.status', 'active')
                ->exists();

            if ($extendedArtistActive) {
                ServiceType::getOrCreateExtendedArtistService($marketplace->id);
            }
        }

        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Service Information'))
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Service Name'))
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label(__('Code'))
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('audience')
                            ->label(__('Audience'))
                            ->options([
                                ServiceType::AUDIENCE_ORGANIZER => 'Organizator',
                                ServiceType::AUDIENCE_ARTIST => 'Artist',
                                ServiceType::AUDIENCE_BOTH => 'Ambele',
                            ])
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText(__('Cine poate cumpara acest serviciu (read-only).')),

                        Forms\Components\Textarea::make('description')
                            ->label(__('Description'))
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Active'))
                            ->default(true)
                            ->helperText(__('Deactivate to hide this service from buyers')),
                    ])
                    ->columns(2),

                // Extended Artist Pricing
                Section::make(__('Extended Artist Pricing'))
                    ->icon('heroicon-o-sparkles')
                    ->description(__('Abonament lunar pentru artiști + trial gratuit'))
                    ->statePath('pricing')
                    ->schema([
                        Forms\Components\TextInput::make('monthly')
                            ->label(__('Cost lunar (RON)'))
                            ->numeric()
                            ->step(0.01)
                            ->helperText(__('Pretul facturat lunar dupa expirarea trial-ului')),

                        Forms\Components\TextInput::make('trial_days')
                            ->label(__('Zile trial gratuit'))
                            ->numeric()
                            ->step(1)
                            ->minValue(0)
                            ->helperText(__('0 = fără trial. Default: 30 zile.')),

                        Forms\Components\TextInput::make('currency')
                            ->label(__('Moneda'))
                            ->default('RON')
                            ->maxLength(3),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record?->code === ServiceType::CODE_EXTENDED_ARTIST),

                // Featuring Pricing
                Section::make(__('Featuring Pricing'))
                    ->icon('heroicon-o-star')
                    ->description(__('Prices per day for featuring an event on different pages'))
                    ->statePath('pricing')
                    ->schema([
                        Forms\Components\TextInput::make('home_hero')
                            ->label(__('Prima pagina - Hero (RON/zi)'))
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('home_recommendations')
                            ->label(__('Prima pagina - Recomandari (RON/zi)'))
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('category')
                            ->label(__('Pagina categorie eveniment (RON/zi)'))
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('city')
                            ->label(__('Pagina oras eveniment (RON/zi)'))
                            ->numeric()
                            ->step(0.01),
                    ])
                    ->columns(4)
                    // location_featuring (bilete.online only) uses the very same
                    // per-placement daily rates, so it reuses this section.
                    ->visible(fn ($record) => in_array($record?->code, ['featuring', ServiceType::CODE_LOCATION_FEATURING], true)),

                // Email Marketing Pricing
                Section::make(__('Email Marketing Pricing'))
                    ->icon('heroicon-o-envelope')
                    ->description(__('Prices per email for different audience types'))
                    ->statePath('pricing')
                    ->schema([
                        Forms\Components\TextInput::make('own_per_email')
                            ->label(__('Own Customers (RON/email)'))
                            ->numeric()
                            ->step(0.01)
                            ->helperText(__('Price for emailing the organizer\'s own customers')),

                        Forms\Components\TextInput::make('marketplace_per_email')
                            ->label(__('Marketplace Database (RON/email)'))
                            ->numeric()
                            ->step(0.01)
                            ->helperText(__('Price for emailing the marketplace\'s user base')),

                        Forms\Components\TextInput::make('minimum')
                            ->label(__('Minimum Order (emails)'))
                            ->numeric()
                            ->helperText(__('Minimum number of emails per order')),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record?->code === 'email'),

                // Ad Tracking Pricing
                Section::make(__('Ad Tracking Pricing'))
                    ->icon('heroicon-o-chart-bar')
                    ->description(__('Multiple pricing tiers for ad tracking integration'))
                    ->statePath('pricing')
                    ->schema([
                        // The keys the price calculation and the organizer page read
                        // (ServiceOrderController::calculatePrice): a monthly price per
                        // platform and a discount per duration (0.10 = 10%).
                        Forms\Components\TextInput::make('per_platform_monthly')
                            ->label(__('Cost lunar / platformă (RON, fără TVA)'))
                            ->numeric()
                            ->step(0.01)
                            ->helperText(__('Preț per platformă (Facebook, Google, TikTok) pe lună')),

                        Forms\Components\TextInput::make('discounts.3')
                            ->label(__('Reducere 3 luni'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->helperText(__('Fracție: 0.10 = 10%')),

                        Forms\Components\TextInput::make('discounts.6')
                            ->label(__('Reducere 6 luni'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->helperText(__('Fracție: 0.15 = 15%')),

                        Forms\Components\TextInput::make('discounts.12')
                            ->label(__('Reducere 12 luni'))
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0)
                            ->maxValue(1)
                            ->helperText(__('Fracție: 0.25 = 25%')),
                    ])
                    ->columns(4)
                    ->visible(fn ($record) => $record?->code === 'tracking'),

                // Campaign Creation Pricing
                Section::make(__('Campaign Creation Pricing'))
                    ->icon('heroicon-o-megaphone')
                    ->description(__('Prices for professional ad campaign creation packages'))
                    ->statePath('pricing')
                    ->schema([
                        Forms\Components\TextInput::make('basic')
                            ->label(__('Basic Package (RON)'))
                            ->numeric()
                            ->step(0.01)
                            ->helperText(__('Basic campaign setup')),

                        Forms\Components\TextInput::make('standard')
                            ->label(__('Standard Package (RON)'))
                            ->numeric()
                            ->step(0.01)
                            ->helperText(__('Standard campaign with optimization')),

                        Forms\Components\TextInput::make('premium')
                            ->label(__('Premium Package (RON)'))
                            ->numeric()
                            ->step(0.01)
                            ->helperText(__('Full-service campaign management')),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record?->code === 'campaign'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Service'))
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label(__('Type'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'featuring' => 'primary',
                        'email' => 'success',
                        'tracking' => 'info',
                        'campaign' => 'warning',
                        'extended_artist' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('audience')
                    ->label(__('Audience'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        ServiceType::AUDIENCE_ORGANIZER => 'gray',
                        ServiceType::AUDIENCE_ARTIST => 'purple',
                        ServiceType::AUDIENCE_BOTH => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?string $state) => match ($state) {
                        ServiceType::AUDIENCE_ORGANIZER => 'Organizator',
                        ServiceType::AUDIENCE_ARTIST => 'Artist',
                        ServiceType::AUDIENCE_BOTH => 'Ambele',
                        default => $state ?? '—',
                    }),

                Tables\Columns\TextColumn::make('pricing_summary')
                    ->label(__('Pricing (fără TVA)'))
                    ->getStateUsing(function (ServiceType $record): string {
                        $pricing = $record->pricing;
                        return match ($record->code) {
                            'featuring', ServiceType::CODE_LOCATION_FEATURING => sprintf(
                                'Hero: %d RON/zi, Rec: %d RON/zi, Cat: %d RON/zi, Oras: %d RON/zi',
                                $pricing['home_hero'] ?? ($pricing['home'] ?? 0),
                                $pricing['home_recommendations'] ?? ($pricing['genre'] ?? 0),
                                $pricing['category'] ?? 0,
                                $pricing['city'] ?? 0
                            ),
                            'email' => sprintf(
                                'Own: %.2f RON/email, Marketplace: %.2f RON/email',
                                $pricing['own_per_email'] ?? 0,
                                $pricing['marketplace_per_email'] ?? 0
                            ),
                            'tracking' => sprintf(
                                '%s RON/lună/platformă · reduceri 3/6/12 luni: %s%% / %s%% / %s%%',
                                $pricing['per_platform_monthly'] ?? $pricing['monthly'] ?? 0,
                                round(((float) ($pricing['discounts']['3'] ?? 0)) * 100),
                                round(((float) ($pricing['discounts']['6'] ?? 0)) * 100),
                                round(((float) ($pricing['discounts']['12'] ?? 0)) * 100)
                            ),
                            'campaign' => sprintf(
                                'Basic: %d RON, Premium: %d RON',
                                $pricing['basic'] ?? 0,
                                $pricing['premium'] ?? 0
                            ),
                            'extended_artist' => sprintf(
                                'Lunar: %d %s · Trial: %d zile',
                                $pricing['monthly'] ?? 0,
                                $pricing['currency'] ?? 'RON',
                                $pricing['trial_days'] ?? 0
                            ),
                            default => 'N/A',
                        };
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Active'))
                    ->boolean(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label(__('Orders'))
                    ->counts('orders')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('code')
                    ->label(__('Service Type'))
                    ->options([
                        'featuring' => 'Featuring',
                        // bilete.online only (activities module).
                        ServiceType::CODE_LOCATION_FEATURING => 'Promovare locatie',
                        'email' => 'Email Marketing',
                        'tracking' => 'Ad Tracking',
                        'campaign' => 'Campaign Creation',
                        'extended_artist' => 'Extended Artist',
                    ]),

                Tables\Filters\SelectFilter::make('audience')
                    ->label(__('Audience'))
                    ->options([
                        ServiceType::AUDIENCE_ORGANIZER => 'Organizator',
                        ServiceType::AUDIENCE_ARTIST => 'Artist',
                        ServiceType::AUDIENCE_BOTH => 'Ambele',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Active')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([])
            ->defaultSort('code', 'asc')
            ->paginated(false);
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
            'index' => Pages\ListServiceTypes::route('/'),
            'edit' => Pages\EditServiceType::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Service types are auto-created, no manual creation
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false; // Service types should not be deleted
    }
}
