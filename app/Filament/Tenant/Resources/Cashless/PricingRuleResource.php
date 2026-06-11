<?php

namespace App\Filament\Tenant\Resources\Cashless;

use App\Enums\PricingComponentType;
use App\Enums\TenantType;
use App\Filament\Tenant\Resources\Cashless\PricingRuleResource\Pages;
use App\Models\Cashless\PricingRule;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Pricing Rules';

    protected static \UnitEnum|string|null $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 41;

    protected static ?string $slug = 'cashless-pricing-rules';

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
            \Filament\Schemas\Components\Section::make('Pricing Rule')->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\Select::make('festival_edition_id')->label('Edition')
                    ->relationship('edition', 'name')->required(),
                Forms\Components\Select::make('supplier_product_id')->label('Supplier Product')
                    ->relationship('supplierProduct', 'name')->nullable()->searchable(),
                Forms\Components\Select::make('supplier_brand_id')->label('Supplier Brand')
                    ->relationship('supplierBrand', 'name')->nullable(),
                Forms\Components\TextInput::make('product_category')->nullable(),
                Forms\Components\TextInput::make('final_price_cents')->numeric()->required()
                    ->label('Final Price (cents)')->helperText('e.g. 1049 = 10.49 RON'),
                Forms\Components\Toggle::make('is_mandatory')->default(true),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\DatePicker::make('valid_from'),
                Forms\Components\DatePicker::make('valid_until'),
                Forms\Components\Textarea::make('notes')->rows(2),
            ])->columns(2),

            \Filament\Schemas\Components\Section::make('Price Components')->schema([
                Forms\Components\Repeater::make('components')
                    ->relationship()
                    ->schema([
                        Forms\Components\Select::make('component_type')
                            ->options(collect(PricingComponentType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                            ->required(),
                        Forms\Components\TextInput::make('label')->required(),
                        Forms\Components\TextInput::make('amount_cents')->numeric()->label('Amount (cents)'),
                        Forms\Components\TextInput::make('percentage')->numeric()->label('Percentage (%)'),
                        Forms\Components\Select::make('applies_on')
                            ->options(['base_price' => 'Base Price', 'subtotal' => 'Subtotal']),
                        Forms\Components\Toggle::make('is_included_in_final')->default(true),
                    ])
                    ->columns(3)
                    ->orderColumn('sort_order'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('final_price_cents')->label('Final Price')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2) . ' RON'),
                Tables\Columns\TextColumn::make('supplierProduct.name')->label('Product')->placeholder('-'),
                Tables\Columns\TextColumn::make('supplierBrand.name')->label('Brand')->placeholder('-'),
                Tables\Columns\IconColumn::make('is_mandatory')->boolean(),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('edition.name')->label('Edition'),
            ])
            ->actions([Actions\EditAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPricingRules::route('/'),
            'create' => Pages\CreatePricingRule::route('/create'),
            'edit'   => Pages\EditPricingRule::route('/{record}/edit'),
        ];
    }
}
