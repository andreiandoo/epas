<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\MarketplaceCustomerResource\Pages;
use App\Filament\Marketplace\Resources\OrderResource;
use App\Filament\Marketplace\Resources\TicketResource;
use App\Models\MarketplaceCustomer;
use Filament\Actions\Action;
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
    protected static ?string $navigationLabel = 'Clienți';
    protected static ?string $modelLabel = 'User';
    protected static ?string $pluralModelLabel = 'Clienți';

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    SC\Section::make('Account Information')
                        ->icon('heroicon-o-user-circle')
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
                        ->icon('heroicon-o-user')
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
                        ->icon('heroicon-o-map-pin')
                        ->collapsible()
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

                    SC\Section::make('Notification Preferences')
                        ->icon('heroicon-o-bell')
                        ->description('User notification settings')
                        ->collapsible()
                        ->visibleOn('edit')
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
                        ])->columns(2),

                    SC\Section::make('Favorites')
                        ->icon('heroicon-o-heart')
                        ->description('Favorite events, artists, and venues')
                        ->collapsible()
                        ->collapsed()
                        ->visibleOn('edit')
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
                        ->columns(1),
                ]),
                SC\Group::make()->columnSpan(1)->schema([
                    // Customer Preview Card (doar pe Edit)
                    SC\Section::make('')
                        ->compact()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Placeholder::make('customer_preview')
                                ->hiddenLabel()
                                ->content(fn (?MarketplaceCustomer $record) => self::renderCustomerPreview($record)),
                        ]),

                    // Statistics (doar pe Edit)
                    SC\Section::make('Statistics')
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Placeholder::make('stats')
                                ->hiddenLabel()
                                ->content(fn (?MarketplaceCustomer $record) => self::renderStatistics($record)),
                        ]),

                    // Saved Payment Methods (doar pe Edit)
                    SC\Section::make('Payment Methods')
                        ->icon('heroicon-o-credit-card')
                        ->compact()
                        ->collapsible()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Placeholder::make('payment_methods_list')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderPaymentMethods($record)),
                        ]),

                    // Quick Actions (doar pe Edit) - stacked vertically with fullWidth
                    SC\Section::make('Quick Actions')
                        ->icon('heroicon-o-bolt')
                        ->compact()
                        ->visibleOn('edit')
                        ->schema([
                            SC\Actions::make([
                                Action::make('view_orders')
                                    ->label('View Orders')
                                    ->icon('heroicon-o-shopping-bag')
                                    ->color('gray')
                                    ->url(fn ($record) => OrderResource::getUrl('index', ['customer' => $record->id])),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('view_tickets')
                                    ->label('View Tickets')
                                    ->icon('heroicon-o-ticket')
                                    ->color('gray')
                                    ->url(fn ($record) => TicketResource::getUrl('index', ['customer' => $record->id])),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('send_email')
                                    ->label('Send Email')
                                    ->icon('heroicon-o-envelope')
                                    ->color('success')
                                    ->url(fn ($record) => "mailto:{$record->email}"),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('suspend')
                                    ->label('Suspend User')
                                    ->icon('heroicon-o-x-circle')
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->visible(fn ($record) => $record->status === 'active')
                                    ->action(fn ($record) => $record->update(['status' => 'suspended'])),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('reactivate')
                                    ->label('Reactivate User')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('success')
                                    ->visible(fn ($record) => $record->status === 'suspended')
                                    ->action(fn ($record) => $record->update(['status' => 'active'])),
                            ])->fullWidth(),
                        ])->columns(1),

                    // Meta Info (doar pe Edit, collapsed)
                    SC\Section::make('Meta Info')
                        ->icon('heroicon-o-information-circle')
                        ->compact()
                        ->collapsible()
                        ->collapsed()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Placeholder::make('meta_info')
                                ->hiddenLabel()
                                ->content(fn (?MarketplaceCustomer $record) => self::renderMetaInfo($record)),
                        ]),
                ]),
            ]), 
        ])->columns(1);
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

                Tables\Filters\TernaryFilter::make('imported')
                    ->label('Importat AmBilet')
                    ->trueLabel('Doar importați')
                    ->falseLabel('Doar noi')
                    ->queries(
                        true: fn (Builder $query) => $query->whereRaw("JSON_EXTRACT(settings, '$.imported_from') = ?", ['ambilet']),
                        false: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNull('settings')
                                ->orWhereRaw("JSON_EXTRACT(settings, '$.imported_from') IS NULL");
                        }),
                    ),
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

    protected static function renderCustomerPreview(?MarketplaceCustomer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        $initials = mb_substr($record->first_name ?? '', 0, 1) . mb_substr($record->last_name ?? '', 0, 1);
        $fullName = trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: 'Unknown';

        $statusBadge = match($record->status) {
            'active' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #10B981;">✓ Active</span>',
            'suspended' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #EF4444;">✕ Suspended</span>',
            default => '',
        };

        $verifiedBadge = $record->email_verified_at 
            ? '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(59, 130, 246, 0.15); color: #60A5FA;">✓ Verified</span>'
            : '';

        $typeBadge = $record->isGuest()
            ? '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: #334155; color: #E2E8F0;">Guest</span>'
            : '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(139, 92, 246, 0.15); color: #A78BFA;">Registered</span>';

        return new HtmlString("
            <div style='display: flex; gap: 12px; align-items: center; margin-bottom: 12px;'>
                <div style='width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #6366F1, #8B5CF6); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; color: white;'>{$initials}</div>
                <div style='flex: 1;'>
                    <div style='font-size: 16px; font-weight: 700; color: white;'>" . e($fullName) . "</div>
                    <div style='font-size: 12px; color: #64748B;'>" . e($record->email) . "</div>
                </div>
            </div>
            <div style='display: flex; flex-wrap: wrap; gap: 6px;'>
                {$statusBadge}
                {$verifiedBadge}
                {$typeBadge}
            </div>
        ");
    }

    protected static function renderStatistics(?MarketplaceCustomer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        $lastLogin = $record->last_login_at?->diffForHumans() ?? 'Never';
        $registered = $record->created_at?->format('d M Y') ?? '-';

        return new HtmlString("
            <div style='display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px;'>
                <div style='background: #0F172A; border-radius: 8px; padding: 12px; text-align: center;'>
                    <div style='font-size: 18px; font-weight: 700; color: white;'>{$record->total_orders}</div>
                    <div style='font-size: 10px; color: #64748B; text-transform: uppercase;'>Total Orders</div>
                </div>
                <div style='background: #0F172A; border-radius: 8px; padding: 12px; text-align: center;'>
                    <div style='font-size: 16px; font-weight: 700; color: white;'>" . number_format($record->total_spent, 2) . " RON</div>
                    <div style='font-size: 10px; color: #64748B; text-transform: uppercase;'>Total Spent</div>
                </div>
                <div style='background: #0F172A; border-radius: 8px; padding: 12px; text-align: center;'>
                    <div style='font-size: 14px; font-weight: 700; color: #60A5FA;'>{$lastLogin}</div>
                    <div style='font-size: 10px; color: #64748B; text-transform: uppercase;'>Last Login</div>
                </div>
                <div style='background: #0F172A; border-radius: 8px; padding: 12px; text-align: center;'>
                    <div style='font-size: 12px; font-weight: 700; color: white;'>{$registered}</div>
                    <div style='font-size: 10px; color: #64748B; text-transform: uppercase;'>Registered</div>
                </div>
            </div>
        ");
    }

    protected static function renderPaymentMethods(?MarketplaceCustomer $record): HtmlString
    {
        if (!$record) return new HtmlString('<p style="color: #64748B; text-align: center;">Save the record first.</p>');

        $paymentMethods = $record->activePaymentMethods;

        if ($paymentMethods->isEmpty()) {
            return new HtmlString('
                <div style="text-align: center; padding: 16px; color: #64748B;">
                    <div style="margin-bottom: 8px;">💳</div>
                    <div>No saved payment methods.</div>
                </div>
            ');
        }

        $html = '';
        foreach ($paymentMethods as $pm) {
            $brand = ucfirst($pm->card_brand ?? 'Card');
            $defaultBadge = $pm->is_default 
                ? '<span style="margin-left: 8px; padding: 2px 8px; background: rgba(16, 185, 129, 0.15); color: #10B981; border-radius: 10px; font-size: 10px;">Default</span>' 
                : '';
            $expiredBadge = $pm->isExpired() 
                ? '<span style="margin-left: 8px; padding: 2px 8px; background: rgba(239, 68, 68, 0.15); color: #EF4444; border-radius: 10px; font-size: 10px;">Expired</span>' 
                : '';

            $html .= "
                <div style='display: flex; align-items: center; gap: 12px; padding: 12px; background: #0F172A; border-radius: 8px; margin-bottom: 8px;'>
                    <div style='width: 40px; height: 28px; background: linear-gradient(135deg, #1E40AF, #3B82F6); border-radius: 4px; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; color: white;'>💳</div>
                    <div style='flex: 1;'>
                        <div style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$brand} •••• " . e($pm->card_last_four) . "{$defaultBadge}{$expiredBadge}</div>
                        <div style='font-size: 11px; color: #64748B;'>Expires " . e($pm->expiry_date ?? 'N/A') . "</div>
                    </div>
                </div>
            ";
        }

        return new HtmlString($html);
    }

    protected static function renderWatchlistEvents(?MarketplaceCustomer $record): HtmlString
    {
        if (!$record) return new HtmlString('-');

        $events = $record->watchlistEvents;

        if ($events->isEmpty()) {
            return new HtmlString('<p style="color: #64748B;">No watchlist events.</p>');
        }

        $html = '';
        foreach ($events->take(10) as $event) {
            $title = is_array($event->title) ? ($event->title['en'] ?? $event->title['ro'] ?? reset($event->title)) : $event->title;
            $date = $event->event_date ? \Carbon\Carbon::parse($event->event_date)->format('d M Y') : '';

            $html .= "
                <div style='display: flex; align-items: center; gap: 8px; padding: 10px 12px; background: #0F172A; border-radius: 8px; margin-bottom: 8px;'>
                    <span style='font-size: 13px; font-weight: 500; color: #E2E8F0; flex: 1;'>" . e($title) . "</span>
                    <span style='font-size: 12px; color: #64748B;'>({$date})</span>
                </div>
            ";
        }

        if ($events->count() > 10) {
            $html .= "<p style='font-size: 12px; color: #64748B;'>+ " . ($events->count() - 10) . " more</p>";
        }

        return new HtmlString($html);
    }

    protected static function renderFavoriteArtists(?MarketplaceCustomer $record): HtmlString
    {
        if (!$record) return new HtmlString('-');

        $artists = $record->favoriteArtists;

        if ($artists->isEmpty()) {
            return new HtmlString('<p style="color: #64748B;">No favorite artists.</p>');
        }

        $html = '<div style="display: flex; flex-wrap: wrap; gap: 6px;">';
        foreach ($artists->take(20) as $artist) {
            $name = is_array($artist->name) ? ($artist->name['en'] ?? $artist->name['ro'] ?? reset($artist->name)) : $artist->name;
            $html .= '<span style="padding: 6px 12px; background: #334155; border-radius: 20px; font-size: 12px; color: #E2E8F0;">' . e($name) . '</span>';
        }
        if ($artists->count() > 20) {
            $html .= '<span style="padding: 6px 12px; font-size: 12px; color: #64748B;">+ ' . ($artists->count() - 20) . ' more</span>';
        }
        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function renderFavoriteVenues(?MarketplaceCustomer $record): HtmlString
    {
        if (!$record) return new HtmlString('-');

        $venues = $record->favoriteVenues;

        if ($venues->isEmpty()) {
            return new HtmlString('<p style="color: #64748B;">No favorite venues.</p>');
        }

        $html = '<div style="display: flex; flex-wrap: wrap; gap: 6px;">';
        foreach ($venues->take(20) as $venue) {
            $name = is_array($venue->name) ? ($venue->name['en'] ?? $venue->name['ro'] ?? reset($venue->name)) : $venue->name;
            $city = $venue->city ?? '';
            $label = $city ? "{$name} ({$city})" : $name;
            $html .= '<span style="padding: 6px 12px; background: #334155; border-radius: 20px; font-size: 12px; color: #E2E8F0;">' . e($label) . '</span>';
        }
        if ($venues->count() > 20) {
            $html .= '<span style="padding: 6px 12px; font-size: 12px; color: #64748B;">+ ' . ($venues->count() - 20) . ' more</span>';
        }
        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function renderMetaInfo(?MarketplaceCustomer $record): HtmlString
    {
        if (!$record) return new HtmlString('');

        $createdAt = $record->created_at?->format('d M Y H:i') ?? '-';
        $updatedAt = $record->updated_at?->diffForHumans() ?? '-';
        $lastLogin = $record->last_login_at?->diffForHumans() ?? 'Never';

        return new HtmlString("
            <div>
                <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #64748B;'>Created</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$createdAt}</span>
                </div>
                <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #64748B;'>Updated</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$updatedAt}</span>
                </div>
                <div style='display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #64748B;'>Last Login</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$lastLogin}</span>
                </div>
                <div style='display: flex; justify-content: space-between; padding: 8px 0;'>
                    <span style='font-size: 13px; color: #64748B;'>ID</span>
                    <span style='font-size: 11px; font-weight: 600; color: #64748B; font-family: monospace;'>{$record->id}</span>
                </div>
            </div>
        ");
    }
}
