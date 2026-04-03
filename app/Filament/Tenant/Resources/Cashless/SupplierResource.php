<?php

namespace App\Filament\Tenant\Resources\Cashless;

use App\Enums\TenantType;
use App\Filament\Tenant\Resources\Cashless\SupplierResource\Pages;
use App\Models\MerchandiseSupplier;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SupplierResource extends Resource
{
    protected static ?string $model = MerchandiseSupplier::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationLabel = 'Suppliers';

    protected static \UnitEnum|string|null $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 30;

    protected static ?string $slug = 'cashless-suppliers';

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
            \Filament\Schemas\Components\Section::make('Company Details')->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\TextInput::make('company_name'),
                Forms\Components\TextInput::make('cui')->label('CUI'),
                Forms\Components\TextInput::make('reg_com')->label('Reg. Com.'),
                Forms\Components\TextInput::make('contact_person'),
                Forms\Components\TextInput::make('email')->email(),
                Forms\Components\TextInput::make('phone'),
                Forms\Components\TextInput::make('website')->url(),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('Fiscal & Banking')->schema([
                Forms\Components\Textarea::make('fiscal_address')->rows(2),
                Forms\Components\TextInput::make('county'),
                Forms\Components\TextInput::make('city'),
                Forms\Components\Toggle::make('is_vat_payer')->label('VAT Payer'),
                Forms\Components\TextInput::make('bank_name'),
                Forms\Components\TextInput::make('iban')->label('IBAN'),
            ])->columns(2),
            \Filament\Schemas\Components\Section::make('Contract')->schema([
                Forms\Components\TextInput::make('contract_number'),
                Forms\Components\DatePicker::make('contract_start'),
                Forms\Components\DatePicker::make('contract_end'),
                Forms\Components\TextInput::make('payment_terms_days')->numeric()->default(30),
                Forms\Components\Select::make('status')
                    ->options(['active' => 'Active', 'inactive' => 'Inactive', 'pending' => 'Pending'])
                    ->default('active'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('company_name')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('cui')->label('CUI')->toggleable(),
                Tables\Columns\TextColumn::make('contact_person'),
                Tables\Columns\TextColumn::make('email'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['success' => 'active', 'gray' => 'inactive', 'warning' => 'pending']),
                Tables\Columns\TextColumn::make('supplierProducts_count')
                    ->counts('supplierProducts')->label('Products'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit'   => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}
