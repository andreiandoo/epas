<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\AffiliateSettingsResource\Pages;
use App\Models\AffiliateSettings;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;

class AffiliateSettingsResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = AffiliateSettings::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Affiliate Settings';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Affiliate Settings';

    protected static ?string $pluralModelLabel = 'Affiliate Settings';

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

        public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('affiliates');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                SC\Tabs::make('Settings')
                    ->tabs([
                        SC\Tabs\Tab::make('Commission')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                SC\Section::make('Default Commission Settings')
                                    ->description('Set the default commission for new affiliates')
                                    ->schema([
                                        Forms\Components\Select::make('default_commission_type')
                                            ->label('Commission Type')
                                            ->options([
                                                'percent' => 'Percentage (%)',
                                                'fixed' => 'Fixed Amount',
                                            ])
                                            ->default('percent')
                                            ->required()
                                            ->live(),

                                        Forms\Components\TextInput::make('default_commission_value')
                                            ->label(fn (Forms\Get $get) => $get('default_commission_type') === 'percent' ? 'Commission Percentage' : 'Commission Amount')
                                            ->numeric()
                                            ->default(10)
                                            ->required()
                                            ->suffix(fn (Forms\Get $get) => $get('default_commission_type') === 'percent' ? '%' : null)
                                            ->helperText(fn (Forms\Get $get) => $get('default_commission_type') === 'percent'
                                                ? 'Percentage of order value'
                                                : 'Fixed amount per conversion'),

                                        Forms\Components\TextInput::make('commission_hold_days')
                                            ->label('Commission Hold Period')
                                            ->numeric()
                                            ->default(30)
                                            ->suffix('days')
                                            ->helperText('Days before commission becomes available for withdrawal'),
                                    ])
                                    ->columns(3),

                                SC\Section::make('Commission Rules')
                                    ->schema([
                                        Forms\Components\Toggle::make('exclude_taxes')
                                            ->label('Exclude Taxes')
                                            ->helperText('Calculate commission on net amount (excluding taxes)')
                                            ->default(true),

                                        Forms\Components\Toggle::make('exclude_shipping')
                                            ->label('Exclude Shipping')
                                            ->helperText('Calculate commission excluding shipping costs')
                                            ->default(true),

                                        Forms\Components\Toggle::make('prevent_self_purchase')
                                            ->label('Prevent Self-Purchase')
                                            ->helperText('Prevent affiliates from earning commission on their own orders')
                                            ->default(true),
                                    ])
                                    ->columns(3),
                            ]),

                        SC\Tabs\Tab::make('Registration')
                            ->icon('heroicon-o-user-plus')
                            ->schema([
                                SC\Section::make('Self-Registration')
                                    ->schema([
                                        Forms\Components\Toggle::make('allow_self_registration')
                                            ->label('Allow Self-Registration')
                                            ->helperText('Allow customers to register as affiliates through your website')
                                            ->default(true)
                                            ->live(),

                                        Forms\Components\Toggle::make('require_approval')
                                            ->label('Require Approval')
                                            ->helperText('New affiliates must be approved before they can start earning')
                                            ->default(true)
                                            ->visible(fn (Forms\Get $get) => $get('allow_self_registration')),
                                    ])
                                    ->columns(2),

                                SC\Section::make('Program Information')
                                    ->description('Information shown on the affiliate registration page')
                                    ->schema([
                                        Forms\Components\TextInput::make('program_name')
                                            ->label('Program Name')
                                            ->placeholder('Partner Program')
                                            ->maxLength(255),

                                        Forms\Components\Textarea::make('program_description')
                                            ->label('Program Description')
                                            ->rows(3)
                                            ->placeholder('Describe your affiliate program...'),

                                        Forms\Components\Repeater::make('program_benefits')
                                            ->label('Benefits')
                                            ->simple(
                                                Forms\Components\TextInput::make('benefit')
                                                    ->placeholder('e.g., Earn 10% on every sale')
                                            )
                                            ->addActionLabel('Add Benefit')
                                            ->collapsible()
                                            ->defaultItems(0),

                                        Forms\Components\RichEditor::make('registration_terms')
                                            ->label('Terms & Conditions')
                                            ->helperText('Affiliates must accept these terms to register')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        SC\Tabs\Tab::make('Withdrawals')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                SC\Section::make('Withdrawal Settings')
                                    ->schema([
                                        Forms\Components\TextInput::make('min_withdrawal_amount')
                                            ->label('Minimum Withdrawal Amount')
                                            ->numeric()
                                            ->default(50)
                                            ->required()
                                            ->prefix(fn (Forms\Get $get) => $get('currency') ?? 'RON'),

                                        Forms\Components\Select::make('currency')
                                            ->label('Currency')
                                            ->options([
                                                'RON' => 'RON (Romanian Leu)',
                                                'EUR' => 'EUR (Euro)',
                                                'USD' => 'USD (US Dollar)',
                                            ])
                                            ->default('RON')
                                            ->required(),

                                        Forms\Components\TextInput::make('withdrawal_processing_days')
                                            ->label('Processing Time')
                                            ->numeric()
                                            ->default(14)
                                            ->suffix('days')
                                            ->helperText('Estimated days to process withdrawals'),

                                        Forms\Components\Toggle::make('auto_approve_withdrawals')
                                            ->label('Auto-Approve Withdrawals')
                                            ->helperText('Automatically approve withdrawal requests')
                                            ->default(false),
                                    ])
                                    ->columns(2),

                                SC\Section::make('Payment Methods')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('payment_methods')
                                            ->label('Available Payment Methods')
                                            ->options([
                                                'bank_transfer' => 'Bank Transfer',
                                                'paypal' => 'PayPal',
                                                'revolut' => 'Revolut',
                                                'wise' => 'Wise',
                                            ])
                                            ->default(['bank_transfer'])
                                            ->columns(2),
                                    ]),
                            ]),

                        SC\Tabs\Tab::make('Tracking')
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                SC\Section::make('Cookie Settings')
                                    ->schema([
                                        Forms\Components\TextInput::make('cookie_name')
                                            ->label('Cookie Name')
                                            ->default('aff_ref')
                                            ->required()
                                            ->maxLength(50)
                                            ->helperText('Name of the tracking cookie'),

                                        Forms\Components\TextInput::make('cookie_duration_days')
                                            ->label('Cookie Duration')
                                            ->numeric()
                                            ->default(90)
                                            ->suffix('days')
                                            ->required()
                                            ->helperText('How long the affiliate attribution lasts'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Enable or disable the affiliate program')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('default_commission_value')
                    ->label('Commission')
                    ->formatStateUsing(fn ($record) => $record->getFormattedCommission()),

                Tables\Columns\TextColumn::make('min_withdrawal_amount')
                    ->label('Min. Withdrawal')
                    ->money(fn ($record) => $record->currency ?? 'RON'),

                Tables\Columns\IconColumn::make('allow_self_registration')
                    ->label('Self-Reg')
                    ->boolean(),

                Tables\Columns\IconColumn::make('require_approval')
                    ->label('Approval Req.')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                //
            ]);
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
            'index' => Pages\ListAffiliateSettings::route('/'),
            'edit' => Pages\EditAffiliateSettings::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $marketplaceClientId = static::getMarketplaceClientId();

        return parent::getEloquentQuery()
            ->where('marketplace_client_id', $marketplaceClientId);
    }
}
