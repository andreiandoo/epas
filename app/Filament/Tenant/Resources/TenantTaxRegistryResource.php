<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\TenantTaxRegistryResource\Pages;
use App\Models\Leisure\TenantTaxRegistry;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Illuminate\Database\Eloquent\Builder;

class TenantTaxRegistryResource extends Resource
{
    protected static ?string $model = TenantTaxRegistry::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-library';
    protected static \UnitEnum|string|null $navigationGroup = 'Leisure';
    protected static ?int $navigationSort = 40;
    protected static ?string $navigationLabel = 'Societăți (multi-CIF)';
    protected static ?string $modelLabel = 'Societate';
    protected static ?string $pluralModelLabel = 'Societăți';

    public static function shouldRegisterNavigation(): bool
    {
        $tenant = auth()->user()?->tenant;
        $type = $tenant?->tenant_type instanceof \App\Enums\TenantType
            ? $tenant->tenant_type->value : (string) $tenant?->tenant_type;
        return $type === 'leisure' && ($tenant?->features['leisure']['multi_society']['enabled'] ?? false);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tenant_id', auth()->user()?->tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            SC\Section::make('Date fiscale')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('company_name')->label('Denumire')->required(),
                    Forms\Components\TextInput::make('cui')->label('CUI / CIF')->required(),
                    Forms\Components\TextInput::make('reg_com')->label('Nr. registru comerțului'),
                    Forms\Components\Toggle::make('vat_payer')->label('Plătitor TVA'),
                    Forms\Components\TextInput::make('vat_number')->label('Cod TVA (RO...)'),
                ]),
            SC\Section::make('Adresă')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('country')->label('Țară')->default('RO')->maxLength(2),
                    Forms\Components\TextInput::make('state')->label('Județ'),
                    Forms\Components\TextInput::make('city')->label('Oraș'),
                    Forms\Components\TextInput::make('postal_code')->label('Cod poștal'),
                    Forms\Components\TextInput::make('address')->label('Adresă')->columnSpanFull(),
                ]),
            SC\Section::make('Bancă')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('bank_name')->label('Bancă'),
                    Forms\Components\TextInput::make('bank_account')->label('IBAN'),
                ]),
            SC\Section::make('Facturare')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('invoice_series')->label('Serie facturi')->placeholder('ex: AQUA, ROL'),
                    Forms\Components\TextInput::make('invoice_next_number')
                        ->label('Următorul număr')
                        ->numeric()->default(1),
                    Forms\Components\Toggle::make('is_default')
                        ->label('Societate implicită')
                        ->helperText('Produsele fără societate asignată vor fi facturate de aici.'),
                    Forms\Components\Toggle::make('is_active')->label('Activă')->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('company_name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cui')->copyable(),
                Tables\Columns\IconColumn::make('vat_payer')->boolean()->label('TVA'),
                Tables\Columns\TextColumn::make('invoice_series')->toggleable(),
                Tables\Columns\TextColumn::make('invoice_next_number')->label('Următor')->alignEnd(),
                Tables\Columns\IconColumn::make('is_default')->boolean()->label('Implicită'),
                Tables\Columns\IconColumn::make('is_active')->boolean()->label('Activă'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('makeDefault')
                    ->label('Setează ca implicită')
                    ->visible(fn ($record) => ! $record->is_default)
                    ->icon('heroicon-o-star')
                    ->action(function ($record) {
                        TenantTaxRegistry::where('tenant_id', $record->tenant_id)->update(['is_default' => false]);
                        $record->update(['is_default' => true]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantTaxRegistries::route('/'),
            'create' => Pages\CreateTenantTaxRegistry::route('/create'),
            'edit' => Pages\EditTenantTaxRegistry::route('/{record}/edit'),
        ];
    }
}
