<?php

namespace App\Filament\Marketplace\Resources;

use App\Filament\Marketplace\Resources\OrderResource\Pages;
use App\Filament\Marketplace\Resources\MarketplaceCustomerResource;
use App\Filament\Marketplace\Resources\EventResource;
use App\Filament\Marketplace\Resources\TicketResource;
use App\Models\Order;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components as SC;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Marketplace\Concerns\HasMarketplaceContext;
use Illuminate\Support\Facades\Storage;
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
        return $schema->components([
            SC\Grid::make(4)->schema([
                SC\Group::make()->columnSpan(3)->schema([
                    // Hero Stats Card
                    Forms\Components\Placeholder::make('order_hero')
                        ->hiddenLabel()
                        ->content(fn ($record) => self::renderOrderHero($record)),

                    // Customer Section
                    SC\Section::make('Client')
                        ->icon('heroicon-o-user')
                        ->schema([
                            Forms\Components\Placeholder::make('customer_card')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderCustomerCard($record)),
                        ]),

                    // Event Section
                    SC\Section::make('Eveniment')
                        ->icon('heroicon-o-calendar')
                        ->collapsible()
                        ->schema([
                            Forms\Components\Placeholder::make('event_card')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderEventCard($record)),
                        ]),

                    // Tickets Section
                    SC\Section::make('Bilete comandate')
                        ->icon('heroicon-o-ticket')
                        ->headerActions([
                            Action::make('download_all')
                                ->label('Download toate')
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('gray')
                                ->size('sm'),
                        ])
                        ->schema([
                            Forms\Components\Placeholder::make('tickets_list')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderTicketsList($record)),
                        ]),

                ]),
                SC\Group::make()->columnSpan(1)->schema([
                    // Price Details
                    SC\Section::make('Detalii preț')
                        ->icon('heroicon-o-calculator')
                        ->compact()
                        ->schema([
                            Forms\Components\Placeholder::make('price_breakdown')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderPriceBreakdown($record)),
                        ]),

                    // Commission Details
                    SC\Section::make('Detalii comision')
                        ->icon('heroicon-o-currency-dollar')
                        ->compact()
                        ->extraAttributes(['class' => 'bg-gradient-to-r from-emerald-500/10 to-emerald-600/5 border-emerald-500/30'])
                        ->schema([
                            Forms\Components\Placeholder::make('commission_details')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderCommissionDetails($record)),
                        ]),

                    // Quick Actions
                    SC\Section::make('Acțiuni rapide')
                        ->icon('heroicon-o-bolt')
                        ->compact()
                        ->schema([
                            SC\Actions::make([
                                Action::make('resend_confirmation')
                                    ->label('Retrimite confirmare')
                                    ->icon('heroicon-o-envelope')
                                    ->color('gray')
                                    ->action(fn ($record) => self::resendConfirmation($record)),
                                Action::make('download_tickets')
                                    ->label('Download bilete')
                                    ->icon('heroicon-o-arrow-down-tray')
                                    ->color('gray'),
                                Action::make('print_invoice')
                                    ->label('Printează factura')
                                    ->icon('heroicon-o-printer')
                                    ->color('gray'),
                                Action::make('change_status')
                                    ->label('Schimbă status')
                                    ->icon('heroicon-o-arrow-path')
                                    ->color('warning')
                                    ->form([
                                        Forms\Components\Select::make('status')
                                            ->options([
                                                'pending' => 'În așteptare',
                                                'confirmed' => 'Confirmată',
                                                'cancelled' => 'Anulată',
                                                'refunded' => 'Rambursată',
                                            ])
                                            ->required(),
                                    ])
                                    ->action(fn ($record, array $data) => $record->update(['status' => $data['status']])),
                                Action::make('request_refund')
                                    ->label('Solicită rambursare')
                                    ->icon('heroicon-o-arrow-uturn-left')
                                    ->color('gray')
                                    ->requiresConfirmation()
                                    ->visible(fn ($record) => in_array($record->status, ['confirmed', 'paid'])),
                            ])->fullWidth(),
                        ]),

                    // Order Timeline
                    SC\Section::make('Istoric comandă')
                        ->icon('heroicon-o-clock')
                        ->compact()
                        ->collapsible()
                        ->schema([
                            Forms\Components\Placeholder::make('timeline')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderTimeline($record)),
                        ]),

                    // Beneficiaries (if any)
                    SC\Section::make('Beneficiari')
                        ->icon('heroicon-o-users')
                        ->compact()
                        ->collapsible()
                        ->collapsed()
                        ->visible(fn ($record) => !empty($record->meta['beneficiaries']) || $record->tickets->whereNotNull('beneficiary_name')->isNotEmpty())
                        ->schema([
                            Forms\Components\Placeholder::make('beneficiaries')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderBeneficiaries($record)),
                        ]),

                    // Payment Details
                    SC\Section::make('Detalii plată')
                        ->icon('heroicon-o-credit-card')
                        ->compact()
                        ->collapsible()
                        ->collapsed()
                        ->schema([
                            Forms\Components\Placeholder::make('payment_details')
                                ->hiddenLabel()
                                ->content(fn ($record) => self::renderPaymentDetails($record)),
                        ]),
                ]),
            ]),
        ])->columns(1);
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

    protected static function renderOrderHero(Order $record): HtmlString
    {
        $orderNumber = '#' . str_pad($record->id, 6, '0', STR_PAD_LEFT);
        $date = $record->created_at->format('d M Y, H:i');
        $currency = $record->currency ?? 'RON';
        $total = number_format($record->total ?? ($record->total_cents / 100), 2);
        $ticketCount = $record->tickets->count();

        // Payment method display - show processor name properly
        $paymentMethod = match($record->payment_processor) {
            'netopia', 'payment-netopia' => 'Netopia',
            'stripe', 'payment-stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'cash' => 'Cash',
            'bank_transfer' => 'Transfer',
            default => $record->payment_processor ? ucfirst(str_replace(['_', '-', 'payment-'], ['', '', ''], $record->payment_processor)) : 'N/A',
        };
        $updatedAt = $record->updated_at->format('d M H:i');

        // Calculate savings (promo discount + target price savings)
        $savings = (float) ($record->promo_discount ?? $record->discount_amount ?? 0);

        // Add target price savings
        $targetPrice = 0;
        if ($record->event) {
            $targetPrice = (float) ($record->event->target_price ?? 0);
        } elseif ($record->marketplaceEvent) {
            $targetPrice = (float) ($record->marketplaceEvent->target_price ?? 0);
        }

        if ($targetPrice > 0) {
            foreach ($record->tickets as $ticket) {
                $ticketPrice = (float) ($ticket->price ?? 0);
                if ($targetPrice > $ticketPrice && $ticketPrice > 0) {
                    $savings += ($targetPrice - $ticketPrice);
                }
            }
        }

        $savingsHtml = $savings > 0
            ? '<div style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; background: rgba(16, 185, 129, 0.15); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 8px; font-size: 13px; color: #10B981;">
                🏷️ Economii: -' . number_format($savings, 2) . ' ' . $currency . '
            </div>'
            : '';

        return new HtmlString("
            <div style='background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%); border: 1px solid #334155; border-radius: 16px; padding: 24px; margin-bottom: 20px; position: relative; overflow: hidden;'>
                <div style='display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 20px;'>
                    <div>
                        <div style='font-size: 32px; font-weight: 800; color: white;'>{$orderNumber}</div>
                        <div style='font-size: 13px; color: #64748B; margin-top: 4px;'>{$date}</div>
                    </div>
                    {$savingsHtml}
                </div>
                <div style='display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px;'>
                    <div style='text-align: center; padding: 16px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; border: 1px solid #334155;'>
                        <div style='font-size: 24px; font-weight: 700; color: white;'>{$total}</div>
                        <div style='font-size: 11px; color: #64748B; text-transform: uppercase;'>Total {$currency}</div>
                    </div>
                    <div style='text-align: center; padding: 16px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; border: 1px solid #334155;'>
                        <div style='font-size: 24px; font-weight: 700; color: white;'>{$ticketCount}</div>
                        <div style='font-size: 11px; color: #64748B; text-transform: uppercase;'>Bilete</div>
                    </div>
                    <div style='text-align: center; padding: 16px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; border: 1px solid #334155;'>
                        <div style='font-size: 24px; font-weight: 700; color: #10B981;'>{$paymentMethod}</div>
                        <div style='font-size: 11px; color: #64748B; text-transform: uppercase;'>Plată</div>
                    </div>
                    <div style='text-align: center; padding: 16px; background: rgba(15, 23, 42, 0.5); border-radius: 12px; border: 1px solid #334155;' class='flex flex-col items-center justify-center'>
                        <div style='font-size: 14px; font-weight: 700; color: white;'>{$updatedAt}</div>
                        <div style='font-size: 11px; color: #64748B; text-transform: uppercase;'>Ultima actualizare</div>
                    </div>
                </div>
            </div>
        ");
    }

    protected static function renderCustomerCard(Order $record): HtmlString
    {
        $name = $record->customer_name ?? 'N/A';
        $email = $record->customer_email ?? '';
        $phone = $record->customer_phone ?? $record->meta['customer_phone'] ?? '';
        $initials = collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');

        $phoneHtml = $phone ? "
            <div style='display: flex; align-items: center; gap: 6px;'>
                <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-4'>
                    <path stroke-linecap='round' stroke-linejoin='round' d='M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3' />
                </svg>
                {$phone}
            </div>
        " : '';

        return new HtmlString("
            <div style='display: flex; gap: 16px; align-items: center;'>
                <div style='width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, #6366F1, #8B5CF6); display: flex; align-items: center; justify-content: center; font-size: 20px; font-weight: 700; color: white;'>{$initials}</div>
                <div style='flex: 1;'>
                    <div style='font-size: 16px; font-weight: 700; color: white; margin-bottom: 4px;'>" . e($name) . "</div>
                    <div style='display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; color: #94A3B8;'>
                        <div style='display: flex; align-items: center; gap: 6px;'>
                            <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-4'>
                                <path stroke-linecap='round' stroke-linejoin='round' d='M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75' />
                            </svg>
                            <a href='mailto:{$email}' style='color: #60A5FA; text-decoration: none;'>{$email}</a>
                        </div>
                        {$phoneHtml}
                    </div>
                </div>
                <div class='flex gap-8 pr-2'>
                    <a href='" . MarketplaceCustomerResource::getUrl('edit', ['record' => $record->marketplace_customer_id]) . "' class='fi-btn fi-size-sm  fi-ac-btn-action no-underline'>
                        <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-4'>
                            <path stroke-linecap='round' stroke-linejoin='round' d='M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' />
                        </svg>
                         Vezi profil
                    </a>
                    <a href='mailto:{$email}' class='fi-btn fi-size-sm fi-ac-btn-action no-underline'>
                        <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='size-4'>
                            <path stroke-linecap='round' stroke-linejoin='round' d='M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75' />
                        </svg>
                        Trimite email
                    </a>
                </div>
            </div>
        ");
    }

    protected static function renderTicketsList(Order $record): HtmlString
    {
        $html = '';

        foreach ($record->tickets as $ticket) {
            $typeNameRaw = $ticket->ticketType?->name;
            $typeName = is_array($typeNameRaw) ? ($typeNameRaw['ro'] ?? $typeNameRaw['en'] ?? reset($typeNameRaw) ?: 'Bilet') : ($typeNameRaw ?? 'Bilet');
            $code = $ticket->code ?? $ticket->unique_code ?? 'N/A';
            $barcode = $ticket->barcode ?? $code;
            $price = number_format($ticket->price ?? 0, 2);
            $currency = $ticket->ticketType?->currency ?? 'RON';

            // Get beneficiary from meta or order
            $meta = $ticket->meta ?? [];
            $beneficiary = $meta['beneficiary']['name'] ?? $meta['beneficiary_name'] ?? $ticket->attendee_name ?? $record->customer_name ?? '';
            $beneficiaryEmail = $meta['beneficiary']['email'] ?? $meta['beneficiary_email'] ?? $ticket->attendee_email ?? $record->customer_email ?? '';

            $statusBadge = match($ticket->status ?? 'valid') {
                'valid' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #10B981;">✓ Valid</span>',
                'used' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(59, 130, 246, 0.15); color: #60A5FA;">✓ Folosit</span>',
                'cancelled' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #EF4444;">✕ Anulat</span>',
                default => '',
            };

            // Generate QR code URL using a simple QR generator API (Google Charts or similar)
            $qrData = urlencode($barcode);
            $qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=80x80&data={$qrData}";

            // View URL for ticket
            $viewUrl = TicketResource::getUrl('view', ['record' => $ticket->id]);

            $html .= "
                <div style='display: flex; align-items: stretch; gap: 16px; padding: 16px; background: #0F172A; border-radius: 12px; margin-bottom: 12px; border: 1px solid #334155;'>
                    <!-- QR Code -->
                    <div style='display: flex; flex-direction: column; align-items: center; gap: 4px; flex-shrink: 0;'>
                        <img src='{$qrCodeUrl}' alt='QR Code' style='width: 60px; height: 60px; border-radius: 4px; background: white; padding: 2px;'>
                        <span style='font-size: 9px; color: #64748B;'>QR Code</span>
                    </div>

                    <!-- Ticket details -->
                    <div style='flex: 1;'>
                        <div style='display: flex; align-items: center; gap: 8px; margin-bottom: 4px;'>
                            <span style='font-size: 14px; font-weight: 600; color: white;'>" . e($typeName) . "</span>
                            {$statusBadge}
                        </div>
                        <div style='font-size: 12px; color: #64748B; display: flex; align-items: center; gap: 4px; margin-bottom: 6px;'>
                            <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width: 16px; height: 16px;'>
                                <path stroke-linecap='round' stroke-linejoin='round' d='M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z' />
                            </svg>
                            " . e($beneficiary) . " (" . e($beneficiaryEmail) . ")
                        </div>
                        <!-- Barcode display -->
                        <div style='display: flex; align-items: center; gap: 8px;'>
                            <span style='font-size: 11px; color: #64748B;'>Cod:</span>
                            <span style='padding: 2px 8px; background: #334155; border-radius: 4px; font-size: 11px; font-family: monospace; color: #94A3B8; letter-spacing: 1px;'>" . e($barcode) . "</span>
                        </div>
                    </div>

                    <!-- Price and actions -->
                    <div style='display: flex; flex-direction: column; justify-content: space-between; align-items: flex-end; min-width: 120px;'>
                        <div style='font-size: 16px; font-weight: 700; color: white;'>{$price} {$currency}</div>
                        <div style='display: flex; gap: 8px;'>
                            <a href='{$viewUrl}' style='width: 32px; height: 32px; border-radius: 6px; background: #334155; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #94A3B8; text-decoration: none;' title='Vezi bilet'>
                                <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width: 16px; height: 16px;'>
                                    <path stroke-linecap='round' stroke-linejoin='round' d='M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z' />
                                    <path stroke-linecap='round' stroke-linejoin='round' d='M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z' />
                                </svg>
                            </a>
                            <button style='width: 32px; height: 32px; border-radius: 6px; background: #334155; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #94A3B8;' title='Download bilet'>
                                <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' style='width: 16px; height: 16px;'>
                                    <path stroke-linecap='round' stroke-linejoin='round' d='M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m.75 12 3 3m0 0 3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z' />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            ";
        }

        return new HtmlString($html);
    }

    protected static function renderEventCard(Order $record): HtmlString
    {
        $events = $record->tickets
            ->pluck('event')
            ->filter()
            ->unique('id');

        if ($events->isEmpty()) {
            return new HtmlString('<p style="color: #64748B;">Nu există evenimente asociate.</p>');
        }

        $html = '';
        
        foreach ($events as $event) {
            $title = $event->getTranslation('title', app()->getLocale()) ?? $event->title ?? 'Eveniment';
            
            // Format date based on duration mode
            $dateStr = '';
            if ($event->duration_mode === 'range' && $event->range_start_date) {
                $start = $event->range_start_date;
                $end = $event->range_end_date;
                if ($start && $end) {
                    if ($start->format('m Y') === $end->format('m Y')) {
                        $dateStr = $start->format('d') . '-' . $end->format('d M Y');
                    } else {
                        $dateStr = $start->format('d M') . ' - ' . $end->format('d M Y');
                    }
                } else {
                    $dateStr = $start->format('d M Y');
                }
            } elseif ($event->event_date) {
                $dateStr = $event->event_date->format('d M Y');
            }
            
            // Time
            $timeStr = '';
            if ($event->event_time) {
                $timeStr = is_string($event->event_time) 
                    ? $event->event_time 
                    : $event->event_time->format('H:i');
            } elseif ($event->start_time) {
                $timeStr = is_string($event->start_time) 
                    ? $event->start_time 
                    : $event->start_time->format('H:i');
            }
            
            // Venue
            $venue = $event->venue;
            $venueName = '';
            $venueCity = '';
            if ($venue) {
                $venueName = $venue->getTranslation('name', app()->getLocale()) ?? $venue->name ?? '';
                $venueCity = $venue->city ?? '';
            }
            $locationStr = $venueName . ($venueCity ? ', ' . $venueCity : '');
            
            // Poster/Image - use Storage::url() for correct path
            $posterPath = $event->poster_url ?? $event->hero_image_url ?? null;
            $posterUrl = $posterPath ? Storage::disk('public')->url($posterPath) : null;
            $posterHtml = $posterUrl
                ? "<img src='{$posterUrl}' alt='" . e($title) . "' style='width: 100%; height: 100%; object-fit: cover;'>"
                : "<span style='font-size: 32px;'>🎸</span>";

            $html .= "
                <div style='display: flex; gap: 16px;align-items:center;padding-right:3px;'>
                    <div style='width: 100px; height: 50px; border-radius: 12px; background: linear-gradient(135deg, #374151, #1F2937); display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden;'>
                        {$posterHtml}
                    </div>
                    <div style='flex: 1;'>
                        <div style='font-size: 16px; font-weight: 700; color: white; margin-bottom: 8px;'>" . e($title) . "</div>
                        <div style='display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; color: #94A3B8;'>";
            
            if ($dateStr) {
                $html .= "
                            <div style='display: flex; align-items: center; gap: 6px;'>
                                <svg style='width: 14px; height: 14px; color: #64748B;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'/></svg>
                                {$dateStr}
                            </div>";
            }
            
            if ($locationStr) {
                $html .= "
                            <div style='display: flex; align-items: center; gap: 6px;'>
                                <svg style='width: 14px; height: 14px; color: #64748B;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z'/></svg>
                                " . e($locationStr) . "
                            </div>";
            }
            
            if ($timeStr) {
                $html .= "
                            <div style='display: flex; align-items: center; gap: 6px;'>
                                <svg style='width: 14px; height: 14px; color: #64748B;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'/></svg>
                                {$timeStr}
                            </div>";
            }
            
            $html .= "
                        </div>
                    </div>
                    <div>
                        <a href='" . EventResource::getUrl('edit', ['record' => $event->id]) . "' 
                        class='fi-btn fi-size-sm fi-ac-btn-action no-underline'>
                            <svg style='width: 14px; height: 14px;' fill='none' stroke='currentColor' viewBox='0 0 24 24'><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M15 12a3 3 0 11-6 0 3 3 0 016 0z'/><path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z'/></svg>
                            Vezi eveniment
                        </a>
                    </div>
                </div>
            ";
        }

        return new HtmlString($html);
    }

    protected static function renderPriceBreakdown(Order $record): HtmlString
    {
        $currency = $record->currency ?? 'RON';
        $ticketsValue = $record->tickets->sum('price');
        $discount = $record->discount_amount ?? $record->promo_discount ?? 0;
        $total = $record->total ?? ($record->total_cents / 100);

        // Get commission info from event
        $event = $record->event;
        $commissionRate = $event?->commission_rate
            ?? $event?->marketplaceOrganizer?->commission_rate
            ?? 0;
        $commissionMode = $event?->commission_mode
            ?? $event?->marketplaceOrganizer?->default_commission_mode
            ?? $record->marketplaceClient?->commission_mode
            ?? 'included';

        $isOnTop = in_array($commissionMode, ['on_top', 'add_on_top', 'added_on_top']);
        $commission = $ticketsValue * ($commissionRate / 100);

        $html = '<div>';

        // Tickets value
        $html .= "
            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                <span style='font-size: 13px; color: #94A3B8;'>Valoare bilete</span>
                <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>" . number_format($ticketsValue, 2) . " {$currency}</span>
            </div>
        ";

        // Commission (if any)
        if ($commission > 0) {
            $modeLabel = $isOnTop ? '(peste preț)' : '(inclus)';
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Comision " . number_format($commissionRate, 1) . "% {$modeLabel}</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>" . number_format($commission, 2) . " {$currency}</span>
                </div>
            ";
        }

        // Discount (if any)
        if ($discount > 0) {
            $promoCode = $record->promo_code ?? $record->meta['coupon_code'] ?? '';
            $promoLabel = $promoCode ? " ({$promoCode})" : '';
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Reducere{$promoLabel}</span>
                    <span style='font-size: 13px; font-weight: 600; color: #10B981;'>-" . number_format($discount, 2) . " {$currency}</span>
                </div>
            ";
        }

        // Calculate final total
        $finalTotal = $isOnTop ? ($ticketsValue + $commission - $discount) : $total;

        // Total
        $html .= "
            <div style='display: flex; justify-content: space-between; align-items: center; padding: 12px 0 0; margin-top: 4px;'>
                <span style='font-size: 13px; font-weight: 600; color: white;'>Total plătit</span>
                <span style='font-size: 18px; font-weight: 700; color: white;'>" . number_format($finalTotal, 2) . " {$currency}</span>
            </div>
        ";

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function renderCommissionDetails(Order $record): HtmlString
    {
        $currency = $record->currency ?? 'RON';
        
        // Get commission from EVENT
        $event = $record->event;
        $commissionRate = $event?->commission_rate
            ?? $event?->marketplaceOrganizer?->commission_rate
            ?? 0;
        $commissionMode = $event?->commission_mode
            ?? $event?->marketplaceOrganizer?->default_commission_mode
            ?? $record->marketplaceClient?->commission_mode
            ?? 'included';

        // Calculate values
        $ticketsValue = $record->tickets->sum('price');
        $commission = $ticketsValue * ($commissionRate / 100);
        $isOnTop = in_array($commissionMode, ['on_top', 'add_on_top', 'added_on_top']);

        if ($commission <= 0 && $commissionRate <= 0) {
            return new HtmlString('<p style="color: #64748B; text-align: center;">Fără comision</p>');
        }

        // Calculate organizer revenue
        $organizerRevenue = $isOnTop ? $ticketsValue : ($ticketsValue - $commission);
        $modeLabel = $isOnTop ? 'Adăugat peste preț' : 'Inclus în preț';

        return new HtmlString("
            <div>
                <!-- Commission Stats Grid -->
                <div style='display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;'>
                    <div style='text-align: center; padding: 12px; background: rgba(15, 23, 42, 0.5); border-radius: 8px;'>
                        <div style='font-size: 16px; font-weight: 700; color: white;'>" . number_format($commissionRate, 2) . "%</div>
                        <div style='font-size: 10px; color: #64748B; text-transform: uppercase; margin-top: 2px;'>Rată</div>
                    </div>
                    <div style='text-align: center; padding: 12px; background: rgba(15, 23, 42, 0.5); border-radius: 8px;'>
                        <div style='font-size: 16px; font-weight: 700; color: white;'>" . number_format($commission, 2) . " {$currency}</div>
                        <div style='font-size: 10px; color: #64748B; text-transform: uppercase; margin-top: 2px;'>Valoare</div>
                    </div>
                </div>
                
                <!-- Commission Mode -->
                <div style='padding: 12px; background: rgba(15, 23, 42, 0.5); border-radius: 8px; margin-bottom: 12px;'>
                    <div style='display: flex; justify-content: space-between; align-items: center;'>
                        <span style='font-size: 13px; color: #94A3B8;'>Mod comision</span>
                        <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$modeLabel}</span>
                    </div>
                </div>
                
                <!-- Organizer Revenue -->
                <div style='padding: 12px; background: rgba(16, 185, 129, 0.1); border-radius: 8px; border: 1px solid rgba(16, 185, 129, 0.2);'>
                    <div style='display: flex; justify-content: space-between; align-items: center;'>
                        <span style='font-size: 13px; color: #94A3B8;'>Organizator primește</span>
                        <span style='font-size: 16px; font-weight: 700; color: #10B981;'>" . number_format($organizerRevenue, 2) . " {$currency}</span>
                    </div>
                </div>
            </div>
        ");
    }

    protected static function renderBeneficiaries(Order $record): HtmlString
    {
        // Collect beneficiaries from tickets
        $beneficiaries = $record->tickets
            ->filter(fn ($ticket) => $ticket->beneficiary_name || $ticket->beneficiary_email)
            ->map(fn ($ticket) => [
                'name' => $ticket->beneficiary_name ?? $record->customer_name ?? 'N/A',
                'email' => $ticket->beneficiary_email ?? $record->customer_email ?? '',
                'ticket_type' => (function() use ($ticket) {
                    $name = $ticket->ticketType?->name;
                    return is_array($name) ? ($name['ro'] ?? $name['en'] ?? reset($name) ?: 'Bilet') : ($name ?? 'Bilet');
                })(),
                'ticket_code' => $ticket->code ?? $ticket->unique_code ?? '',
            ]);

        // Also check meta for beneficiaries
        if (!empty($record->meta['beneficiaries'])) {
            foreach ($record->meta['beneficiaries'] as $index => $beneficiary) {
                $beneficiaries->push([
                    'name' => $beneficiary['name'] ?? $beneficiary['first_name'] . ' ' . ($beneficiary['last_name'] ?? '') ?? 'N/A',
                    'email' => $beneficiary['email'] ?? '',
                    'ticket_type' => $beneficiary['ticket_type'] ?? 'Bilet',
                    'ticket_code' => $beneficiary['ticket_code'] ?? '',
                ]);
            }
        }

        // Fallback to customer if no beneficiaries
        if ($beneficiaries->isEmpty()) {
            $beneficiaries->push([
                'name' => $record->customer_name ?? 'N/A',
                'email' => $record->customer_email ?? '',
                'ticket_type' => 'Toate biletele',
                'ticket_code' => '',
            ]);
        }

        $html = '';

        foreach ($beneficiaries->unique('email') as $beneficiary) {
            $name = $beneficiary['name'];
            $email = $beneficiary['email'];
            $ticketType = $beneficiary['ticket_type'];
            $initials = collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->join('');

            $html .= "
                <div style='display: flex; align-items: center; gap: 12px; padding: 12px; background: #0F172A; border-radius: 8px; margin-bottom: 8px;'>
                    <div style='width: 36px; height: 36px; border-radius: 50%; background: #334155; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; color: #E2E8F0;'>{$initials}</div>
                    <div style='flex: 1;'>
                        <div style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>" . e($name) . "</div>
                        <div style='font-size: 11px; color: #64748B;'>" . e($email) . "</div>
                    </div>
                    <div style='font-size: 11px; color: #94A3B8; padding: 4px 8px; background: #334155; border-radius: 4px;'>" . e($ticketType) . "</div>
                </div>
            ";
        }

        return new HtmlString($html);
    }

    protected static function renderPaymentDetails(Order $record): HtmlString
    {
        // Processor name (Netopia, Stripe, etc.)
        $processorRaw = $record->payment_processor;
        $processor = match($processorRaw) {
            'netopia', 'payment-netopia' => 'Netopia',
            'stripe', 'payment-stripe' => 'Stripe',
            'paypal' => 'PayPal',
            'cash' => 'Numerar',
            'bank_transfer' => 'Transfer bancar',
            default => $processorRaw ? ucfirst(str_replace(['_', '-', 'payment-'], [' ', ' ', ''], $processorRaw)) : null,
        };

        // Payment method (Card, Bank transfer, etc.) - from meta or derived from processor
        $paymentMethod = $record->meta['payment_method'] ?? $record->meta['method'] ?? null;
        if (!$paymentMethod && $processorRaw) {
            // Derive method from processor if not explicitly set
            $paymentMethod = match($processorRaw) {
                'netopia', 'payment-netopia', 'stripe', 'payment-stripe' => 'Card bancar',
                'paypal' => 'PayPal',
                'cash' => 'Numerar',
                'bank_transfer' => 'Transfer bancar',
                default => null,
            };
        }

        $transactionId = $record->payment_reference ?? $record->meta['payment_intent_id'] ?? $record->meta['transaction_id'] ?? '';
        $cardLast4 = $record->meta['card_last4'] ?? $record->meta['card_last_four'] ?? '';
        $cardBrand = ucfirst($record->meta['card_brand'] ?? '');
        $paidAt = $record->paid_at ?? $record->meta['paid_at'] ?? null;

        $html = '<div>';

        // Payment Processor
        if ($processor) {
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Procesor</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$processor}</span>
                </div>
            ";
        }

        // Payment Method
        if ($paymentMethod) {
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Metodă plată</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$paymentMethod}</span>
                </div>
            ";
        }

        // Transaction ID
        if ($transactionId) {
            // Truncate long transaction IDs
            $displayId = strlen($transactionId) > 20
                ? substr($transactionId, 0, 10) . '...' . substr($transactionId, -6)
                : $transactionId;

            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>ID Tranzacție</span>
                    <span style='font-size: 11px; font-weight: 600; color: #E2E8F0; font-family: monospace; cursor: pointer;' title='" . e($transactionId) . "'>{$displayId}</span>
                </div>
            ";
        }

        // Card Info
        if ($cardLast4) {
            $cardDisplay = $cardBrand ? "{$cardBrand} •••• {$cardLast4}" : "•••• {$cardLast4}";
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Card</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$cardDisplay}</span>
                </div>
            ";
        }

        // Payment Date
        if ($paidAt) {
            $paidAtFormatted = $paidAt instanceof \Carbon\Carbon 
                ? $paidAt->format('d M Y, H:i') 
                : \Carbon\Carbon::parse($paidAt)->format('d M Y, H:i');
            
            $html .= "
                <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid rgba(51, 65, 85, 0.5);'>
                    <span style='font-size: 13px; color: #94A3B8;'>Data plății</span>
                    <span style='font-size: 13px; font-weight: 600; color: #E2E8F0;'>{$paidAtFormatted}</span>
                </div>
            ";
        }

        // Payment Status
        $statusBadge = match($record->status) {
            'paid', 'confirmed' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(16, 185, 129, 0.15); color: #10B981;">✓ Plătit</span>',
            'pending' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(245, 158, 11, 0.15); color: #F59E0B;">⏳ În așteptare</span>',
            'refunded' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(59, 130, 246, 0.15); color: #60A5FA;">↩ Rambursat</span>',
            'cancelled' => '<span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #EF4444;">✕ Anulat</span>',
            default => '',
        };

        $html .= "
            <div style='display: flex; justify-content: space-between; align-items: center; padding: 8px 0;'>
                <span style='font-size: 13px; color: #94A3B8;'>Status plată</span>
                {$statusBadge}
            </div>
        ";

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function renderTimeline(Order $record): HtmlString
    {
        // Build timeline events from order history
        $events = collect();

        // Current status
        $statusText = match($record->status) {
            'confirmed' => 'Comandă confirmată',
            'paid' => 'Comandă plătită',
            'pending' => 'Comandă în așteptare',
            'cancelled' => 'Comandă anulată',
            'refunded' => 'Comandă rambursată',
            default => 'Status: ' . $record->status,
        };

        $statusColor = match($record->status) {
            'confirmed', 'paid' => 'success',
            'pending' => 'warning',
            'cancelled' => 'danger',
            'refunded' => 'info',
            default => 'gray',
        };

        $events->push([
            'status' => $statusColor,
            'text' => $statusText,
            'time' => $record->updated_at,
        ]);

        // Payment processed (if paid)
        if (in_array($record->status, ['paid', 'confirmed'])) {
            $paidAt = $record->paid_at ?? $record->meta['paid_at'] ?? $record->created_at->addMinutes(2);
            $events->push([
                'status' => 'success',
                'text' => 'Plată procesată cu succes',
                'time' => $paidAt instanceof \Carbon\Carbon ? $paidAt : \Carbon\Carbon::parse($paidAt),
            ]);
        }

        // Email sent (assume sent after creation)
        if ($record->meta['confirmation_sent'] ?? true) {
            $events->push([
                'status' => 'info',
                'text' => 'Email confirmare trimis',
                'time' => $record->created_at->addMinutes(1),
            ]);
        }

        // Order created
        $events->push([
            'status' => 'warning',
            'text' => 'Comandă plasată',
            'time' => $record->created_at,
        ]);

        // Sort by time descending
        $events = $events->sortByDesc('time')->values();

        // Render timeline
        $html = '<div style="position: relative; padding-left: 24px;">';
        $html .= '<div style="position: absolute; left: 7px; top: 8px; bottom: 8px; width: 2px; background: #334155;"></div>';

        foreach ($events as $index => $event) {
            $dotColor = match($event['status']) {
                'success' => '#10B981',
                'warning' => '#F59E0B',
                'danger' => '#EF4444',
                'info' => '#60A5FA',
                default => '#334155',
            };

            $time = $event['time'] instanceof \Carbon\Carbon 
                ? $event['time']->format('d M Y, H:i') 
                : $event['time'];

            $isLast = $index === $events->count() - 1;
            $paddingBottom = $isLast ? '0' : '16px';

            $html .= "
                <div style='position: relative; padding-bottom: {$paddingBottom};'>
                    <div style='position: absolute; left: -24px; top: 4px; width: 16px; height: 16px; border-radius: 50%; background: {$dotColor}; border: 3px solid #1E293B;'></div>
                    <div style='font-size: 13px; color: #E2E8F0;'>{$event['text']}</div>
                    <div style='font-size: 11px; color: #64748B; margin-top: 2px;'>{$time}</div>
                </div>
            ";
        }

        $html .= '</div>';

        return new HtmlString($html);
    }

    protected static function resendConfirmation(Order $record): void
    {
        // Send confirmation email
        try {
            // Option 1: Using a Mailable
            \Mail::to($record->customer_email)
                ->send(new \App\Mail\OrderConfirmation($record));
            
            // Option 2: Using a Notification
            // $record->customer?->notify(new \App\Notifications\OrderConfirmed($record));
            
            // Update meta
            $record->update([
                'meta' => array_merge($record->meta ?? [], [
                    'confirmation_resent_at' => now()->toISOString(),
                    'confirmation_resent_count' => ($record->meta['confirmation_resent_count'] ?? 0) + 1,
                ]),
            ]);
            
            \Filament\Notifications\Notification::make()
                ->title('Email trimis')
                ->body('Confirmarea a fost retrimisă către ' . $record->customer_email)
                ->success()
                ->send();
                
        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Eroare')
                ->body('Nu s-a putut trimite emailul: ' . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected static function downloadAllTickets(Order $record)
    {
        // Generate PDF with all tickets
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('tickets.pdf', [
            'order' => $record,
            'tickets' => $record->tickets,
        ]);
        
        return response()->streamDownload(
            fn () => print($pdf->output()),
            "tickets-order-{$record->id}.pdf"
        );
    }

    protected static function printInvoice(Order $record)
    {
        // Generate invoice PDF
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('invoices.order', [
            'order' => $record,
        ]);
        
        return response()->streamDownload(
            fn () => print($pdf->output()),
            "invoice-{$record->id}.pdf"
        );
    }
}
