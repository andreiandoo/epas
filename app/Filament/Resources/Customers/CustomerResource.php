<?php

namespace App\Filament\Resources\Customers;

use App\Models\Customer;
use BackedEnum;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Enums\IconPosition;
use Filament\Support\Colors\Color;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Filament\Resources\Customers\Pages\ViewCustomerStats;
use Filament\Schemas\Components as SC;
use Illuminate\Support\HtmlString;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    // Filament v4 typing
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-user-group';
    protected static \UnitEnum|string|null $navigationGroup = 'Core';
    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Forms\Components\Select::make('tenant_id')
                ->label('Tenant')
                ->relationship('tenant', 'name')
                ->searchable()
                ->preload()
                ->required(),

            SC\Grid::make(2)->schema([
                Forms\Components\TextInput::make('first_name')->maxLength(120),
                Forms\Components\TextInput::make('last_name')->maxLength(120),
            ]),
            Forms\Components\TextInput::make('email')->email()->required()->maxLength(190),
            Forms\Components\TextInput::make('phone')->maxLength(60),
            Forms\Components\Select::make('primary_tenant_id')
                ->label('Primary Tenant')
                ->relationship('primaryTenant', 'name')
                ->searchable()
                ->preload(),
            Forms\Components\Select::make('tenants')
                ->label('Member of Tenants')
                ->multiple()
                ->relationship('tenants', 'name')
                ->preload()
                ->helperText('Tenants where this customer has a relationship (e.g., orders).'),
            Forms\Components\KeyValue::make('meta')
                ->keyLabel('Key')->valueLabel('Value')
                ->columnSpanFull()
                ->addable()->deletable()->reorderable(),

            // Gamification Points Section
            SC\Section::make('Puncte de fidelitate')
                ->description('Gestionează punctele de fidelitate ale clientului')
                ->icon('heroicon-o-star')
                ->collapsible()
                ->collapsed(false)
                ->columnSpanFull()
                ->schema([
                    SC\Grid::make(3)->schema([
                        Forms\Components\Placeholder::make('points_balance')
                            ->label('Sold curent')
                            ->content(fn ($record) => new HtmlString(
                                '<div class="flex items-center gap-2">
                                    <span class="text-3xl font-bold text-amber-600">' . number_format($record?->points_balance ?? 0) . '</span>
                                    <span class="text-gray-500">puncte</span>
                                </div>'
                            )),
                        Forms\Components\Placeholder::make('points_earned')
                            ->label('Total câștigate')
                            ->content(fn ($record) => new HtmlString(
                                '<div class="flex items-center gap-2">
                                    <span class="text-2xl font-semibold text-green-600">+' . number_format($record?->points_earned ?? 0) . '</span>
                                    <span class="text-gray-500">puncte</span>
                                </div>'
                            )),
                        Forms\Components\Placeholder::make('points_spent')
                            ->label('Total cheltuite')
                            ->content(fn ($record) => new HtmlString(
                                '<div class="flex items-center gap-2">
                                    <span class="text-2xl font-semibold text-red-600">-' . number_format($record?->points_spent ?? 0) . '</span>
                                    <span class="text-gray-500">puncte</span>
                                </div>'
                            )),
                    ]),

                    SC\Fieldset::make('Ajustare manuală puncte')
                        ->schema([
                            Forms\Components\Select::make('points_action')
                                ->label('Acțiune')
                                ->options([
                                    'add' => 'Adaugă puncte',
                                    'subtract' => 'Scade puncte',
                                ])
                                ->native(false)
                                ->dehydrated(false),
                            Forms\Components\TextInput::make('points_amount')
                                ->label('Cantitate')
                                ->numeric()
                                ->minValue(1)
                                ->dehydrated(false),
                            Forms\Components\TextInput::make('points_reason')
                                ->label('Motiv')
                                ->placeholder('Ex: Bonus aniversar, Corecție, etc.')
                                ->dehydrated(false),
                        ])
                        ->columns(3),

                    Forms\Components\Placeholder::make('points_history')
                        ->label('Istoric puncte (ultimele 10 tranzacții)')
                        ->content(fn ($record) => new HtmlString(
                            static::renderPointsHistory($record)
                        ))
                        ->columnSpanFull(),
                ]),
        ])->columns(2);
    }

    protected static function renderPointsHistory($record): string
    {
        if (!$record) {
            return '<p class="text-gray-500 text-sm">Salvați clientul pentru a vedea istoricul punctelor.</p>';
        }

        // Get points transactions from customer meta or dedicated table
        $transactions = $record->pointsTransactions()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($transactions->isEmpty()) {
            return '<p class="text-gray-500 text-sm">Nu există tranzacții de puncte.</p>';
        }

        $html = '<div class="overflow-x-auto"><table class="w-full text-sm">';
        $html .= '<thead class="bg-gray-50"><tr>';
        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-600">Data</th>';
        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-600">Tip</th>';
        $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600">Puncte</th>';
        $html .= '<th class="px-3 py-2 text-left font-medium text-gray-600">Descriere</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($transactions as $tx) {
            $pointsClass = $tx->points >= 0 ? 'text-green-600' : 'text-red-600';
            $pointsPrefix = $tx->points >= 0 ? '+' : '';
            $typeLabel = match($tx->type) {
                'earned' => '<span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">Câștigate</span>',
                'spent' => '<span class="px-2 py-0.5 bg-red-100 text-red-700 rounded text-xs">Cheltuite</span>',
                'bonus' => '<span class="px-2 py-0.5 bg-blue-100 text-blue-700 rounded text-xs">Bonus</span>',
                'adjustment' => '<span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs">Ajustare</span>',
                'referral' => '<span class="px-2 py-0.5 bg-purple-100 text-purple-700 rounded text-xs">Referral</span>',
                default => '<span class="px-2 py-0.5 bg-gray-100 text-gray-700 rounded text-xs">' . ucfirst($tx->type) . '</span>',
            };

            $html .= '<tr class="border-t border-gray-100">';
            $html .= '<td class="px-3 py-2 text-gray-600">' . $tx->created_at->format('d.m.Y H:i') . '</td>';
            $html .= '<td class="px-3 py-2">' . $typeLabel . '</td>';
            $html .= '<td class="px-3 py-2 text-right font-semibold ' . $pointsClass . '">' . $pointsPrefix . number_format($tx->points) . '</td>';
            $html .= '<td class="px-3 py-2 text-gray-600">' . e($tx->description ?? '-') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable()
                    ->url(fn (\App\Models\Customer $record) => static::getUrl('edit', ['record' => $record])),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('orders_count')
                    ->label('Orders')
                    ->counts('orders')   // necesită ->orders() pe modelul Customer
                    ->sortable(),

                Tables\Columns\TextColumn::make('points_balance')
                    ->label('Puncte')
                    ->sortable()
                    ->toggleable()
                    ->badge()
                    ->color('warning'),

                Tables\Columns\TextColumn::make('primaryTenant.name')
                    ->label('Primary Tenant')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('view_orders')
                    ->label('View Orders')
                    ->state('Open')
                    ->url(fn ($record) => route('filament.admin.resources.orders.index') . '?tableSearch=' . urlencode($record->email))
                    ->openUrlInNewTab(),

                Tables\Columns\TextColumn::make('stats_link')
                    ->label('Stats')
                    ->state('Open')
                    ->url(fn ($record) => static::getUrl('stats', ['record' => $record]))
                    ->openUrlInNewTab(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([])
            ->bulkActions([])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit'   => Pages\EditCustomer::route('/{record}/edit'),
            'stats'  => Pages\ViewCustomerStats::route('/{record}/stats'),
        ];
    }

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return parent::getEloquentQuery()
            ->withCount('orders')
            ->with('primaryTenant'); // pt. afișare rapidă
    }
}
