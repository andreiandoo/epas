<?php

namespace App\Filament\Resources\Marketplace;

use App\Models\Tenant;
use App\Models\Marketplace\MarketplaceOrganizer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';
    protected static ?string $navigationGroup = 'Marketplaces';
    protected static ?string $navigationLabel = 'Marketplace Platforms';
    protected static ?string $modelLabel = 'Marketplace';
    protected static ?string $pluralModelLabel = 'Marketplaces';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_type', Tenant::TYPE_MARKETPLACE);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Marketplace')
                    ->tabs([
                        // TAB 1: Basic Information
                        Forms\Components\Tabs\Tab::make('General')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                Forms\Components\Section::make('Basic Information')
                                    ->schema([
                                        Forms\Components\Hidden::make('tenant_type')
                                            ->default(Tenant::TYPE_MARKETPLACE),

                                        Forms\Components\TextInput::make('name')
                                            ->label('Marketplace Name')
                                            ->required()
                                            ->maxLength(255),

                                        Forms\Components\TextInput::make('public_name')
                                            ->label('Public Display Name')
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(function ($state, Forms\Set $set) {
                                                if (!$state) return;
                                                $set('slug', \Illuminate\Support\Str::slug($state));
                                            }),

                                        Forms\Components\TextInput::make('slug')
                                            ->label('URL Slug')
                                            ->required()
                                            ->unique(ignoreRecord: true),

                                        Forms\Components\Select::make('status')
                                            ->label('Status')
                                            ->options([
                                                'pending' => 'Pending Approval',
                                                'active' => 'Active',
                                                'suspended' => 'Suspended',
                                                'cancelled' => 'Cancelled',
                                            ])
                                            ->default('pending')
                                            ->required(),

                                        Forms\Components\Select::make('owner_id')
                                            ->label('Platform Owner')
                                            ->relationship('owner', 'name')
                                            ->searchable()
                                            ->preload()
                                            ->required(),
                                    ])->columns(2),

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
                                            ->label('City'),

                                        Forms\Components\TextInput::make('country')
                                            ->label('Country')
                                            ->default('RO'),
                                    ])->columns(2),
                            ]),

                        // TAB 2: Commission Settings
                        Forms\Components\Tabs\Tab::make('Commission')
                            ->icon('heroicon-o-currency-euro')
                            ->schema([
                                Forms\Components\Section::make('Tixello Platform Fee')
                                    ->description('Tixello always takes 1% from all orders. This cannot be changed.')
                                    ->schema([
                                        Forms\Components\Placeholder::make('tixello_fee')
                                            ->label('Tixello Fee')
                                            ->content('1% of all orders (automatic)'),
                                    ]),

                                Forms\Components\Section::make('Marketplace Commission')
                                    ->description('Set the default commission this marketplace takes from organizers.')
                                    ->schema([
                                        Forms\Components\Select::make('marketplace_commission_type')
                                            ->label('Commission Type')
                                            ->options([
                                                'percent' => 'Percentage Only',
                                                'fixed' => 'Fixed Amount Only',
                                                'both' => 'Percentage + Fixed',
                                            ])
                                            ->live()
                                            ->required(),

                                        Forms\Components\TextInput::make('marketplace_commission_percent')
                                            ->label('Commission Percentage')
                                            ->numeric()
                                            ->suffix('%')
                                            ->minValue(0)
                                            ->maxValue(100)
                                            ->visible(fn (Forms\Get $get) => in_array($get('marketplace_commission_type'), ['percent', 'both'])),

                                        Forms\Components\TextInput::make('marketplace_commission_fixed')
                                            ->label('Fixed Commission Amount')
                                            ->numeric()
                                            ->prefix(fn (Forms\Get $get) => $get('currency') ?? 'RON')
                                            ->minValue(0)
                                            ->visible(fn (Forms\Get $get) => in_array($get('marketplace_commission_type'), ['fixed', 'both'])),

                                        Forms\Components\Select::make('currency')
                                            ->label('Currency')
                                            ->options([
                                                'RON' => 'RON - Romanian Leu',
                                                'EUR' => 'EUR - Euro',
                                                'USD' => 'USD - US Dollar',
                                            ])
                                            ->default('RON')
                                            ->required(),
                                    ])->columns(2),

                                Forms\Components\Section::make('Commission Preview')
                                    ->schema([
                                        Forms\Components\Placeholder::make('commission_preview')
                                            ->label('Example: €100 Order')
                                            ->content(function (Forms\Get $get) {
                                                $type = $get('marketplace_commission_type');
                                                $percent = (float) ($get('marketplace_commission_percent') ?? 0);
                                                $fixed = (float) ($get('marketplace_commission_fixed') ?? 0);

                                                if (!$type) {
                                                    return 'Configure commission to see preview';
                                                }

                                                // Calculate for €100 order
                                                $orderTotal = 100;
                                                $tixelloFee = $orderTotal * 0.01; // 1%
                                                $afterTixello = $orderTotal - $tixelloFee;

                                                $marketplaceFee = match ($type) {
                                                    'percent' => $afterTixello * ($percent / 100),
                                                    'fixed' => $fixed,
                                                    'both' => ($afterTixello * ($percent / 100)) + $fixed,
                                                    default => 0,
                                                };

                                                $organizerRevenue = $afterTixello - $marketplaceFee;

                                                return sprintf(
                                                    "Order: €%.2f → Tixello: €%.2f (1%%) → Marketplace: €%.2f → Organizer: €%.2f",
                                                    $orderTotal,
                                                    $tixelloFee,
                                                    $marketplaceFee,
                                                    max(0, $organizerRevenue)
                                                );
                                            }),
                                    ]),
                            ]),

                        // TAB 3: Payment Settings
                        Forms\Components\Tabs\Tab::make('Payments')
                            ->icon('heroicon-o-credit-card')
                            ->schema([
                                Forms\Components\Section::make('Payment Processor')
                                    ->description('Configure the payment processor for this marketplace.')
                                    ->schema([
                                        Forms\Components\Select::make('payment_processor')
                                            ->label('Payment Processor')
                                            ->options([
                                                'stripe' => 'Stripe',
                                                'netopia' => 'Netopia',
                                                'euplatesc' => 'euPlatesc',
                                            ])
                                            ->searchable(),

                                        Forms\Components\Select::make('payment_processor_mode')
                                            ->label('Mode')
                                            ->options([
                                                'sandbox' => 'Sandbox / Test',
                                                'live' => 'Live / Production',
                                            ])
                                            ->default('sandbox'),
                                    ])->columns(2),
                            ]),

                        // TAB 4: Contact
                        Forms\Components\Tabs\Tab::make('Contact')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Forms\Components\Section::make('Contact Information')
                                    ->schema([
                                        Forms\Components\TextInput::make('contact_first_name')
                                            ->label('First Name'),

                                        Forms\Components\TextInput::make('contact_last_name')
                                            ->label('Last Name'),

                                        Forms\Components\TextInput::make('contact_email')
                                            ->label('Email')
                                            ->email(),

                                        Forms\Components\TextInput::make('contact_phone')
                                            ->label('Phone'),

                                        Forms\Components\TextInput::make('contact_position')
                                            ->label('Position'),

                                        Forms\Components\TextInput::make('website')
                                            ->label('Website URL')
                                            ->url(),
                                    ])->columns(2),
                            ]),

                        // TAB 5: Settings
                        Forms\Components\Tabs\Tab::make('Settings')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Forms\Components\Section::make('Marketplace Settings')
                                    ->schema([
                                        Forms\Components\KeyValue::make('marketplace_settings')
                                            ->label('Custom Settings')
                                            ->addActionLabel('Add Setting')
                                            ->keyLabel('Setting Key')
                                            ->valueLabel('Value'),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('public_name')
                    ->label('Public Name')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('organizers_count')
                    ->label('Organizers')
                    ->counts('organizers')
                    ->sortable(),

                Tables\Columns\TextColumn::make('marketplace_commission_description')
                    ->label('Commission')
                    ->getStateUsing(fn (Tenant $record) => $record->getMarketplaceCommissionDescription()),

                Tables\Columns\TextColumn::make('owner.name')
                    ->label('Owner')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'pending' => 'Pending',
                        'suspended' => 'Suspended',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Tenant $record) => $record->status === 'pending')
                    ->action(fn (Tenant $record) => $record->update(['status' => 'active']))
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Marketplace Overview')
                    ->schema([
                        Infolists\Components\TextEntry::make('name'),
                        Infolists\Components\TextEntry::make('public_name'),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'suspended' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('owner.name')
                            ->label('Owner'),
                    ])->columns(2),

                Infolists\Components\Section::make('Commission')
                    ->schema([
                        Infolists\Components\TextEntry::make('marketplace_commission_type')
                            ->label('Type'),
                        Infolists\Components\TextEntry::make('marketplace_commission_percent')
                            ->label('Percentage')
                            ->suffix('%'),
                        Infolists\Components\TextEntry::make('marketplace_commission_fixed')
                            ->label('Fixed Amount'),
                    ])->columns(3),

                Infolists\Components\Section::make('Statistics')
                    ->schema([
                        Infolists\Components\TextEntry::make('organizers_count')
                            ->label('Total Organizers')
                            ->getStateUsing(fn (Tenant $record) => $record->organizers()->count()),
                        Infolists\Components\TextEntry::make('active_organizers_count')
                            ->label('Active Organizers')
                            ->getStateUsing(fn (Tenant $record) => $record->activeOrganizers()->count()),
                        Infolists\Components\TextEntry::make('events_count')
                            ->label('Total Events')
                            ->getStateUsing(fn (Tenant $record) => $record->events()->count()),
                        Infolists\Components\TextEntry::make('orders_count')
                            ->label('Total Orders')
                            ->getStateUsing(fn (Tenant $record) => $record->orders()->count()),
                    ])->columns(4),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\Marketplace\MarketplaceResource\RelationManagers\OrganizersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\Marketplace\MarketplaceResource\Pages\ListMarketplaces::route('/'),
            'create' => \App\Filament\Resources\Marketplace\MarketplaceResource\Pages\CreateMarketplace::route('/create'),
            'view' => \App\Filament\Resources\Marketplace\MarketplaceResource\Pages\ViewMarketplace::route('/{record}'),
            'edit' => \App\Filament\Resources\Marketplace\MarketplaceResource\Pages\EditMarketplace::route('/{record}/edit'),
        ];
    }
}
