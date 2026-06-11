<?php

namespace App\Filament\Tenant\Resources\Cashless;

use App\Enums\TenantType;
use App\Filament\Tenant\Resources\Cashless\VoucherResource\Pages;
use App\Models\Cashless\CashlessVoucher;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VoucherResource extends Resource
{
    protected static ?string $model = CashlessVoucher::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Vouchers';

    protected static \UnitEnum|string|null $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 50;

    protected static ?string $slug = 'cashless-vouchers';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        return $tenant && $tenant->tenant_type === TenantType::Festival;
    }

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            \Filament\Schemas\Components\Section::make('Voucher Details')->schema([
                Forms\Components\TextInput::make('code')->required()->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\Select::make('festival_edition_id')->label('Edition')
                    ->relationship('edition', 'name')->required(),
                Forms\Components\Select::make('voucher_type')
                    ->options(['fixed_credit' => 'Fixed Credit', 'percentage_bonus' => 'Percentage Bonus', 'topup_bonus' => 'Top-up Bonus'])
                    ->required()->reactive(),
                Forms\Components\TextInput::make('amount_cents')->numeric()->label('Credit Amount (cents)')
                    ->visible(fn ($get) => $get('voucher_type') === 'fixed_credit'),
                Forms\Components\TextInput::make('bonus_percentage')->numeric()->label('Bonus %')
                    ->visible(fn ($get) => in_array($get('voucher_type'), ['percentage_bonus', 'topup_bonus'])),
                Forms\Components\TextInput::make('min_topup_cents')->numeric()->label('Min Top-up (cents)')
                    ->visible(fn ($get) => $get('voucher_type') === 'topup_bonus'),
                Forms\Components\TextInput::make('max_bonus_cents')->numeric()->label('Max Bonus (cents)'),
                Forms\Components\TextInput::make('sponsor_name'),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('Limits')->schema([
                Forms\Components\TextInput::make('total_budget_cents')->numeric()->label('Total Budget (cents)'),
                Forms\Components\TextInput::make('max_redemptions')->numeric(),
                Forms\Components\TextInput::make('max_per_customer')->numeric()->default(1),
                Forms\Components\DateTimePicker::make('valid_from'),
                Forms\Components\DateTimePicker::make('valid_until'),
                Forms\Components\Toggle::make('is_active')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable()->sortable()->copyable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\BadgeColumn::make('voucher_type'),
                Tables\Columns\TextColumn::make('amount_cents')->label('Amount')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2) : '-'),
                Tables\Columns\TextColumn::make('bonus_percentage')->label('Bonus %')
                    ->formatStateUsing(fn ($state) => $state ? $state . '%' : '-'),
                Tables\Columns\TextColumn::make('current_redemptions')->label('Used')
                    ->formatStateUsing(fn ($record) => $record->current_redemptions . ($record->max_redemptions ? '/' . $record->max_redemptions : '')),
                Tables\Columns\TextColumn::make('used_budget_cents')->label('Budget Used')
                    ->formatStateUsing(fn ($record) => number_format($record->used_budget_cents / 100, 2) . ($record->total_budget_cents ? '/' . number_format($record->total_budget_cents / 100, 2) : '')),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('sponsor_name')->toggleable(),
            ])
            ->actions([Actions\EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListVouchers::route('/'),
            'create' => Pages\CreateVoucher::route('/create'),
            'edit'   => Pages\EditVoucher::route('/{record}/edit'),
        ];
    }
}
