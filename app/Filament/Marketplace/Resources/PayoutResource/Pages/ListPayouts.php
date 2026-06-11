<?php

namespace App\Filament\Marketplace\Resources\PayoutResource\Pages;

use App\Filament\Marketplace\Resources\OrganizerResource;
use App\Filament\Marketplace\Resources\PayoutResource;
use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceOrganizerBankAccount;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceTaxTemplate;
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
                    ->afterStateUpdated(function ($state, Set $set) {
                        $set('event_id', null);
                        // Auto-select primary bank account
                        $primary = $state ? MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $state)
                            ->where('is_primary', true)->first() : null;
                        $set('bank_account_id', $primary?->id);
                    }),

                Forms\Components\Select::make('event_id')
                    ->label('Eveniment')
                    ->options(function (Get $get) {
                        $organizerId = $get('marketplace_organizer_id');
                        if (!$organizerId) return [];

                        $marketplaceAdmin = Auth::guard('marketplace_admin')->user();
                        $events = Event::where('marketplace_organizer_id', $organizerId)
                            ->where('marketplace_client_id', $marketplaceAdmin->marketplace_client_id)
                            ->get();

                        // Resolve dates via the Event accessors (start_date /
                        // end_date / isPast) so Interval (range), multi-day and
                        // recurring events show their real date instead of "TBD".
                        // The raw event_date column is only populated for single_day.
                        $live = $events->filter(fn ($e) => $e->start_date && !$e->isPast())->sortBy(fn ($e) => $e->start_date);
                        $ended = $events->filter(fn ($e) => $e->start_date && $e->isPast())->sortByDesc(fn ($e) => $e->start_date);
                        $noDate = $events->filter(fn ($e) => !$e->start_date);

                        return $live->concat($ended)->concat($noDate)
                            ->mapWithKeys(function ($event) {
                                $title = is_array($event->title)
                                    ? ($event->title['ro'] ?? $event->title['en'] ?? array_values($event->title)[0] ?? 'Untitled')
                                    : ($event->title ?? 'Untitled');
                                $status = (!$event->start_date) ? '⚪ TBD' : ($event->isPast() ? '🔴 Încheiat' : '🟢 Live');
                                $start = $event->start_date?->format('d.m.Y');
                                $end = $event->end_date?->format('d.m.Y');
                                // Show range as "start – end" when the end differs (Interval/multi-day)
                                $date = $start
                                    ? ($end && $end !== $start ? "{$start} – {$end}" : $start)
                                    : 'TBD';
                                return [$event->id => "{$title} ({$date}) — {$status}"];
                            })
                            ->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                        if ($state) {
                            $event = Event::with(['marketplaceOrganizer', 'ticketTypes'])->find($state);
                            if ($event) {
                                $fin = self::calculateEventFinancials($event);
                                $eventDiscount = (float) ($fin['discount'] ?? 0);
                                $eventExtras = (float) ($fin['extras'] ?? 0);
                                $set('discount_amount', number_format($eventDiscount, 2, '.', ''));
                                $set('fees_amount', number_format($eventExtras, 2, '.', ''));

                                if ($fin['balance'] <= 0) {
                                    // Distinguish: no sales vs fully paid
                                    $set('has_balance', false);
                                    $set('zero_reason', $fin['gross'] > 0 ? 'fully_paid' : 'no_sales');
                                    $set('payout_tickets', []);
                                    $set('gross_amount', '0.00');
                                    $set('commission_amount', '0.00');
                                    $set('discount_amount', '0.00');
                                    $set('fees_amount', '0.00');
                                    $set('net_amount', '0.00');
                                    $set('desired_net_amount', null);
                                } else {
                                    $set('has_balance', true);
                                    // Populate ticket selector with available (not yet paid) tickets
                                    $this->populatePayoutTicketsFromEvent($set, $event, $fin);

                                    // Check if tickets were populated
                                    // If no specific tickets remain, set amounts based on balance directly
                                    $populatedTickets = $get('payout_tickets') ?? [];
                                    $hasTickets = collect($populatedTickets)->sum(fn ($t) => (int) ($t['qty'] ?? 0)) > 0;

                                    if ($hasTickets) {
                                        // Calculate from populated tickets
                                        $ticketGross = 0;
                                        $ticketComm = 0;
                                        foreach ($populatedTickets as $t) {
                                            $qty = (int) ($t['qty'] ?? 0);
                                            $ticketGross += $qty * ((float) ($t['unit_price'] ?? 0) + (float) ($t['commission_per_ticket'] ?? 0));
                                            $ticketComm += $qty * (float) ($t['commission_per_ticket'] ?? 0);
                                        }
                                        $set('gross_amount', number_format($ticketGross, 2, '.', ''));
                                        $set('commission_amount', number_format($ticketComm, 2, '.', ''));
                                        $set('net_amount', number_format(max(0, $ticketGross - $ticketComm - $eventDiscount - $eventExtras), 2, '.', ''));
                                    } else {
                                        // Remainder payout — no specific tickets, just the balance
                                        // (balance already nets out discount + extras + refunds + previous payouts)
                                        $set('gross_amount', number_format($fin['balance'], 2, '.', ''));
                                        $set('commission_amount', '0.00');
                                        $set('discount_amount', '0.00');
                                        $set('fees_amount', '0.00');
                                        $set('net_amount', number_format($fin['balance'], 2, '.', ''));
                                    }
                                }
                            }
                        } else {
                            $set('gross_amount', '0.00');
                            $set('commission_amount', '0.00');
                            $set('discount_amount', '0.00');
                            $set('fees_amount', '0.00');
                            $set('net_amount', '0.00');
                            $set('payout_tickets', []);
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
                            $html .= '<br><span class="text-xs text-gray-500">Setari eveniment: ' . number_format($effectiveRate, 2) . '% (' . $modeLabel . ')</span>';
                        } else {
                            $orgName = $event->marketplaceOrganizer?->name ?? 'Organizator';
                            $html .= '<br><span class="text-xs text-gray-500">Setari organizator: ' . e($orgName) . ' ' . number_format($effectiveRate, 2) . '% (' . $modeLabel . ')</span>';
                        }

                        // Check if any ticket types have custom commission
                        $customCommTts = $event->ticketTypes->filter(fn ($tt) => $tt->commission_type && $tt->commission_type !== '');
                        if ($customCommTts->isNotEmpty()) {
                            $html .= '<br><span class="text-xs text-amber-600 dark:text-amber-400">⚠ ' . $customCommTts->count() . ' tip(uri) de bilet cu comision personalizat</span>';
                        }

                        return new \Illuminate\Support\HtmlString($html);
                    })
                    ->visible(fn (Get $get) => $get('event_id') !== null),

                Forms\Components\Hidden::make('has_balance')->default(false)->dehydrated(false),
                Forms\Components\Hidden::make('zero_reason')->default(null)->dehydrated(false),

                // Zero balance message
                Forms\Components\Placeholder::make('zero_balance_message')
                    ->hiddenLabel()
                    ->content(function (Get $get) {
                        $reason = $get('zero_reason');
                        if ($reason === 'no_sales') {
                            $icon = '<svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.072 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>';
                            $msg = 'Evenimentul încă nu are vânzări înregistrate. Nu se poate face decont.';
                        } else {
                            $icon = '<svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
                            $msg = 'Sold eveniment 0. Nu se mai pot face deconturi suplimentare.';
                        }
                        return new \Illuminate\Support\HtmlString(
                            '<div class="flex items-center gap-2 p-4 rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-800">' .
                            $icon . '<span class="text-gray-600 dark:text-gray-400">' . $msg . '</span></div>'
                        );
                    })
                    ->visible(fn (Get $get) => $get('event_id') !== null && !$get('has_balance')),

                Forms\Components\Placeholder::make('event_details')
                    ->label('')
                    ->visible(fn (Get $get) => $get('event_id') !== null && $get('has_balance'))
                    ->content(function (Get $get) {
                        $eventId = $get('event_id');
                        if (!$eventId) return '';

                        $event = Event::with(['ticketTypes'])->find($eventId);
                        if (!$event) return '';

                        return new HtmlString($this->renderEventBreakdown($event));
                    })
                    ->visible(fn (Get $get) => $get('event_id') !== null),

                // Refund picker — operator opts in to including refunds in
                // this new decont right from the create modal. Mirrors the
                // edit-page picker (ViewPayout.php) so operators don't
                // need a two-step Create-then-Edit. On submit, the action
                // handler reads `included_refund_ids`, links them via
                // syncIncludedRefunds(), and stores `refund_amount` on
                // the payout. The PDF then renders 1a/2a/E correctly.
                \Filament\Forms\Components\CheckboxList::make('included_refund_ids')
                    ->label('Rambursări incluse în acest decont')
                    ->helperText('Bifează rambursările care intră în decontul curent — valoarea lor se scade din suma de plată și apar în PDF.')
                    ->options(function (Get $get) {
                        $eventId = $get('event_id');
                        if (!$eventId) return [];
                        return \App\Models\MarketplaceRefundRequest::query()
                            ->whereIn('status', [
                                \App\Models\MarketplaceRefundRequest::STATUS_REFUNDED,
                                \App\Models\MarketplaceRefundRequest::STATUS_PARTIALLY_REFUNDED,
                            ])
                            ->whereHas('order', function ($q) use ($eventId) {
                                $q->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId);
                            })
                            ->whereNull('marketplace_payout_id') // unlinked only — already-linked stay on their payout
                            ->orderByDesc('completed_at')
                            ->get(['id', 'reference', 'approved_amount', 'completed_at'])
                            ->mapWithKeys(fn ($r) => [
                                $r->id => sprintf(
                                    '%s · %s RON · %s',
                                    $r->reference,
                                    number_format((float) $r->approved_amount, 2),
                                    optional($r->completed_at)->format('d.m.Y') ?: '—'
                                ),
                            ])
                            ->all();
                    })
                    ->live()
                    ->columns(1)
                    ->bulkToggleable()
                    ->columnSpanFull()
                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                        // Recompute net_amount to reflect refund deduction in
                        // real time so the operator sees the final payable
                        // before submit. Falls back to the event balance
                        // when no tickets are selected.
                        $event = Event::find($get('event_id'));
                        if (!$event) return;
                        $fin = self::calculateEventFinancials($event);
                        $populated = $get('payout_tickets') ?? [];
                        $hasTickets = collect($populated)->sum(fn ($t) => (int) ($t['qty'] ?? 0)) > 0;

                        $baseTicketNet = 0.0;
                        if ($hasTickets) {
                            foreach ($populated as $t) {
                                $qty = (int) ($t['qty'] ?? 0);
                                if ($qty <= 0) continue;
                                $unit = (float) ($t['unit_price'] ?? 0);
                                $commPer = (float) ($t['commission_per_ticket'] ?? 0);
                                $rowMode = $t['commission_mode'] ?? 'included';
                                $isOnTop = in_array($rowMode, ['added_on_top', 'on_top'], true);
                                $baseTicketNet += $qty * $unit + ($isOnTop ? $qty * $commPer : 0) - $qty * $commPer;
                            }
                            $baseTicketNet -= (float) ($get('discount_amount') ?? 0);
                        } else {
                            $baseTicketNet = (float) ($fin['balance'] ?? 0);
                        }

                        $refundIds = is_array($state) ? array_values(array_filter($state)) : [];
                        $refundTotal = 0.0;
                        if (!empty($refundIds)) {
                            $refundTotal = (float) \App\Models\MarketplaceRefundItem::query()
                                ->whereIn('refund_request_id', $refundIds)
                                ->where('status', 'refunded')
                                ->sum('face_value');
                        }

                        $set('net_amount', number_format(max(0, $baseTicketNet - $refundTotal), 2, '.', ''));
                    })
                    ->visible(function (Get $get) {
                        $eventId = $get('event_id');
                        if (!$eventId) return false;
                        return \App\Models\MarketplaceRefundRequest::query()
                            ->whereIn('status', [
                                \App\Models\MarketplaceRefundRequest::STATUS_REFUNDED,
                                \App\Models\MarketplaceRefundRequest::STATUS_PARTIALLY_REFUNDED,
                            ])
                            ->whereHas('order', function ($q) use ($eventId) {
                                $q->where('event_id', $eventId)->orWhere('marketplace_event_id', $eventId);
                            })
                            ->whereNull('marketplace_payout_id')
                            ->exists();
                    }),

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

                // Quick payout amount — auto-distributes tickets
                \Filament\Schemas\Components\Grid::make(3)->schema([
                    Forms\Components\TextInput::make('desired_net_amount')
                        ->label('Cât vrei să decontezi?')
                        ->helperText(function (Get $get) {
                            $eventId = $get('event_id');
                            if (!$eventId) return 'Introdu suma netă dorită, apoi apasă pe Distribuie automat.';
                            $event = Event::with(['marketplaceOrganizer'])->find($eventId);
                            if (!$event) return 'Introdu suma netă dorită, apoi apasă pe Distribuie automat.';
                            $balance = self::calculateEventFinancials($event)['balance'];
                            $fmt = number_format($balance, 2, ',', '.');
                            return "Pentru un decont integral ({$fmt} RON), nu introduce nimic aici — biletele și sumele sunt deja pre-completate, doar trimite formul. Completează acest câmp DOAR pentru un decont parțial.";
                        })
                        ->numeric()
                        ->minValue(0)
                        ->suffix('RON')
                        ->columnSpan(2)
                        ->visible(fn (Get $get) => $get('event_id') !== null)
                        ->dehydrated(false)
                        ->maxValue(function (Get $get) {
                            $eventId = $get('event_id');
                            if (!$eventId) return 0;
                            $event = Event::with(['marketplaceOrganizer', 'ticketTypes'])->find($eventId);
                            if (!$event) return 0;
                            return self::calculateEventFinancials($event)['balance'];
                        }),
                    \Filament\Schemas\Components\Actions::make([
                        \Filament\Actions\Action::make('auto_distribute')
                            ->label('Distribuie automat')
                            ->icon('heroicon-o-sparkles')
                            ->color('primary')
                            ->size('sm')
                            ->action(function (Get $get, Set $set) {
                                $desiredNet = (float) ($get('desired_net_amount') ?? 0);
                                if ($desiredNet <= 0) return;

                                $tickets = $get('payout_tickets') ?? [];
                                if (empty($tickets)) return;

                                // Check against balance
                                $eventId = $get('event_id');
                                if ($eventId) {
                                    $event = Event::with(['marketplaceOrganizer', 'ticketTypes'])->find($eventId);
                                    if ($event) {
                                        $maxBalance = self::calculateEventFinancials($event)['balance'];
                                        $desiredNet = min($desiredNet, $maxBalance);
                                    }
                                }

                                // Sort by net_per_ticket descending (fill with most expensive first)
                                $indexed = [];
                                foreach ($tickets as $key => $item) {
                                    $indexed[] = [
                                        'key' => $key,
                                        'net_per_ticket' => (float) ($item['unit_price'] ?? 0),
                                        'available' => (int) ($item['available'] ?? 0),
                                        'unit_price' => (float) ($item['unit_price'] ?? 0),
                                        'commission_per_ticket' => (float) ($item['commission_per_ticket'] ?? 0),
                                    ];
                                }
                                usort($indexed, fn ($a, $b) => $b['net_per_ticket'] <=> $a['net_per_ticket']);

                                // Greedy: assign tickets starting from most expensive
                                $remaining = $desiredNet;
                                $allocation = array_fill_keys(array_column($indexed, 'key'), 0);

                                foreach ($indexed as $item) {
                                    if ($remaining <= 0) break;
                                    $netPerTicket = $item['net_per_ticket'];
                                    if ($netPerTicket <= 0) continue;

                                    $maxQty = min(
                                        $item['available'],
                                        (int) floor($remaining / $netPerTicket)
                                    );
                                    $allocation[$item['key']] = $maxQty;
                                    $remaining -= $maxQty * $netPerTicket;
                                }

                                // If remaining > 0 and we can fit one more cheap ticket
                                if ($remaining > 0) {
                                    foreach (array_reverse($indexed) as $item) {
                                        $netPerTicket = $item['net_per_ticket'];
                                        $currentQty = $allocation[$item['key']];
                                        if ($netPerTicket > 0 && $netPerTicket <= $remaining && $currentQty < $item['available']) {
                                            $allocation[$item['key']]++;
                                            $remaining -= $netPerTicket;
                                            break;
                                        }
                                    }
                                }

                                // Apply allocation to repeater
                                $updatedTickets = $tickets;
                                foreach ($updatedTickets as $key => &$item) {
                                    $item['qty'] = $allocation[$key] ?? 0;
                                }
                                unset($item);
                                $set('payout_tickets', $updatedTickets);

                                // Recalculate amounts
                                $gross = 0;
                                $commission = 0;
                                foreach ($updatedTickets as $item) {
                                    $qty = (int) ($item['qty'] ?? 0);
                                    $unitPrice = (float) ($item['unit_price'] ?? 0);
                                    $commPerTicket = (float) ($item['commission_per_ticket'] ?? 0);
                                    $gross += $qty * ($unitPrice + $commPerTicket);
                                    $commission += $qty * $commPerTicket;
                                }
                                $discount = (float) ($get('discount_amount') ?? 0);
                                $fees = (float) ($get('fees_amount') ?? 0);
                                $set('gross_amount', number_format($gross, 2, '.', ''));
                                $set('commission_amount', number_format($commission, 2, '.', ''));
                                $set('net_amount', number_format(max(0, $gross - $commission - $discount - $fees), 2, '.', ''));

                                \Filament\Notifications\Notification::make()
                                    ->title('Bilete distribuite automat')
                                    ->body('Suma netă: ' . number_format($gross - $commission - $discount - $fees, 2) . ' RON din ' . number_format($desiredNet, 2) . ' RON solicitate')
                                    ->success()
                                    ->send();
                            }),

                        // Snapshot the event state AS OF a chosen date:
                        // tickets with order.created_at <= cutoff + refunds
                        // with refund_request.created_at <= cutoff (none of
                        // them already in another active decont). Use case:
                        // back-filling a decont generated offline at a past
                        // date — aligned with the "Data creării" override.
                        \Filament\Actions\Action::make('fill_tickets_as_of_date')
                            ->label('Calculează la o dată')
                            ->icon('heroicon-o-clock')
                            ->color('gray')
                            ->size('sm')
                            ->modalHeading('Calculează decontul la o dată din trecut')
                            ->modalDescription('Repeater-ul de bilete + lista de rambursări se vor înlocui cu starea evenimentului la data aleasă (excluzând ce e deja în alte deconturi). Util pentru a recrea un decont făcut offline la o dată anterioară.')
                            ->modalSubmitActionLabel('Calculează')
                            ->form([
                                Forms\Components\DatePicker::make('cutoff_date')
                                    ->label('Data limită (inclusiv)')
                                    ->required()
                                    ->default(fn () => now()->format('Y-m-d'))
                                    ->maxDate(now())
                                    ->helperText('Vânzările și rambursările făcute până la sfârșitul acestei zile vor fi incluse.'),
                            ])
                            ->action(function (array $data, Get $get, Set $set) {
                                $eventId = $get('event_id');
                                if (!$eventId) {
                                    \Filament\Notifications\Notification::make()->title('Alege întâi un eveniment')->warning()->send();
                                    return;
                                }
                                $event = Event::with(['marketplaceOrganizer', 'ticketTypes'])->find($eventId);
                                if (!$event) {
                                    \Filament\Notifications\Notification::make()->title('Evenimentul nu a fost găsit')->danger()->send();
                                    return;
                                }
                                $cutoff = \Carbon\Carbon::parse($data['cutoff_date']);
                                $items = MarketplacePayout::buildRemainingTicketsItems($event, null, $cutoff);
                                $refundIds = MarketplacePayout::getRefundIdsAsOfDate($event, $cutoff, null);

                                if (empty($items) && empty($refundIds)) {
                                    \Filament\Notifications\Notification::make()
                                        ->title('Nimic la data aleasă')
                                        ->body('Nu există bilete sau rambursări noi până la ' . $cutoff->format('d.m.Y') . ' care să nu fie deja în alt decont.')
                                        ->warning()->send();
                                    return;
                                }

                                $set('payout_tickets', $items);
                                $set('included_refund_ids', $refundIds);
                                // Surface the per-type discount aggregate as the
                                // form's discount_amount so the net subtracts it.
                                $discountTotal = MarketplacePayout::sumDiscountFromItems($items);
                                $set('discount_amount', number_format($discountTotal, 2, '.', ''));

                                // Recompute gross/commission/net from new selection
                                // (mirrors auto_distribute's tail so the totals
                                // section stays in sync without an extra click).
                                $gross = 0;
                                $commission = 0;
                                foreach ($items as $row) {
                                    $qty = (int) ($row['qty'] ?? 0);
                                    $unit = (float) ($row['unit_price'] ?? 0);
                                    $commPer = (float) ($row['commission_per_ticket'] ?? 0);
                                    $isOnTop = in_array($row['commission_mode'] ?? null, ['added_on_top', 'on_top'], true);
                                    $gross += $qty * $unit + ($isOnTop ? $qty * $commPer : 0);
                                    $commission += $qty * $commPer;
                                }
                                $fees = (float) ($get('fees_amount') ?? 0);
                                $set('gross_amount', number_format($gross, 2, '.', ''));
                                $set('commission_amount', number_format($commission, 2, '.', ''));
                                $set('net_amount', number_format(max(0, $gross - $commission - $discountTotal - $fees), 2, '.', ''));

                                $totalQty = array_sum(array_column($items, 'qty'));
                                \Filament\Notifications\Notification::make()
                                    ->title('Stare la ' . $cutoff->format('d.m.Y'))
                                    ->body(count($items) . ' tipuri · ' . $totalQty . ' bilete · ' . count($refundIds) . ' rambursări · ' . number_format($discountTotal, 2) . ' RON discount. Net calculat: ' . number_format(max(0, $gross - $commission - $discountTotal - $fees), 2) . ' RON.')
                                    ->success()
                                    ->send();
                            }),
                    ])->visible(fn (Get $get) => $get('event_id') !== null)
                      ->extraAttributes(['class' => 'flex items-end pb-6 gap-2']),
                ])->visible(fn (Get $get) => $get('event_id') !== null && $get('has_balance')),

                // Ticket selection for partial payout
                Forms\Components\Repeater::make('payout_tickets')
                    ->label('Bilete pentru decont')
                    ->helperText('Selectează câte bilete din fiecare tip incluzi în acest decont. Implicit: toate.')
                    ->schema([
                        Forms\Components\Hidden::make('ticket_type_id'),
                        Forms\Components\Hidden::make('ticket_type_name'),
                        Forms\Components\Hidden::make('available'),
                        Forms\Components\Hidden::make('unit_price'),
                        Forms\Components\Hidden::make('commission_per_ticket'),
                        // Per-row promo discount surfaced by the helper.
                        Forms\Components\Hidden::make('discount')->default('0'),
                        // Per-price tier breakdown — {price, qty}[] expanded by the PDF.
                        Forms\Components\Hidden::make('tiers')->default([]),
                        Forms\Components\Placeholder::make('label')
                            ->hiddenLabel()
                            ->content(fn (Get $get) => new \Illuminate\Support\HtmlString(
                                '<div class="flex items-center h-full py-2">' .
                                '<span class="font-medium">' . e($get('ticket_type_name') ?? '') . '</span>' .
                                '<span class="text-xs text-gray-400 ml-2">' . $get('available') . ' disponibile · ' . number_format((float) ($get('unit_price') ?? 0), 2) . ' RON/bilet · comision ' . number_format((float) ($get('commission_per_ticket') ?? 0), 2) . ' RON/bilet</span>' .
                                '</div>'
                            ))
                            ->columnSpan(2),
                        Forms\Components\TextInput::make('qty')
                            ->hiddenLabel()
                            ->numeric()
                            ->minValue(0)
                            ->suffix('bilete')
                            ->columnSpan(1),
                    ])
                    ->columns(3)
                    ->reorderable(false)
                    ->addable(false)
                    ->deletable(false)
                    ->defaultItems(0)
                    ->visible(fn (Get $get) => $get('event_id') !== null && $get('has_balance'))
                    ->columnSpanFull(),

                // Refund picker — operator chooses which event refunds are
                // accounted for IN this new payout. Each checked refund's
                // amount is deducted from the payout's net (the operator
                // sees this in the form totals after clicking Recalculează).
                // Only unattached refunds appear here — refunds already
                // linked to another payout are filtered out.
                Forms\Components\CheckboxList::make('included_refund_ids')
                    ->label('Rambursări incluse în acest decont')
                    ->helperText('Bifează rambursările care intră în decontul curent. Valoarea lor se scade din suma de plată și apar în documentul PDF.')
                    ->options(function (Get $get) {
                        $eventId = $get('event_id');
                        if (!$eventId) return [];

                        return \App\Models\MarketplaceRefundRequest::query()
                            ->whereIn('status', [
                                \App\Models\MarketplaceRefundRequest::STATUS_REFUNDED,
                                \App\Models\MarketplaceRefundRequest::STATUS_PARTIALLY_REFUNDED,
                            ])
                            ->where(function ($q) use ($eventId) {
                                $q->whereHas('order', function ($q2) use ($eventId) {
                                    $q2->where('event_id', $eventId)
                                        ->orWhere('marketplace_event_id', $eventId);
                                });
                            })
                            ->whereNull('marketplace_payout_id')
                            ->orderByDesc('completed_at')
                            ->get(['id', 'reference', 'approved_amount', 'completed_at'])
                            ->mapWithKeys(fn ($r) => [
                                $r->id => sprintf(
                                    '%s · %s RON · %s',
                                    $r->reference,
                                    number_format((float) $r->approved_amount, 2),
                                    $r->completed_at?->format('d.m.Y') ?? '—'
                                ),
                            ])
                            ->all();
                    })
                    ->columns(1)
                    ->bulkToggleable()
                    ->visible(fn (Get $get) => $get('event_id') !== null && $get('has_balance'))
                    ->columnSpanFull(),

                // Recalculate button
                \Filament\Schemas\Components\Actions::make([
                    \Filament\Actions\Action::make('recalculate_payout')
                        ->label('Recalculează din bilete selectate')
                        ->icon('heroicon-o-calculator')
                        ->color('gray')
                        ->size('sm')
                        ->action(function (Get $get, Set $set) {
                            $tickets = $get('payout_tickets') ?? [];
                            $gross = 0;
                            $commission = 0;

                            foreach ($tickets as $item) {
                                $qty = (int) ($item['qty'] ?? 0);
                                $unitPrice = (float) ($item['unit_price'] ?? 0);
                                $commPerTicket = (float) ($item['commission_per_ticket'] ?? 0);
                                $gross += $qty * ($unitPrice + $commPerTicket);
                                $commission += $qty * $commPerTicket;
                            }

                            $discount = (float) ($get('discount_amount') ?? 0);
                            $fees = (float) ($get('fees_amount') ?? 0);
                            $set('gross_amount', number_format($gross, 2, '.', ''));
                            $set('commission_amount', number_format($commission, 2, '.', ''));
                            $set('net_amount', number_format(max(0, $gross - $commission - $discount - $fees), 2, '.', ''));
                        }),
                    \Filament\Actions\Action::make('reset_payout_tickets')
                        ->label('Resetează la valori inițiale')
                        ->icon('heroicon-o-arrow-path')
                        ->color('danger')
                        ->size('sm')
                        ->requiresConfirmation()
                        ->modalHeading('Resetare bilete')
                        ->modalDescription('Ești sigur? Se vor reseta cantitățile de bilete și sumele la valorile inițiale.')
                        ->action(function (Get $get, Set $set) {
                            $eventId = $get('event_id');
                            if (!$eventId) return;
                            $event = Event::with(['marketplaceOrganizer', 'ticketTypes'])->find($eventId);
                            if (!$event) return;

                            $fin = self::calculateEventFinancials($event);
                            $eventDiscount = (float) ($fin['discount'] ?? 0);
                            $eventExtras = (float) ($fin['extras'] ?? 0);
                            $this->populatePayoutTicketsFromEvent($set, $event, $fin);
                            $set('desired_net_amount', null);
                            $set('discount_amount', number_format($eventDiscount, 2, '.', ''));
                            $set('fees_amount', number_format($eventExtras, 2, '.', ''));

                            $populatedTickets = $get('payout_tickets') ?? [];
                            $hasTickets = collect($populatedTickets)->sum(fn ($t) => (int) ($t['qty'] ?? 0)) > 0;

                            if ($hasTickets) {
                                $ticketGross = 0;
                                $ticketComm = 0;
                                foreach ($populatedTickets as $t) {
                                    $qty = (int) ($t['qty'] ?? 0);
                                    $ticketGross += $qty * ((float) ($t['unit_price'] ?? 0) + (float) ($t['commission_per_ticket'] ?? 0));
                                    $ticketComm += $qty * (float) ($t['commission_per_ticket'] ?? 0);
                                }
                                $set('gross_amount', number_format($ticketGross, 2, '.', ''));
                                $set('commission_amount', number_format($ticketComm, 2, '.', ''));
                                $set('net_amount', number_format(max(0, $ticketGross - $ticketComm - $eventDiscount - $eventExtras), 2, '.', ''));
                            } else {
                                $set('gross_amount', number_format($fin['balance'], 2, '.', ''));
                                $set('commission_amount', '0.00');
                                $set('discount_amount', '0.00');
                                $set('fees_amount', '0.00');
                                $set('net_amount', number_format($fin['balance'], 2, '.', ''));
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Resetat la valori inițiale')
                                ->success()
                                ->send();
                        }),
                ])->visible(fn (Get $get) => $get('event_id') !== null && $get('has_balance')),

                // Financial totals — operator no longer enters or edits these
                // directly; they're computed from the ticket selection (or set
                // by event_id afterStateUpdated for the balance fallback) and
                // persisted with the payout for downstream reports and the PDF.
                // Kept as Hidden so the existing $set(...) callers keep working
                // and the action handler still reads them out of $data.
                Forms\Components\Hidden::make('gross_amount')->default('0.00'),
                Forms\Components\Hidden::make('commission_amount')->default('0.00'),
                Forms\Components\Hidden::make('discount_amount')->default('0.00'),
                Forms\Components\Hidden::make('fees_amount')->default('0.00'),
                Forms\Components\Hidden::make('net_amount')->default('0.00'),

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
                        if (!$get('has_balance')) return false;
                        $organizerId = $get('marketplace_organizer_id');
                        if (!$organizerId) return false;
                        return MarketplaceOrganizerBankAccount::where('marketplace_organizer_id', $organizerId)->count() > 0;
                    }),

                Forms\Components\Textarea::make('admin_notes')
                    ->visible(fn (Get $get) => $get('has_balance'))
                    ->label('Note admin')
                    ->rows(2),

                // Overrides for operators who need fine control: leave the
                // series empty for the auto-generated prefix+counter (from
                // Settings → Personalization), or override created_at when
                // back-filling a decont retroactively. Both visible only when
                // a payout is actually being created (event has balance).
                \Filament\Schemas\Components\Grid::make(2)
                    ->visible(fn (Get $get) => (bool) $get('has_balance'))
                    ->schema([
                        Forms\Components\TextInput::make('decont_series')
                            ->label('Serie decont')
                            ->maxLength(40)
                            ->prefix(function () {
                                $admin = Auth::guard('marketplace_admin')->user();
                                $client = $admin?->marketplaceClient;
                                $settings = $client?->settings ?? [];
                                return $settings['decont_prefix'] ?? 'DEC';
                            })
                            ->placeholder('Auto (folosește contorul din Settings)')
                            ->helperText('Tastează doar partea numerică / sufixul — prefixul de mai sus se adaugă automat. Lasă gol pentru auto-generare cu următorul număr din contor.'),

                        Forms\Components\DateTimePicker::make('created_at_override')
                            ->label('Data creării')
                            // Default Filament minute step (5) + now() (any
                            // minute) → the popover's hidden minute input fails
                            // HTML5 validation on submit ("invalid form control
                            // is not focusable"), silently blocking Creează
                            // decont. Lock step to 1 + zero seconds on default
                            // so any chosen value is valid.
                            ->seconds(false)
                            ->minutesStep(1)
                            ->default(fn () => now()->setTime(now()->hour, now()->minute, 0))
                            ->helperText('Influențează slicing-ul pentru deconturile viitoare ale evenimentului — păstrează data actuală dacă nu ai un motiv anume să o ajustezi.'),
                    ]),
            ])
            ->modalSubmitActionLabel('Creează decont')
            ->modalCancelActionLabel('Anulează')
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

                // ticket_breakdown is now AUTHORITATIVELY built from what the
                // operator selected in the "Bilete pentru decont" repeater —
                // not from a service-computed slice of the event. The previous
                // code overrode any operator selection with the entire event's
                // tickets, so the saved breakdown never matched the entered
                // amount/gross/commission (the source of all the "Detalii
                // bilete shows 44,000 but amount is 24,480" confusion).
                //
                // If the operator entered a net_amount that differs from the
                // repeater's natural sum (e.g. wanted to decont a custom amount
                // without manually rebalancing every row), qtys are scaled
                // proportionally so the breakdown matches the entered net.
                $organizerId = (int) $data['marketplace_organizer_id'];
                $periodStart = $event
                    ? MarketplacePayout::resolveNextPeriodStart($event->id, $organizerId, $event)
                    : null;
                $periodEnd = now();

                $payoutTicketsInput = $data['payout_tickets'] ?? [];
                $enteredNet = (float) ($data['net_amount'] ?? 0);
                $includedRefundIds = is_array($data['included_refund_ids'] ?? null) ? $data['included_refund_ids'] : [];

                // Sum refund face_value for the selected refunds. The
                // entered net is the FINAL amount paid to the organizer,
                // so the ticket-only target before refund deduction is
                // enteredNet + refund_total. buildBreakdownFromSelection
                // scales ticket qtys to that ticket target, then we
                // subtract refund_total to get back to the operator's
                // entered net.
                $refundTotal = !empty($includedRefundIds)
                    ? (float) \App\Models\MarketplaceRefundItem::query()
                        ->whereIn('refund_request_id', $includedRefundIds)
                        ->where('status', 'refunded')
                        ->sum('face_value')
                    : 0.0;

                if ($event && !empty($payoutTicketsInput)) {
                    // The repeater qty selection is the operator's source of truth
                    // on submit. Proportional scaling is performed up-front by the
                    // explicit "Distribuie automat" / "Distribuie proporțional"
                    // buttons (which write scaled qtys back into the repeater).
                    // Without `null` here the handler would re-scale to match
                    // net_amount — which event_id loads to the FULL event net —
                    // even when the operator reduced qtys manually (e.g. setting
                    // most types to 0 and one to 50 would balloon back to 100+
                    // chasing the event's full balance).
                    $built = MarketplacePayout::buildBreakdownFromSelection(
                        $payoutTicketsInput,
                        $event,
                        null
                    );
                    $ticketBreakdown = $built['rows'];
                    $finalGross = $built['totals']['gross'];
                    $finalCommission = $built['totals']['commission'];
                    // totals['net'] already excludes the per-row promo discount
                    // (buildBreakdownFromSelection threads each item's discount
                    // through every pass + into the saved rows). Just peel off
                    // the refund total to arrive at the actual amount due.
                    $ticketNet = $built['totals']['net'];
                    $discountAmount = (float) ($built['totals']['discount'] ?? 0);
                    $finalNet = round($ticketNet - $refundTotal, 2);
                    $commissionMode = $built['commission_mode'];
                } else {
                    // Refund-only or zero-selection payout: persist the manually
                    // entered amounts without a breakdown.
                    $ticketBreakdown = [];
                    $finalGross = (float) ($data['gross_amount'] ?? 0);
                    $finalCommission = (float) ($data['commission_amount'] ?? 0);
                    $discountAmount = (float) ($data['discount_amount'] ?? 0);
                    $finalNet = $enteredNet;
                    $commissionMode = $event?->getEffectiveCommissionMode() ?: 'included';
                }

                // Optional operator-provided overrides — leave series empty to
                // let MarketplacePayout::assignDecontSeries auto-generate from
                // settings. The input shows the marketplace's configured prefix
                // as a visual prefix; the operator types only the suffix, which
                // we prepend with the prefix here so the stored value is the
                // full series (e.g. "DECAMB47").
                $customSuffix = trim((string) ($data['decont_series'] ?? ''));
                if ($customSuffix !== '') {
                    $client = $marketplaceAdmin->marketplaceClient;
                    $settings = $client?->settings ?? [];
                    $prefix = $settings['decont_prefix'] ?? 'DEC';
                    $customSeries = $prefix . $customSuffix;
                } else {
                    $customSeries = '';
                }
                $createdAtOverride = !empty($data['created_at_override'])
                    ? \Carbon\Carbon::parse($data['created_at_override'])
                    : null;

                // When the operator back-dates the decont, the period_end of
                // its sales slice must match the back-date too — otherwise the
                // displayed period reads "01.01 → today" instead of
                // "01.01 → official decont date".
                if ($createdAtOverride) {
                    $periodEnd = $createdAtOverride;
                }

                $payout = MarketplacePayout::create([
                    'marketplace_client_id' => $marketplaceAdmin->marketplace_client_id,
                    'marketplace_organizer_id' => $data['marketplace_organizer_id'],
                    'event_id' => $data['event_id'],
                    'amount' => round($finalNet, 2),
                    'currency' => 'RON',
                    // Save the SAME bounds we used for the slice so the
                    // displayed "period" and the actual snapshot agree.
                    'period_start' => $periodStart?->toDateString() ?? $event?->created_at?->toDateString(),
                    'period_end' => $periodEnd?->toDateString() ?? now()->toDateString(),
                    'gross_amount' => round($finalGross, 2),
                    'commission_amount' => round($finalCommission, 2),
                    'discount_amount' => $discountAmount,
                    'refund_amount' => round($refundTotal, 2),
                    'fees_amount' => (float) ($data['fees_amount'] ?? 0),
                    'adjustments_amount' => 0,
                    'status' => 'approved',
                    'source' => 'manual',
                    'approved_by' => $marketplaceAdmin->id,
                    'approved_at' => now(),
                    'payout_method' => $payoutMethod,
                    'ticket_breakdown' => !empty($ticketBreakdown) ? $ticketBreakdown : null,
                    'admin_notes' => $data['admin_notes'] ?? null,
                    'commission_mode' => $commissionMode,
                    'invoice_recipient_type' => $commissionMode === 'added_on_top' ? 'general_client' : 'organizer',
                    // When set, the boot hook's assignDecontSeries() short-
                    // circuits and the counter isn't consumed; when empty it
                    // auto-generates with a locked-row counter increment.
                    'decont_series' => $customSeries !== '' ? $customSeries : null,
                ]);

                // Override created_at when the operator picked a custom date.
                // Eloquent's performInsert always stamps `now()`, so we rewrite
                // it after the fact. Direct attribute assignment + saveQuietly
                // because created_at isn't in $fillable, so updateQuietly would
                // silently drop it. approved_at stays at the real now() so the
                // Cronologie section keeps a true audit trail of who/when —
                // see the "Creat de admin" entry's state() callback.
                if ($createdAtOverride && abs($createdAtOverride->diffInSeconds(now())) > 60) {
                    $payout->created_at = $createdAtOverride;
                    $payout->saveQuietly();
                }

                // Link refunds to this payout AFTER it's created so the FK
                // points at a real id. syncIncludedRefunds also recomputes
                // refund_amount from the actual linked rows (defensive — in
                // case some refund_id became stale between option-render
                // and submit).
                if (!empty($includedRefundIds)) {
                    $payout->syncIncludedRefunds($includedRefundIds);
                }

                // Generate decont document immediately based on commission mode
                try {
                    $templateType = $commissionMode === 'added_on_top' ? 'decont_ontop' : 'decont_inclus';

                    // Try specific template first, fall back to generic 'decont'
                    $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplaceAdmin->marketplace_client_id)
                        ->where('type', $templateType)
                        ->where('is_active', true)
                        ->first();

                    if (!$template) {
                        $template = MarketplaceTaxTemplate::where('marketplace_client_id', $marketplaceAdmin->marketplace_client_id)
                            ->where('type', 'decont')
                            ->where('is_active', true)
                            ->first();
                    }

                    if ($template) {
                        $marketplace = \App\Models\MarketplaceClient::find($marketplaceAdmin->marketplace_client_id);
                        $organizer = \App\Models\MarketplaceOrganizer::find($data['marketplace_organizer_id']);
                        $taxRegistry = $event ? \App\Models\MarketplaceTaxRegistry::where('marketplace_client_id', $marketplace->id)
                            ->where(function ($q) use ($event) {
                                $venue = $event->venue;
                                if ($venue?->county) $q->where('county', $venue->county);
                                if ($venue?->city) $q->orWhere('city', $venue->city);
                            })->first() : null;

                        $variables = $template->getVariablesForContext(
                            taxRegistry: $taxRegistry,
                            marketplace: $marketplace,
                            organizer: $organizer,
                            event: $event,
                            payout: $payout,
                            template: $template,
                        );

                        $htmlContent = $template->processTemplate($variables);
                        if (!str_contains($htmlContent, '<html')) {
                            $htmlContent = '<html><head><meta charset="UTF-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;}</style></head><body>' . $htmlContent . '</body></html>';
                        }

                        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($htmlContent);
                        $pdf->setPaper('A4', $template->page_orientation ?? 'portrait');
                        $pdfContent = $pdf->output();

                        $fileName = 'decont_' . $payout->reference . '_' . now()->format('Ymd_His') . '.pdf';
                        $filePath = "organizer-documents/{$organizer->id}/{$fileName}";
                        \Illuminate\Support\Facades\Storage::disk('public')->put($filePath, $pdfContent);

                        \App\Models\OrganizerDocument::create([
                            'marketplace_client_id' => $marketplace->id,
                            'marketplace_organizer_id' => $organizer->id,
                            'event_id' => $payout->event_id,
                            'marketplace_payout_id' => $payout->id,
                            'tax_template_id' => $template->id,
                            'title' => 'Decont ' . $payout->reference,
                            'document_type' => 'decont',
                            'file_path' => $filePath,
                            'file_name' => $fileName,
                            'file_size' => strlen($pdfContent),
                            'html_content' => $htmlContent,
                            'document_data' => [
                                'payout_reference' => $payout->reference,
                                'payout_amount' => $payout->amount,
                                'commission_mode' => $commissionMode,
                                'template_type' => $templateType,
                                'template_name' => $template->name,
                            ],
                            'issued_at' => now(),
                        ]);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::warning('Decont document generation failed: ' . $e->getMessage());
                }

                \Filament\Notifications\Notification::make()
                    ->title('Decont creat')
                    ->body("Decontul {$payout->reference} a fost creat cu succes.")
                    ->success()
                    ->send();

                // Redirect to payout view page
                $this->redirect(\App\Filament\Marketplace\Resources\PayoutResource::getUrl('view', ['record' => $payout]));
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

        // Only real finished events: event_date in the past. The previous
        // OR fallback included drafts with NULL event_date that were >3
        // months old, which (combined with PG's NULLS-FIRST default for
        // DESC) pushed ancient stale rows to the top of "Ultimele 10".
        $query = Event::where('marketplace_client_id', $marketplaceClientId)
            ->whereNotNull('marketplace_organizer_id')
            ->whereNotNull('event_date')
            ->where('event_date', '<', now())
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

        // One-shot refund aggregate per event so we don't N+1 the modal.
        // Counts and sums refund requests that actually paid out
        // (refunded / partially_refunded). Joins on either event_id or
        // marketplace_event_id since orders use either column depending
        // on origin.
        $eventIdsList = $events->pluck('id')->all();
        $refundsByEvent = collect();
        if (!empty($eventIdsList)) {
            $refundsByEvent = \DB::table('marketplace_refund_requests as rr')
                ->join('orders as o', 'rr.order_id', '=', 'o.id')
                ->whereIn(\DB::raw('COALESCE(o.event_id, o.marketplace_event_id)'), $eventIdsList)
                ->whereIn('rr.status', ['refunded', 'partially_refunded'])
                ->groupBy(\DB::raw('COALESCE(o.event_id, o.marketplace_event_id)'))
                ->select(
                    \DB::raw('COALESCE(o.event_id, o.marketplace_event_id) as event_id'),
                    \DB::raw('COUNT(*) as refund_count'),
                    \DB::raw('SUM(rr.approved_amount) as refund_total')
                )
                ->get()
                ->keyBy('event_id');
        }

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

            $refundInfo = $refundsByEvent->get($event->id);

            $rows[] = [
                'event' => $event,
                'title' => $title,
                'organizer_name' => $organizerName,
                'event_date' => $eventDate,
                'balance' => $balance,
                'existing_payout' => $existingPayout,
                'refund_count' => (int) ($refundInfo?->refund_count ?? 0),
                'refund_total' => (float) ($refundInfo?->refund_total ?? 0),
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

        // Build ticket breakdown using the same logic as the manual flow.
        $event->loadMissing('ticketTypes');
        $items = $this->buildTicketBreakdownForEvent($event);

        // Refund-only case: an event sold tickets that were entirely
        // refunded. The breakdown is empty (no valid/used tickets remain)
        // but the operator still needs a 0-net decont to document the
        // refund history. Detect that here so the empty-items guard
        // doesn't reject the request outright.
        $refundCount = \DB::table('marketplace_refund_requests as rr')
            ->join('orders as o', 'rr.order_id', '=', 'o.id')
            ->where(function ($q) use ($event) {
                $q->where('o.event_id', $event->id)
                    ->orWhere('o.marketplace_event_id', $event->id);
            })
            ->whereIn('rr.status', ['refunded', 'partially_refunded'])
            ->count();

        if (empty($items) && $refundCount === 0) {
            \Filament\Notifications\Notification::make()
                ->title('Sold insuficient')
                ->body('Nu există bilete neîncasate pentru acest eveniment.')
                ->warning()
                ->send();
            return;
        }

        $ttMap = $event->ticketTypes->keyBy('id');
        $ticketBreakdown = collect($items)->map(function ($t) use ($ttMap) {
            $tt = $ttMap->get($t['ticket_type_id'] ?? null);
            return [
                'ticket_type_id' => $t['ticket_type_id'] ?? null,
                'ticket_type_name' => $t['ticket_type_name'] ?? '',
                'qty' => (int) ($t['qty'] ?? 0),
                'unit_price' => (float) ($t['unit_price'] ?? 0),
                'commission_per_ticket' => (float) ($t['commission_per_ticket'] ?? 0),
                'commission_type' => $tt?->commission_type ?? null,
                'commission_rate' => $tt?->commission_rate !== null ? (float) $tt->commission_rate : null,
                'commission_fixed' => $tt?->commission_fixed !== null ? (float) $tt->commission_fixed : null,
                'commission_mode' => $tt?->commission_mode ?? null,
            ];
        })->values()->toArray();

        // Resolve commission_mode from ticket types first (same as manual flow)
        $modesFromTickets = collect($ticketBreakdown)->pluck('commission_mode')->filter()->unique()->values();
        if ($modesFromTickets->count() === 1) {
            $commissionMode = $modesFromTickets->first();
        } elseif ($modesFromTickets->contains('added_on_top')) {
            $commissionMode = 'added_on_top';
        } else {
            $commissionMode = $event->getEffectiveCommissionMode() ?? 'included';
        }

        // Compute gross / commission / net from the breakdown
        $grossAmount = 0;
        $commissionAmount = 0;
        foreach ($ticketBreakdown as $tb) {
            $qty = (int) $tb['qty'];
            $unit = (float) $tb['unit_price'];
            $commPer = (float) $tb['commission_per_ticket'];
            $grossAmount += $qty * $unit;
            $commissionAmount += $qty * $commPer;
        }

        if ($commissionMode === 'added_on_top') {
            // Customer paid commission separately; organizer receives full gross
            $netAmount = $grossAmount;
        } else {
            // Commission is deducted from gross
            $netAmount = $grossAmount - $commissionAmount;
        }

        if ($netAmount <= 0 && $refundCount === 0) {
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
            'amount' => round($netAmount, 2),
            'currency' => 'RON',
            'period_start' => $periodStart,
            'period_end' => now()->toDateString(),
            'gross_amount' => round($grossAmount, 2),
            'commission_amount' => round($commissionAmount, 2),
            'fees_amount' => 0,
            'adjustments_amount' => 0,
            // Triggered by an admin clicking "Generează decont" in the
            // Evenimente încheiate modal — treat it as admin-approved on
            // creation so it doesn't sit in pending waiting for a second
            // click. Source is 'manual' (not 'automated') because a human
            // clicks the button — there is no cron creating payouts here;
            // the auto-decont schedule is disabled in routes/console.php.
            'status' => 'approved',
            'source' => 'manual',
            'approved_by' => $marketplaceAdmin->id,
            'approved_at' => now(),
            'payout_method' => $payoutMethod,
            // Annotate refund-only deconts so anyone looking at the
            // 0-net record later understands why it exists.
            'admin_notes' => empty($items) && $refundCount > 0
                ? "Decont generat din lista evenimente încheiate. Eveniment cu {$refundCount} bilet(e) rambursat(e) integral; net 0."
                : 'Decont generat din lista evenimente încheiate.',
            'ticket_breakdown' => $ticketBreakdown,
            'commission_mode' => $commissionMode,
            'invoice_recipient_type' => $commissionMode === 'added_on_top' ? 'general_client' : 'organizer',
        ]);

        \Filament\Notifications\Notification::make()
            ->title('Decont generat')
            ->body("Decontul {$payout->reference} (" . number_format($netAmount, 2) . " RON) a fost creat.")
            ->success()
            ->send();

        $this->redirect(PayoutResource::getUrl('index'));
    }

    /**
     * Populate payout_tickets repeater with ticket type breakdown from event.
     *
     * Delegates to MarketplacePayout::buildRemainingTicketsItems so the create
     * modal, the "Adu biletele rămase" button on the edit modal, the "Adu la
     * dată" / "Calculează la o dată" buttons, AND this initial event-pick
     * populate all share one source of truth — including the effective unit
     * price (post-promo) and per-TicketType commission_mode. Augments each
     * item with `available` for the UI label that shows max qty.
     */
    protected function populatePayoutTicketsFromEvent(Set $set, Event $event, ?array $financials = null): void
    {
        $items = MarketplacePayout::buildRemainingTicketsItems($event, null, null);
        foreach ($items as &$item) {
            $item['available'] = $item['qty'];
        }
        unset($item);
        $set('payout_tickets', $items);
        // Fill the discount_amount hidden input so the modal's totals already
        // reflect promo-code reductions on initial event pick.
        $discountTotal = MarketplacePayout::sumDiscountFromItems($items);
        $set('discount_amount', number_format($discountTotal, 2, '.', ''));
    }

    /**
     * Same as populatePayoutTicketsFromEvent but returns the items array
     * directly. Used by automated payout generation from the "Evenimente
     * încheiate" modal. Same delegation to keep effective-price + commission
     * logic in one place.
     */
    public function buildTicketBreakdownForEvent(Event $event): array
    {
        // No augmentation needed for the auto-payout path — buildBreakdownFromSelection
        // consumes the items directly and doesn't care about the `available` field.
        return MarketplacePayout::buildRemainingTicketsItems($event, null, null);
    }

    /**
     * Calculate event financials: gross, commission (per-ticket-type aware), net.
     * Single source of truth for all payout calculations.
     */
    public static function calculateEventFinancials(Event $event): array
    {
        $organizer = $event->marketplaceOrganizer;
        if (!$organizer) return ['gross' => 0, 'commission' => 0, 'net' => 0, 'refunds' => 0, 'paid' => 0, 'pending' => 0, 'balance' => 0];

        // Source of truth — same SalesBreakdownService used by the event-edit
        // "Vânzări" tab and the payout-detail "Detalii Bilete" table. Reads
        // actual paid prices per ticket and allocates discounts + extras.
        // POS/test_order excluded — money never flowed through marketplace,
        // commissions are invoiced separately.
        $service = app(\App\Services\Marketplace\SalesBreakdownService::class);
        $breakdown = $service->build($event, null, null, excludePos: true);

        $grossRevenue = 0.0;
        $totalCommission = 0.0;
        $totalDiscount = 0.0;
        $totalExtras = 0.0;
        $netRevenueFromBreakdown = 0.0;
        foreach ($breakdown['per_type'] as $row) {
            $isOnTop = in_array($row['commission_mode'] ?? null, ['added_on_top', 'on_top'], true);
            // Mirror the "Total brut" displayed in the breakdown blade: for
            // added_on_top, the customer paid price*qty + commission.
            $grossRevenue += (float) $row['gross'] + ($isOnTop ? (float) $row['commission_amount'] : 0);
            $totalCommission += (float) $row['commission_amount'];
            $totalDiscount += (float) $row['discount'];
            $totalExtras += (float) $row['extras'];
            $netRevenueFromBreakdown += (float) $row['net'];
        }

        // Refunds (also exclude pos_app for same reason as above). Surfaced
        // as informational only — refunds are NOT automatically subtracted
        // from `balance` or `net` anymore. Operators link refunds
        // explicitly on the payout-detail edit page (via the existing
        // refund-selection UI), and the PDF generator uses
        // payout->refund_amount to render the 1a / 2a / E rows. Auto-
        // subtracting refunds here led to the modal showing 2,899 RON
        // "Sold disponibil" for payouts where the operator expected
        // 3,200 RON — and the generated PDF didn't agree either, because
        // payout->refund_amount stayed at 0 until manually linked.
        $refundedAmount = (float) Order::where(function ($q) use ($event) {
                $q->where('event_id', $event->id)->orWhere('marketplace_event_id', $event->id);
            })
            ->where('status', 'refunded')
            ->where('source', '!=', 'pos_app')
            ->sum(\DB::raw('COALESCE(refund_amount, total)'));

        // Net = breakdown net only. Refunds excluded from balance math but
        // still surfaced via 'refunds' for the modal info line.
        $netRevenue = $netRevenueFromBreakdown;

        // Previous payouts
        $paidPayouts = (float) MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->where('status', 'completed')
            ->sum('amount');

        $pendingPayouts = (float) MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->whereIn('status', ['pending', 'approved', 'processing'])
            ->sum('amount');

        return [
            'gross' => round($grossRevenue, 2),
            'commission' => round($totalCommission, 2),
            'discount' => round($totalDiscount, 2),
            'extras' => round($totalExtras, 2),
            'refunds' => round($refundedAmount, 2),
            'net' => round($netRevenue, 2),
            'paid' => round($paidPayouts, 2),
            'pending' => round($pendingPayouts, 2),
            'balance' => round(max(0, $netRevenue - $paidPayouts - $pendingPayouts), 2),
            // For partial payout: how much gross/commission was already paid
            'paid_gross' => round($paidPayouts > 0 ? $paidPayouts + ($paidPayouts / max(1, $netRevenue) * $totalCommission) : 0, 2),
            'paid_commission' => round($paidPayouts > 0 ? ($paidPayouts / max(1, $netRevenue) * $totalCommission) : 0, 2),
        ];
    }

    /**
     * Calculate available balance for an event (backward compat)
     */
    public static function calculateEventBalance(Event $event): float
    {
        return self::calculateEventFinancials($event)['balance'];
    }

    /**
     * Render detailed event breakdown HTML for the manual decont modal.
     *
     * Uses SalesBreakdownService + calculateEventFinancials as the single
     * source of truth so the numbers shown here match (a) the modal
     * header "Sold disponibil", (b) the event "Vânzări" tab, and (c) the
     * payout detail page. The previous implementation re-derived every
     * value from Order::sum('total') with its own commission math and
     * routinely disagreed with the rest of the system — see the audit
     * thread for the specific bugs (wrong on_top base extraction, double-
     * counting cancelled tickets, missing discount/extras lines, etc.).
     */
    protected function renderEventBreakdown(Event $event): string
    {
        $organizer = $event->marketplaceOrganizer;
        if (!$organizer) return '';

        // Authoritative numbers (same call used by the modal header)
        $financials = self::calculateEventFinancials($event);

        // Per-ticket-type breakdown — same service the Vânzări tab uses,
        // so the per-row figures match what the organizer sees on the
        // event edit page.
        $breakdown = app(\App\Services\Marketplace\SalesBreakdownService::class)
            ->build($event, null, null, excludePos: true);

        $perType = $breakdown['per_type'] ?? [];
        $totalRevenue = (float) ($breakdown['total_revenue'] ?? 0);
        $totalCommission = (float) ($breakdown['total_commission'] ?? 0);
        $totalDiscount = (float) ($breakdown['total_discount'] ?? 0);
        $totalExtras = (float) ($breakdown['total_extras'] ?? 0);
        $totalNet = (float) ($breakdown['total_net'] ?? 0);

        $totalTicketsSold = array_sum(array_map(fn ($r) => (int) ($r['qty'] ?? 0), $perType));
        $hasDiscountAny = array_sum(array_map(fn ($r) => (float) ($r['discount'] ?? 0), $perType)) > 0.01;
        $hasExtrasAny = array_sum(array_map(fn ($r) => (float) ($r['extras'] ?? 0), $perType)) > 0.01;

        $totalRefundedAmount = (float) $financials['refunds'];
        $totalRefundedCount = (int) Order::where(function ($q) use ($event) {
                $q->where('event_id', $event->id)->orWhere('marketplace_event_id', $event->id);
            })
            ->where('status', 'refunded')
            ->where('source', '!=', 'pos_app')
            ->count();

        // Previous payouts for this event (status badges + list)
        $previousPayouts = \App\Models\MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
            ->where('event_id', $event->id)
            ->whereIn('status', ['completed', 'processing', 'approved', 'pending'])
            ->orderBy('created_at', 'desc')
            ->get();
        $totalPreviouslyPaid = (float) $financials['paid'];
        $totalPreviousPending = (float) $financials['pending'];

        $modeLabelFor = function (?string $mode): string {
            return in_array($mode, ['added_on_top', 'on_top'], true) ? 'on top' : 'inclus';
        };
        $commissionLabelFor = function (array $row) use ($modeLabelFor): string {
            $modeLabel = $modeLabelFor($row['commission_mode'] ?? null);
            $type = $row['commission_type'] ?? null;
            $rate = (float) ($row['commission_rate'] ?? 0);
            $fixed = (float) ($row['commission_fixed'] ?? 0);
            if (!$type) {
                $perTicket = (float) ($row['commission_per_ticket'] ?? 0);
                return number_format($perTicket, 2) . ' RON/bilet ' . $modeLabel;
            }
            return match ($type) {
                'percentage' => number_format($rate, 2) . '% ' . $modeLabel,
                'fixed' => number_format($fixed, 2) . ' RON/bilet ' . $modeLabel,
                'both' => number_format($rate, 2) . '% + ' . number_format($fixed, 2) . ' RON/bilet ' . $modeLabel,
                default => number_format($rate, 2) . '% ' . $modeLabel,
            };
        };

        // === BUILD HTML ===
        $html = '<div class="border border-gray-200 dark:border-white/10 rounded-lg overflow-hidden text-sm">';

        if (!empty($perType)) {
            $html .= '<table class="w-full">';
            $html .= '<thead><tr class="bg-gray-50 dark:bg-white/5 border-b border-gray-200 dark:border-white/10">';
            $html .= '<th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-400">Tip bilet</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Preț bilet</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Qty</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Total brut</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Comision</th>';
            if ($hasDiscountAny) {
                $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Discount</th>';
            }
            if ($hasExtrasAny) {
                $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Extras</th>';
            }
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Net</th>';
            $html .= '</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/5">';

            foreach ($perType as $row) {
                $name = (string) ($row['ticket_type_name'] ?? 'Bilet');
                $price = (float) ($row['price'] ?? 0);
                $qty = (int) ($row['qty'] ?? 0);
                $gross = (float) ($row['gross'] ?? 0);
                $commAmt = (float) ($row['commission_amount'] ?? 0);
                $discAmt = (float) ($row['discount'] ?? 0);
                $extrAmt = (float) ($row['extras'] ?? 0);
                $netAmt = (float) ($row['net'] ?? 0);

                $html .= '<tr>';
                $html .= '<td class="px-3 py-1.5 text-gray-900 dark:text-white">' . e($name) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-600 dark:text-gray-400">' . number_format($price, 2) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-600 dark:text-gray-400">' . $qty . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-900 dark:text-white">' . number_format($gross, 2) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right text-gray-500 dark:text-gray-400">';
                $html .= '<span class="font-mono">' . number_format($commAmt, 2) . '</span>';
                $html .= '<br><span class="text-xs text-gray-400">' . e($commissionLabelFor($row)) . '</span>';
                $html .= '</td>';
                if ($hasDiscountAny) {
                    $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-500 dark:text-gray-400">' . ($discAmt > 0 ? '-' . number_format($discAmt, 2) : '–') . '</td>';
                }
                if ($hasExtrasAny) {
                    $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-500 dark:text-gray-400">' . ($extrAmt > 0 ? '-' . number_format($extrAmt, 2) : '–') . '</td>';
                }
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-emerald-700 dark:text-emerald-400 font-medium">' . number_format($netAmt, 2) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        // Summary block — same numbers as header + Vânzări tab + payout
        // detail page. Order mirrors the math: revenue − commission −
        // discount − extras − refunds = net brut; minus deconturi
        // anterioare = sold disponibil.
        $html .= '<div class="border-t border-gray-200 dark:border-white/10 px-3 py-2 space-y-1 bg-gray-50 dark:bg-white/5">';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total bilete vândute:</span><span class="font-medium">' . number_format($totalTicketsSold) . '</span></div>';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Venituri totale (brut):</span><span class="font-mono font-medium">' . number_format($totalRevenue, 2) . ' RON</span></div>';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Comision platformă:</span><span class="font-mono font-medium text-amber-600 dark:text-amber-400">-' . number_format($totalCommission, 2) . ' RON</span></div>';
        if ($totalDiscount > 0) {
            $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Discounturi aplicate:</span><span class="font-mono font-medium text-violet-600 dark:text-violet-400">-' . number_format($totalDiscount, 2) . ' RON</span></div>';
        }
        if ($totalExtras > 0) {
            $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Taxe / Asigurări (extras):</span><span class="font-mono font-medium text-sky-600 dark:text-sky-400">-' . number_format($totalExtras, 2) . ' RON</span></div>';
        }
        if ($totalRefundedAmount > 0) {
            $html .= '<div class="flex justify-between text-red-600 dark:text-red-400">'
                . '<span>Retururi disponibile (' . $totalRefundedCount . ' comenzi):</span>'
                . '<span class="font-mono font-medium">' . number_format($totalRefundedAmount, 2) . ' RON</span>'
                . '</div>'
                . '<div class="text-xs text-gray-400 italic -mt-0.5">Bifează mai jos cele pe care vrei să le incluzi în acest decont — Sold disponibil se recalculează live.</div>';
        }
        $html .= '<div class="flex justify-between pt-1 border-t border-gray-200 dark:border-white/10"><span class="text-gray-700 dark:text-gray-300 font-medium">Net (brut organizator):</span><span class="font-mono font-medium">' . number_format($totalNet, 2) . ' RON</span></div>';

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
                $refLabel = $pp->reference ? '<span class="font-mono text-gray-400">' . e($pp->reference) . '</span> · ' : '';
                $html .= '<span class="text-gray-500">' . $refLabel . $pp->created_at->format('d.m.Y') . ' ' . $statusBadge . '</span>';
                $html .= '<span class="font-mono font-medium text-gray-700 dark:text-gray-300">' . number_format($pp->amount, 2) . ' RON</span>';
                $html .= '</div>';
            }
            if ($totalPreviouslyPaid > 0) {
                $html .= '<div class="flex justify-between text-xs font-semibold mt-1 pt-1 border-t border-gray-100 dark:border-white/5"><span>Total achitat anterior:</span><span class="font-mono">-' . number_format($totalPreviouslyPaid, 2) . ' RON</span></div>';
            }
            if ($totalPreviousPending > 0) {
                $html .= '<div class="flex justify-between text-xs font-semibold"><span>În curs de plată:</span><span class="font-mono">-' . number_format($totalPreviousPending, 2) . ' RON</span></div>';
            }
            $html .= '</div>';
        }

        // Final balance — identical to the modal header (both call
        // calculateEventFinancials()['balance']).
        $html .= '<div class="flex justify-between pt-1 border-t border-gray-200 dark:border-white/10 font-semibold">';
        $html .= '<span>Sold disponibil</span>';
        $html .= '<span class="font-mono text-emerald-600 dark:text-emerald-400">' . number_format($financials['balance'], 2) . ' RON</span>';
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
