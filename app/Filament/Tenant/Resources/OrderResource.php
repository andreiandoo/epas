<?php

namespace App\Filament\Tenant\Resources;

use App\Filament\Tenant\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $tenant = auth()->user()->tenant;
        return parent::getEloquentQuery()->where('tenant_id', $tenant?->id);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Detalii comandă')
                    ->icon('heroicon-o-shopping-cart')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Placeholder::make('order_number')
                            ->label('Număr comandă')
                            ->content(fn ($record) => new HtmlString('<span class="text-lg font-bold">#' . str_pad($record->id, 6, '0', STR_PAD_LEFT) . '</span>')),
                        Forms\Components\Placeholder::make('status')
                            ->label('Status')
                            // Culori scrise explicit: clasele de nuanță nu sunt toate în tema compilată, iar pe tema
                            // închisă textul ajungea alb pe fundal verde deschis.
                            ->content(fn ($record) => new HtmlString('<span style="display:inline-block;padding:4px 10px;border-radius:6px;font-size:14px;font-weight:600;' . match ($record->status) {
                                'pending' => 'background:#fef3c7;color:#92400e;',
                                'paid', 'confirmed', 'completed' => 'background:#dcfce7;color:#166534;',
                                'cancelled', 'failed' => 'background:#fee2e2;color:#991b1b;',
                                default => 'background:#e5e7eb;color:#374151;',
                            } . '">' . match ($record->status) {
                                'pending' => 'În așteptare',
                                'paid' => 'Plătită',
                                'confirmed' => 'Confirmată',
                                'cancelled' => 'Anulată',
                                'refunded' => 'Rambursată',
                                default => ucfirst($record->status),
                            } . '</span>')),
                        Forms\Components\Placeholder::make('total_cents')
                            ->label('Total')
                            ->content(fn ($record) => new HtmlString('<span class="text-lg font-bold">' . number_format($record->total_cents / 100, 2) . ' ' . ($record->tickets->first()?->ticketType?->currency ?? 'RON') . '</span>')),
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Data comenzii')
                            ->content(fn ($record) => $record->created_at?->format('d M Y H:i')),
                        Forms\Components\Placeholder::make('payment_method')
                            ->label('Metodă plată')
                            ->content(fn ($record) => $record->meta['payment_method'] ?? 'Card'),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Ultima actualizare')
                            ->content(fn ($record) => $record->updated_at?->format('d M Y H:i')),
                    ]),

                SC\Section::make('Detalii plată')
                    ->icon('heroicon-o-credit-card')
                    ->columns(4)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('pay_subtotal')
                            ->label('Valoare bilete')
                            ->content(function ($record) {
                                $meta = is_array($record->meta) ? $record->meta : [];
                                $fee = (int) ($meta['processing_fee_cents'] ?? 0);
                                $discount = (int) ($meta['discount_cents'] ?? 0);
                                $subtotal = (int) ($meta['subtotal_cents'] ?? (($record->total_cents ?? 0) - $fee + $discount));

                                return number_format($subtotal / 100, 2, ',', '.') . ' RON';
                            }),
                        Forms\Components\Placeholder::make('pay_discount')
                            ->label('Reducere')
                            ->content(function ($record) {
                                $discount = (int) ((is_array($record->meta) ? $record->meta : [])['discount_cents'] ?? 0);

                                return $discount > 0 ? '− ' . number_format($discount / 100, 2, ',', '.') . ' RON' : '—';
                            }),
                        Forms\Components\Placeholder::make('pay_fee')
                            ->label('Taxă de procesare')
                            ->content(function ($record) {
                                $fee = (int) ((is_array($record->meta) ? $record->meta : [])['processing_fee_cents'] ?? 0);

                                return $fee > 0 ? number_format($fee / 100, 2, ',', '.') . ' RON (plătită de cumpărător)' : '—';
                            }),
                        Forms\Components\Placeholder::make('pay_total')
                            ->label('Total încasat')
                            ->content(fn ($record) => new HtmlString('<span style="font-size:18px;font-weight:700;">' . number_format(($record->total_cents ?? 0) / 100, 2, ',', '.') . ' RON</span>')),
                        Forms\Components\Placeholder::make('pay_email')
                            ->label('Email cu biletele')
                            ->columnSpan(2)
                            ->content(function ($record) {
                                $sent = (is_array($record->meta) ? $record->meta : [])['confirmation_email_sent_at'] ?? null;
                                if (! $sent) {
                                    return 'Netrimis încă';
                                }
                                try {
                                    return 'Trimis la ' . \Illuminate\Support\Carbon::parse($sent)->timezone('Europe/Bucharest')->format('d.m.Y H:i');
                                } catch (\Throwable) {
                                    return 'Trimis';
                                }
                            }),
                    ]),

                SC\Section::make('Reducere aplicată')
                    ->icon('heroicon-o-receipt-percent')
                    ->columns(3)
                    ->collapsible()
                    ->visible(fn ($record) => !empty($record->promo_code) || !empty($record->meta['coupon_code']) || !empty($record->meta['discount']))
                    ->schema([
                        Forms\Components\Placeholder::make('discount_code')
                            ->label('Cod promoțional')
                            ->content(fn ($record) => new HtmlString(
                                '<span class="px-2 py-1 rounded text-sm font-medium bg-primary-100 text-primary-700">' .
                                ($record->promo_code ?: $record->meta['coupon_code'] ?? 'N/A') .
                                '</span>'
                            )),
                        Forms\Components\Placeholder::make('discount_amount')
                            ->label('Reducere')
                            ->content(function ($record) {
                                $currency = $record->tickets->first()?->ticketType?->currency ?? 'RON';
                                $discount = $record->promo_discount ?? $record->meta['discount_amount'] ?? $record->meta['discount'] ?? 0;
                                if ($discount > 0) {
                                    return new HtmlString('<span class="text-success-600 font-bold">-' . number_format($discount, 2) . ' ' . $currency . '</span>');
                                }
                                return 'N/A';
                            }),
                        Forms\Components\Placeholder::make('discount_type')
                            ->label('Tip reducere')
                            ->content(fn ($record) => match ($record->meta['discount_type'] ?? null) {
                                'percentage' => 'Procentual',
                                'fixed' => 'Sumă fixă',
                                default => $record->promo_discount > 0 ? 'Sumă fixă' : 'N/A',
                            }),
                    ]),

                SC\Section::make('Client')
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('customer_name')
                            ->label('Nume')
                            ->content(fn ($record) => $record->meta['customer_name'] ?? 'N/A'),
                        Forms\Components\Placeholder::make('customer_email')
                            ->label('Email')
                            ->content(fn ($record) => new HtmlString('<a href="mailto:' . $record->customer_email . '" class="text-primary-600 hover:underline">' . $record->customer_email . '</a>')),
                        Forms\Components\Placeholder::make('customer_phone')
                            ->label('Telefon')
                            ->content(fn ($record) => $record->meta['customer_phone'] ?? 'N/A'),
                    ]),

                SC\Section::make('Bilete comandate')
                    ->icon('heroicon-o-ticket')
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('tickets_count')
                            ->label('Total bilete')
                            ->content(function ($record) {
                                $count = $record->tickets->count();
                                return $count . ' bilet' . ($count > 1 ? 'e' : '');
                            }),
                        Forms\Components\Placeholder::make('tickets_list')
                            ->label('')
                            ->content(fn ($record) => new HtmlString(
                                view('filament.tenant.resources.order-resource.tickets-list', ['record' => $record])->render()
                            )),
                    ]),

                SC\Section::make('Istoric comandă')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('order_history')
                            ->label('')
                            ->content(function ($record) {
                                $rows = [['Comandă plasată', $record->created_at]];
                                try {
                                    $log = \Spatie\Activitylog\Models\Activity::where('subject_type', $record->getMorphClass())
                                        ->where('subject_id', $record->id)->orderBy('created_at')->limit(30)->get();
                                    foreach ($log as $entry) {
                                        $old = $entry->properties['old_status'] ?? ($entry->properties['old']['status'] ?? null);
                                        $new = $entry->properties['new_status'] ?? ($entry->properties['attributes']['status'] ?? null);
                                        if ($new && $old !== $new) {
                                            $rows[] = ['Stare: ' . ($old ?: '—') . ' → ' . $new, $entry->created_at];
                                        }
                                    }
                                } catch (\Throwable) {
                                    // jurnalul de activitate e opțional
                                }
                                $html = '<div style="display:grid;gap:6px;font-size:14px;">';
                                foreach ($rows as [$label, $at]) {
                                    $html .= '<div style="display:flex;justify-content:space-between;gap:16px;"><span>' . e($label) . '</span><span style="opacity:.7;white-space:nowrap;">' . e($at?->format('d.m.Y H:i')) . '</span></div>';
                                }

                                return new HtmlString($html . '</div>');
                            }),
                    ]),

                SC\Section::make('Beneficiari')
                    ->icon('heroicon-o-users')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn ($record) => !empty($record->meta['beneficiaries']))
                    ->schema([
                        Forms\Components\Placeholder::make('beneficiaries_list')
                            ->label('')
                            ->content(fn ($record) => new HtmlString(
                                view('filament.tenant.resources.order-resource.beneficiaries-list', ['record' => $record])->render()
                            )),
                    ]),
            ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                SC\Section::make('Order Details')
                    ->schema([
                        Forms\Components\TextInput::make('order_number')
                            ->disabled(),
                        Forms\Components\Select::make('customer_id')
                            ->relationship('customer', 'email')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'pending' => 'Pending',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('total')
                            ->numeric()
                            ->prefix('€')
                            ->disabled(),
                        Forms\Components\Textarea::make('notes')
                            ->rows(3),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Nr. Comandă')
                    ->formatStateUsing(fn ($state) => '#' . str_pad($state, 6, '0', STR_PAD_LEFT))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer.email')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('meta.customer_name')
                    ->label('Nume')
                    ->searchable(),
                Tables\Columns\TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record) => number_format($state / 100, 2) . ' ' . ($record->tickets->first()?->ticketType?->currency ?? 'RON'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('promo_code')
                    ->label('Cod discount')
                    ->placeholder('-')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'warning' => 'pending',
                        'success' => fn ($state) => in_array($state, ['confirmed', 'paid']),
                        'danger' => 'cancelled',
                        'gray' => 'refunded',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'În așteptare',
                        'paid' => 'Plătită',
                        'confirmed' => 'Confirmată',
                        'cancelled' => 'Anulată',
                        'refunded' => 'Rambursată',
                        default => ucfirst($state),
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'În așteptare',
                        'paid' => 'Plătită',
                        'confirmed' => 'Confirmată',
                        'cancelled' => 'Anulată',
                        'refunded' => 'Rambursată',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
