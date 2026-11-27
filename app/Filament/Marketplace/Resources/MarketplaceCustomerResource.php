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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Support\MarketplaceTz;

class MarketplaceCustomerResource extends Resource
{
    use \App\Filament\Marketplace\Concerns\TranslatesResourceLabels;

    use HasMarketplaceContext;

    protected static ?string $model = MarketplaceCustomer::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
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
            // Import source badge
            Forms\Components\Placeholder::make('import_badge')
                ->hiddenLabel()
                ->visible(fn ($record) => !empty($record?->settings['imported_from'] ?? null))
                ->content(fn ($record) => new \Illuminate\Support\HtmlString(
                    '<div style="padding:8px 12px;background:rgba(99,102,241,0.15);border:1px solid rgba(99,102,241,0.3);border-radius:8px;font-size:13px;color:#818CF8;display:flex;align-items:center;gap:6px;">🌐 Client importat din: <strong>' . e($record->settings['imported_from'] ?? 'Extern') . '</strong></div>'
                )),

            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    SC\Section::make(__('Account Information'))
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(
                                    table: 'marketplace_customers',
                                    column: 'email',
                                    ignoreRecord: true,
                                    modifyRuleUsing: fn ($rule) => $rule->where('marketplace_client_id', static::getMarketplaceClient()?->id),
                                ),

                            Forms\Components\Select::make('status')
                                ->options([
                                    'active' => __('Active'),
                                    'suspended' => __('Suspended'),
                                ])
                                ->required()
                                ->default('active'),

                            Forms\Components\Toggle::make('email_verified_at')
                                ->label(__('Email Verified'))
                                ->formatStateUsing(fn ($state) => $state !== null)
                                ->dehydrateStateUsing(fn ($state) => $state ? now() : null)
                                ->helperText(__('Mark if email is manually verified')),

