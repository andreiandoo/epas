<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\AffiliateResource\Pages;
use App\Models\Affiliate;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AffiliateResource extends Resource
{
    protected static ?string $model = Affiliate::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Affiliates';
    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;

        return parent::getEloquentQuery()
            ->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                SC\Section::make('Affiliate Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(190),

                        Forms\Components\TextInput::make('code')
                            ->label('Affiliate Code')
                            ->helperText('Leave empty to auto-generate')
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record !== null),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('Contact Email')
                            ->email()
                            ->required()
                            ->maxLength(190),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'active' => 'Active',
                                'suspended' => 'Suspended',
                                'inactive' => 'Inactive',
                            ])
                            ->default('active')
                            ->required(),
                    ]),

                SC\Section::make('Commission Settings')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('commission_type')
                            ->label('Commission Type')
                            ->options([
                                'percent' => 'Percentage (%)',
                                'fixed' => 'Fixed Amount (RON)',
                            ])
                            ->default('percent')
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('commission_rate')
                            ->label(fn ($get) => $get('commission_type') === 'fixed' ? 'Fixed Amount (RON)' : 'Commission Rate (%)')
                            ->numeric()
                            ->default(10)
                            ->minValue(0)
                            ->maxValue(fn ($get) => $get('commission_type') === 'percent' ? 100 : 10000)
                            ->required(),
                    ]),

                SC\Section::make('Coupon Code')
                    ->description('Assign a coupon code for coupon-based attribution')
                    ->schema([
                        Forms\Components\TextInput::make('coupon_code')
                            ->label('Coupon Code')
                            ->helperText('If this coupon is used at checkout, the order will be attributed to this affiliate')
                            ->maxLength(50)
                            ->dehydrated(false)
                            ->afterStateHydrated(function ($state, $set, $record) {
                                if ($record) {
                                    $coupon = $record->coupons()->where('active', true)->first();
                                    $set('coupon_code', $coupon?->coupon_code);
                                }
                            }),
                    ])
                    ->collapsed(),

                SC\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\KeyValue::make('meta')
                            ->label('Additional Data')
                            ->keyLabel('Key')
                            ->valueLabel('Value')
                            ->addActionLabel('Add field'),
                    ])
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => static::getUrl('view', ['record' => $record])),

                Tables\Columns\TextColumn::make('contact_email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'suspended',
                        'gray' => 'inactive',
                    ]),

                Tables\Columns\TextColumn::make('commission_display')
                    ->label('Commission')
                    ->getStateUsing(function ($record) {
                        if ($record->commission_type === 'fixed') {
                            return number_format($record->commission_rate, 2) . ' RON';
                        }
                        return $record->commission_rate . '%';
                    }),

                Tables\Columns\TextColumn::make('conversions_count')
                    ->label('Conversions')
                    ->counts('conversions')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_commission')
                    ->label('Commission Earned')
                    ->getStateUsing(function ($record) {
                        $total = $record->conversions()
                            ->where('status', 'approved')
                            ->sum('commission_value');
                        return number_format($total, 2) . ' RON';
                    })
                    ->color('success'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('edit_link')
                    ->label('')
                    ->getStateUsing(fn () => '✏️')
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record]))
                    ->tooltip('Edit affiliate'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->actions([])
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAffiliates::route('/'),
            'create' => Pages\CreateAffiliate::route('/create'),
            'view' => Pages\ViewAffiliate::route('/{record}'),
            'edit' => Pages\EditAffiliate::route('/{record}/edit'),
        ];
    }
}
