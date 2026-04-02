<?php

namespace App\Filament\Vendor\Resources;

use App\Enums\ProductType;
use App\Filament\Vendor\Resources\ProductResource\Pages;
use App\Models\VendorProduct;
use App\Models\VendorProductCategory;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ProductResource extends Resource
{
    protected static ?string $model = VendorProduct::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $employee = Auth::guard('vendor_employee')->user();

        return $employee && in_array($employee->role, ['manager', 'admin']);
    }

    public static function getEloquentQuery(): Builder
    {
        $employee = Auth::guard('vendor_employee')->user();

        return parent::getEloquentQuery()
            ->where('vendor_id', $employee->vendor_id);
    }

    public static function form(Schema $schema): Schema
    {
        $employee = Auth::guard('vendor_employee')->user();

        return $schema->schema([
            Forms\Components\Section::make('Product Details')->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Select::make('type')
                    ->options(collect(ProductType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                    ->required(),

                Forms\Components\Select::make('vendor_product_category_id')
                    ->label('Category')
                    ->options(fn () => VendorProductCategory::where('vendor_id', $employee->vendor_id)->pluck('name', 'id'))
                    ->searchable(),

                Forms\Components\Textarea::make('description')
                    ->rows(3),

                Forms\Components\TextInput::make('sku')
                    ->label('SKU')
                    ->maxLength(100),
            ])->columns(2),

            Forms\Components\Section::make('Pricing')->schema([
                Forms\Components\TextInput::make('price_cents')
                    ->label('Price (cents)')
                    ->numeric()
                    ->required(),

                Forms\Components\TextInput::make('sale_price_cents')
                    ->label('Sale Price (cents)')
                    ->numeric()
                    ->helperText('Override price. Leave empty to use base price.'),

                Forms\Components\TextInput::make('vat_rate')
                    ->label('VAT Rate (%)')
                    ->numeric()
                    ->default(19),

                Forms\Components\Toggle::make('vat_included')
                    ->label('Price includes VAT')
                    ->default(true),

                Forms\Components\TextInput::make('sgr_cents')
                    ->label('SGR Tax (cents)')
                    ->numeric()
                    ->default(0)
                    ->helperText('Recycling tax per item'),
            ])->columns(2),

            Forms\Components\Section::make('Details')->schema([
                Forms\Components\TextInput::make('weight_volume')
                    ->label('Weight/Volume')
                    ->numeric(),

                Forms\Components\TextInput::make('unit_measure')
                    ->label('Unit')
                    ->placeholder('ml, g, kg, buc'),

                Forms\Components\Toggle::make('is_age_restricted')
                    ->label('Age Restricted')
                    ->reactive(),

                Forms\Components\TextInput::make('min_age')
                    ->label('Minimum Age')
                    ->numeric()
                    ->default(18)
                    ->visible(fn ($get) => $get('is_age_restricted')),

                Forms\Components\Toggle::make('is_available')
                    ->label('Available')
                    ->default(true),

                Forms\Components\TextInput::make('image_url')
                    ->label('Image URL')
                    ->url(),
            ])->columns(2),

            Forms\Components\Hidden::make('vendor_id')
                ->default(fn () => $employee->vendor_id),

            Forms\Components\Hidden::make('currency')
                ->default('RON'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ProductType ? $state->label() : $state),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),

                Tables\Columns\TextColumn::make('price_cents')
                    ->label('Price')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sale_price_cents')
                    ->label('Sale Price')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2) . ' RON' : '-'),

                Tables\Columns\IconColumn::make('is_available')
                    ->boolean()
                    ->label('Available'),

                Tables\Columns\IconColumn::make('is_age_restricted')
                    ->boolean()
                    ->label('18+'),

                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options(collect(ProductType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()])),

                Tables\Filters\SelectFilter::make('vendor_product_category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),

                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('Available'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_availability')
                    ->label(fn ($record) => $record->is_available ? 'Disable' : 'Enable')
                    ->icon(fn ($record) => $record->is_available ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_available ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['is_available' => ! $record->is_available])),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
