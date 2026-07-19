<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use App\Filament\Marketplace\Resources\InstallmentPlanResource\Pages;
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

class InstallmentPlanResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = InstallmentPlan::class;

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-calendar-days';

    protected static \UnitEnum|string|null $navigationGroup = 'Plăți flexibile';

    protected static ?string $navigationLabel = 'Planuri de rate';

    protected static ?string $modelLabel = 'Plan de plată';

    protected static ?string $pluralModelLabel = 'Planuri de plată';

    protected static ?string $slug = 'flexible-payment-plans';

    protected static ?int $navigationSort = 10;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('marketplace_client_id', static::getMarketplaceClientId());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::marketplaceHasMicroservice('flexible-payments');
    }

    public static function form(Schema $schema): Schema
    {
        $marketplace = static::getMarketplaceClient();

        return $schema->schema([
            Forms\Components\Hidden::make('marketplace_client_id')->default($marketplace?->id),

            SC\Section::make('Detalii plan')
                ->schema([
                    Forms\Components\Select::make('plan_type')
                        ->label('Tip')
                        ->options([
                            'installments' => 'Plată în rate',
                            'bnpl_single' => 'BNPL (o singură plată amânată)',
                        ])
                        ->default('installments')
                        ->live()
                        ->required(),
                    Forms\Components\TextInput::make('name.ro')->label('Nume (RO)')->required(),
                    Forms\Components\TextInput::make('name.en')->label('Nume (EN)'),
                    Forms\Components\TextInput::make('slug')->helperText('Se generează automat dacă e gol'),
                    Forms\Components\TextInput::make('currency')->default('RON')->maxLength(3),
                    Forms\Components\Toggle::make('is_active')->label('Activ')->default(true),
                    Forms\Components\TextInput::make('sort_order')->numeric()->default(0),
                ])->columns(2),

            SC\Section::make('Grafic')
                ->schema([
                    Forms\Components\Select::make('schedule_type')
                        ->label('Tip programare')
                        ->options([
                            'fit_to_event' => 'Auto (N rate împărțite automat până la eveniment) — recomandat',
                            'interval' => 'Interval fix (ex: la fiecare 2 săptămâni)',
                            'fixed_dates' => 'Date calendaristice fixe',
                            'custom' => 'Personalizat (fiecare rată cu decalaj + procent propriu)',
                        ])
                        ->default('fit_to_event')->live()
                        ->helperText('„Auto" calculează intervalul în funcție de data cumpărării și a evenimentului, deci încape mereu la timp.'),
                    Forms\Components\TextInput::make('number_of_installments')
                        ->label('Număr de rate')
                        ->numeric()->default(3)->minValue(1)
                        ->disabled(fn (Get $get) => $get('plan_type') === 'bnpl_single')
                        ->visible(fn (Get $get) => $get('schedule_type') !== 'custom')
                        ->helperText('BNPL are automat o singură plată; la „Personalizat" numărul e dat de rânduri'),
                    Forms\Components\Select::make('interval_unit')
                        ->label('Unitate interval')
                        ->options(['day' => 'Zile', 'week' => 'Săptămâni', 'month' => 'Luni'])
                        ->default('month')
                        ->visible(fn (Get $get) => $get('schedule_type') === 'interval'),
                    Forms\Components\TextInput::make('interval_count')
                        ->label('La fiecare')->numeric()->default(1)
                        ->visible(fn (Get $get) => $get('schedule_type') === 'interval'),
                    Forms\Components\Select::make('distribution')
                        ->label('Distribuție sume')
                        ->options(['equal' => 'Egale', 'custom_percent' => 'Procente personalizate'])
                        ->default('equal')
                        ->visible(fn (Get $get) => $get('schedule_type') !== 'custom'),

                    // Custom per-installment schedule (timing + amount per row).
                    Forms\Components\Repeater::make('custom_schedule')
                        ->label('Rate personalizate')
                        ->schema([
                            Forms\Components\TextInput::make('offset_days')
                                ->label('Peste câte zile')->numeric()->required()->default(30),
                            Forms\Components\Select::make('offset_from')
                                ->label('Măsurat din')
                                ->options(['previous' => 'Rata anterioară (sau avans, la prima)', 'start' => 'Data avansului'])
                                ->default('previous')->required(),
                            Forms\Components\TextInput::make('percent')
                                ->label('% din suma finanțată')->numeric()->required()->suffix('%'),
                        ])
                        ->columns(3)
                        ->minItems(1)
                        ->addActionLabel('Adaugă rată')
                        ->helperText('Suma procentelor ar trebui să dea 100. Ex: 60% peste 30z din avans, apoi 40% peste 14z din rata anterioară.')
                        ->visible(fn (Get $get) => $get('schedule_type') === 'custom'),
                ])->columns(2)
                ->visible(fn (Get $get) => $get('plan_type') !== 'bnpl_single'),

            SC\Section::make('Costuri (surcharge marketplace)')
                ->description('Surcharge-ul face totalul mai mare decât plata directă și este venitul marketplace-ului. Suma trebuie să fie > 0.')
                ->schema([
                    Forms\Components\TextInput::make('surcharge_percent')
                        ->label('Surcharge procent (× 100, ex: 500 = 5%)')
                        ->numeric()->default(0),
                    Forms\Components\TextInput::make('surcharge_fixed_cents')
                        ->label('Surcharge fix (bani)')
                        ->numeric()->default(0),
                ])->columns(2),

            SC\Section::make('Eligibilitate & limite')
                ->schema([
                    Forms\Components\TextInput::make('min_order_cents')->label('Comandă minimă (bani)')->numeric()->nullable(),
                    Forms\Components\TextInput::make('max_order_cents')->label('Comandă maximă (bani)')->numeric()->nullable(),
                    Forms\Components\TextInput::make('days_before_event_fully_paid')
                        ->label('Zile înainte de eveniment (min 1)')
                        ->numeric()->default(1)->minValue(1),
                    Forms\Components\TextInput::make('max_duration_days')
                        ->label('Durată maximă (zile, ≤90)')
                        ->numeric()->default(90)->maxValue(90),
                    Forms\Components\Toggle::make('compress_schedule')
                        ->label('Comprimă graficul dacă nu încape')->default(false),
                ])->columns(2),

            SC\Section::make('Termeni')
                ->schema([
                    Forms\Components\TextInput::make('terms_url')->label('URL termeni')->url()->nullable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nume')
                    ->formatStateUsing(fn ($record) => $record->getTranslation('name'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('plan_type')
                    ->label('Tip')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'bnpl_single' ? 'BNPL' : 'Rate'),
                Tables\Columns\TextColumn::make('number_of_installments')->label('Rate'),
                Tables\Columns\TextColumn::make('surcharge_percent')
                    ->label('Surcharge %')
                    ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2) . '%'
                        . ($record->surcharge_fixed_cents ? ' + ' . number_format($record->surcharge_fixed_cents / 100, 2) : '')),
                Tables\Columns\IconColumn::make('is_active')->label('Activ')->boolean(),
                Tables\Columns\TextColumn::make('created_at')->label('Creat')->dateTime('d.m.Y')->sortable(),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstallmentPlans::route('/'),
            'create' => Pages\CreateInstallmentPlan::route('/create'),
            'edit' => Pages\EditInstallmentPlan::route('/{record}/edit'),
        ];
    }
}
