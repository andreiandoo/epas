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
                Section::make('Service Information')
                    ->icon('heroicon-o-information-circle')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Service Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('audience')
                            ->label('Audience')
                            ->options([
                                ServiceType::AUDIENCE_ORGANIZER => 'Organizator',
                                ServiceType::AUDIENCE_ARTIST => 'Artist',
                                ServiceType::AUDIENCE_BOTH => 'Ambele',
                            ])
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Cine poate cumpara acest serviciu (read-only).'),

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Deactivate to hide this service from buyers'),
                    ])
                    ->columns(2),

                // Extended Artist Pricing
                Section::make('Extended Artist Pricing')
                    ->icon('heroicon-o-sparkles')
                    ->description('Abonament lunar pentru artiști + trial gratuit')
                    ->statePath('pricing')
                    ->schema([
                        Forms\Components\TextInput::make('monthly')
                            ->label('Cost lunar (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Pretul facturat lunar dupa expirarea trial-ului'),

                        Forms\Components\TextInput::make('trial_days')
                            ->label('Zile trial gratuit')
                            ->numeric()
                            ->step(1)
                            ->minValue(0)
                            ->helperText('0 = fără trial. Default: 30 zile.'),

                        Forms\Components\TextInput::make('currency')
                            ->label('Moneda')
                            ->default('RON')
                            ->maxLength(3),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record?->code === ServiceType::CODE_EXTENDED_ARTIST),

                // Featuring Pricing
                Section::make('Featuring Pricing')
                    ->icon('heroicon-o-star')
                    ->description('Prices per day for featuring an event on different pages')
                    ->statePath('pricing')
                    ->schema([
                        Forms\Components\TextInput::make('home_hero')
                            ->label('Prima pagina - Hero (RON/zi)')
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('home_recommendations')
                            ->label('Prima pagina - Recomandari (RON/zi)')
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('category')
                            ->label('Pagina categorie eveniment (RON/zi)')
                            ->numeric()
                            ->step(0.01),

                        Forms\Components\TextInput::make('city')
                            ->label('Pagina oras eveniment (RON/zi)')
                            ->numeric()
                            ->step(0.01),
                    ])
                    ->columns(4)
                    ->visible(fn ($record) => $record?->code === 'featuring'),

                // Email Marketing Pricing
                Section::make('Email Marketing Pricing')
                    ->icon('heroicon-o-envelope')
                    ->description('Prices per email for different audience types')
                    ->statePath('pricing')
                    ->schema([
                        Forms\Components\TextInput::make('own_per_email')
                            ->label('Own Customers (RON/email)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Price for emailing the organizer\'s own customers'),

                        Forms\Components\TextInput::make('marketplace_per_email')
                            ->label('Marketplace Database (RON/email)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Price for emailing the marketplace\'s user base'),

                        Forms\Components\TextInput::make('minimum')
                            ->label('Minimum Order (emails)')
                            ->numeric()
                            ->helperText('Minimum number of emails per order'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record?->code === 'email'),

                // Ad Tracking Pricing
                Section::make('Ad Tracking Pricing')
                    ->icon('heroicon-o-chart-bar')
                    ->description('Multiple pricing tiers for ad tracking integration')
                    ->statePath('pricing')
                    ->schema([
                        Forms\Components\TextInput::make('monthly')
                            ->label('Cost lunar (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Preț per lună, facturat lunar'),

                        Forms\Components\TextInput::make('biannual')
                            ->label('Cost bianual (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Preț total pentru 6 luni'),

                        Forms\Components\TextInput::make('annual')
                            ->label('Cost anual (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Preț total pentru 12 luni'),

                        Forms\Components\TextInput::make('one_time')
                            ->label('Cost one-time (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Plată unică, acces permanent'),
                    ])
                    ->columns(4)
                    ->visible(fn ($record) => $record?->code === 'tracking'),

                // Campaign Creation Pricing
                Section::make('Campaign Creation Pricing')
                    ->icon('heroicon-o-megaphone')
                    ->description('Prices for professional ad campaign creation packages')
                    ->statePath('pricing')
                    ->schema([
                        Forms\Components\TextInput::make('basic')
                            ->label('Basic Package (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Basic campaign setup'),

                        Forms\Components\TextInput::make('standard')
                            ->label('Standard Package (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Standard campaign with optimization'),

                        Forms\Components\TextInput::make('premium')
                            ->label('Premium Package (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->helperText('Full-service campaign management'),
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
                    ->label('Service')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Type')
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
                    ->label('Audience')
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
                    ->label('Pricing')
                    ->getStateUsing(function (ServiceType $record): string {
                        $pricing = $record->pricing;
                        return match ($record->code) {
                            'featuring' => sprintf(
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
                                'Lunar: %d, Bianual: %d, Anual: %d, One-time: %d RON',
                                $pricing['monthly'] ?? $pricing['per_platform_monthly'] ?? 0,
                                $pricing['biannual'] ?? 0,
                                $pricing['annual'] ?? 0,
                                $pricing['one_time'] ?? 0
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
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('code')
                    ->label('Service Type')
                    ->options([
                        'featuring' => 'Featuring',
                        'email' => 'Email Marketing',
                        'tracking' => 'Ad Tracking',
                        'campaign' => 'Campaign Creation',
                        'extended_artist' => 'Extended Artist',
                    ]),

                Tables\Filters\SelectFilter::make('audience')
                    ->label('Audience')
                    ->options([
                        ServiceType::AUDIENCE_ORGANIZER => 'Organizator',
                        ServiceType::AUDIENCE_ARTIST => 'Artist',
                        ServiceType::AUDIENCE_BOTH => 'Ambele',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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
