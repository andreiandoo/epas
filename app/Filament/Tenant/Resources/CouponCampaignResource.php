<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CouponCampaignResource\Pages;
use App\Models\Coupon\CouponCampaign;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class CouponCampaignResource extends Resource
{
    protected static ?string $model = CouponCampaign::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Coupon Campaigns';

    protected static \UnitEnum|string|null $navigationGroup = 'Services';

    protected static ?int $navigationSort = 10;

    protected static ?string $modelLabel = 'Coupon Campaign';

    protected static ?string $pluralModelLabel = 'Coupon Campaigns';

    protected static ?string $slug = 'coupon-codes';

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Only show if tenant has coupon-codes microservice active
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
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $schema
            ->schema([
                Forms\Components\Hidden::make('tenant_id')
                    ->default($tenant?->id),

                SC\Section::make('Campaign Details')
                    ->schema([
                        Forms\Components\TextInput::make("name.{$tenantLanguage}")
                            ->label('Campaign Name')
                            ->required()
                            ->maxLength(190),

                        Forms\Components\Textarea::make("description.{$tenantLanguage}")
                            ->label('Description')
                            ->rows(3),

                        Forms\Components\Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'paused' => 'Paused',
                                'expired' => 'Expired',
                                'archived' => 'Archived',
                            ])
                            ->default('draft')
                            ->required(),
                    ])->columns(1),

                SC\Section::make('Schedule')
                    ->schema([
                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Start Date'),

                        Forms\Components\DateTimePicker::make('ends_at')
                            ->label('End Date'),
                    ])->columns(2),

                SC\Section::make('Limits')
                    ->schema([
                        Forms\Components\TextInput::make('budget_limit')
                            ->label('Budget Limit')
                            ->numeric()
                            ->prefix('€')
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Maximum total discount amount (leave empty for unlimited)'),

                        Forms\Components\TextInput::make('redemption_limit')
                            ->label('Redemption Limit')
                            ->numeric()
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Maximum number of redemptions (leave empty for unlimited)'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenant = auth()->user()->tenant;
        $tenantLanguage = $tenant->language ?? $tenant->locale ?? 'en';

        return $table
            ->columns([
                Tables\Columns\TextColumn::make("name.{$tenantLanguage}")
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'warning' => 'paused',
                        'danger' => fn ($state) => in_array($state, ['expired', 'archived']),
                    ]),

                Tables\Columns\TextColumn::make('codes_count')
                    ->label('Codes')
                    ->counts('codes'),

                Tables\Columns\TextColumn::make('redemption_count')
                    ->label('Redemptions'),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'expired' => 'Expired',
                        'archived' => 'Archived',
                    ]),
            ])
            ->actions([
                EditAction::make(),
                Actions\Action::make('generate_codes')
                    ->label('Generate Codes')
                    ->icon('heroicon-o-plus-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\TextInput::make('quantity')
                            ->label('Number of codes to generate')
                            ->numeric()
                            ->default(10)
                            ->minValue(1)
                            ->maxValue(1000)
                            ->required(),
                        Forms\Components\Select::make('discount_type')
                            ->label('Discount Type')
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
                            ->hintIcon('heroicon-o-information-circle', tooltip: 'Percentage (e.g., 10 for 10%) or fixed amount in EUR'),
                    ])
                    ->action(function (CouponCampaign $record, array $data): void {
                        $tenant = auth()->user()->tenant;
                        for ($i = 0; $i < $data['quantity']; $i++) {
                            \App\Models\Coupon\CouponCode::create([
                                'campaign_id' => $record->id,
                                'tenant_id' => $tenant->id,
                                // SECURITY FIX: Use cryptographically secure random for coupon codes
                                'code' => strtoupper(substr(bin2hex(random_bytes(5)), 0, 8)),
                                'discount_type' => $data['discount_type'],
                                'discount_value' => $data['discount_value'],
                                'status' => 'active',
                            ]);
                        }
                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Codes Generated')
                            ->body("{$data['quantity']} coupon codes have been created.")
                            ->send();
                    }),
            ])
            ->bulkActions([
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
            'index' => Pages\ListCouponCampaigns::route('/'),
            'create' => Pages\CreateCouponCampaign::route('/create'),
            'edit' => Pages\EditCouponCampaign::route('/{record}/edit'),
        ];
    }
}
