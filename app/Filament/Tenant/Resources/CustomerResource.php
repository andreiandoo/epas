<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\CustomerResource\Pages;
use App\Models\Customer;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    // Etichetele trec prin traduceri: panoul e folosit și în română, și în engleză
    public static function getModelLabel(): string
    {
        return __('Customer');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Customers');
    }
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Detalii Client')
                    ->schema([
                        Forms\Components\TextInput::make('first_name')
                            ->label('Prenume')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('last_name')
                            ->label('Nume')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('phone')
                            ->label('Telefon')
                            ->tel()
                            ->maxLength(50),
                        Forms\Components\Textarea::make('address')
                            ->label('Adresă')
                            ->rows(2),
                        Forms\Components\TextInput::make('city')
                            ->label('Oraș')
                            ->maxLength(100),
                        Forms\Components\TextInput::make('country')
                            ->label('Țară')
                            ->maxLength(100),
                        Forms\Components\Textarea::make('notes')
                            ->label(__('Note'))
                            ->rows(3),
                    ])->columns(2),

                SC\Section::make('Comenzi')
                    ->icon('heroicon-o-shopping-cart')
                    ->collapsible()
                    ->visible(fn ($record) => (bool) $record?->exists)
                    ->schema([
                        Forms\Components\Placeholder::make('customer_orders')
                            ->label('')
                            ->content(function ($record) {
                                if (! $record) {
                                    return '';
                                }
                                $orders = \App\Models\Order::where('tenant_id', $record->tenant_id)
                                    ->where(function ($q) use ($record) {
                                        $q->where('customer_id', $record->id);
                                        if (! empty($record->email)) {
                                            $q->orWhere('customer_email', $record->email);
                                        }
                                    })
                                    ->withCount('tickets')->latest()->limit(50)->get();
                                if ($orders->isEmpty()) {
                                    return new \Illuminate\Support\HtmlString('<span style="opacity:.7">Clientul nu are încă nicio comandă.</span>');
                                }

                                $labels = ['paid' => 'Plătită', 'confirmed' => 'Confirmată', 'completed' => 'Finalizată', 'pending' => 'În așteptare', 'cancelled' => 'Anulată', 'failed' => 'Eșuată', 'refunded' => 'Rambursată'];
                                $paid = $orders->whereIn('status', ['paid', 'confirmed', 'completed']);
                                $html = '<div style="font-size:14px;margin-bottom:10px;"><strong>' . $paid->count() . '</strong> comenzi plătite · <strong>'
                                    . number_format($paid->sum('total_cents') / 100, 2, ',', '.') . ' RON</strong> în total · <strong>' . $paid->sum('tickets_count') . '</strong> bilete</div>';
                                $html .= '<div style="display:grid;gap:6px;font-size:14px;">';
                                foreach ($orders as $o) {
                                    $ok = in_array($o->status, ['paid', 'confirmed', 'completed'], true);
                                    $html .= '<div style="display:flex;flex-wrap:wrap;justify-content:space-between;gap:8px 16px;padding:8px 0;border-top:1px solid rgba(128,128,128,.25);">'
                                        . '<a href="' . e(url('/tenant/orders/' . $o->id)) . '" style="font-weight:600;color:#6366f1;">#' . str_pad((string) $o->id, 6, '0', STR_PAD_LEFT) . '</a>'
                                        . '<span>' . e($o->created_at?->format('d.m.Y H:i')) . '</span>'
                                        . '<span>' . (int) $o->tickets_count . ' bilete</span>'
                                        . '<span style="padding:2px 9px;border-radius:9999px;font-size:12px;font-weight:600;' . ($ok ? 'background:#dcfce7;color:#166534;' : 'background:#e5e7eb;color:#374151;') . '">' . e($labels[$o->status] ?? $o->status) . '</span>'
                                        . '<span style="font-weight:600;">' . number_format(($o->total_cents ?? 0) / 100, 2, ',', '.') . ' RON</span>'
                                        . '</div>';
                                }

                                return new \Illuminate\Support\HtmlString($html . '</div>');
                            }),
                    ]),

                SC\Section::make('Beneficiari')
                    ->description('Lista beneficiarilor adăugați de acest client în comenzile sale')
                    ->icon('heroicon-o-users')
                    ->schema([
                        Forms\Components\Placeholder::make('beneficiaries_list')
                            ->label('')
                            ->content(function ($record) {
                                if (!$record) {
                                    return 'Salvați clientul pentru a vedea beneficiarii.';
                                }

                                $beneficiaries = collect();

                                // Gather all beneficiaries from customer's orders
                                foreach ($record->orders as $order) {
                                    $orderMeta = $order->meta ?? [];
                                    $orderBeneficiaries = $orderMeta['beneficiaries'] ?? [];

                                    foreach ($orderBeneficiaries as $b) {
                                        if (!empty($b['name'])) {
                                            $beneficiaries->push([
                                                'name' => $b['name'] ?? '-',
                                                'email' => $b['email'] ?? '-',
                                                'phone' => $b['phone'] ?? '-',
                                                'order_id' => $order->id,
                                            ]);
                                        }
                                    }
                                }

                                if ($beneficiaries->isEmpty()) {
                                    return new \Illuminate\Support\HtmlString('<p class="text-gray-500">Nu există beneficiari înregistrați.</p>');
                                }

                                // Build table HTML
                                $html = '<div class="overflow-x-auto"><table class="w-full text-sm">';
                                $html .= '<thead class="bg-gray-50"><tr>';
                                $html .= '<th class="px-4 py-2 font-medium text-left text-gray-700">Nume</th>';
                                $html .= '<th class="px-4 py-2 font-medium text-left text-gray-700">Email</th>';
                                $html .= '<th class="px-4 py-2 font-medium text-left text-gray-700">Telefon</th>';
                                $html .= '<th class="px-4 py-2 font-medium text-left text-gray-700">Comandă</th>';
                                $html .= '</tr></thead><tbody>';

                                foreach ($beneficiaries as $b) {
                                    $orderNum = '#' . str_pad($b['order_id'], 6, '0', STR_PAD_LEFT);
                                    $html .= '<tr class="border-t border-gray-200">';
                                    $html .= '<td class="px-4 py-2">' . e($b['name']) . '</td>';
                                    $html .= '<td class="px-4 py-2">' . e($b['email']) . '</td>';
                                    $html .= '<td class="px-4 py-2">' . e($b['phone']) . '</td>';
                                    $html .= '<td class="px-4 py-2">' . $orderNum . '</td>';
                                    $html .= '</tr>';
                                }

                                $html .= '</tbody></table></div>';

                                return new \Illuminate\Support\HtmlString($html);
                            }),
                    ])
                    ->collapsible()
                    ->collapsed(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label(__('Name'))
                    ->searchable(['first_name', 'last_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('orders_count')
                    ->counts('orders')
                    ->label(__('Orders')),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
