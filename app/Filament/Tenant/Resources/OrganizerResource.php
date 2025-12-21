<?php

namespace App\Filament\Tenant\Resources;

use App\Models\Marketplace\MarketplaceOrganizer;
use App\Models\Marketplace\MarketplaceOrganizerUser;
use App\Services\Marketplace\OrganizerRegistrationService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;

class OrganizerResource extends Resource
{
    protected static ?string $model = MarketplaceOrganizer::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Organizers';
    protected static ?string $navigationLabel = 'Event Organizers';
    protected static ?int $navigationSort = 1;

    /**
     * Only show this resource for marketplace tenants.
     */
    public static function canViewAny(): bool
    {
        $tenant = auth()->user()?->tenant;
        return $tenant && $tenant->isMarketplace();
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()?->tenant;

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Organizer')
                    ->tabs([
                        // TAB 1: Basic Information
                        Forms\Components\Tabs\Tab::make('General')
                            ->icon('heroicon-o-user')
                            ->schema([
                                Forms\Components\Section::make('Organizer Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Organizer Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Set $set, $context) {
                                                if ($context === 'create' && $state) {
                                                    $set('slug', \Illuminate\Support\Str::slug($state));
                                                }
                                            }),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL Slug')
                                            ->required()
                                            ->maxLength(255)
                                            ->unique(ignoreRecord: true, modifyRuleUsing: function ($rule) {
                                                $tenant = auth()->user()?->tenant;
                                                return $rule->where('tenant_id', $tenant?->id);
                                            }),

                                        Forms\Components\Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'pending_approval' => 'Pending Approval',
                                                'active' => 'Active',
                                                'suspended' => 'Suspended',
                                                'closed' => 'Closed',
                                            ])
                                            ->default('pending_approval')
                                            ->required(),

                                        Forms\Components\Textarea::make('description')
                                            ->label('Description')
                                            ->rows(3)
                                            ->maxLength(1000),
                                    ])->columns(2),

