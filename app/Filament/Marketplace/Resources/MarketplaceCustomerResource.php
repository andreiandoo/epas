<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;
use App\Models\MarketplaceCustomer;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class MarketplaceCustomerResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceCustomer::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Customers';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Registered Users';
    protected static ?string $modelLabel = 'User';
    protected static ?string $pluralModelLabel = 'Registered Users';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Account Information')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                            ])
                            ->required()
                            ->default('active'),

                        Forms\Components\Toggle::make('email_verified_at')
                            ->label('Email Verified')
                            ->formatStateUsing(fn ($state) => $state !== null)
                            ->dehydrateStateUsing(fn ($state) => $state ? now() : null)
                            ->helperText('Mark if email is manually verified'),

                        Forms\Components\Toggle::make('accepts_marketing')
                            ->label('Accepts Marketing')
                            ->helperText('Customer has consented to marketing communications'),
                    ])->columns(2),

                SC\Section::make('Personal Details')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('First Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('last_name')
                            ->label('Last Name')
                            ->required()
                            ->maxLength(100),

                        Forms\Components\TextInput::make('phone')
                            ->label('Phone')
                            ->tel()
                            ->maxLength(50),

                        Forms\Components\DatePicker::make('birth_date')
                            ->label('Birth Date'),

                        Forms\Components\Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ]),

                        Forms\Components\Select::make('locale')
                            ->label('Language')
                            ->options([
                                'en' => 'English',
                                'ro' => 'Romanian',
                                'de' => 'German',
                                'fr' => 'French',
                                'es' => 'Spanish',
                            ]),
                    ])->columns(2),

                SC\Section::make('Address')
                    ->schema([
                        Forms\Components\TextInput::make('address')
                            ->label('Street Address')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('city')
                            ->label('City')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->label('State/Province')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('postal_code')
                            ->label('Postal Code')
                            ->maxLength(20),

                        Forms\Components\TextInput::make('country')
                            ->label('Country Code')
                            ->maxLength(2)
                            ->helperText('ISO 2-letter code (e.g., RO, US, DE)'),
                    ])->columns(2)
                    ->collapsed(),

                SC\Section::make('Statistics')
                    ->schema([
                        Forms\Components\Placeholder::make('total_orders_display')
                            ->label('Total Orders')
                            ->content(fn ($record) => $record?->total_orders ?? 0),

                        Forms\Components\Placeholder::make('total_spent_display')
                            ->label('Total Spent')
                            ->content(fn ($record) => number_format($record?->total_spent ?? 0, 2) . ' RON'),

                        Forms\Components\Placeholder::make('last_login_display')
                            ->label('Last Login')
                            ->content(fn ($record) => $record?->last_login_at?->diffForHumans() ?? 'Never'),

                        Forms\Components\Placeholder::make('created_at_display')
                            ->label('Registered')
                            ->content(fn ($record) => $record?->created_at?->format('d M Y H:i') ?? '-'),
                    ])->columns(4)
                    ->visibleOn('edit'),

                SC\Section::make('Notification Preferences')
                    ->icon('heroicon-o-bell')
                    ->description('User notification settings')
                    ->schema([
                        Forms\Components\Toggle::make('settings.notification_preferences.reminders')
                            ->label('Event Reminders')
                            ->helperText('Receive reminder 24h before events')
                            ->default(true),

                        Forms\Components\Toggle::make('settings.notification_preferences.newsletter')
                            ->label('Newsletter & Offers')
                            ->helperText('Receive information about new events and special offers')
                            ->default(true),

                        Forms\Components\Toggle::make('settings.notification_preferences.favorites')
                            ->label('Favorite Updates')
                            ->helperText('Receive notifications when favorite events are approaching')
                            ->default(true),

                        Forms\Components\Toggle::make('settings.notification_preferences.history')
                            ->label('Browsing History')
                            ->helperText('Save viewed events for personalized recommendations')
                            ->default(true),

                        Forms\Components\Toggle::make('settings.notification_preferences.marketing')
                            ->label('Marketing Cookies')
                            ->helperText('Allow display of personalized ads')
                            ->default(false),
                    ])->columns(2)
                    ->collapsible()
                    ->visibleOn('edit'),

                SC\Section::make('Saved Payment Methods')
                    ->icon('heroicon-o-credit-card')
                    ->description('Saved cards and payment methods')
                    ->schema([
                        Forms\Components\Placeholder::make('payment_methods_list')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) {
                                    return 'Save the record first to see payment methods.';
                                }

                                $paymentMethods = $record->activePaymentMethods;

                                if ($paymentMethods->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500 dark:text-gray-400">No saved payment methods.</p>');
                                }

                                $html = '<div class="space-y-2">';
                                foreach ($paymentMethods as $pm) {
                                    $brand = ucfirst($pm->card_brand ?? 'Card');
                                    $isDefault = $pm->is_default ? '<span class="ml-2 px-2 py-0.5 text-xs rounded bg-primary-100 text-primary-700 dark:bg-primary-800 dark:text-primary-300">Default</span>' : '';
                                    $expired = $pm->isExpired() ? '<span class="ml-2 px-2 py-0.5 text-xs rounded bg-danger-100 text-danger-700 dark:bg-danger-800 dark:text-danger-300">Expired</span>' : '';

                                    $html .= '<div class="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">';
                                    $html .= '<div class="flex-1">';
                                    $html .= '<div class="font-medium">' . e($brand) . ' •••• ' . e($pm->card_last_four) . $isDefault . $expired . '</div>';
                                    $html .= '<div class="text-sm text-gray-500 dark:text-gray-400">';
                                    $html .= 'Expires ' . e($pm->expiry_date ?? 'N/A');
                                    if ($pm->cardholder_name) {
                                        $html .= ' • ' . e($pm->cardholder_name);
                                    }
                                    $html .= '</div>';
                                    $html .= '</div>';
                                    $html .= '</div>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visibleOn('edit'),

                SC\Section::make('Favorites')
                    ->icon('heroicon-o-heart')
                    ->description('Favorite events, artists, and venues')
                    ->schema([
                        Forms\Components\Placeholder::make('watchlist_events_list')
                            ->label('Watchlist Events')
                            ->content(function ($record) {
                                if (!$record) {
                                    return '-';
                                }

                                $events = $record->watchlistEvents;

                                if ($events->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500 dark:text-gray-400">No watchlist events.</p>');
                                }

                                $html = '<div class="space-y-1">';
                                foreach ($events->take(10) as $event) {
                                    $title = is_array($event->title) ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title)) : $event->title;
                                    $date = $event->event_date ? \Carbon\Carbon::parse($event->event_date)->format('d M Y') : '';
                                    $html .= '<div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-800 rounded">';
                                    $html .= '<span class="font-medium">' . e($title) . '</span>';
                                    if ($date) {
                                        $html .= '<span class="text-sm text-gray-500">(' . $date . ')</span>';
                                    }
                                    $html .= '</div>';
                                }
                                if ($events->count() > 10) {
                                    $html .= '<p class="text-sm text-gray-500">+ ' . ($events->count() - 10) . ' more</p>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),

                        Forms\Components\Placeholder::make('favorite_artists_list')
                            ->label('Favorite Artists')
                            ->content(function ($record) {
                                if (!$record) {
                                    return '-';
                                }

                                $artists = $record->favoriteArtists;

                                if ($artists->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500 dark:text-gray-400">No favorite artists.</p>');
                                }

                                $html = '<div class="flex flex-wrap gap-2">';
                                foreach ($artists->take(20) as $artist) {
                                    $name = is_array($artist->name) ? ($artist->name['en'] ?? $artist->name['ro'] ?? reset($artist->name)) : $artist->name;
                                    $html .= '<span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded-full text-sm">' . e($name) . '</span>';
                                }
                                if ($artists->count() > 20) {
                                    $html .= '<span class="px-3 py-1 text-sm text-gray-500">+ ' . ($artists->count() - 20) . ' more</span>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),

                        Forms\Components\Placeholder::make('favorite_venues_list')
                            ->label('Favorite Venues')
                            ->content(function ($record) {
                                if (!$record) {
                                    return '-';
                                }

                                $venues = $record->favoriteVenues;

                                if ($venues->isEmpty()) {
                                    return new HtmlString('<p class="text-gray-500 dark:text-gray-400">No favorite venues.</p>');
                                }

                                $html = '<div class="flex flex-wrap gap-2">';
                                foreach ($venues->take(20) as $venue) {
                                    $name = is_array($venue->name) ? ($venue->name['en'] ?? $venue->name['ro'] ?? reset($venue->name)) : $venue->name;
                                    $city = $venue->city ?? '';
                                    $label = $city ? "{$name} ({$city})" : $name;
                                    $html .= '<span class="px-3 py-1 bg-gray-100 dark:bg-gray-700 rounded-full text-sm">' . e($label) . '</span>';
                                }
                                if ($venues->count() > 20) {
                                    $html .= '<span class="px-3 py-1 text-sm text-gray-500">+ ' . ($venues->count() - 20) . ' more</span>';
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed()
                    ->visibleOn('edit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => $record->email_verified_at !== null),

                Tables\Columns\IconColumn::make('is_guest')
                    ->label('Type')
                    ->boolean()
                    ->trueIcon('heroicon-o-user')
                    ->falseIcon('heroicon-o-user-circle')
                    ->trueColor('gray')
                    ->falseColor('success')
                    ->getStateUsing(fn ($record) => $record->isGuest())
                    ->tooltip(fn ($record) => $record->isGuest() ? 'Guest (no password)' : 'Registered'),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Spent')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(
                fn (MarketplaceCustomer $record): string => static::getUrl('edit', ['record' => $record]),
            )
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\TernaryFilter::make('email_verified')
                    ->label('Email Verified')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                    ),

                Tables\Filters\TernaryFilter::make('is_guest')
                    ->label('Account Type')
                    ->trueLabel('Guest Only')
                    ->falseLabel('Registered Only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('password'),
                        false: fn (Builder $query) => $query->whereNotNull('password'),
                    ),

                Tables\Filters\TernaryFilter::make('has_orders')
                    ->label('Has Orders')
                    ->queries(
                        true: fn (Builder $query) => $query->where('total_orders', '>', 0),
                        false: fn (Builder $query) => $query->where('total_orders', 0),
                    ),

                Tables\Filters\TernaryFilter::make('accepts_marketing')
                    ->label('Marketing Consent'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        $marketplace = static::getMarketplaceClient();
        if (!$marketplace) {
            return null;
        }

        return static::getModel()::where('marketplace_client_id', $marketplace->id)->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMarketplaceCustomers::route('/'),
            'create' => Pages\CreateMarketplaceCustomer::route('/create'),
            'view' => Pages\ViewMarketplaceCustomer::route('/{record}'),
            'edit' => Pages\EditMarketplaceCustomer::route('/{record}/edit'),
        ];
    }
}
