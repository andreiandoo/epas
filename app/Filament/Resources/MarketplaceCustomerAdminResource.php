<?php

namespace App\Filament\Resources;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use BackedEnum;
use UnitEnum;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class MarketplaceCustomerAdminResource extends Resource
{
    protected static ?string $model = MarketplaceCustomer::class;

    protected static ?string $slug = 'marketplace-customers';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Marketplace Customers';

    protected static UnitEnum|string|null $navigationGroup = 'Marketplace';

    protected static ?int $navigationSort = 31;

    protected static ?string $modelLabel = 'Customer';

    protected static ?string $pluralModelLabel = 'Marketplace Customers';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::count();
        return $count > 0 ? number_format($count) : null;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Grid::make(3)->schema([
                SC\Group::make()->columnSpan(2)->schema([
                    SC\Section::make('Account Information')
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            Forms\Components\Select::make('marketplace_client_id')
                                ->label('Marketplace')
                                ->relationship('marketplaceClient', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disabled(fn ($record) => $record !== null),

                            Forms\Components\TextInput::make('email')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->disabled(fn ($record) => $record !== null),

                            Forms\Components\Select::make('status')
                                ->options([
                                    'active' => 'Active',
                                    'suspended' => 'Suspended',
                                ])
                                ->required()
                                ->default('active'),
                        ])->columns(2),

                    SC\Section::make('Personal Details')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\TextInput::make('first_name')->maxLength(100),
                            Forms\Components\TextInput::make('last_name')->maxLength(100),
                            Forms\Components\TextInput::make('phone')->tel()->maxLength(50),
                            Forms\Components\DatePicker::make('birth_date'),
                            Forms\Components\Select::make('gender')
                                ->options([
                                    'male' => 'Male',
                                    'female' => 'Female',
                                    'other' => 'Other',
                                ]),
                            Forms\Components\TextInput::make('city')->maxLength(100),
                            Forms\Components\TextInput::make('state')->maxLength(100),
                            Forms\Components\TextInput::make('country')->maxLength(2),
                        ])->columns(2),

                    SC\Section::make('Settings & Preferences')
                        ->icon('heroicon-o-cog-6-tooth')
                        ->collapsible()
                        ->collapsed()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Placeholder::make('settings_display')
                                ->hiddenLabel()
                                ->content(fn ($record) => new HtmlString(
                                    '<pre style="font-size:12px;max-height:300px;overflow:auto;background:#0F172A;color:#E2E8F0;padding:12px;border-radius:8px;">'
                                    . e(json_encode($record?->settings ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
                                    . '</pre>'
                                )),
                        ]),
                ]),

                SC\Group::make()->columnSpan(1)->schema([
                    SC\Section::make('Overview')
                        ->compact()
                        ->visibleOn('edit')
                        ->schema([
                            Forms\Components\Placeholder::make('overview')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderOverview($record)),
                        ]),
                ]),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(['first_name']),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('marketplaceClient.name')
                    ->label('Marketplace')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                Tables\Columns\IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-badge')
                    ->falseIcon('heroicon-o-x-circle')
                    ->getStateUsing(fn ($record) => $record->email_verified_at !== null),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'suspended' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('total_spent')
                    ->label('Spent')
                    ->money('RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->since()
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('marketplace_client_id')
                    ->label('Marketplace')
                    ->relationship('marketplaceClient', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'suspended' => 'Suspended',
                    ]),

                Tables\Filters\TernaryFilter::make('email_verified')
                    ->label('Email Verified')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('email_verified_at'),
                        false: fn (Builder $query) => $query->whereNull('email_verified_at'),
                    ),

                Tables\Filters\TernaryFilter::make('has_orders')
                    ->label('Has Orders')
                    ->queries(
                        true: fn (Builder $query) => $query->where('total_orders', '>', 0),
                        false: fn (Builder $query) => $query->where('total_orders', 0),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Resources\MarketplaceCustomerAdminResource\Pages\ListMarketplaceCustomers::route('/'),
            'view' => \App\Filament\Resources\MarketplaceCustomerAdminResource\Pages\ViewMarketplaceCustomer::route('/{record}'),
            'edit' => \App\Filament\Resources\MarketplaceCustomerAdminResource\Pages\EditMarketplaceCustomer::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('marketplaceClient');
    }

    protected static function renderOverview(?MarketplaceCustomer $record): HtmlString
    {
        if (!$record) {
            return new HtmlString('');
        }

        $initials = mb_substr($record->first_name ?? '', 0, 1) . mb_substr($record->last_name ?? '', 0, 1);
        $fullName = e(trim(($record->first_name ?? '') . ' ' . ($record->last_name ?? '')) ?: 'Unknown');
        $email = e($record->email);
        $marketplace = e($record->marketplaceClient?->name ?? '-');
        $lastLogin = $record->last_login_at?->diffForHumans() ?? 'Never';
        $registered = $record->created_at?->format('d M Y') ?? '-';

        $statusBadge = match ($record->status) {
            'active' => '<span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(16,185,129,0.15);color:#10B981;">Active</span>',
            'suspended' => '<span style="padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;background:rgba(239,68,68,0.15);color:#EF4444;">Suspended</span>',
            default => '',
        };

        return new HtmlString("
            <div style='text-align:center;margin-bottom:16px;'>
                <div style='width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#6366F1,#8B5CF6);display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;color:white;margin:0 auto 8px;'>{$initials}</div>
                <div style='font-size:16px;font-weight:700;'>{$fullName}</div>
                <div style='font-size:12px;color:#64748B;'>{$email}</div>
                <div style='margin-top:6px;'>{$statusBadge}</div>
            </div>
            <div style='display:grid;grid-template-columns:repeat(2,1fr);gap:8px;'>
                <div style='background:#0F172A;border-radius:8px;padding:12px;text-align:center;'>
                    <div style='font-size:18px;font-weight:700;color:white;'>{$record->total_orders}</div>
                    <div style='font-size:10px;color:#64748B;text-transform:uppercase;'>Orders</div>
                </div>
                <div style='background:#0F172A;border-radius:8px;padding:12px;text-align:center;'>
                    <div style='font-size:14px;font-weight:700;color:white;'>" . number_format((float) $record->total_spent, 2) . "</div>
                    <div style='font-size:10px;color:#64748B;text-transform:uppercase;'>RON Spent</div>
                </div>
            </div>
            <div style='margin-top:12px;font-size:12px;'>
                <div style='display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(51,65,85,0.3);'>
                    <span style='color:#64748B;'>Marketplace</span>
                    <span style='font-weight:600;'>{$marketplace}</span>
                </div>
                <div style='display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid rgba(51,65,85,0.3);'>
                    <span style='color:#64748B;'>Last Login</span>
                    <span style='font-weight:600;'>{$lastLogin}</span>
                </div>
                <div style='display:flex;justify-content:space-between;padding:6px 0;'>
                    <span style='color:#64748B;'>Registered</span>
                    <span style='font-weight:600;'>{$registered}</span>
                </div>
            </div>
        ");
    }
}
