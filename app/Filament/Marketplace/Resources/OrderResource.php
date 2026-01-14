<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\HtmlString;

class OrderResource extends Resource
{
    use HasMarketplaceContext;

    protected static ?string $model = Order::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-cart';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $marketplace = static::getMarketplaceClient();
        return parent::getEloquentQuery()->where('marketplace_client_id', $marketplace?->id);
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
                            ->content(fn ($record) => new HtmlString('<span class="px-2 py-1 rounded text-sm font-medium ' . match ($record->status) {
                                'pending' => 'bg-warning-100 text-warning-700',
                                'paid', 'confirmed' => 'bg-success-100 text-success-700',
                                'cancelled' => 'bg-danger-100 text-danger-700',
                                default => 'bg-gray-100 text-gray-700',
                            } . '">' . match ($record->status) {
                                'pending' => 'În așteptare',
                                'paid' => 'Plătită',
                                'confirmed' => 'Confirmată',
                                'cancelled' => 'Anulată',
                                'refunded' => 'Rambursată',
                                default => ucfirst($record->status),
                            } . '</span>')),
                        Forms\Components\Placeholder::make('total')
                            ->label('Total')
                            ->content(function ($record) {
                                $currency = $record->currency ?? 'RON';
                                $baseTotal = $record->total ?? ($record->total_cents / 100);

                                // Get commission info from event
                                $event = $record->event;
                                $commissionRate = $event?->commission_rate
                                    ?? $event?->marketplaceOrganizer?->commission_rate
                                    ?? 0;
                                $commissionMode = $event?->commission_mode
                                    ?? $event?->marketplaceOrganizer?->default_commission_mode
                                    ?? $record->marketplaceClient?->commission_mode
                                    ?? 'included';

                                // If on_top mode, add commission to total
                                $isOnTop = in_array($commissionMode, ['on_top', 'add_on_top', 'added_on_top']);
                                if ($isOnTop && $commissionRate > 0) {
                                    $ticketsValue = $record->tickets->sum('price');
                                    $commission = $ticketsValue * ($commissionRate / 100);
                                    $baseTotal = $ticketsValue + $commission;
                                }

                                return new HtmlString('<span class="text-lg font-bold">' . number_format($baseTotal, 2) . ' ' . $currency . '</span>');
                            }),
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Data comenzii')
                            ->content(fn ($record) => $record->created_at?->format('d M Y H:i')),
                        Forms\Components\Placeholder::make('payment_method')
                            ->label('Metodă plată')
                            ->content(fn ($record) => $record->meta['payment_method'] ?? 'Card'),
                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Ultima actualizare')
                            ->content(fn ($record) => $record->updated_at?->format('d M Y H:i')),
                        Forms\Components\Placeholder::make('savings')
                            ->label('Economii')
                            ->content(function ($record) {
                                $currency = $record->currency ?? 'RON';
                                $savings = 0;

                                // Add promo/discount savings
                                $discount = $record->promo_discount ?? $record->discount_amount ?? $record->meta['discount_amount'] ?? $record->meta['discount'] ?? 0;
                                $savings += (float) $discount;

                                // Add target_price savings (if event had target_price > ticket price)
                                $event = $record->event;
                                $targetPrice = $event?->target_price ?? 0;
                                if ($targetPrice > 0) {
                                    foreach ($record->tickets as $ticket) {
                                        $ticketPrice = $ticket->price ?? 0;
                                        if ($targetPrice > $ticketPrice && $ticketPrice > 0) {
                                            $savings += ($targetPrice - $ticketPrice);
                                        }
                                    }
                                }

                                if ($savings > 0) {
                                    return new HtmlString('<span class="text-lg font-bold text-success-600">-' . number_format($savings, 2) . ' ' . $currency . '</span>');
                                }
                                return new HtmlString('<span class="text-muted">-</span>');
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

                SC\Section::make('Detalii sume')
                    ->icon('heroicon-o-calculator')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('price_breakdown')
                            ->label('Detalii preț')
                            ->content(function ($record) {
                                $currency = $record->currency ?? 'RON';
                                $total = $record->total;
                                $discount = $record->discount_amount ?? $record->promo_discount ?? 0;

                                // Get commission from EVENT (use Order's direct event relationship)
                                $event = $record->event;
                                $commissionRate = $event?->commission_rate
                                    ?? $event?->marketplaceOrganizer?->commission_rate
                                    ?? 0;
                                $commissionMode = $event?->commission_mode
                                    ?? $event?->marketplaceOrganizer?->default_commission_mode
                                    ?? $record->marketplaceClient?->commission_mode
                                    ?? 'included';

                                // Calculate base tickets value
                                $ticketsValue = $record->tickets->sum('price');

                                // Calculate commission amount from event's rate
                                $commission = $ticketsValue * ($commissionRate / 100);

                                $html = '<div class="space-y-2 text-sm">';

                                $html .= '<div class="flex justify-between"><span class="text-gray-500">Valoare bilete:</span><span class="font-medium">' . number_format($ticketsValue, 2) . ' ' . $currency . '</span></div>';

                                if ($commission > 0) {
                                    // Handle all variations: on_top, add_on_top, added_on_top
                                    $isOnTop = in_array($commissionMode, ['on_top', 'add_on_top', 'added_on_top']);
                                    $modeLabel = $isOnTop ? '(peste preț)' : '(inclus în preț)';
                                    $html .= '<div class="flex justify-between"><span class="text-gray-500">Comision ' . number_format($commissionRate, 1) . '% ' . $modeLabel . ':</span><span class="font-medium">' . number_format($commission, 2) . ' ' . $currency . '</span></div>';
                                }

                                if ($discount > 0) {
                                    $html .= '<div class="flex justify-between"><span class="text-gray-500">Reducere:</span><span class="font-medium text-success-600">-' . number_format($discount, 2) . ' ' . $currency . '</span></div>';
                                }

                                // Calculate final total - for on_top, add commission
                                $isOnTop = in_array($commissionMode, ['on_top', 'add_on_top', 'added_on_top']);
                                $finalTotal = $isOnTop ? ($ticketsValue + $commission - $discount) : $total;

                                $html .= '<hr class="my-2 border-gray-200">';
                                $html .= '<div class="flex justify-between"><span class="font-semibold">Total plătit:</span><span class="font-bold text-lg">' . number_format($finalTotal, 2) . ' ' . $currency . '</span></div>';

                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                        Forms\Components\Placeholder::make('commission_details')
                            ->label('Detalii comision')
                            ->content(function ($record) {
                                $currency = $record->currency ?? 'RON';

                                // Get commission from EVENT (use Order's direct event relationship)
                                $event = $record->event;
                                $commissionRate = $event?->commission_rate
                                    ?? $event?->marketplaceOrganizer?->commission_rate
                                    ?? 0;
                                $commissionMode = $event?->commission_mode
                                    ?? $event?->marketplaceOrganizer?->default_commission_mode
                                    ?? $record->marketplaceClient?->commission_mode
                                    ?? 'included';

                                // Calculate base tickets value and commission
                                $ticketsValue = $record->tickets->sum('price');
                                $commission = $ticketsValue * ($commissionRate / 100);

                                if ($commission <= 0 && $commissionRate <= 0) {
                                    return new HtmlString('<span class="text-gray-500">Fără comision</span>');
                                }

                                // Handle all variations: on_top, add_on_top, added_on_top
                                $isOnTop = in_array($commissionMode, ['on_top', 'add_on_top', 'added_on_top']);

                                $html = '<div class="space-y-2 text-sm">';
                                $html .= '<div class="flex justify-between"><span class="text-gray-500">Rată comision:</span><span class="font-medium">' . number_format($commissionRate, 2) . '%</span></div>';
                                $html .= '<div class="flex justify-between"><span class="text-gray-500">Valoare comision:</span><span class="font-medium">' . number_format($commission, 2) . ' ' . $currency . '</span></div>';
                                $html .= '<div class="flex justify-between"><span class="text-gray-500">Mod:</span><span class="font-medium">' . ($isOnTop ? 'Adăugat peste preț' : 'Inclus în preț') . '</span></div>';

                                // Calculate organizer revenue
                                $organizerRevenue = $ticketsValue - (!$isOnTop ? $commission : 0);

                                $html .= '<hr class="my-2 border-gray-200">';
                                $html .= '<div class="flex justify-between"><span class="text-gray-500">Organizator primește:</span><span class="font-semibold text-success-600">' . number_format($organizerRevenue, 2) . ' ' . $currency . '</span></div>';
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                    ]),

                SC\Section::make('Client')
                    ->icon('heroicon-o-user')
                    ->columns(3)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('customer_name')
                            ->label('Nume')
                            ->content(fn ($record) => $record->customer_name ?? $record->meta['customer_name'] ?? 'N/A'),
                        Forms\Components\Placeholder::make('customer_email')
                            ->label('Email')
                            ->content(fn ($record) => new HtmlString('<a href="mailto:' . $record->customer_email . '" class="text-primary-600 hover:underline">' . $record->customer_email . '</a>')),
                        Forms\Components\Placeholder::make('customer_phone')
                            ->label('Telefon')
                            ->content(fn ($record) => $record->customer_phone ?? $record->meta['customer_phone'] ?? 'N/A'),
                    ]),

                SC\Section::make('Eveniment')
                    ->icon('heroicon-o-calendar')
                    ->columns(2)
                    ->collapsible()
                    ->schema([
                        Forms\Components\Placeholder::make('event_name')
                            ->label('Eveniment')
                            ->content(function ($record) {
                                $events = $record->tickets
                                    ->pluck('event')
                                    ->filter()
                                    ->unique('id');

                                if ($events->isEmpty()) {
                                    return 'N/A';
                                }

                                $html = '<div class="space-y-1">';
                                foreach ($events as $event) {
                                    $title = $event->getTranslation('title', app()->getLocale()) ?? $event->title ?? 'Eveniment';
                                    $date = $event->event_date?->format('d M Y') ?? '';
                                    $html .= '<div class="font-medium">' . e($title) . '</div>';
                                    if ($date) {
                                        $html .= '<div class="text-sm text-gray-500">' . $date . '</div>';
                                    }
                                }
                                $html .= '</div>';

                                return new HtmlString($html);
                            }),
                        Forms\Components\Placeholder::make('venue_info')
                            ->label('Locație')
                            ->content(function ($record) {
                                $event = $record->tickets->first()?->event;
                                if (!$event || !$event->venue) {
                                    return 'N/A';
                                }

                                $venue = $event->venue;
                                $venueName = $venue->getTranslation('name', app()->getLocale()) ?? $venue->name ?? '';
                                $city = $venue->city ?? '';

                                return $venueName . ($city ? ', ' . $city : '');
                            }),
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
                                view('filament.marketplace.resources.order-resource.tickets-list', ['record' => $record])->render()
                            )),
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
                                view('filament.marketplace.resources.order-resource.beneficiaries-list', ['record' => $record])->render()
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
                Tables\Columns\TextColumn::make('customer_email')
                    ->label('Client')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Nume')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_names')
                    ->label('Eveniment')
                    ->getStateUsing(function ($record) {
                        // Get unique events from tickets with names and dates
                        $events = $record->tickets
                            ->pluck('event')
                            ->filter()
                            ->unique('id')
                            ->take(2)
                            ->map(function ($event) {
                                $name = $event->getTranslation('title', app()->getLocale()) ?? $event->title;

                                // Format date based on duration mode
                                $dateStr = '';
                                if ($event->duration_mode === 'range' && $event->range_start_date) {
                                    $start = $event->range_start_date;
                                    $end = $event->range_end_date;
                                    if ($start && $end) {
                                        if ($start->format('m Y') === $end->format('m Y')) {
                                            $dateStr = $start->format('d') . '-' . $end->format('d M');
                                        } else {
                                            $dateStr = $start->format('d M') . ' - ' . $end->format('d M');
                                        }
                                    } else {
                                        $dateStr = $start->format('d M');
                                    }
                                } elseif ($event->event_date) {
                                    $dateStr = $event->event_date->format('d M');
                                } elseif ($event->range_start_date) {
                                    $dateStr = $event->range_start_date->format('d M');
                                }

                                return $name . ($dateStr ? " ({$dateStr})" : '');
                            })
                            ->filter()
                            ->implode(', ');

                        $totalEvents = $record->tickets->pluck('event_id')->unique()->count();
                        if ($totalEvents > 2) {
                            $events .= ' +' . ($totalEvents - 2);
                        }

                        return $events ?: '-';
                    })
                    ->wrap()
                    ->limit(40),
                Tables\Columns\TextColumn::make('tickets_count')
                    ->label('Bilete')
                    ->getStateUsing(fn ($record) => $record->tickets->count())
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state, $record) => number_format($state ?? ($record->total_cents / 100), 2) . ' ' . ($record->currency ?? 'RON'))
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
