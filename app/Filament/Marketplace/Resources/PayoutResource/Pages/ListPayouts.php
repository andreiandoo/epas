<?php

namespace App\Filament\Marketplace\Resources\PayoutResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerResource;
use App\Filament\Marketplace\Resources\PayoutResource;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerBankAccount;
use App\Models\MarketplacePayout;
use App\Models\Order;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ListPayouts extends ListRecords
{
    protected static string $resource = PayoutResource::class;

    public function getHeading(): string|Htmlable
    {
        $count = number_format(static::getResource()::getEloquentQuery()->count());
        return new HtmlString("Deconturi <span class=\"ml-2 inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-sm font-medium text-gray-700 dark:bg-white/10 dark:text-gray-300\">{$count}</span>");
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreatePayoutAction(),
            $this->getFinishedEventsAction(),
        ];
    }

    protected function getCreatePayoutAction(): Actions\Action
    {
        return Actions\Action::make('create_payout')
            ->label('Crează Decont Manual')
            ->icon('heroicon-o-plus-circle')
            ->color('primary')
            ->form([
                Forms\Components\Select::make('marketplace_organizer_id')
                    ->label('Organizator')
                    ->options(function () {
                        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
                        return MarketplaceOrganizer::where('marketplace_client_id', $marketplaceAdmin->marketplace_client_id)
                            ->whereNotNull('verified_at')
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set) {
                        $set('event_id', null);
                        $set('bank_account_id', null);
                    }),

                Forms\Components\Select::make('event_id')
                    ->label('Eveniment')
                    ->options(function (Get $get) {
                        $organizerId = $get('marketplace_organizer_id');
                        if (!$organizerId) return [];

                        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
                        return Event::where('marketplace_organizer_id', $organizerId)
                            ->where('marketplace_client_id', $marketplaceAdmin->marketplace_client_id)
                            ->orderByDesc('event_date')
                            ->get()
                            ->mapWithKeys(function ($event) {
                                $title = is_array($event->title)
                                    ? ($event->title['ro'] ?? $event->title['en'] ?? array_values($event->title)[0] ?? 'Untitled')
                                    : ($event->title ?? 'Untitled');
                                $status = $event->isPast() ? '🔴 Încheiat' : '🟢 Live';
                                $date = $event->event_date?->format('d.m.Y') ?? '';
                                return [$event->id => "{$title} ({$date}) — {$status}"];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state) {
                            $event = Event::with('marketplaceOrganizer')->find($state);
                            if ($event) {
                                // Calculate and prefill all amount fields
                                $organizer = $event->marketplaceOrganizer;
                                $commissionMode = $event->getEffectiveCommissionMode();
                                $commissionRate = $event->getEffectiveCommissionRate();

                                $completedOrders = Order::where('marketplace_organizer_id', $organizer?->id)
                                    ->where('event_id', $event->id)
                                    ->whereIn('status', ['paid', 'confirmed', 'completed'])
                                    ->get();

                                $grossRevenue = (float) $completedOrders->sum('total');
                                $subtotalRevenue = (float) $completedOrders->sum('subtotal');

                                if ($commissionMode === 'added_on_top') {
                                    $commissionAmount = round($grossRevenue - $subtotalRevenue, 2);
                                } else {
                                    $commissionAmount = round($grossRevenue * ($commissionRate / 100), 2);
                                }

                                $set('gross_amount', number_format($grossRevenue, 2, '.', ''));
                                $set('commission_amount', number_format($commissionAmount, 2, '.', ''));
                                $set('fees_amount', '0.00');
                                $set('net_amount', number_format(max(0, $grossRevenue - $commissionAmount), 2, '.', ''));
                            }
                        } else {
                            $set('gross_amount', '0.00');
                            $set('commission_amount', '0.00');
                            $set('fees_amount', '0.00');
                            $set('net_amount', '0.00');
                        }
                    }),

                Forms\Components\Placeholder::make('available_balance_info')
                    ->label('Sold disponibil')
                    ->content(function (Get $get) {
                        $eventId = $get('event_id');
                        if (!$eventId) return '-';
                        $event = Event::with(['marketplaceOrganizer', 'ticketTypes'])->find($eventId);
                        if (!$event) return '-';
                        $balance = self::calculateEventBalance($event);

                        // Commission info — check at event level first
                        $html = '<span class="font-semibold text-emerald-600 dark:text-emerald-400">' . number_format($balance, 2) . ' RON</span> disponibil';

                        // Event-level commission
                        $eventHasOwnCommission = $event->commission_rate !== null || $event->commission_mode !== null;
                        $effectiveMode = $event->getEffectiveCommissionMode();
                        $effectiveRate = $event->getEffectiveCommissionRate();
                        $modeLabel = $effectiveMode === 'added_on_top' ? 'Adăugat peste preț' : 'Inclus în preț';

                        if ($eventHasOwnCommission) {
                            $html .= '<br><span class="text-xs text-gray-500">Eveniment: ' . $modeLabel . ' · ' . number_format($effectiveRate, 2) . '%</span>';
                        } else {
                            $orgName = $event->marketplaceOrganizer?->name ?? 'Organizator';
                            $html .= '<br><span class="text-xs text-gray-500">Moștenit de la ' . e($orgName) . ': ' . $modeLabel . ' · ' . number_format($effectiveRate, 2) . '%</span>';
                        }

                        // Check if any ticket types have custom commission
                        $customCommTts = $event->ticketTypes->filter(fn ($tt) => $tt->commission_type && $tt->commission_type !== '');
                        if ($customCommTts->isNotEmpty()) {
                            $html .= '<br><span class="text-xs text-amber-600 dark:text-amber-400">⚠ ' . $customCommTts->count() . ' tip(uri) de bilet cu comision personalizat</span>';
                        }

                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->visible(fn (Get $get) => $get('event_id') !== null),

                Forms\Components\Placeholder::make('event_details')
                    ->label('')
                    ->content(function (Get $get) {
                        $eventId = $get('event_id');
                        if (!$eventId) return '';

                        $event = Event::with(['ticketTypes'])->find($eventId);
                        if (!$event) return '';

                        return new HtmlString($this->renderEventBreakdown($event));
                    })
                    ->visible(fn (Get $get) => $get('event_id') !== null),

                Forms\Components\Placeholder::make('no_bank_account_warning')
                    ->label('')
                    ->content(function (Get $get) {
                        $organizerId = $get('marketplace_organizer_id');
                        if (!$organizerId) return '';
                        $count = MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $organizerId)->count();
                        if ($count > 0) return '';
                        $editUrl = OrganizerResource::getUrl('edit', ['record' => $organizerId]);
                        return new HtmlString(
                            '<div class="rounded-lg border border-warning-300 bg-warning-50 dark:border-warning-600 dark:bg-warning-950 p-3 text-sm">'
                            . '<div class="flex items-center gap-2 text-warning-700 dark:text-warning-400">'
                            . '<svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>'
                            . '<span>Acest organizator nu are niciun cont bancar configurat.</span>'
                            . '</div>'
                            . '<a href="' . $editUrl . '" target="_blank" class="mt-2 inline-flex items-center gap-1 text-sm font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400">'
                            . '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>'
                            . 'Deschide pagina organizatorului pentru a adăuga un cont bancar'
                            . '</a></div>'
                        );
                    })
                    ->visible(function (Get $get) {
                        $organizerId = $get('marketplace_organizer_id');
                        if (!$organizerId) return false;
                        return MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $organizerId)->count() === 0;
                    }),

                Forms\Components\Select::make('bank_account_id')
                    ->label('Cont bancar')
                    ->options(function (Get $get) {
                        $organizerId = $get('marketplace_organizer_id');
                        if (!$organizerId) return [];

                        return MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $organizerId)
                            ->orderByDesc('is_primary')
                            ->get()
                            ->mapWithKeys(fn ($acc) => [
                                $acc->id => $acc->bank_name . ' - ' . $acc->iban . ($acc->is_primary ? ' ★ primar' : ''),
                            ])
                            ->toArray();
                    })
                    ->searchable()
                    ->required()
                    ->visible(function (Get $get) {
                        $organizerId = $get('marketplace_organizer_id');
                        if (!$organizerId) return false;
                        return MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $organizerId)->count() > 0;
                    }),

                \Filament\Schemas\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('gross_amount')
                        ->label('Suma brută')
                        ->numeric()
                        ->required()
                        ->suffix('RON')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            $gross = (float) $state;
                            $commission = (float) $get('commission_amount');
                            $fees = (float) $get('fees_amount');
                            $set('net_amount', number_format(max(0, $gross - $commission - $fees), 2, '.', ''));
                        }),

                    Forms\Components\TextInput::make('commission_amount')
                        ->label('Comision')
                        ->numeric()
                        ->default('0.00')
                        ->suffix('RON')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            $gross = (float) $get('gross_amount');
                            $commission = (float) $state;
                            $fees = (float) $get('fees_amount');
                            $set('net_amount', number_format(max(0, $gross - $commission - $fees), 2, '.', ''));
                        }),

                    Forms\Components\TextInput::make('fees_amount')
                        ->label('Taxe')
                        ->numeric()
                        ->default('0.00')
                        ->suffix('RON')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Set $set, Get $get) {
                            $gross = (float) $get('gross_amount');
                            $commission = (float) $get('commission_amount');
                            $fees = (float) $state;
                            $set('net_amount', number_format(max(0, $gross - $commission - $fees), 2, '.', ''));
                        }),

                    Forms\Components\TextInput::make('net_amount')
                        ->label('Suma netă (de plată)')
                        ->numeric()
                        ->required()
                        ->suffix('RON')
                        ->readOnly(),
                ]),

                Forms\Components\Textarea::make('admin_notes')
                    ->label('Note admin')
                    ->rows(2),
            ])
            ->action(function (array $data): void {
                $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
                $bankAccount = MarketplaceOrganizerBankAccount::find($data['bank_account_id']);

                if (!$bankAccount) {
                    \Filament\Notifications\Notification::make()
                        ->title('Eroare')
                        ->body('Contul bancar selectat nu a fost găsit.')
                        ->danger()
                        ->send();
                    return;
                }

                $payoutMethod = [
                    'type' => 'bank_transfer',
                    'bank_account_id' => $bankAccount->id,
                    'bank_name' => $bankAccount->bank_name,
                    'iban' => $bankAccount->iban,
                    'account_holder' => $bankAccount->account_holder,
                ];

                $event = Event::find($data['event_id']);

                $payout = MarketplacePayout::create([
                    'marketplace_client_id' => $marketplaceAdmin->marketplace_client_id,
                    'marketplace_organizer_id' => $data['marketplace_organizer_id'],
                    'event_id' => $data['event_id'],
                    'amount' => (float) $data['net_amount'],
                    'currency' => 'RON',
                    'period_start' => $event?->created_at?->toDateString(),
                    'period_end' => $event?->event_date?->toDateString() ?? now()->toDateString(),
                    'gross_amount' => (float) $data['gross_amount'],
                    'commission_amount' => (float) ($data['commission_amount'] ?? 0),
                    'fees_amount' => (float) ($data['fees_amount'] ?? 0),
                    'adjustments_amount' => 0,
                    'status' => 'pending',
                    'source' => 'manual',
                    'payout_method' => $payoutMethod,
                    'admin_notes' => $data['admin_notes'] ?? null,
                ]);

                \Filament\Notifications\Notification::make()
                    ->title('Decont creat')
                    ->body("Decontul {$payout->reference} a fost creat cu succes.")
                    ->success()
                    ->send();
            });
    }

    protected function getFinishedEventsAction(): Actions\Action
    {
        return Actions\Action::make('finished_events')
            ->label('Evenimente încheiate')
            ->icon('heroicon-o-calendar')
            ->color('gray')
            ->modalHeading('Evenimente încheiate')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Închide')
            ->modalWidth('5xl')
            ->form([
                Forms\Components\Radio::make('quick_filter')
                    ->label('Perioadă rapidă')
                    ->options([
                        '' => 'Ultimele 10',
                        '1' => '1 zi',
                        '3' => '3 zile',
                        '7' => '7 zile',
                        'custom' => 'Personalizat',
                    ])
                    ->default('')
                    ->inline()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set) {
                        if ($state !== 'custom') {
                            $set('date_from', null);
                            $set('date_to', null);
                        }
                    }),

                \Filament\Schemas\Components\Grid::make(2)->schema([
                    Forms\Components\DatePicker::make('date_from')
                        ->label('De la')
                        ->live()
                        ->placeholder('Fără limită'),

                    Forms\Components\DatePicker::make('date_to')
                        ->label('Până la')
                        ->live()
                        ->placeholder('Fără limită'),
                ])->visible(fn (Get $get) => $get('quick_filter') === 'custom'),

                Forms\Components\Placeholder::make('events_table')
                    ->label('')
                    ->content(function (Get $get) {
                        $quickFilter = $get('quick_filter');
                        $dateFrom = null;
                        $dateTo = null;
                        $limit = null;

                        if ($quickFilter === 'custom') {
                            $dateFrom = $get('date_from');
                            $dateTo = $get('date_to');
                        } elseif ($quickFilter && $quickFilter !== '') {
                            $days = (int) $quickFilter;
                            $dateFrom = now()->subDays($days)->toDateString();
                        } else {
                            // Default: last 10
                            $limit = 10;
                        }

                        return new HtmlString(
                            $this->renderFinishedEventsTable($dateFrom, $dateTo, $limit)
                        );
                    }),
            ]);
    }

    /**
     * Render the finished events table HTML
     */
    protected function renderFinishedEventsTable(?string $dateFrom, ?string $dateTo, ?int $limit = null): string
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        $marketplaceClientId = $marketplaceAdmin->marketplace_client_id;

        $query = Event::where('marketplace_client_id', $marketplaceClientId)
            ->whereNotNull('marketplace_organizer_id')
            ->where(function ($q) {
                $q->where('event_date', '<', now())
                  ->orWhere(function ($q2) {
                      $q2->whereNull('event_date')->where('created_at', '<', now()->subMonths(3));
                  });
            })
            ->orderByDesc('event_date');

        if ($dateFrom) {
            $query->where('event_date', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->where('event_date', '<=', $dateTo . ' 23:59:59');
        }

        if ($limit) {
            $query->limit($limit);
        }

        $events = $query->get();

        $rows = [];
        foreach ($events as $event) {
            $title = is_array($event->title)
                ? ($event->title['ro'] ?? $event->title['en'] ?? array_values($event->title)[0] ?? 'Untitled')
                : ($event->title ?? 'Untitled');

            $organizer = $event->marketplaceOrganizer;
            $organizerName = $organizer?->name ?? '-';
            $eventDate = $event->event_date?->format('d.m.Y') ?? '-';

            $existingPayout = MarketplacePayout::where('event_id', $event->id)
                ->where('marketplace_client_id', $marketplaceClientId)
                ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
                ->first();

            $balance = $organizer ? self::calculateEventBalance($event) : 0;

            $rows[] = [
                'event' => $event,
                'title' => $title,
                'organizer_name' => $organizerName,
                'event_date' => $eventDate,
                'balance' => $balance,
                'existing_payout' => $existingPayout,
            ];
        }

        return view('filament.marketplace.payouts.finished-events', [
            'rows' => $rows,
        ])->render();
    }

    /**
     * Generate automated payout for an event (called via Livewire)
     */
    public function generateEventDecont(int $eventId): void
    {
        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
        $event = Event::where('id', $eventId)
            ->where('marketplace_client_id', $marketplaceAdmin->marketplace_client_id)
            ->firstOrFail();

        $organizer = $event->marketplaceOrganizer;
        if (!$organizer) {
            \Filament\Notifications\Notification::make()
                ->title('Eroare')
                ->body('Evenimentul nu are organizator asociat.')
                ->danger()
                ->send();
            return;
        }

        // Check if payout already exists
        $existing = MarketplacePayout::where('event_id', $event->id)
            ->where('marketplace_organizer_id', $organizer->id)
            ->whereIn('status', ['pending', 'approved', 'processing', 'completed'])
            ->exists();

        if ($existing) {
            \Filament\Notifications\Notification::make()
                ->title('Decont existent')
                ->body('Există deja un decont pentru acest eveniment.')
                ->warning()
                ->send();
            return;
        }

        $balance = self::calculateEventBalance($event);
        if ($balance <= 0) {
            \Filament\Notifications\Notification::make()
                ->title('Sold insuficient')
                ->body('Nu există sold disponibil pentru acest eveniment.')
                ->warning()
                ->send();
            return;
        }

        // Get bank account
        $bankAccount = $organizer->bankAccounts()->where('is_primary', true)->first()
            ?? $organizer->bankAccounts()->first();

        $payoutMethod = $bankAccount ? [
            'type' => 'bank_transfer',
            'bank_account_id' => $bankAccount->id,
            'bank_name' => $bankAccount->bank_name,
            'iban' => $bankAccount->iban,
            'account_holder' => $bankAccount->account_holder,
        ] : ($organizer->payout_details ?? []);

        // Calculate commission
        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionRate = $event->getEffectiveCommissionRate();

        if ($commissionMode === 'added_on_top') {
            $grossAmount = $balance;
            $commissionAmount = 0;
        } else {
            $grossAmount = $balance / (1 - $commissionRate / 100);
            $commissionAmount = $grossAmount - $balance;
        }

        // Period
        $lastPayout = MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->orderByDesc('period_end')
            ->first();

        $periodStart = $lastPayout
            ? $lastPayout->period_end->addDay()->toDateString()
            : $event->created_at->toDateString();

        $payout = MarketplacePayout::create([
            'marketplace_client_id' => $marketplaceAdmin->marketplace_client_id,
            'marketplace_organizer_id' => $organizer->id,
            'event_id' => $event->id,
            'amount' => round($balance, 2),
            'currency' => 'RON',
            'period_start' => $periodStart,
            'period_end' => now()->toDateString(),
            'gross_amount' => round($grossAmount, 2),
            'commission_amount' => round($commissionAmount, 2),
            'fees_amount' => 0,
            'adjustments_amount' => 0,
            'status' => 'pending',
            'source' => 'automated',
            'payout_method' => $payoutMethod,
            'admin_notes' => 'Decont generat automat din lista evenimente încheiate.',
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Decont generat')
            ->body("Decontul {$payout->reference} ({$balance} RON) a fost creat.")
            ->success()
            ->send();

        $this->redirect(PayoutResource::getUrl('index'));
    }

    /**
     * Calculate available balance for an event
     */
    public static function calculateEventBalance(Event $event): float
    {
        $organizer = $event->marketplaceOrganizer;
        if (!$organizer) return 0;

        $completedOrders = Order::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->get();

        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionRate = $event->getEffectiveCommissionRate();

        $grossRevenue = (float) $completedOrders->sum('total');
        $subtotalRevenue = (float) $completedOrders->sum('subtotal');

        if ($commissionMode === 'added_on_top') {
            $netRevenue = $subtotalRevenue;
        } else {
            $commissionAmount = round($grossRevenue * ($commissionRate / 100), 2);
            $netRevenue = $grossRevenue - $commissionAmount;
        }

        $eventPayouts = (float) MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->sum('amount');

        $eventPendingPayouts = (float) MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('amount');

        return max(0, $netRevenue - $eventPayouts - $eventPendingPayouts);
    }

    /**
     * Render detailed event breakdown HTML for the manual decont modal
     */
    protected function renderEventBreakdown(Event $event): string
    {
        $organizer = $event->marketplaceOrganizer;
        if (!$organizer) return '';

        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionRate = $event->getEffectiveCommissionRate();
        $commissionModeLabel = $commissionMode === 'added_on_top' ? 'Adăugat peste preț' : 'Inclus în preț';

        // Completed orders — match by event_id OR marketplace_event_id
        // Include orders with or without marketplace_organizer_id (migrated may lack it)
        $completedOrders = Order::where(function ($q) use ($event) {
                $q->where('event_id', $event->id)
                  ->orWhere('marketplace_event_id', $event->id);
            })
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('source', '!=', 'test_order')
            ->with(['items.ticketType'])
            ->get();

        // Refunded orders
        $refundedOrders = Order::where(function ($q) use ($event) {
                $q->where('event_id', $event->id)
                  ->orWhere('marketplace_event_id', $event->id);
            })
            ->where('status', 'refunded')
            ->get();

        $totalRefundedAmount = (float) $refundedOrders->sum(fn ($o) => $o->refund_amount ?: $o->total);
        $totalRefundedCount = $refundedOrders->count();

        // Ticket type breakdown with per-ticket commission info
        $ticketBreakdown = [];
        $totalTicketsSold = 0;

        // Also load ticket types for the event (for commission info fallback)
        $eventTicketTypes = $event->ticketTypes()->get()->keyBy('id');

        foreach ($completedOrders as $order) {
            if ($order->items->isNotEmpty()) {
                foreach ($order->items as $item) {
                    $tt = $item->ticketType ?? ($item->ticket_type_id ? $eventTicketTypes->get($item->ticket_type_id) : null);
                    $name = $item->name ?? $tt?->name ?? 'Bilet';

                    if (!isset($ticketBreakdown[$name])) {
                        $ticketBreakdown[$name] = [
                            'quantity' => 0,
                            'total' => 0,
                            'unit_price' => (float) $item->unit_price,
                            'commission_type' => $tt?->commission_type,
                            'commission_rate' => $tt?->commission_rate,
                            'commission_fixed' => $tt?->commission_fixed,
                            'commission_mode' => $tt?->commission_mode,
                            'ticket_type_id' => $tt?->id,
                        ];
                    }
                    $ticketBreakdown[$name]['quantity'] += $item->quantity;
                    $ticketBreakdown[$name]['total'] += (float) $item->total;
                    $totalTicketsSold += $item->quantity;
                }
            } else {
                // Orders without items (migrated) — try to match ticket records
                $orderTickets = \App\Models\Ticket::where('order_id', $order->id)->get();
                if ($orderTickets->isNotEmpty()) {
                    foreach ($orderTickets->groupBy('ticket_type_id') as $ttId => $tickets) {
                        $tt = $eventTicketTypes->get($ttId);
                        $name = $tt?->name ?? 'Bilet #' . $ttId;
                        $qty = $tickets->count();
                        $unitPrice = (float) ($tickets->first()->price ?? ($order->total / max(1, $orderTickets->count())));

                        if (!isset($ticketBreakdown[$name])) {
                            $ticketBreakdown[$name] = [
                                'quantity' => 0,
                                'total' => 0,
                                'unit_price' => $unitPrice,
                                'commission_type' => $tt?->commission_type,
                                'commission_rate' => $tt?->commission_rate,
                                'commission_fixed' => $tt?->commission_fixed,
                                'commission_mode' => $tt?->commission_mode,
                                'ticket_type_id' => $tt?->id,
                            ];
                        }
                        $ticketBreakdown[$name]['quantity'] += $qty;
                        $ticketBreakdown[$name]['total'] += $unitPrice * $qty;
                        $totalTicketsSold += $qty;
                    }
                } else {
                    // No items, no tickets — fallback
                    $name = 'Bilet (fără detalii)';
                    if (!isset($ticketBreakdown[$name])) {
                        $ticketBreakdown[$name] = [
                            'quantity' => 0, 'total' => 0, 'unit_price' => (float) $order->total,
                            'commission_type' => null, 'commission_rate' => null,
                            'commission_fixed' => null, 'commission_mode' => null,
                            'ticket_type_id' => null,
                        ];
                    }
                    $ticketBreakdown[$name]['quantity'] += 1;
                    $ticketBreakdown[$name]['total'] += (float) $order->total;
                    $totalTicketsSold += 1;
                }
            }
        }

        // Also count actual ticket records as fallback
        $ticketRecordCount = \App\Models\Ticket::whereHas('ticketType', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status', ['valid', 'used'])
            ->count();
        if ($ticketRecordCount > $totalTicketsSold) {
            $totalTicketsSold = $ticketRecordCount;
        }

        $grossRevenue = (float) $completedOrders->sum('total');
        $subtotalRevenue = (float) $completedOrders->sum('subtotal');

        // Per-ticket-type commission calculation
        $totalCommission = 0;
        foreach ($ticketBreakdown as $name => &$data) {
            $ttCommType = $data['commission_type'] ?? null;
            $ttCommRate = (float) ($data['commission_rate'] ?? 0);
            $ttCommFixed = (float) ($data['commission_fixed'] ?? 0);
            $ttCommMode = $data['commission_mode'] ?? null;

            if ($ttCommType && $ttCommType !== '') {
                // Per-ticket custom commission
                $comm = match ($ttCommType) {
                    'percentage' => round($data['total'] * ($ttCommRate / 100), 2),
                    'fixed' => round($ttCommFixed * $data['quantity'], 2),
                    'both' => round($data['total'] * ($ttCommRate / 100), 2) + round($ttCommFixed * $data['quantity'], 2),
                    default => round($data['total'] * ($commissionRate / 100), 2),
                };
                $data['calculated_commission'] = $comm;

                $modeLabel = match ($ttCommMode ?? $commissionMode) {
                    'added_on_top' => 'on top',
                    'included' => 'inclus',
                    default => '',
                };
                $data['commission_label'] = match ($ttCommType) {
                    'percentage' => number_format($ttCommRate, 2) . '% ' . $modeLabel,
                    'fixed' => number_format($ttCommFixed, 2) . ' RON/bilet ' . $modeLabel,
                    'both' => number_format($ttCommRate, 2) . '% + ' . number_format($ttCommFixed, 2) . ' RON/bilet ' . $modeLabel,
                    default => number_format($commissionRate, 2) . '% ' . $modeLabel,
                };
            } else {
                // Use event-level commission
                $data['calculated_commission'] = round($data['total'] * ($commissionRate / 100), 2);
                $modeLabel = $commissionMode === 'added_on_top' ? 'on top' : 'inclus';
                $data['commission_label'] = number_format($commissionRate, 2) . '% ' . $modeLabel . ' (eveniment)';
            }
            $totalCommission += $data['calculated_commission'];
        }
        unset($data);

        $netRevenue = $grossRevenue - $totalCommission - $totalRefundedAmount;

        // Previous payouts for this event
        $previousPayouts = \App\Models\MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->whereIn('status', ['completed', 'processing', 'approved', 'pending'])
            ->orderBy('created_at', 'desc')
            ->get();
        $totalPreviouslyPaid = (float) $previousPayouts->where('status', 'completed')->sum('amount');
        $totalPreviousPending = (float) $previousPayouts->whereIn('status', ['pending', 'approved', 'processing'])->sum('amount');

        // === BUILD HTML ===
        $html = '<div class="border border-gray-200 dark:border-white/10 rounded-lg overflow-hidden text-sm">';

        // Ticket types table with commission per type
        if (!empty($ticketBreakdown)) {
            $html .= '<table class="w-full">';
            $html .= '<thead><tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">';
            $html .= '<th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Tip bilet</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Preț</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Qty</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Total</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Comision</th>';
            $html .= '</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/5">';

            foreach ($ticketBreakdown as $name => $data) {
                $html .= '<tr>';
                $html .= '<td class="px-3 py-1.5 text-gray-900 dark:text-white">' . e($name) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-600 dark:text-gray-400">' . number_format($data['unit_price'], 2) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-600 dark:text-gray-400">' . $data['quantity'] . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-900 dark:text-white">' . number_format($data['total'], 2) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right text-gray-500 dark:text-gray-400">';
                $html .= '<span class="font-mono">' . number_format($data['calculated_commission'], 2) . '</span>';
                $html .= '<br><span class="text-xs text-gray-400">' . $data['commission_label'] . '</span>';
                $html .= '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        // Summary section
        $html .= '<div class="border-t border-gray-200 dark:border-white/10 px-3 py-2 space-y-1 bg-gray-50 dark:bg-white/5">';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total bilete vândute:</span><span class="font-medium">' . number_format($totalTicketsSold) . '</span></div>';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Vânzări brute:</span><span class="font-mono font-medium">' . number_format($grossRevenue, 2) . ' RON</span></div>';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total comision:</span><span class="font-mono font-medium">' . number_format($totalCommission, 2) . ' RON</span></div>';

        if ($totalRefundedCount > 0) {
            $html .= '<div class="flex justify-between text-red-600 dark:text-red-400"><span>Retururi (' . $totalRefundedCount . ' comenzi):</span><span class="font-mono font-medium">-' . number_format($totalRefundedAmount, 2) . ' RON</span></div>';
        }

        // Previous payouts
        if ($previousPayouts->isNotEmpty()) {
            $html .= '<div class="pt-1 mt-1 border-t border-gray-200 dark:border-white/10">';
            $html .= '<div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-1">Deconturi anterioare:</div>';
            foreach ($previousPayouts as $pp) {
                $statusBadge = match ($pp->status) {
                    'completed' => '<span class="text-xs px-1.5 py-0.5 rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Achitat</span>',
                    'pending' => '<span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Pending</span>',
                    'approved' => '<span class="text-xs px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">Aprobat</span>',
                    'processing' => '<span class="text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400">În procesare</span>',
                    default => '<span class="text-xs px-1.5 py-0.5 rounded bg-gray-100 text-gray-700">' . $pp->status . '</span>',
                };
                $html .= '<div class="flex items-center justify-between text-xs py-0.5">';
                $html .= '<span class="text-gray-500">' . $pp->created_at->format('d.m.Y') . ' ' . $statusBadge . '</span>';
                $html .= '<span class="font-mono font-medium text-gray-700 dark:text-gray-300">' . number_format($pp->amount, 2) . ' RON</span>';
                $html .= '</div>';
            }
            if ($totalPreviouslyPaid > 0) {
                $html .= '<div class="flex justify-between text-xs font-semibold mt-1 pt-1 border-t border-gray-100 dark:border-white/5"><span>Total achitat anterior:</span><span class="font-mono">' . number_format($totalPreviouslyPaid, 2) . ' RON</span></div>';
            }
            $html .= '</div>';
        }

        // Net balance line with commission mode
        $html .= '<div class="flex justify-between pt-1 border-t border-gray-200 dark:border-white/10 font-semibold">';
        $html .= '<span>Sold disponibil <span class="text-xs font-normal text-gray-400">(' . $commissionModeLabel . ')</span></span>';
        $html .= '<span class="font-mono text-emerald-600 dark:text-emerald-400">' . number_format(max(0, $netRevenue - $totalPreviouslyPaid - $totalPreviousPending), 2) . ' RON</span>';
        $html .= '</div>';

        $html .= '</div></div>';

        return $html;
    }

    public function getTabsContentComponent(): Component
    {
        return parent::getTabsContentComponent()
            ->extraAttributes([
                'x-data' => '{}',
                'x-init' => "\$nextTick(() => {
                    const toolbar = document.querySelector('.fi-ta-header-toolbar');
                    if (!toolbar) return;
                    const nav = \$el.querySelector('.fi-tabs');
                    if (!nav) return;
                    nav.style.order = '-1';
                    toolbar.prepend(nav);
                })",
            ]);
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->badge(fn () => $this->getResource()::getEloquentQuery()->count()),
            'pending' => Tab::make('Pending')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'pending'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'pending')->count())
                ->badgeColor('warning'),
            'approved' => Tab::make('Approved')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'approved'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'approved')->count())
                ->badgeColor('info'),
            'processing' => Tab::make('Processing')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'processing'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'processing')->count())
                ->badgeColor('primary'),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'completed'))
                ->badge(fn () => $this->getResource()::getEloquentQuery()->where('status', 'completed')->count())
                ->badgeColor('success'),
        ];
    }
}
