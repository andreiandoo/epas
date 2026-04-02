<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\MerchandiseSupplierResource\Pages;
use App\Models\MerchandiseSupplier;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\TenantType;

class MerchandiseSupplierResource extends Resource
{
    protected static ?string $model = MerchandiseSupplier::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-truck';
    protected static \UnitEnum|string|null $navigationGroup = 'Festival';
    protected static ?int $navigationSort = 7;
    protected static ?string $navigationLabel = 'Furnizori marfa';
    protected static ?string $modelLabel = 'Furnizor';
    protected static ?string $pluralModelLabel = 'Furnizori marfa';

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
        return $schema
            ->components([
                SC\Section::make('Date furnizor')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nume furnizor')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('cui')
                            ->label('CUI'),
                        Forms\Components\TextInput::make('contact_person')
                            ->label('Persoana contact'),
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel(),
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('cui')
                    ->label('CUI')
                    ->searchable(),
                Tables\Columns\TextColumn::make('contact_person')
                    ->label('Contact'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Telefon'),
                Tables\Columns\TextColumn::make('email')
                    ->label('Email'),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Produse')
                    ->counts('items'),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMerchandiseSuppliers::route('/'),
            'create' => Pages\CreateMerchandiseSupplier::route('/create'),
            'edit'   => Pages\EditMerchandiseSupplier::route('/{record}/edit'),
        ];
    }
}
