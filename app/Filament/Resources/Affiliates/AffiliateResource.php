<?php

namespace App\Filament\Resources\Affiliates;

use App\Models\Affiliate;
use App\Services\AffiliateTrackingService;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use BackedEnum;

class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 20;
    protected static ?string $modelLabel = 'Affiliate';
    protected static ?string $pluralModelLabel = 'Affiliates';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Tenant Information')
                ->schema([
                    Forms\Components\Placeholder::make('tenant_name')
                        ->label('Tenant')
                        ->content(fn ($record) => $record?->tenant?->public_name ?? 'N/A'),

                    Forms\Components\Placeholder::make('tenant_domain')
                        ->label('Website')
                        ->content(fn ($record) => $record?->tenant?->domains?->first()?->domain ?? 'N/A'),
                ])
                ->columns(2)
                ->visible(fn ($record) => $record !== null),

            SC\Section::make('Affiliate Details')
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('Affiliate Code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->helperText('Unique code for this affiliate (e.g., PARTNER123)'),

                    Forms\Components\TextInput::make('name')
                        ->label('Affiliate Name')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('contact_email')
                        ->label('Contact Email')
                        ->email()
                        ->maxLength(255)
                        ->helperText('Email for self-purchase guard'),

                    Forms\Components\Select::make('status')
                        ->label('Status')
                        ->options([
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'suspended' => 'Suspended',
                        ])
                        ->default('active')
                        ->required(),
                ])->columns(2),

            SC\Section::make('Coupons')
                ->schema([
                    Forms\Components\Repeater::make('coupons')
                        ->relationship('coupons')
                        ->schema([
                            Forms\Components\TextInput::make('coupon_code')
                                ->label('Coupon Code')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\Select::make('discount_type')
                                ->label('Discount Type')
                                ->options([
                                    'percentage' => 'Percentage (%)',
                                    'fixed' => 'Fixed Amount',
                                ])
                                ->default('percentage')
                                ->required(),

                            Forms\Components\TextInput::make('discount_value')
                                ->label('Discount Value')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->required()
                                ->helperText('Enter percentage (e.g., 10 for 10%) or fixed amount'),

                            Forms\Components\TextInput::make('min_order_amount')
                                ->label('Min Order Amount')
                                ->numeric()
                                ->minValue(0)
                                ->step(0.01)
                                ->helperText('Minimum order value to apply coupon'),

                            Forms\Components\TextInput::make('max_uses')
                                ->label('Max Uses')
                                ->numeric()
                                ->minValue(1)
                                ->integer()
                                ->helperText('Leave empty for unlimited'),

                            Forms\Components\DateTimePicker::make('expires_at')
                                ->label('Expires At')
                                ->native(false)
                                ->helperText('Leave empty for no expiration'),

                            Forms\Components\Toggle::make('active')
                                ->label('Active')
                                ->default(true),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->addActionLabel('Add Coupon'),
                ]),

            SC\Section::make('Links')
                ->schema([
                    Forms\Components\Repeater::make('links')
                        ->relationship('links')
                        ->schema([
                            Forms\Components\TextInput::make('code')
                                ->label('Link Code')
                                ->required()
                                ->maxLength(255),

                            Forms\Components\TextInput::make('slug')
                                ->label('Slug (optional)')
                                ->maxLength(255),

                            Forms\Components\TextInput::make('landing_url')
                                ->label('Landing URL (optional)')
                                ->url()
                                ->maxLength(255),
                        ])
                        ->columns(3)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->addActionLabel('Add Link'),
                ]),

            SC\Section::make('Additional Information')
                ->schema([
                    Forms\Components\KeyValue::make('meta')
                        ->label('Metadata')
                        ->columnSpanFull()
                        ->helperText('Additional information about this affiliate'),
                ]),
        ]);
    }

    public static function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Affiliate $record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('tenant.public_name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Affiliate $record) => $record->tenant?->domains?->first()?->domain ?? '-')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'inactive',
                        'danger' => 'suspended',
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('conversions_count')
                    ->label('Conversions')
                    ->counts('conversions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->counts('clicks')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('stats_link')
                    ->label('')
                    ->icon('heroicon-o-chart-bar')
                    ->color('primary')
                    ->url(fn (Affiliate $record) => static::getUrl('stats', ['record' => $record]))
                    ->openUrlInNewTab(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'public_name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliates::route('/'),
            'create' => Pages\CreateAffiliate::route('/create'),
            'edit' => Pages\EditAffiliate::route('/{record}/edit'),
            'stats' => Pages\ViewAffiliateStats::route('/{record}/stats'),
        ];
    }
}
