<?php

namespace App\Filament\Tenant\Resources\Cashless;

use App\Enums\FeeType;
use App\Enums\TenantType;
use App\Filament\Tenant\Resources\Cashless\FinanceFeeRuleResource\Pages;
use App\Models\Cashless\FinanceFeeRule;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FinanceFeeRuleResource extends Resource
{
    protected static ?string $model = FinanceFeeRule::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Fee Rules';

    protected static \UnitEnum|string|null $navigationGroup = 'Cashless';

    protected static ?int $navigationSort = 40;

    protected static ?string $slug = 'cashless-fee-rules';

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
            \Filament\Schemas\Components\Section::make('Fee Rule')->schema([
                Forms\Components\TextInput::make('name')->required(),
                Forms\Components\Select::make('fee_type')
                    ->options(collect(FeeType::cases())->mapWithKeys(fn ($t) => [$t->value => $t->label()]))
                    ->required()->reactive(),
                Forms\Components\Select::make('festival_edition_id')->label('Edition')
                    ->relationship('edition', 'name')->required(),
                Forms\Components\Select::make('vendor_id')->label('Vendor (blank = all)')
                    ->relationship('vendor', 'name')->nullable(),
                Forms\Components\TextInput::make('amount_cents')->numeric()->label('Amount (RON)')
                    ->suffix('RON')->step(0.01)
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2, '.', '') : '0.00')
                    ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 100))
                    ->visible(fn ($get) => in_array($get('fee_type'), ['fixed_daily', 'fixed_period', 'fixed_per_transaction'])),
                Forms\Components\TextInput::make('percentage')->numeric()->label('Percentage (%)')
                    ->visible(fn ($get) => in_array($get('fee_type'), ['percentage_sales', 'percentage_per_category'])),
                Forms\Components\TagsInput::make('category_filter')->label('Categories (for % per category)')
                    ->visible(fn ($get) => $get('fee_type') === 'percentage_per_category'),
                Forms\Components\DatePicker::make('period_start'),
                Forms\Components\DatePicker::make('period_end'),
                Forms\Components\Select::make('apply_on')
                    ->options(['gross_sales' => 'Gross Sales', 'net_sales' => 'Net Sales']),
                Forms\Components\Select::make('billing_frequency')
                    ->options(['daily' => 'Daily', 'weekly' => 'Weekly', 'end_of_festival' => 'End of Festival']),
                Forms\Components\Toggle::make('is_active')->default(true),
                Forms\Components\Textarea::make('notes')->rows(2),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('fee_type')->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof FeeType ? $state->label() : $state),
                Tables\Columns\TextColumn::make('amount_cents')->label('Amount')
                    ->formatStateUsing(fn ($state) => $state ? number_format($state / 100, 2) . ' RON' : '-'),
                Tables\Columns\TextColumn::make('percentage')
                    ->formatStateUsing(fn ($state) => $state ? $state . '%' : '-'),
                Tables\Columns\TextColumn::make('vendor.name')->label('Vendor')->placeholder('All'),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
                Tables\Columns\TextColumn::make('edition.name')->label('Edition'),
            ])
            ->actions([Actions\EditAction::make(), Actions\DeleteAction::make()])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFinanceFeeRules::route('/'),
            'create' => Pages\CreateFinanceFeeRule::route('/create'),
            'edit'   => Pages\EditFinanceFeeRule::route('/{record}/edit'),
        ];
    }
}
