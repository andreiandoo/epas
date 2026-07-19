<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\EventFlexiblePaymentConfigResource\Pages;
use App\Models\Event;
use App\Models\EventFlexiblePaymentConfig;
use App\Models\InstallmentPlan;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Builder;

/**
 * Per-event flexible-payment configuration: the operator enables methods,
 * sets the down payment, and attaches applicable plans for a specific event.
 */
class EventFlexiblePaymentConfigResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = EventFlexiblePaymentConfig::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static \UnitEnum|string|null $navigationGroup = 'Plăți flexibile';

    protected static ?string $navigationLabel = 'Rate pe evenimente';

    protected static ?string $modelLabel = 'Config eveniment';

    protected static ?string $pluralModelLabel = 'Config evenimente';

    protected static ?string $slug = 'flexible-payment-events';

    protected static ?int $navigationSort = 5;

    public static function getEloquentQuery(): Builder
    {
        $clientId = static::getMarketplaceClientId();
        return parent::getEloquentQuery()
            ->whereHas('event', fn ($q) => $q->where('marketplace_client_id', $clientId));
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('flexible-payments');
    }

    public static function form(Schema $schema): Schema
    {
        $clientId = static::getMarketplaceClientId();

        return $schema->schema([
            SC\Section::make('Eveniment')->schema([
                Forms\Components\Select::make('event_id')
                    ->label('Eveniment')
                    ->options(fn () => Event::where('marketplace_client_id', $clientId)
                        ->orderByDesc('id')->limit(500)
                        ->get()
                        ->mapWithKeys(fn ($e) => [$e->id => (is_array($e->title ?? null) ? ($e->title['ro'] ?? $e->title['en'] ?? "Event #{$e->id}") : ($e->title ?? "Event #{$e->id}"))])
                        ->all())
                    ->searchable()
                    ->required()
                    ->unique(ignoreRecord: true),
            ]),

            SC\Section::make('Metode active (per eveniment)')->schema([
                Forms\Components\Toggle::make('enable_installments')->label('Plată în rate')->default(false),
                Forms\Components\Toggle::make('enable_bnpl')->label('BNPL (plată amânată)')->default(false),
                Forms\Components\Toggle::make('enable_delegated_pay')->label('Plată delegată')->default(false),
            ])->columns(3),

            SC\Section::make('Avans (pentru rate)')->schema([
                Forms\Components\Select::make('down_payment_type')
                    ->label('Tip avans')
                    ->options(['none' => 'Fără avans', 'percent' => 'Procent', 'fixed' => 'Sumă fixă'])
                    ->default('percent')->live(),
                Forms\Components\TextInput::make('down_payment_value')
                    ->label(fn (Get $get) => $get('down_payment_type') === 'fixed' ? 'Avans (bani)' : 'Avans (procent × 100, ex 2000 = 20%)')
                    ->numeric()->default(2000)
                    ->visible(fn (Get $get) => $get('down_payment_type') !== 'none'),
            ])->columns(2)
            ->visible(fn (Get $get) => (bool) $get('enable_installments')),

            SC\Section::make('Planuri aplicabile')->schema([
                Forms\Components\Select::make('plans')
                    ->label('Planuri')
                    ->multiple()
                    ->relationship('plans', 'slug')
                    ->options(fn () => InstallmentPlan::where('marketplace_client_id', $clientId)
                        ->where('is_active', true)->get()
                        ->mapWithKeys(fn ($p) => [$p->id => $p->getTranslation('name') . ' (' . ($p->plan_type === 'bnpl_single' ? 'BNPL' : 'Rate') . ')'])
                        ->all())
                    ->helperText('Alege planurile din modulul „Planuri de rate” care se aplică acestui eveniment.'),
            ]),

            SC\Section::make('BNPL & plată delegată')->schema([
                Forms\Components\TextInput::make('bnpl_max_horizon_days')
                    ->label('BNPL: zile max până la plată (≤30)')
                    ->numeric()->default(30)->maxValue(30),
                Forms\Components\TextInput::make('delegated_hold_hours')
                    ->label('Plată delegată: ore de blocare (≤24)')
                    ->numeric()->default(24)->maxValue(24),
                Forms\Components\TextInput::make('delegated_max_locked_tickets')
                    ->label('Plată delegată: max bilete blocate simultan')
                    ->numeric()->nullable(),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('event_id')->label('Eveniment')->searchable(),
                Tables\Columns\IconColumn::make('enable_installments')->label('Rate')->boolean(),
                Tables\Columns\IconColumn::make('enable_bnpl')->label('BNPL')->boolean(),
                Tables\Columns\IconColumn::make('enable_delegated_pay')->label('Delegat')->boolean(),
                Tables\Columns\TextColumn::make('down_payment_value')->label('Avans')
                    ->formatStateUsing(fn ($state, $r) => $r->down_payment_type === 'percent' ? number_format($state / 100, 0) . '%' : number_format($state / 100, 2)),
                Tables\Columns\TextColumn::make('plans_count')->counts('plans')->label('Planuri'),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEventFlexiblePaymentConfigs::route('/'),
            'create' => Pages\CreateEventFlexiblePaymentConfig::route('/create'),
            'edit' => Pages\EditEventFlexiblePaymentConfig::route('/{record}/edit'),
        ];
    }
}