                                Forms\Components\Section::make('Contact Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('contact_name')
                                            ->label('Contact Name')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('contact_email')
                                            ->label('Contact Email')
                                            ->email()
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('contact_phone')
                                            ->label('Contact Phone')
                                            ->tel()
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('website_url')
                                            ->label('Website')
                                            ->url()
                                            ->maxLength(255),
                                    ])->columns(2),
                            ]),

                        // TAB 2: Company Details
                        Forms\Components\Tabs\Tab::make('Company')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\Section::make('Company Details')
                                    ->schema([
                                        Forms\Components\TextInput::make('company_name')
                                            ->label('Legal Company Name')
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('cui')
                                            ->label('Tax ID (CUI)')
                                            ->maxLength(50),

                                        Forms\Components\TextInput::make('reg_com')
                                            ->label('Trade Register Number')
                                            ->maxLength(50),

                                        Forms\Components\Textarea::make('address')
                                            ->label('Address')
                                            ->rows(2),

                                        Forms\Components\TextInput::make('city')
                                            ->label('City')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('county')
                                            ->label('County/State')
                                            ->maxLength(100),

                                        Forms\Components\TextInput::make('postal_code')
                                            ->label('Postal Code')
                                            ->maxLength(20),

                                        Forms\Components\TextInput::make('country')
                                            ->label('Country')
                                            ->default('RO')
                                            ->maxLength(10),
                                    ])->columns(2),
                            ]),

                        // TAB 3: Branding
                        Forms\Components\Tabs\Tab::make('Branding')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\Section::make('Branding')
                                    ->schema([
                                        Forms\Components\FileUpload::make('logo')
                                            ->label('Logo')
                                            ->image()
                                            ->directory('organizers/logos')
                                            ->maxSize(2048),

                                        Forms\Components\FileUpload::make('cover_image')
                                            ->label('Cover Image')
                                            ->image()
                                            ->directory('organizers/covers')
                                            ->maxSize(5120),
                                    ])->columns(2),

                                Forms\Components\Section::make('Social Links')
                                    ->schema([
                                        Forms\Components\TextInput::make('facebook_url')
                                            ->label('Facebook URL')
                                            ->url()
                                            ->prefix('https://'),

                                        Forms\Components\TextInput::make('instagram_url')
                                            ->label('Instagram URL')
                                            ->url()
                                            ->prefix('https://'),
                                    ])->columns(2),
                            ]),

                        // TAB 4: Commission
                        Forms\Components\Tabs\Tab::make('Commission')
                            ->icon('heroicon-o-currency-euro')
                            ->schema([
                                Forms\Components\Section::make('Commission Override')
                                    ->description('Override the default marketplace commission for this organizer. Leave empty to use marketplace defaults.')
                                    ->schema([
                                        Forms\Components\Select::make('commission_type')
                                            ->label('Commission Type')
                                            ->options([
                                                '' => '-- Use Marketplace Default --',
                                                'percent' => 'Percentage Only',
                                                'fixed' => 'Fixed Amount Only',
                                                'both' => 'Percentage + Fixed',
                                            ])
                                            ->live(),

                                        Forms\Components\TextInput::make('commission_percent')
                                            ->label('Commission Percentage')
                                            ->numeric()
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->visible(fn (Forms\Get $get) => in_array($get('commission_type'), ['percent', 'both'])),

                                        Forms\Components\TextInput::make('commission_fixed')
                                            ->label('Fixed Commission Amount')
                                            ->numeric()
                                            ->minValue(0)
                                            ->visible(fn (Forms\Get $get) => in_array($get('commission_type'), ['fixed', 'both'])),
                                    ])->columns(3),

                                Forms\Components\Section::make('Current Effective Commission')
                                    ->schema([
                                        Forms\Components\Placeholder::make('effective_commission')
                                            ->label('Commission Applied')
                                            ->content(function (?MarketplaceOrganizer $record) {
                                                if (!$record) {
                                                    return 'Will use marketplace default after creation';
                                                }

                                                if ($record->hasCustomCommission()) {
                                                    $type = $record->commission_type;
                                                    $percent = $record->commission_percent;
                                                    $fixed = $record->commission_fixed;

                                                    return match ($type) {
                                                        'percent' => $percent . '% (Custom)',
                                                        'fixed' => number_format($fixed, 2) . ' RON fixed (Custom)',
                                                        'both' => $percent . '% + ' . number_format($fixed, 2) . ' RON (Custom)',
                                                        default => 'Not configured',
                                                    };
                                                }

                                                return $record->marketplace->getMarketplaceCommissionDescription() . ' (Marketplace Default)';
                                            }),
                                    ]),
                            ]),

                        // TAB 5: Payouts
                        Forms\Components\Tabs\Tab::make('Payouts')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\Section::make('Payout Settings')
                                    ->schema([
                                        Forms\Components\Select::make('payout_method')
                                            ->label('Payout Method')
                                            ->options([
                                                'bank_transfer' => 'Bank Transfer',
                                                'paypal' => 'PayPal',
                                                'stripe_connect' => 'Stripe Connect',
                                            ])
                                            ->default('bank_transfer')
                                            ->required(),

                                        Forms\Components\Select::make('payout_frequency')
                                            ->label('Payout Frequency')
                                            ->options([
                                                'weekly' => 'Weekly',
                                                'biweekly' => 'Bi-weekly',
                                                'monthly' => 'Monthly',
                                            ])
                                            ->default('monthly')
                                            ->required(),

                                        Forms\Components\TextInput::make('minimum_payout')
                                            ->label('Minimum Payout Amount')
                                            ->numeric()
                                            ->default(50)
                                            ->minValue(0)
                                            ->required(),

                                        Forms\Components\Select::make('payout_currency')
                                            ->label('Payout Currency')
                                            ->options([
                                                'RON' => 'RON',
                                                'EUR' => 'EUR',
                                                'USD' => 'USD',
                                            ])
                                            ->default('RON')
                                            ->required(),
                                    ])->columns(2),

                                Forms\Components\Section::make('Bank Details')
                                    ->schema([
                                        Forms\Components\KeyValue::make('payout_details')
                                            ->label('Payout Details')
                                            ->addActionLabel('Add Field')
                                            ->keyLabel('Field')
                                            ->valueLabel('Value')
                                            ->reorderable()
                                            ->default([
                                                'bank_name' => '',
                                                'iban' => '',
                                                'swift' => '',
                                                'account_holder' => '',
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=O&background=random'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Organizer')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending_approval' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_verified')
                    ->label('Verified')
                    ->boolean(),

                Tables\Columns\TextColumn::make('total_events')
                    ->label('Events')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('Revenue')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('pending_payout')
                    ->label('Pending')
                    ->money('RON')
                    ->sortable()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Contact')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Joined')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_approval' => 'Pending Approval',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'closed' => 'Closed',
                    ]),
                Tables\Filters\TernaryFilter::make('is_verified')
                    ->label('Verified'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (MarketplaceOrganizer $record) => $record->isPendingApproval())
                    ->action(function (MarketplaceOrganizer $record) {
                        $record->approve(auth()->id());
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Organizer')
                    ->modalDescription('This organizer will be able to create events and sell tickets.'),
                Tables\Actions\Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause')
                    ->color('danger')
                    ->visible(fn (MarketplaceOrganizer $record) => $record->isActive())
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Suspension Reason')
                            ->required(),
                    ])
                    ->action(function (MarketplaceOrganizer $record, array $data) {
                        app(OrganizerRegistrationService::class)->suspend($record, $data['reason']);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Overview')
                    ->schema([
                        Infolists\Components\ImageEntry::make('logo')
                            ->label('')
                            ->circular()
                            ->size(80),
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'pending_approval' => 'warning',
                                'suspended' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\IconEntry::make('is_verified')
                            ->label('Verified')
                            ->boolean(),
                    ])->columns(4),

                Infolists\Components\Section::make('Contact')
                    ->schema([
                        Infolists\Components\TextEntry::make('contact_name'),
                        Infolists\Components\TextEntry::make('contact_email')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('contact_phone')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('website_url')
                            ->url(fn ($state) => $state),
                    ])->columns(2),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('total_events')
                            ->label('Total Events'),
                        Infolists\Components\TextEntry::make('total_orders')
                            ->label('Total Orders'),
                        Infolists\Components\TextEntry::make('total_revenue')
                            ->label('Total Revenue')
                            ->money('RON'),
                        Infolists\Components\TextEntry::make('pending_payout')
                            ->label('Pending Payout')
                            ->money('RON'),
                    ])->columns(4),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrganizerResource\RelationManagers\UsersRelationManager::class,
            OrganizerResource\RelationManagers\EventsRelationManager::class,
            OrganizerResource\RelationManagers\PayoutsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => OrganizerResource\Pages\ListOrganizers::route('/'),
            'create' => OrganizerResource\Pages\CreateOrganizer::route('/create'),
            'view' => OrganizerResource\Pages\ViewOrganizer::route('/{record}'),
            'edit' => OrganizerResource\Pages\EditOrganizer::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $tenant = auth()->user()?->tenant;
        if (!$tenant || !$tenant->isMarketplace()) {
            return null;
        }

        $pending = static::getEloquentQuery()->pendingApproval()->count();
        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
