<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CouponCodeResource\Pages;
use App\Models\Coupon\CouponCode;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CouponCodeResource extends Resource
{
    protected static ?string $model = CouponCode::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Coupon Codes';

    protected static ?string $navigationParentItem = 'Coupon Campaigns';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 15;

    protected static ?string $modelLabel = 'Coupon Code';

    protected static ?string $pluralModelLabel = 'Coupon Codes';

    protected static ?string $slug = 'coupon-codes-list';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()->tenant;
        if (!$tenant) return false;

        return $tenant->microservices()
            ->where('slug', 'coupon-codes')
            ->wherePivot('is_active', true)
            ->exists();
    }

    public static function form(Schema $schema): Schema
    {
        $tenant = auth()->user()->tenant;

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Code Information')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(50)
                            ->unique(ignoreRecord: true)
                            ->formatStateUsing(fn ($state) => strtoupper($state))
                            ->dehydrateStateUsing(fn ($state) => strtoupper($state)),

                        Forms\Components\Select::make('campaign_id')
                            ->label('Campaign')
                            ->relationship('campaign', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()) ?? ($record->name[app()->getLocale()] ?? ($record->name['en'] ?? array_values((array) $record->name)[0] ?? 'Untitled')))
                            ->searchable()
                            ->preload(),

                        Forms\Components\Select::make('status')
                            ->options([
                                'active' => 'Active',
                                'inactive' => 'Inactive',
                                'exhausted' => 'Exhausted',
                                'expired' => 'Expired',
                            ])
                            ->default('active')
                            ->required(),
                    ])->columns(3),

                SC\Section::make('Discount')
                    ->schema([
                        Forms\Components\Select::make('discount_type')
                            ->options([
                                'percentage' => 'Percentage',
                                'fixed_amount' => 'Fixed Amount',
                                'free_shipping' => 'Free Shipping',
                            ])
                            ->default('percentage')
                            ->required(),

                        Forms\Components\TextInput::make('discount_value')
                            ->label('Discount Value')
                            ->numeric()
                            ->required()
                            ->helperText('Percentage (10 = 10%) or fixed amount'),

                        Forms\Components\TextInput::make('max_discount_amount')
                            ->label('Max Discount')
                            ->numeric()
                            ->prefix('€')
                            ->helperText('Cap for percentage discounts'),

                        Forms\Components\TextInput::make('min_purchase_amount')
                            ->label('Min. Purchase')
                            ->numeric()
                            ->prefix('€'),
                    ])->columns(4),

                SC\Section::make('Usage Limits')
                    ->schema([
                        Forms\Components\TextInput::make('max_uses_total')
                            ->label('Max Uses (Total)')
                            ->numeric()
                            ->helperText('Leave empty for unlimited'),

                        Forms\Components\TextInput::make('max_uses_per_user')
                            ->label('Max Uses (Per User)')
                            ->numeric()
                            ->default(1),

                        Forms\Components\TextInput::make('current_uses')
                            ->label('Current Uses')
                            ->numeric()
                            ->default(0)
                            ->disabled(),
                    ])->columns(3),

                SC\Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Valid From'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At'),
                    ])->columns(2),

                SC\Section::make('Options')
                    ->schema([
                        Forms\Components\Toggle::make('is_public')
                            ->label('Public Code')
                            ->helperText('Show in public listings'),

                        Forms\Components\Toggle::make('first_purchase_only')
                            ->label('First Purchase Only'),

                        Forms\Components\Toggle::make('combinable')
                            ->label('Combinable')
                            ->helperText('Can be combined with other codes'),
                    ])->columns(3),
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
                    ->weight('bold')
                    ->copyable(),

                Tables\Columns\TextColumn::make('campaign.name')
                    ->label('Campaign')
                    ->getStateUsing(fn ($record) => $record->campaign?->getTranslation('name', app()->getLocale()) ?? ($record->campaign?->name[app()->getLocale()] ?? ($record->campaign?->name['en'] ?? null)))
                    ->searchable()
                    ->sortable()
                    ->limit(20)
                    ->placeholder('No campaign'),

                Tables\Columns\TextColumn::make('discount_display')
                    ->label('Discount')
                    ->getStateUsing(function ($record) {
                        if ($record->discount_type === 'percentage') {
                            return $record->discount_value . '%';
                        }
                        if ($record->discount_type === 'fixed_amount') {
                            return '€' . number_format($record->discount_value, 2);
                        }
                        return 'Free Shipping';
                    }),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'gray' => 'inactive',
                        'danger' => fn ($state) => in_array($state, ['exhausted', 'expired']),
                    ]),

                Tables\Columns\TextColumn::make('usage')
                    ->label('Usage')
                    ->getStateUsing(function ($record) {
                        $max = $record->max_uses_total ?? '∞';
                        return "{$record->current_uses}/{$max}";
                    }),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'exhausted' => 'Exhausted',
                        'expired' => 'Expired',
                    ]),
                Tables\Filters\SelectFilter::make('campaign_id')
                    ->label('Campaign')
                    ->relationship('campaign', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->getTranslation('name', app()->getLocale()) ?? ($record->name[app()->getLocale()] ?? ($record->name['en'] ?? array_values((array) $record->name)[0] ?? 'Untitled')))
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('discount_type')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed_amount' => 'Fixed Amount',
                        'free_shipping' => 'Free Shipping',
                    ]),
                Tables\Filters\TernaryFilter::make('is_public')
                    ->label('Public'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                Actions\Action::make('toggle_status')
                    ->label(fn ($record) => $record->status === 'active' ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->status === 'active' ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->status === 'active' ? 'danger' : 'success')
                    ->action(function ($record) {
                        $record->update([
                            'status' => $record->status === 'active' ? 'inactive' : 'active',
                        ]);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'active']))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['status' => 'inactive']))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCouponCodes::route('/'),
            'create' => Pages\CreateCouponCode::route('/create'),
            'view' => Pages\ViewCouponCode::route('/{record}'),
            'edit' => Pages\EditCouponCode::route('/{record}/edit'),
        ];
    }
}
