<?php

namespace App\Filament\Resources;

use App\Models\MarketplaceClient;
use App\Models\Microservice;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components as SC;
use Illuminate\Support\Str;
use Illuminate\Support\HtmlString;
use BackedEnum;
use UnitEnum;

class MarketplaceClientResource extends Resource
{
    protected static ?string $model = MarketplaceClient::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Marketplace Clients';

    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Marketplace Client';

    protected static ?string $pluralModelLabel = 'Marketplace Clients';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::where('status', 'active')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Tabs::make('MarketplaceClient')
                ->tabs([
                    // TAB 1: General Information
                    SC\Tabs\Tab::make('General')
                        ->icon('heroicon-o-building-storefront')
                        ->schema([
                            SC\Section::make('Client Information')
                                ->schema([
                                    Forms\Components\TextInput::make('name')
                                        ->label('Client Name')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn ($state, $set, $get) =>
                                            $get('slug') ? null : $set('slug', Str::slug($state))
                                        ),

                                    Forms\Components\TextInput::make('slug')
                                        ->label('Slug')
                                        ->required()
                                        ->unique(ignoreRecord: true)
                                        ->maxLength(255)
                                        ->helperText('Used for folder organization'),

                                    Forms\Components\TextInput::make('domain')
                                        ->label('Domain')
                                        ->url()
                                        ->placeholder('https://example.com')
                                        ->maxLength(255),

                                    Forms\Components\Select::make('status')
                                        ->label('Status')
                                        ->options([
                                            'active' => 'Active',
                                            'inactive' => 'Inactive',
                                            'suspended' => 'Suspended',
                                        ])
                                        ->default('active')
                                        ->required(),
                                ])
                                ->columns(2),

                            SC\Section::make('Contact Information')
                                ->schema([
                                    Forms\Components\TextInput::make('contact_email')
                                        ->label('Contact Email')
                                        ->email()
                                        ->required()
                                        ->maxLength(255),

                                    Forms\Components\TextInput::make('contact_phone')
                                        ->label('Contact Phone')
                                        ->tel()
                                        ->maxLength(50),

                                    Forms\Components\TextInput::make('company_name')
                                        ->label('Company Name')
                                        ->maxLength(255),
                                ])
                                ->columns(3),

                            SC\Section::make('Commission Settings')
                                ->schema([
                                    Forms\Components\TextInput::make('commission_rate')
                                        ->label('Commission Rate (%)')
                                        ->numeric()
                                        ->minValue(0)
                                        ->maxValue(100)
                                        ->step(0.01)
                                        ->default(0)
                                        ->suffix('%')
                                        ->helperText('Percentage of each sale'),
                                ])
                                ->columns(1),

                            SC\Section::make('Billing Settings')
                                ->schema([
                                    Forms\Components\DatePicker::make('billing_starts_at')
                                        ->label('Billing Start Date')
                                        ->native(false)
                                        ->helperText('When the first billing cycle begins'),

                                    Forms\Components\TextInput::make('billing_cycle_days')
                                        ->label('Billing Cycle (days)')
                                        ->numeric()
                                        ->minValue(1)
                                        ->default(30)
                                        ->suffix('days'),

                                    Forms\Components\DatePicker::make('next_billing_date')
                                        ->label('Next Billing Date')
                                        ->native(false)
                                        ->helperText('Auto-calculated when billing starts'),

                                    Forms\Components\DatePicker::make('last_billing_date')
                                        ->label('Last Billing Date')
                                        ->native(false)
                                        ->disabled()
                                        ->dehydrated(),

                                    Forms\Components\Placeholder::make('earnings_link')
                                        ->label('')
                                        ->content(fn ($record) => $record
                                            ? new \Illuminate\Support\HtmlString(
                                                '<a href="' . \App\Filament\Pages\ClientEarnings::getUrl(['type' => 'marketplace', 'id' => $record->id]) . '" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg hover:bg-primary-700"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>View Earnings & Usage</a>'
                                            )
                                            : 'Save the client first to view earnings.'
                                        ),
                                ])
                                ->columns(2),

                            SC\Section::make('Notes')
                                ->schema([
                                    Forms\Components\Textarea::make('notes')
                                        ->label('Internal Notes')
                                        ->rows(3),
                                ])
                                ->collapsed(),
                        ]),

                    // TAB 2: API & Security
                    SC\Tabs\Tab::make('API & Security')
                        ->icon('heroicon-o-key')
                        ->schema([
                            SC\Section::make('API Credentials')
                                ->schema([
                                    Forms\Components\TextInput::make('api_key')
                                        ->label('API Key')
                                        ->disabled()
                                        ->helperText('Auto-generated. Use the "Regenerate Key" action to create new credentials.')
                                        ->copyable(),

                                    Forms\Components\TextInput::make('api_secret')
                                        ->label('API Secret')
                                        ->disabled()
                                        ->password()
                                        ->revealable()
                                        ->helperText('Keep this secret! Use it to authenticate API requests.')
                                        ->copyable(),
                                ])
                                ->columns(2)
                                ->visible(fn ($record) => $record !== null),

                            SC\Section::make('Security Settings')
                                ->schema([
                                    Forms\Components\Repeater::make('settings.allowed_ips')
                                        ->label('Allowed IP Addresses')
                                        ->simple(
                                            Forms\Components\TextInput::make('ip')
                                                ->placeholder('192.168.1.1 or 10.0.0.0/24')
                                        )
                                        ->helperText('Leave empty to allow all IPs. Supports CIDR notation.'),

                                    Forms\Components\Repeater::make('settings.allowed_domains')
                                        ->label('Allowed Domains')
                                        ->simple(
                                            Forms\Components\TextInput::make('domain')
                                                ->placeholder('example.com or *.example.com')
                                        )
                                        ->helperText('Leave empty to allow all domains. Supports wildcard subdomains.'),
                                ])
                                ->columns(2),

                            SC\Section::make('Webhook Settings')
                                ->schema([
                                    Forms\Components\TextInput::make('settings.webhook_url')
                                        ->label('Webhook URL')
                                        ->url()
                                        ->placeholder('https://example.com/webhook')
                                        ->helperText('URL to receive order notifications'),

                                    Forms\Components\TextInput::make('settings.webhook_secret')
                                        ->label('Webhook Secret')
                                        ->password()
                                        ->revealable()
                                        ->helperText('Used to sign webhook payloads'),
                                ])
                                ->columns(2),
                        ]),

                    // TAB 3: Microservices
                    SC\Tabs\Tab::make('Microservices')
                        ->icon('heroicon-o-puzzle-piece')
                        ->schema([
                            SC\Section::make('Enable Microservices')
                                ->description('Select which microservices to enable for this marketplace. Payment methods are configured in the Payment Methods relation tab below.')
                                ->schema([
                                    Forms\Components\CheckboxList::make('enabled_microservices')
                                        ->label('')
                                        ->options(fn () => Microservice::active()->where('category', '!=', 'payment')->pluck('name', 'id')->map(fn ($name) => is_array($name) ? ($name['en'] ?? $name['ro'] ?? reset($name)) : $name))
                                        ->descriptions(fn () => Microservice::active()->where('category', '!=', 'payment')->pluck('short_description', 'id')->map(fn ($desc) => is_array($desc) ? ($desc['en'] ?? $desc['ro'] ?? reset($desc) ?? '') : ($desc ?? '')))
                                        ->columns(2)
                                        ->bulkToggleable()
                                        ->afterStateHydrated(function ($component, $record) {
                                            if ($record) {
                                                $enabledIds = $record->microservices()
                                                    ->where('category', '!=', 'payment')
                                                    ->wherePivot('is_active', true)
                                                    ->pluck('microservices.id')
                                                    ->toArray();
                                                $component->state($enabledIds);
                                            }
                                        })
                                        ->dehydrated(false),
                                ]),

                            SC\Actions::make([
                                Actions\Action::make('save_microservices')
                                    ->label('Save Microservices')
                                    ->icon('heroicon-o-check')
                                    ->color('primary')
                                    ->action(function ($record, \Filament\Schemas\Components\Utilities\Get $get) {
                                        $enabledIds = $get('enabled_microservices') ?? [];

                                        // Get all non-payment microservices
                                        $allMicroservices = Microservice::active()->where('category', '!=', 'payment')->pluck('id')->toArray();

                                        foreach ($allMicroservices as $microserviceId) {
                                            $isEnabled = in_array($microserviceId, $enabledIds);
                                            $existing = $record->microservices()->where('microservices.id', $microserviceId)->first();

                                            if ($isEnabled && !$existing) {
                                                // Attach new
                                                $record->microservices()->attach($microserviceId, [
                                                    'is_active' => true,
                                                    'status' => 'active',
                                                    'activated_at' => now(),
                                                ]);
                                            } elseif ($isEnabled && $existing) {
                                                // Update to active
                                                $record->microservices()->updateExistingPivot($microserviceId, [
                                                    'is_active' => true,
                                                    'status' => 'active',
                                                ]);
                                            } elseif (!$isEnabled && $existing) {
                                                // Deactivate
                                                $record->microservices()->updateExistingPivot($microserviceId, [
                                                    'is_active' => false,
                                                    'status' => 'inactive',
                                                ]);
                                            }
                                        }

                                        \Filament\Notifications\Notification::make()
                                            ->title('Microservices Updated')
                                            ->success()
                                            ->send();
                                    }),
                            ])->visible(fn ($record) => $record !== null),
                        ])
                        ->visible(fn ($record) => $record !== null),

                    // TAB 4: Statistics
                    SC\Tabs\Tab::make('Statistics')
                        ->icon('heroicon-o-chart-bar')
                        ->schema([
                            SC\Section::make('Financial Overview')
                                ->schema([
                                    Forms\Components\Placeholder::make('total_revenue')
                                        ->label('Total Revenue (All Time)')
                                        ->content(fn ($record) => $record
                                            ? number_format(\App\Models\Order::where('marketplace_client_id', $record->id)->whereIn('status', ['paid', 'confirmed', 'completed'])->sum('total'), 2) . ' ' . ($record->currency ?? 'RON')
                                            : '0.00'
                                        ),

                                    Forms\Components\Placeholder::make('total_commission')
                                        ->label('Total Commission (All Time)')
                                        ->content(fn ($record) => $record
                                            ? number_format(\App\Models\Order::where('marketplace_client_id', $record->id)->whereIn('status', ['paid', 'confirmed', 'completed'])->sum('commission_amount'), 2) . ' ' . ($record->currency ?? 'RON')
                                            : '0.00'
                                        ),

                                    Forms\Components\Placeholder::make('this_month_revenue')
                                        ->label('This Month Revenue')
                                        ->content(fn ($record) => $record
                                            ? number_format(\App\Models\Order::where('marketplace_client_id', $record->id)->whereIn('status', ['paid', 'confirmed', 'completed'])->where('created_at', '>=', now()->startOfMonth())->sum('total'), 2) . ' ' . ($record->currency ?? 'RON')
                                            : '0.00'
                                        ),

                                    Forms\Components\Placeholder::make('total_orders')
                                        ->label('Total Orders')
                                        ->content(fn ($record) => $record
                                            ? number_format(\App\Models\Order::where('marketplace_client_id', $record->id)->whereIn('status', ['paid', 'confirmed', 'completed'])->count())
                                            : '0'
                                        ),

                                    Forms\Components\Placeholder::make('unpaid_invoices')
                                        ->label('Unpaid Invoices')
                                        ->content(fn ($record) => $record
                                            ? \App\Models\Invoice::where('marketplace_client_id', $record->id)->whereIn('status', ['pending', 'overdue', 'outstanding', 'new'])->count() . ' (' . number_format(\App\Models\Invoice::where('marketplace_client_id', $record->id)->whereIn('status', ['pending', 'overdue', 'outstanding', 'new'])->sum('amount'), 2) . ' ' . ($record->currency ?? 'RON') . ')'
                                            : '0'
                                        ),

                                    Forms\Components\Placeholder::make('view_earnings')
                                        ->label('')
                                        ->content(fn ($record) => $record
                                            ? new \Illuminate\Support\HtmlString(
                                                '<a href="' . \App\Filament\Pages\ClientEarnings::getUrl(['type' => 'marketplace', 'id' => $record->id]) . '" class="inline-flex items-center gap-2 text-sm font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>View Detailed Earnings & Usage</a>'
                                            )
                                            : ''
                                        ),
                                ])
                                ->columns(3),

                            SC\Section::make('Usage Statistics')
                                ->schema([
                                    Forms\Components\Placeholder::make('api_calls_count')
                                        ->label('Total API Calls')
                                        ->content(fn ($record) => number_format($record?->api_calls_count ?? 0)),

                                    Forms\Components\Placeholder::make('last_api_call_at')
                                        ->label('Last API Call')
                                        ->content(fn ($record) => $record?->last_api_call_at?->diffForHumans() ?? 'Never'),

                                    Forms\Components\Placeholder::make('organizers_count')
                                        ->label('Organizers')
                                        ->content(fn ($record) => $record?->organizers()->count() ?? 0),

                                    Forms\Components\Placeholder::make('admins_count')
                                        ->label('Admins')
                                        ->content(fn ($record) => $record?->admins()->count() ?? 0),

                                    Forms\Components\Placeholder::make('tenants_count')
                                        ->label('Linked Tenants')
                                        ->content(fn ($record) => $record?->tenants()->count() ?? 0),

                                    Forms\Components\Placeholder::make('microservices_count')
                                        ->label('Active Microservices')
                                        ->content(fn ($record) => $record?->microservices()->wherePivot('is_active', true)->count() ?? 0),
                                ])
                                ->columns(3),

                            SC\Section::make('Account Information')
                                ->schema([
                                    Forms\Components\Placeholder::make('created_at')
                                        ->label('Created')
                                        ->content(fn ($record) => $record?->created_at?->format('d M Y H:i') ?? 'N/A'),

                                    Forms\Components\Placeholder::make('updated_at')
                                        ->label('Last Updated')
                                        ->content(fn ($record) => $record?->updated_at?->format('d M Y H:i') ?? 'N/A'),
                                ])
                                ->columns(2),
                        ])
                        ->visible(fn ($record) => $record !== null),
                ])
                ->columnSpanFull()
                ->persistTabInQueryString('tab'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Client')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->domain)
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'active' => 'success',
                        'inactive' => 'gray',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('commission_rate')
                    ->label('Commission')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organizers_count')
                    ->label('Organizers')
                    ->counts('organizers')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('tenants_count')
                    ->label('Tenants')
                    ->counts('tenants')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('api_calls_count')
                    ->label('API Calls')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_api_call_at')
                    ->label('Last API Call')
                    ->since()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->actions([
                Actions\Action::make('login_to_marketplace')
                    ->label('Login')
                    ->icon('heroicon-o-arrow-right-on-rectangle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === 'active' && auth()->user()?->isSuperAdmin())
                    ->action(function ($record) {
                        // Set session for the target marketplace client
                        session(['super_admin_marketplace_client_id' => $record->id]);
                        // Clear any existing marketplace admin session to force re-login
                        auth('marketplace_admin')->logout();
                        session()->forget('marketplace_is_super_admin');

                        // Redirect to marketplace panel
                        return redirect('/marketplace');
                    }),
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            MarketplaceClientResource\RelationManagers\TenantsRelationManager::class,
            MarketplaceClientResource\RelationManagers\AdminsRelationManager::class,
            MarketplaceClientResource\RelationManagers\OrganizersRelationManager::class,
            MarketplaceClientResource\RelationManagers\PaymentMethodsRelationManager::class,
            MarketplaceClientResource\RelationManagers\MicroservicesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => MarketplaceClientResource\Pages\ListMarketplaceClients::route('/'),
            'create' => MarketplaceClientResource\Pages\CreateMarketplaceClient::route('/create'),
            'view' => MarketplaceClientResource\Pages\ViewMarketplaceClient::route('/{record}'),
            'edit' => MarketplaceClientResource\Pages\EditMarketplaceClient::route('/{record}/edit'),
        ];
    }
}