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

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

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

                        Forms\Components\Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Deactivate to hide this service from organizers'),
                    ])
                    ->columns(2),

                // Featuring Pricing
                Section::make('Featuring Pricing')
                    ->icon('heroicon-o-star')
                    ->description('Prices per day for featuring an event on different pages')
                    ->schema([
                        Forms\Components\TextInput::make('pricing.home_hero')
                            ->label('Prima pagina - Hero (RON/zi)')
                            ->numeric()
                            ->step(0.01)
                            ->default(120),

                        Forms\Components\TextInput::make('pricing.home_recommendations')
                            ->label('Prima pagina - Recomandari (RON/zi)')
                            ->numeric()
                            ->step(0.01)
                            ->default(80),

                        Forms\Components\TextInput::make('pricing.category')
                            ->label('Pagina categorie eveniment (RON/zi)')
                            ->numeric()
                            ->step(0.01)
                            ->default(60),

                        Forms\Components\TextInput::make('pricing.city')
                            ->label('Pagina oras eveniment (RON/zi)')
                            ->numeric()
                            ->step(0.01)
                            ->default(40),
                    ])
                    ->columns(4)
                    ->visible(fn ($record) => $record?->code === 'featuring'),

                // Email Marketing Pricing
                Section::make('Email Marketing Pricing')
                    ->icon('heroicon-o-envelope')
                    ->description('Prices per email for different audience types')
                    ->schema([
                        Forms\Components\TextInput::make('pricing.own_per_email')
                            ->label('Own Customers (RON/email)')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.40)
                            ->helperText('Price for emailing the organizer\'s own customers'),

                        Forms\Components\TextInput::make('pricing.marketplace_per_email')
                            ->label('Marketplace Database (RON/email)')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.50)
                            ->helperText('Price for emailing the marketplace\'s user base'),

                        Forms\Components\TextInput::make('pricing.minimum')
                            ->label('Minimum Order (emails)')
                            ->numeric()
                            ->default(100)
                            ->helperText('Minimum number of emails per order'),
                    ])
                    ->columns(3)
                    ->visible(fn ($record) => $record?->code === 'email'),

                // Ad Tracking Pricing
                Section::make('Ad Tracking Pricing')
                    ->icon('heroicon-o-chart-bar')
                    ->description('Prices for ad platform tracking integration')
                    ->schema([
                        Forms\Components\TextInput::make('pricing.per_platform_monthly')
                            ->label('Per Platform Monthly (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->default(49)
                            ->helperText('Monthly price per tracking platform (Facebook, Google, TikTok)')
                            ->columnSpanFull(),

                        Forms\Components\Placeholder::make('discounts_label')
                            ->label('')
                            ->content('Volume Discounts (by duration)')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('pricing.discounts.1')
                            ->label('1 Month Discount')
                            ->numeric()
                            ->step(0.01)
                            ->default(0)
                            ->suffix('%')
                            ->helperText('0 = no discount'),

                        Forms\Components\TextInput::make('pricing.discounts.3')
                            ->label('3 Months Discount')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.10)
                            ->suffix('%')
                            ->helperText('0.10 = 10%'),

                        Forms\Components\TextInput::make('pricing.discounts.6')
                            ->label('6 Months Discount')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.15)
                            ->suffix('%'),

                        Forms\Components\TextInput::make('pricing.discounts.12')
                            ->label('12 Months Discount')
                            ->numeric()
                            ->step(0.01)
                            ->default(0.25)
                            ->suffix('%'),
                    ])
                    ->columns(4)
                    ->visible(fn ($record) => $record?->code === 'tracking'),

                // Campaign Creation Pricing
                Section::make('Campaign Creation Pricing')
                    ->icon('heroicon-o-megaphone')
                    ->description('Prices for professional ad campaign creation packages')
                    ->schema([
                        Forms\Components\TextInput::make('pricing.basic')
                            ->label('Basic Package (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->default(499)
                            ->helperText('Basic campaign setup'),

                        Forms\Components\TextInput::make('pricing.standard')
                            ->label('Standard Package (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->default(899)
                            ->helperText('Standard campaign with optimization'),

                        Forms\Components\TextInput::make('pricing.premium')
                            ->label('Premium Package (RON)')
                            ->numeric()
                            ->step(0.01)
                            ->default(1499)
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
                        default => 'gray',
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
                                '%d RON/platform/month',
                                $pricing['per_platform_monthly'] ?? 0
                            ),
                            'campaign' => sprintf(
                                'Basic: %d RON, Premium: %d RON',
                                $pricing['basic'] ?? 0,
                                $pricing['premium'] ?? 0
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
