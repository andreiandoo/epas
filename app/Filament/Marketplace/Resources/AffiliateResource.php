<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\AffiliateResource\Pages;
use App\Models\Affiliate;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Notifications\Notification;
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
    protected static \UnitEnum|string|null $navigationGroup = 'Services';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $tenant = auth()->user()?->tenant;
        if (!$tenant) {
            return null;
        }

        $count = Affiliate::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', Affiliate::STATUS_PENDING)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

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
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Leave empty to auto-generate')
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
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'If this coupon is used at checkout, the order will be attributed to this affiliate')
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
                        'warning' => 'pending',
                        'danger' => 'suspended',
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
                        'pending' => 'Pending Approval',
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                        'inactive' => 'Inactive',
                    ]),
            ])
            ->actions([
                Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => $record->status === Affiliate::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Approve Affiliate')
                    ->modalDescription('This will activate the affiliate and allow them to start earning commissions.')
                    ->action(function ($record) {
                        $record->approve();
                        Notification::make()
                            ->success()
                            ->title('Affiliate approved')
                            ->body("'{$record->name}' has been approved and can now earn commissions.")
                            ->send();
                    }),

                Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record) => $record->status === Affiliate::STATUS_PENDING)
                    ->requiresConfirmation()
                    ->modalHeading('Reject Affiliate Application')
                    ->modalDescription('This will permanently reject the affiliate application.')
                    ->action(function ($record) {
                        $record->update(['status' => Affiliate::STATUS_INACTIVE]);
                        Notification::make()
                            ->warning()
                            ->title('Affiliate rejected')
                            ->body("'{$record->name}' application has been rejected.")
                            ->send();
                    }),

                Action::make('suspend')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === Affiliate::STATUS_ACTIVE)
                    ->requiresConfirmation()
                    ->modalHeading('Suspend Affiliate')
                    ->modalDescription('This will temporarily suspend the affiliate from earning commissions.')
                    ->action(function ($record) {
                        $record->update(['status' => Affiliate::STATUS_SUSPENDED]);
                        Notification::make()
                            ->warning()
                            ->title('Affiliate suspended')
                            ->body("'{$record->name}' has been suspended.")
                            ->send();
                    }),

                Action::make('reactivate')
                    ->label('Reactivate')
                    ->icon('heroicon-o-play-circle')
                    ->color('success')
                    ->visible(fn ($record) => in_array($record->status, [Affiliate::STATUS_SUSPENDED, Affiliate::STATUS_INACTIVE]))
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update(['status' => Affiliate::STATUS_ACTIVE]);
                        Notification::make()
                            ->success()
                            ->title('Affiliate reactivated')
                            ->body("'{$record->name}' has been reactivated.")
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkAction::make('bulk_approve')
                    ->label('Approve Selected')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve Selected Affiliates')
                    ->modalDescription('This will approve all selected pending affiliates.')
                    ->action(function ($records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status === Affiliate::STATUS_PENDING) {
                                $record->approve();
                                $count++;
                            }
                        }
                        Notification::make()
                            ->success()
                            ->title("{$count} affiliates approved")
                            ->send();
                    }),
            ])
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