                            Forms\Components\Toggle::make('accepts_marketing')
                                ->label(__('Accepts Marketing'))
                                ->helperText(__('Customer has consented to marketing communications')),
                        ])->columns(2),

                    SC\Section::make(__('Personal Details'))
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\TextInput::make('first_name')
                                ->label(__('First Name'))
                                ->required()
                                ->maxLength(100),

                            Forms\Components\TextInput::make('last_name')
                                ->label(__('Last Name'))
                                ->required()
                                ->maxLength(100),

                            Forms\Components\TextInput::make('phone')
                                ->label(__('Phone'))
                                ->tel()
                                ->maxLength(50),

                            Forms\Components\DatePicker::make('birth_date')
                                ->label(__('Birth Date')),

                            Forms\Components\Select::make('gender')
                                ->options([
                                    'male' => __('Male'),
                                    'female' => __('Female'),
                                    'other' => __('Other'),
                                ]),

                            Forms\Components\Select::make('locale')
                                ->label(__('Language'))
                                ->options([
                                    'en' => __('English'),
                                    'ro' => __('Romanian'),
                                    'de' => __('German'),
                                    'fr' => __('French'),
                                    'es' => __('Spanish'),
                                ]),

                            Forms\Components\TextInput::make('new_password')
                                ->label(__('New Password'))
                                ->password()
                                ->revealable()
                                ->minLength(8)
                                ->dehydrated(false)
                                ->helperText(__('Leave empty to keep current password. A new password also applies to the organizer / venue account with the same email.')),

                            Forms\Components\TextInput::make('new_password_confirmation')
                                ->label(__('Confirm Password'))
                                ->password()
                                ->revealable()
                                ->dehydrated(false)
                                ->same('new_password'),
                        ])->columns(2),

                    SC\Section::make(__('Address'))
                        ->icon('heroicon-o-map-pin')
                        ->collapsible()
                        ->schema([
                            Forms\Components\TextInput::make('address')
                                ->label(__('Street Address'))
                                ->maxLength(255)
                                ->columnSpanFull(),

                            Forms\Components\TextInput::make('city')
                                ->label(__('City'))
                                ->maxLength(100),

                            Forms\Components\TextInput::make('state')
                                ->label(__('State/Province'))
                                ->maxLength(100),

                            Forms\Components\TextInput::make('postal_code')
                                ->label(__('Postal Code'))
                                ->maxLength(20),

                            Forms\Components\TextInput::make('country')
                                ->label(__('Country Code'))
                                ->maxLength(2)
                                ->helperText(__('ISO 2-letter code (e.g., RO, US, DE)')),
                        ])->columns(2)
                        ->collapsed(),

                    // Auto-detected city from order history. Separate from
                    // the user-editable Address section above — this one is
                    // read-only and driven by customers:detect-cities.
                    SC\Section::make(__('Oraș detectat (din comenzi)'))
                        ->icon('heroicon-o-map')
                        ->description(__('Determinat automat din istoricul de comenzi — ce orașe a frecventat pentru evenimente.'))
                        ->collapsible()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Placeholder::make('detected_city_summary')
                                ->label('')
                                ->content(function ($record) {
                                    $loc = data_get($record?->settings, 'detected_location');
                                    if (!is_array($loc) || empty($loc['distribution'])) {
                                        return new \Illuminate\Support\HtmlString(
                                            '<div class="text-sm text-gray-500 dark:text-gray-400">Niciun oraș detectat încă. Rulează <code class="bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">php artisan customers:detect-cities --marketplace=' . ($record?->marketplace_client_id ?? '1') . '</code> sau apasă butonul "Re-detectează" de mai jos.</div>'
                                        );
                                    }

                                    $determined = $loc['determined_city'] ?? null;
                                    $determinedAt = $loc['determined_at'] ?? null;
                                    $distribution = $loc['distribution'];

                                    $header = $determined
                                        ? '<div class="mb-3"><span class="text-xs uppercase tracking-wide text-gray-500">Oraș determinat</span><div class="text-2xl font-bold text-green-700 dark:text-green-400 mt-0.5">' . e($determined) . '</div></div>'
                                        : '<div class="mb-3"><span class="text-xs uppercase tracking-wide text-gray-500">Oraș determinat</span><div class="text-sm text-amber-700 dark:text-amber-400 mt-0.5 italic">Niciunul peste pragul de 85% — vezi distribuția.</div></div>';

                                    // Defensive translate in case an older
                                    // payload still has the raw Translatable
                                    // JSON {"ro":"...","en":"..."}.
                                    $translate = function ($raw): string {
                                        $raw = (string) $raw;
                                        if ($raw !== '' && $raw[0] === '{') {
                                            $d = json_decode($raw, true);
                                            if (is_array($d) && !empty($d)) {
                                                return (string) ($d['ro'] ?? $d['en'] ?? reset($d));
                                            }
                                        }
                                        return $raw ?: '?';
                                    };
                                    $determined = $determined ? $translate($determined) : null;

                                    $rows = '<div class="space-y-2">';
                                    foreach ($distribution as $entry) {
                                        $name = $translate($entry['city_name'] ?? '?');
                                        $orders = (int) ($entry['orders'] ?? 0);
                                        $pct = (float) ($entry['pct'] ?? 0);
                                        $barWidth = max(2, min(100, $pct));
                                        $highlight = ($determined && $name === $determined) ? 'bg-green-50 dark:bg-green-900/20 border-green-300 dark:border-green-700' : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700';
                                        $rows .= '<div class="border rounded-lg p-3 ' . $highlight . '">'
                                            . '<div class="flex items-center justify-between mb-1.5">'
                                            . '<span class="font-semibold">' . e($name) . '</span>'
                                            . '<span class="text-sm tabular-nums"><strong>' . number_format($pct, 1) . '%</strong> <span class="text-gray-500">· ' . $orders . ' comenzi</span></span>'
                                            . '</div>'
                                            . '<div class="h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">'
                                            . '<div class="h-full bg-primary-500" style="width: ' . $barWidth . '%;"></div>'
                                            . '</div>'
                                            . '</div>';
                                    }
                                    $rows .= '</div>';

                                    $footer = $determinedAt
                                        ? '<div class="text-xs text-gray-500 mt-3">Ultima detectare: ' . e(\Carbon\Carbon::parse($determinedAt)->format('d.m.Y H:i')) . '</div>'
                                        : '';

                                    return new \Illuminate\Support\HtmlString($header . $rows . $footer);
                                })
                                ->columnSpanFull(),
                        ])
                        ->collapsed(false),

                    SC\Section::make(__('Notification Preferences'))
                        ->icon('heroicon-o-bell')
                        ->description(__('User notification settings'))
                        ->collapsible()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Toggle::make('settings.notification_preferences.reminders')
                                ->label(__('Event Reminders'))
                                ->helperText(__('Receive reminder 24h before events'))
                                ->default(true),

                            Forms\Components\Toggle::make('settings.notification_preferences.newsletter')
                                ->label(__('Newsletter & Offers'))
                                ->helperText(__('Receive information about new events and special offers'))
                                ->default(true),

                            Forms\Components\Toggle::make('settings.notification_preferences.favorites')
                                ->label(__('Favorite Updates'))
                                ->helperText(__('Receive notifications when favorite events are approaching'))
                                ->default(true),

                            Forms\Components\Toggle::make('settings.notification_preferences.history')
                                ->label(__('Browsing History'))
                                ->helperText(__('Save viewed events for personalized recommendations'))
                                ->default(true),

                            Forms\Components\Toggle::make('settings.notification_preferences.marketing')
                                ->label(__('Marketing Cookies'))
                                ->helperText(__('Allow display of personalized ads'))
                                ->default(false),
                        ])->columns(2),

                    SC\Section::make(__('Favorites'))
                        ->icon('heroicon-o-heart')
                        ->description(__('Favorite events, artists, and venues'))
                        ->collapsible()
                        ->collapsed()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Placeholder::make('watchlist_events_list')
                                ->label(__('Watchlist Events'))
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
                                ->label(__('Favorite Artists'))
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
                                ->label(__('Favorite Venues'))
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
                    SC\Section::make(__('Statistics'))
                        ->icon('heroicon-o-chart-bar')
                        ->compact()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Placeholder::make('stats')
                                ->hiddenLabel()
                                ->content(fn (?MarketplaceCustomer $record) => self::renderStatistics($record)),
                        ]),

                    // Saved Payment Methods (doar pe Edit)
                    SC\Section::make(__('Payment Methods'))
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
                    SC\Section::make(__('Quick Actions'))
                        ->icon('heroicon-o-bolt')
                        ->compact()
                        ->visibleOn('edit')
                        ->schema([
                            SC\Actions::make([
                                Action::make('view_orders')
                                    ->label(__('View Orders'))
                                    ->icon('heroicon-o-shopping-bag')
                                    ->color('gray')
                                    ->url(fn ($record) => OrderResource::getUrl('index', ['customer' => $record->id])),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('view_tickets')
                                    ->label(__('View Tickets'))
                                    ->icon('heroicon-o-ticket')
                                    ->color('gray')
                                    ->url(fn ($record) => TicketResource::getUrl('index', ['customer' => $record->id])),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('send_email')
                                    ->label(__('Send Email'))
                                    ->icon('heroicon-o-envelope')
                                    ->color('success')
                                    ->url(fn ($record) => "mailto:{$record->email}"),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('suspend')
                                    ->label(__('Suspend User'))
                                    ->icon('heroicon-o-x-circle')
                                    ->color('danger')
                                    ->requiresConfirmation()
                                    ->visible(fn ($record) => $record->status === 'active')
                                    ->action(fn ($record) => $record->update(['status' => 'suspended'])),
                            ])->fullWidth(),
                            SC\Actions::make([
                                Action::make('reactivate')
                                    ->label(__('Reactivate User'))
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('success')
                                    ->visible(fn ($record) => $record->status === 'suspended')
                                    ->action(fn ($record) => $record->update(['status' => 'active'])),
                            ])->fullWidth(),
                        ])->columns(1),

                    // Meta Info (doar pe Edit, collapsed)
                    SC\Section::make(__('Meta Info'))
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
                // Name with e-mail and phone as the muted line; the separate columns stay in "Coloane".
                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('Name'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")))
                    ->sortable()
                    ->html()
                    ->formatStateUsing(function ($state, MarketplaceCustomer $record) {
                        $name = trim((string) $state) !== '' ? (string) $state : '—';
                        $initials = collect(preg_split('/\s+/u', trim((string) $state)))
                            ->filter(fn ($w) => mb_strlen($w) > 0)
                            ->take(2)
                            ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))
                            ->implode('');
                        $source = $record->settings['imported_from'] ?? null;
                        $import = $source
                            ? ' <span class="ep-pill ep-pill--mute" title="' . e('Import: ' . $source) . '">' . e(__('Import')) . '</span>'
                            : '';
                        $sub = collect([$record->email, $record->phone])->filter()->map(fn ($v) => e($v))->implode(' | ');

                        return '<div class="ep-venue"><span class="ep-av ep-av--round" aria-hidden="true">' . e($initials !== '' ? $initials : '·') . '</span>'
                            . '<div style="min-width:0"><div class="ep-venue-name">' . e($name) . $import . '</div>'
                            . ($sub !== '' ? '<div class="ep-sub">' . $sub . '</div>' : '')
                            . '</div></div>';
                    })
                    ->toggleable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Status + e-mail verification + account type as pills (the three columns they replace stay in "Coloane").
                Tables\Columns\TextColumn::make('account_state')
                    ->label(__('Stare'))
                    ->html()
                    ->state(function (MarketplaceCustomer $record): HtmlString {
                        [$label, $tone] = match ($record->status) {
                            'active' => [__('Activ'), 'ok'],
                            'suspended' => [__('Suspendat'), 'bad'],
                            default => [(string) $record->status, 'mute'],
                        };
                        $html = '<span class="ep-pill ep-pill--' . $tone . '"><span class="ep-dot"></span>' . e($label) . '</span>';
                        $html .= $record->email_verified_at !== null
                            ? ' <span class="ep-pill ep-pill--ok">' . e(__('Email verificat')) . '</span>'
                            : ' <span class="ep-pill ep-pill--mute">' . e(__('Email neverificat')) . '</span>';
                        $html .= $record->isGuest()
                            ? ' <span class="ep-pill ep-pill--mute" title="' . e('Guest (no password)') . '">' . e(__('Oaspete')) . '</span>'
                            : ' <span class="ep-pill ep-pill--ok" title="Registered">' . e(__('Cu cont')) . '</span>';

                        return new HtmlString('<div class="ep-stare">' . $html . '</div>');
                    })
                    ->toggleable(),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label(__('Verified'))
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => $record->email_verified_at !== null)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_guest')
                    ->label(__('Type'))
                    ->boolean()
                    ->trueIcon('heroicon-o-user')
                    ->falseIcon('heroicon-o-user-circle')
                    ->trueColor('gray')
                    ->falseColor('success')
                    ->getStateUsing(fn ($record) => $record->isGuest())
                    ->tooltip(fn ($record) => $record->isGuest() ? 'Guest (no password)' : 'Registered')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // Orders with the amount spent under it (Spent stays in "Coloane").
                Tables\Columns\TextColumn::make('total_orders')
                    ->label(__('Orders'))
                    ->numeric(thousandsSeparator: '.')
                    ->description(fn (MarketplaceCustomer $record): string => number_format((float) $record->total_spent, 2, ',', '.') . ' RON')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label(__('Spent'))
                    ->money('RON')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Last login with the registration date under it (Registered stays in "Coloane").
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label(__('Last Login'))
                    ->dateTime(timezone: MarketplaceTz::tz())
                    ->placeholder('—')
                    ->description(fn (MarketplaceCustomer $record): ?string => $record->created_at
                        ? __('Înregistrat') . ' ' . $record->created_at->copy()->setTimezone(MarketplaceTz::tz())->format('d.m.Y')
                        : null)
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Registered'))
                    ->dateTime(timezone: MarketplaceTz::tz())
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
                        'active' => __('Active'),
                        'suspended' => __('Suspended'),
                    ]),

                Tables\Filters\TernaryFilter::make('email_verified')
                    ->label(__('Email Verified'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                    ),

                Tables\Filters\TernaryFilter::make('is_guest')
                    ->label(__('Account Type'))
                    ->trueLabel(__('Guest Only'))
                    ->falseLabel(__('Registered Only'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereNull('password'),
                        false: fn (Builder $query) => $query->whereNotNull('password'),
                    ),

                Tables\Filters\TernaryFilter::make('has_orders')
                    ->label(__('Has Orders'))
                    ->queries(
                        true: fn (Builder $query) => $query->where('total_orders', '>', 0),
                        false: fn (Builder $query) => $query->where('total_orders', 0),
                    ),

                Tables\Filters\TernaryFilter::make('accepts_marketing')
                    ->label(__('Marketing Consent')),

                Tables\Filters\TernaryFilter::make('imported')
                    ->label(__('Importat AmBilet'))
                    ->trueLabel(__('Doar importați'))
                    ->falseLabel(__('Doar noi'))
                    ->queries(
                        true: fn (Builder $query) => $query->whereRaw(
                            DB::getDriverName() === 'pgsql'
                                ? "settings->>'imported_from' = ?"
                                : "JSON_EXTRACT(settings, '$.imported_from') = ?",
                            ['ambilet']
                        ),
                        false: fn (Builder $query) => $query->where(function (Builder $q) {
                            $q->whereNull('settings')
                                ->orWhereRaw(
                                    DB::getDriverName() === 'pgsql'
                                        ? "settings->>'imported_from' IS NULL"
                                        : "JSON_EXTRACT(settings, '$.imported_from') IS NULL"
                                );
                        }),
                    ),
            ])
            ->recordActions([
                // The row opens the edit page; everything else sits in one menu.
                \Filament\Actions\ActionGroup::make([
                    Action::make('login_as')
                        ->label(__('Login as'))
                        ->icon('heroicon-o-arrow-right-on-rectangle')
                        ->color('warning')
                        ->url(fn ($record) => url('/marketplace/marketplace-customers/' . $record->id . '/login-as'), shouldOpenInNewTab: true),
                    ViewAction::make(),
                    EditAction::make(),
                ])
                    ->label(__('Mai multe'))
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->iconButton(),
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
        $registered = MarketplaceTz::fmt($record->created_at, 'd M Y', $record->marketplaceClient ?? null, fallback: '-');

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

        $createdAt = MarketplaceTz::fmt($record->created_at, 'd M Y H:i', $record->marketplaceClient ?? null, fallback: '-');
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
