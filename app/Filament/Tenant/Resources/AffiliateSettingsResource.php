<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\AffiliateSettingsResource\Pages;
use App\Models\AffiliateSettings;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliateSettingsResource extends Resource
{
    protected static ?string $model = AffiliateSettings::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('Marketing');
    }

    protected static ?string $navigationLabel = 'Affiliate Settings';

    public static function getNavigationLabel(): string
    {
        return __('Affiliate Settings');
    }

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Affiliate Settings';

    public static function getModelLabel(): string
    {
        return __('Affiliate Settings');
    }

    protected static ?string $pluralModelLabel = 'Affiliate Settings';

    public static function getPluralModelLabel(): string
    {
        return __('Affiliate Settings');
    }

    public static function getNavigationBadge(): ?string
    {
        return null;
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Only show if affiliates microservice is enabled
        $tenant = filament()->getTenant();
        if (!$tenant) {
            return false;
        }

        return $tenant->microservices()
            ->where('slug', 'affiliates')
            ->wherePivot('is_active', true)
            ->exists();
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
                                SC\Section::make(__('Default Commission Settings'))
                                    ->description(__('Set the default commission for new affiliates'))
                                    ->schema([
                                        Forms\Components\Select::make('default_commission_type')
                                            ->label(__('Commission Type'))
                                            ->options([
                                                'percent' => __('Percentage (%)'),
                                                'fixed' => 'Fixed Amount',
                                            ])
                                            ->default('percent')
                                            ->required()
                                            ->live(),

                                        Forms\Components\TextInput::make('default_commission_value')
                                            ->label(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('default_commission_type') === 'percent' ? 'Commission Percentage' : 'Commission Amount')
                                            ->numeric()
                                            ->default(10)
                                            ->required()
                                            ->suffix(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('default_commission_type') === 'percent' ? '%' : null)
                                            ->helperText(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('default_commission_type') === 'percent'
                                                ? 'Percentage of order value'
                                                : 'Fixed amount per conversion'),

                                        Forms\Components\TextInput::make('commission_hold_days')
                                            ->label(__('Commission Hold Period'))
                                            ->numeric()
                                            ->default(30)
                                            ->suffix('days')
                                            ->helperText(__('Days before commission becomes available for withdrawal')),
                                    ])
                                    ->columns(3),

                                SC\Section::make(__('Commission Rules'))
                                    ->schema([
                                        Forms\Components\Toggle::make('exclude_taxes')
                                            ->label(__('Exclude Taxes'))
                                            ->helperText(__('Calculate commission on net amount (excluding taxes)'))
                                            ->default(true),

                                        Forms\Components\Toggle::make('exclude_shipping')
                                            ->label(__('Exclude Shipping'))
                                            ->helperText(__('Calculate commission excluding shipping costs'))
                                            ->default(true),

                                        Forms\Components\Toggle::make('prevent_self_purchase')
                                            ->label(__('Prevent Self-Purchase'))
                                            ->helperText(__('Prevent affiliates from earning commission on their own orders'))
                                            ->default(true),
                                    ])
                                    ->columns(3),
                            ]),

                        SC\Tabs\Tab::make(__('Registration'))
                            ->icon('heroicon-o-user-plus')
                            ->schema([
                                SC\Section::make(__('Self-Registration'))
                                    ->schema([
                                        Forms\Components\Toggle::make('allow_self_registration')
                                            ->label(__('Allow Self-Registration'))
                                            ->helperText(__('Allow customers to register as affiliates through your website'))
                                            ->default(true)
                                            ->live(),

                                        Forms\Components\Toggle::make('require_approval')
                                            ->label(__('Require Approval'))
                                            ->helperText(__('New affiliates must be approved before they can start earning'))
                                            ->default(true)
                                            ->visible(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('allow_self_registration')),
                                    ])
                                    ->columns(2),

                                SC\Section::make(__('Program Information'))
                                    ->description(__('Information shown on the affiliate registration page'))
                                    ->schema([
                                        Forms\Components\TextInput::make('program_name')
                                            ->label(__('Program Name'))
                                            ->placeholder(__('Partner Program'))
                                            ->maxLength(255),

                                        Forms\Components\Textarea::make('program_description')
                                            ->label(__('Program Description'))
                                            ->rows(3)
                                            ->placeholder(__('Describe your affiliate program...')),

                                        Forms\Components\Repeater::make('program_benefits')
                                            ->label(__('Benefits'))
                                            ->simple(
                                                Forms\Components\TextInput::make('benefit')
                                                    ->placeholder(__('e.g., Earn 10% on every sale'))
                                            )
                                            ->addActionLabel(__('Add Benefit'))
                                            ->collapsible()
                                            ->defaultItems(0),

                                        Forms\Components\RichEditor::make('registration_terms')
                                            ->label('Terms & Conditions')
                                            ->helperText(__('Affiliates must accept these terms to register'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        SC\Tabs\Tab::make(__('Withdrawals'))
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                SC\Section::make(__('Withdrawal Settings'))
                                    ->schema([
                                        Forms\Components\TextInput::make('min_withdrawal_amount')
                                            ->label(__('Minimum Withdrawal Amount'))
                                            ->numeric()
                                            ->default(50)
                                            ->required()
                                            ->prefix(fn (\Filament\Schemas\Components\Utilities\Get $get) => $get('currency') ?? 'RON'),

                                        Forms\Components\Select::make('currency')
                                            ->label('Currency')
                                            ->options([
                                                'RON' => __('RON (Romanian Leu)'),
                                                'EUR' => __('EUR (Euro)'),
                                                'USD' => __('USD (US Dollar)'),
                                            ])
                                            ->default('RON')
                                            ->required(),

                                        Forms\Components\TextInput::make('withdrawal_processing_days')
                                            ->label(__('Processing Time'))
                                            ->numeric()
                                            ->default(14)
                                            ->suffix('days')
                                            ->helperText(__('Estimated days to process withdrawals')),

                                        Forms\Components\Toggle::make('auto_approve_withdrawals')
                                            ->label(__('Auto-Approve Withdrawals'))
                                            ->helperText(__('Automatically approve withdrawal requests'))
                                            ->default(false),
                                    ])
                                    ->columns(2),

                                SC\Section::make(__('Payment Methods'))
                                    ->schema([
                                        Forms\Components\CheckboxList::make('payment_methods')
                                            ->label(__('Available Payment Methods'))
                                            ->options([
                                                'bank_transfer' => __('Bank Transfer'),
                                                'paypal' => 'PayPal',
                                                'revolut' => 'Revolut',
                                                'wise' => 'Wise',
                                            ])
                                            ->default(['bank_transfer'])
                                            ->columns(2),
                                    ]),
                            ]),

                        SC\Tabs\Tab::make(__('Tracking'))
                            ->icon('heroicon-o-chart-bar')
                            ->schema([
                                SC\Section::make(__('Cookie Settings'))
                                    ->schema([
                                        Forms\Components\TextInput::make('cookie_name')
                                            ->label(__('Cookie Name'))
                                            ->default('aff_ref')
                                            ->required()
                                            ->maxLength(50)
                                            ->helperText(__('Name of the tracking cookie')),

                                        Forms\Components\TextInput::make('cookie_duration_days')
                                            ->label(__('Cookie Duration'))
                                            ->numeric()
                                            ->default(90)
                                            ->suffix('days')
                                            ->required()
                                            ->helperText(__('How long the affiliate attribution lasts')),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->helperText(__('Enable or disable the affiliate program'))
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
                    ->label(__('Min. Withdrawal'))
                    ->money(fn ($record) => $record->currency ?? 'RON'),

                Tables\Columns\IconColumn::make('allow_self_registration')
                    ->label(__('Self-Reg'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('require_approval')
                    ->label(__('Approval Req.'))
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Last Updated'))
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
        $tenant = filament()->getTenant();

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id);
    }
}
