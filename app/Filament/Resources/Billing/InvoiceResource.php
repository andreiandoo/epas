<?php

namespace App\Filament\Resources\Billing;

use App\Filament\Resources\Billing\InvoiceResource\Pages;
use App\Models\Invoice;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Forms;
use Filament\Tables;
use Filament\Tables\Table;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';
    protected static \UnitEnum|string|null $navigationGroup = 'Billing';
    protected static ?int $navigationSort = 10;
    protected static ?string $modelLabel = 'Invoice';
    protected static ?string $navigationLabel = 'Invoices';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            SC\Section::make('Invoice Details')
                // ->extraAttributes([
                //     'style' => '--cols-lg: 1 !important; --cols-default: 1 !important;',
                // ])
                ->schema([
                    Forms\Components\Select::make('tenant_id')
                        ->relationship('tenant','name')
                        ->label('Tenant')
                        ->required()
                        ->searchable()
                        ->preload()
                        ->live()
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                            if ($state) {
                                $tenant = \App\Models\Tenant::find($state);
                                if ($tenant) {
                                    // Set description with contract number
                                    if ($tenant->contract_number) {
                                        $set('description', "Servicii digitale conform contract nr {$tenant->contract_number}");
                                    } else {
                                        $set('description', "Servicii digitale conform contract");
                                    }

                                    // Set billing period from tenant
                                    if ($tenant->billing_starts_at) {
                                        $billingStart = $tenant->billing_starts_at;
                                        $billingCycleDays = $tenant->billing_cycle_days ?? 30;
                                        $billingEnd = $billingStart->copy()->addDays($billingCycleDays);

                                        $set('period_start', $billingStart->toDateString());
                                        $set('period_end', $billingEnd->toDateString());
                                    }
                                }
                            }
                        }),

                    Forms\Components\TextInput::make('number')
                        ->label('Invoice Number')
                        ->required()
                        ->maxLength(64)
                        ->unique(ignoreRecord: true)
                        ->default(function () {
                            $settings = \App\Models\Setting::current();
                            return $settings->getNextInvoiceNumber();
                        })
                        ->helperText('Format: PREFIX-SERIES-NUMBER (e.g., INV-2024-000001). Auto-generated, but you can edit it.'),

                    Forms\Components\Select::make('type')
                        ->label('Invoice Type')
                        ->options([
                            'proforma' => 'Factura Proforma',
                            'fiscal' => 'Factura Fiscala',
                        ])
                        ->default('fiscal')
                        ->required()
                        ->helperText('Proforma invoices are not fiscally binding. Fiscal invoices are official.'),

                    Forms\Components\Textarea::make('description')
                        ->label('Description')
                        ->rows(2)
                        ->default('Servicii digitale conform contract')
                        ->helperText('Invoice description'),

                    Forms\Components\DatePicker::make('issue_date')
                        ->label('Issue Date')
                        ->native(false)
                        ->required()
                        ->default(now()),

                    Forms\Components\DatePicker::make('period_start')
                        ->label('Period Start')
                        ->native(false)
                        ->helperText('Billing period start date'),

                    Forms\Components\DatePicker::make('period_end')
                        ->label('Period End')
                        ->native(false)
                        ->helperText('Billing period end date'),

                    Forms\Components\DatePicker::make('due_date')
                        ->label('Due Date')
                        ->native(false)
                        ->helperText('Payment due date'),

                    Forms\Components\Select::make('currency')
                        ->options([
                            'RON' => 'RON',
                            'EUR' => 'EUR',
                            'USD' => 'USD',
                            'GBP' => 'GBP',
                            'CHF' => 'CHF',
                            'HUF' => 'HUF',
                            'BGN' => 'BGN',
                            'CZK' => 'CZK',
                            'PLN' => 'PLN',
                        ])
                        ->default('RON')
                        ->required()
                        ->searchable(),

                    Forms\Components\TextInput::make('subtotal')
                        ->label('Subtotal (without VAT)')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required()
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, \Filament\Schemas\Components\Utilities\Set $set, \Filament\Schemas\Components\Utilities\Get $get) {
                            $settings = \App\Models\Setting::current();
                            if ($settings->vat_number) {
                                $vatRate = 21.00;
                                $vatAmount = $state * ($vatRate / 100);
                                $total = $state + $vatAmount;

                                $set('vat_rate', $vatRate);
                                $set('vat_amount', round($vatAmount, 2));
                                $set('amount', round($total, 2));
                            } else {
                                $set('vat_rate', 0);
                                $set('vat_amount', 0);
                                $set('amount', $state);
                            }
                        })
                        ->prefix(fn ($get) => $get('currency') ?? 'RON'),

                    Forms\Components\TextInput::make('vat_rate')
                        ->label('VAT Rate (%)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->default(0)
                        ->suffix('%')
                        ->helperText('Automatically set to 21% if VAT Number is configured in Settings'),

                    Forms\Components\TextInput::make('vat_amount')
                        ->label('VAT Amount')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->default(0)
                        ->prefix(fn ($get) => $get('currency') ?? 'RON')
                        ->helperText('Automatically calculated'),

                    Forms\Components\TextInput::make('amount')
                        ->label('Total Amount (with VAT)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->required()
                        ->prefix(fn ($get) => $get('currency') ?? 'RON')
                        ->helperText('Subtotal + VAT'),

                    Forms\Components\Select::make('status')
                        ->options([
                            'new'         => 'New',
                            'outstanding' => 'Outstanding',
                            'paid'        => 'Paid',
                            'cancelled'   => 'Cancelled',
                        ])
                        ->default('new')
                        ->required()
                        ->helperText('New invoices have a grace period before becoming outstanding'),

                    Forms\Components\KeyValue::make('meta')
                        ->label('Additional Metadata')
                        ->keyLabel('Key')
                        ->valueLabel('Value')
                        ->addable()
                        ->deletable()
                        ->reorderable(),
                ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Invoice #')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record) => static::getUrl('edit', ['record' => $record])),
                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'warning' => 'proforma',
                        'success' => 'fiscal',
                    ])
                    ->formatStateUsing(fn ($state) => $state === 'proforma' ? 'Proforma' : 'Fiscal')
                    ->sortable(),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('issue_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('period_start')
                    ->label('Period')
                    ->date()
                    ->formatStateUsing(fn ($record) =>
                        $record->period_start && $record->period_end
                            ? $record->period_start->format('M d') . ' - ' . $record->period_end->format('M d, Y')
                            : '-'
                    )
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($record) =>
                        number_format($record->amount ?? 0, 2) . ' ' . ($record->currency ?? 'RON')
                    )
                    ->sortable(),
                Tables\Columns\TextColumn::make('vat_rate')
                    ->label('VAT Rate')
                    ->formatStateUsing(fn ($record) =>
                        $record->vat_rate > 0 ? number_format($record->vat_rate, 0) . '%' : '0%'
                    )
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'info'    => 'new',
                        'warning' => 'outstanding',
                        'success' => 'paid',
                        'gray'    => 'cancelled',
                    ])
                    ->sortable(),
                Tables\Columns\IconColumn::make('stripe_payment_link_url')
                    ->label('Pay')
                    ->icon(fn ($state) => $state ? 'heroicon-o-credit-card' : 'heroicon-o-minus')
                    ->color(fn ($state) => $state ? 'success' : 'gray')
                    ->url(fn ($record) => $record->stripe_payment_link_url)
                    ->openUrlInNewTab()
                    ->tooltip(fn ($record) => $record->stripe_payment_link_url ? 'Open payment link' : 'No payment link')
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->defaultSort('issue_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')->relationship('tenant','name')->label('Tenant'),
                Tables\Filters\SelectFilter::make('status')->options([
                    'new'=>'New','outstanding'=>'Outstanding','paid'=>'Paid','cancelled'=>'Cancelled'
                ]),
                Tables\Filters\SelectFilter::make('type')->options([
                    'proforma' => 'Proforma',
                    'fiscal' => 'Fiscal',
                ]),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index'        => Pages\ListInvoices::route('/'),
            'outstanding'  => Pages\ListOutstandingInvoices::route('/outstanding'),
            'paid'         => Pages\ListPaidInvoices::route('/paid'),
            'create'       => Pages\CreateInvoice::route('/create'),
            'edit'         => Pages\EditInvoice::route('/{record}/edit'),
        ];
    }
}
