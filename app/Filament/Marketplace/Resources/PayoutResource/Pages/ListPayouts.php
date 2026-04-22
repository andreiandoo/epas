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

                        $now = now()->toDateString();

                        // Split into live (upcoming) and ended (past)
                        $live = $events->filter(fn ($e) => $e->event_date && $e->event_date >= $now)->sortBy('event_date');
                        $ended = $events->filter(fn ($e) => $e->event_date && $e->event_date < $now)->sortByDesc('event_date');
                        $noDate = $events->filter(fn ($e) => !$e->event_date);

                        return $live->concat($ended)->concat($noDate)
                            ->mapWithKeys(function ($event) use ($now) {
                                $title = is_array($event->title)
                                    ? ($event->title['ro'] ?? $event->title['en'] ?? array_values($event->title)[0] ?? 'Untitled')
                                    : ($event->title ?? 'Untitled');
                                $status = (!$event->event_date) ? '⚪ TBD' : ($event->event_date >= $now ? '🟢 Live' : '🔴 Încheiat');
                                $date = $event->event_date?->format('d.m.Y') ?? '';
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
                                $set('fees_amount', '0.00');

                                if ($fin['balance'] <= 0) {
                                    // Distinguish: no sales vs fully paid
                                    $set('has_balance', false);
                                    $set('zero_reason', $fin['gross'] > 0 ? 'fully_paid' : 'no_sales');
                                    $set('payout_tickets', []);
                                    $set('gross_amount', '0.00');
                                    $set('commission_amount', '0.00');
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
                                        $set('net_amount', number_format(max(0, $ticketGross - $ticketComm), 2, '.', ''));
                                    } else {
                                        // Remainder payout — no specific tickets, just the balance
                                        $set('gross_amount', number_format($fin['balance'], 2, '.', ''));
                                        $set('commission_amount', '0.00');
                                        $set('net_amount', number_format($fin['balance'], 2, '.', ''));
                                    }
                                }
                            }
                        } else {
                            $set('gross_amount', '0.00');
                            $set('commission_amount', '0.00');
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
                        ->helperText('Introdu suma netă dorită, apoi apasă pe Distribuie automat.')
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
                                $fees = (float) ($get('fees_amount') ?? 0);
                                $set('gross_amount', number_format($gross, 2, '.', ''));
                                $set('commission_amount', number_format($commission, 2, '.', ''));
                                $set('net_amount', number_format(max(0, $gross - $commission - $fees), 2, '.', ''));

                                \Filament\Notifications\Notification::make()
                                    ->title('Bilete distribuite automat')
                                    ->body('Suma netă: ' . number_format($gross - $commission - $fees, 2) . ' RON din ' . number_format($desiredNet, 2) . ' RON solicitate')
                                    ->success()
                                    ->send();
                            })
                    ])->visible(fn (Get $get) => $get('event_id') !== null)
                      ->extraAttributes(['class' => 'flex items-end pb-6']),
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

                            $fees = (float) ($get('fees_amount') ?? 0);
                            $set('gross_amount', number_format($gross, 2, '.', ''));
                            $set('commission_amount', number_format($commission, 2, '.', ''));
                            $set('net_amount', number_format(max(0, $gross - $commission - $fees), 2, '.', ''));
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
                            $this->populatePayoutTicketsFromEvent($set, $event, $fin);
                            $set('desired_net_amount', null);

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
                                $set('net_amount', number_format(max(0, $ticketGross - $ticketComm), 2, '.', ''));
                            } else {
                                $set('gross_amount', number_format($fin['balance'], 2, '.', ''));
                                $set('commission_amount', '0.00');
                                $set('net_amount', number_format($fin['balance'], 2, '.', ''));
                            }

                            \Filament\Notifications\Notification::make()
                                ->title('Resetat la valori inițiale')
                                ->success()
                                ->send();
                        }),
                ])->visible(fn (Get $get) => $get('event_id') !== null && $get('has_balance')),

                \Filament\Schemas\Components\Grid::make(2)->schema([
                    Forms\Components\TextInput::make('gross_amount')
                        ->label('Suma brută')
                        ->numeric()
                        ->required(fn (Get $get) => (bool) $get('has_balance'))
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
                        ->required(fn (Get $get) => (bool) $get('has_balance'))
                        ->suffix('RON')
                        ->readOnly(),
                ])->visible(fn (Get $get) => $get('has_balance')),

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
                // Commission mode resolution priority:
                // 1. Ticket type level (most specific) — derived from selected tickets in this payout
                // 2. Event > Organizer > Marketplace fallback
                $commissionMode = null;

                // Build ticket breakdown from form data
                // Build ticket breakdown with full commission details
                $eventForBreakdown = Event::with('ticketTypes')->find($data['event_id']);
                $ttMap = $eventForBreakdown ? $eventForBreakdown->ticketTypes->keyBy('id') : collect();

                $ticketBreakdown = collect($data['payout_tickets'] ?? [])->filter(fn ($t) => ($t['qty'] ?? 0) > 0)->map(function ($t) use ($ttMap) {
                    $tt = $ttMap->get($t['ticket_type_id'] ?? null);
                    return [
                        'ticket_type_id' => $t['ticket_type_id'] ?? null,
                        'ticket_type_name' => $t['ticket_type_name'] ?? '',
                        'qty' => (int) $t['qty'],
                        'unit_price' => (float) ($t['unit_price'] ?? 0),
                        'commission_per_ticket' => (float) ($t['commission_per_ticket'] ?? 0),
                        'commission_type' => $tt?->commission_type ?? null,
                        'commission_rate' => $tt?->commission_rate ? (float) $tt->commission_rate : null,
                        'commission_fixed' => $tt?->commission_fixed ? (float) $tt->commission_fixed : null,
                        'commission_mode' => $tt?->commission_mode ?? null,
                    ];
                })->values()->toArray();

                // Derive commission_mode from ticket breakdown (most specific level)
                $modesFromTickets = collect($ticketBreakdown)
                    ->pluck('commission_mode')
                    ->filter()
                    ->unique()
                    ->values();

                if ($modesFromTickets->count() === 1) {
                    // All tickets in this payout share the same mode → use it
                    $commissionMode = $modesFromTickets->first();
                } elseif ($modesFromTickets->contains('added_on_top')) {
                    // Mixed but at least one is added_on_top → treat whole payout as added_on_top
                    $commissionMode = 'added_on_top';
                } else {
                    // No ticket-level info → fall back to Event > Organizer > Marketplace
                    $commissionMode = $event?->getEffectiveCommissionMode() ?? 'included';
                }

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
                    'status' => 'approved',
                    'source' => 'manual',
                    'approved_by' => $marketplaceAdmin->id,
                    'approved_at' => now(),
                    'payout_method' => $payoutMethod,
                    'ticket_breakdown' => !empty($ticketBreakdown) ? $ticketBreakdown : null,
                    'admin_notes' => $data['admin_notes'] ?? null,
                    'commission_mode' => $commissionMode,
                    'invoice_recipient_type' => $commissionMode === 'added_on_top' ? 'general_client' : 'organizer',
                ]);

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

        // Build ticket breakdown using the same logic as the manual flow.
        $event->loadMissing('ticketTypes');
        $items = $this->buildTicketBreakdownForEvent($event);

        if (empty($items)) {
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

        if ($netAmount <= 0) {
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
            'status' => 'pending',
            'source' => 'automated',
            'payout_method' => $payoutMethod,
            'admin_notes' => 'Decont generat automat din lista evenimente încheiate.',
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
     */
    protected function populatePayoutTicketsFromEvent(Set $set, Event $event, ?array $financials = null): void
    {
        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionRate = $event->getEffectiveCommissionRate();

        // Get sold ticket counts per type
        $ticketCounts = \App\Models\Ticket::whereHas('ticketType', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status', ['valid', 'used'])
            ->select('ticket_type_id', \DB::raw('COUNT(*) as cnt'))
            ->groupBy('ticket_type_id')
            ->pluck('cnt', 'ticket_type_id')
            ->toArray();

        // Get exact ticket counts already paid from previous payouts' ticket_breakdown
        $organizer = $event->marketplaceOrganizer;
        $alreadyPaidPerType = [];
        if ($organizer) {
            $previousPayouts = MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
                ->where('event_id', $event->id)
                ->whereIn('status', ['completed', 'processing', 'approved', 'pending'])
                ->whereNotNull('ticket_breakdown')
                ->get();

            foreach ($previousPayouts as $pp) {
                foreach ($pp->ticket_breakdown ?? [] as $tb) {
                    $ttId = $tb['ticket_type_id'] ?? null;
                    if ($ttId) {
                        $alreadyPaidPerType[$ttId] = ($alreadyPaidPerType[$ttId] ?? 0) + (int) ($tb['qty'] ?? 0);
                    }
                }
            }
        }

        // If no ticket_breakdown data on previous payouts, fall back to ratio estimation
        $fin = $financials ?? self::calculateEventFinancials($event);
        $hasExactData = !empty($alreadyPaidPerType);

        if (!$hasExactData && ($fin['paid'] + $fin['pending']) > 0) {
            $paidRatio = $fin['net'] > 0 ? ($fin['paid'] + $fin['pending']) / $fin['net'] : 1;
            $paidRatio = min(1, max(0, $paidRatio));
        }

        $items = [];
        foreach ($event->ticketTypes as $tt) {
            $totalSold = $ticketCounts[$tt->id] ?? 0;
            if ($totalSold <= 0) continue;

            // How many tickets remain (not yet paid out)
            if ($hasExactData) {
                $alreadyPaid = $alreadyPaidPerType[$tt->id] ?? 0;
            } else {
                $alreadyPaid = isset($paidRatio) ? (int) round($totalSold * $paidRatio) : 0;
            }
            $remaining = max(0, $totalSold - $alreadyPaid);

            // price_cents = BASE price
            $basePrice = (float) ($tt->sale_price_cents ? $tt->sale_price_cents / 100 : ($tt->price_cents ? $tt->price_cents / 100 : 0));

            // Commission on BASE price
            if ($tt->commission_type && $tt->commission_type !== '') {
                $commPerTicket = match ($tt->commission_type) {
                    'percentage' => round($basePrice * (($tt->commission_rate ?? 0) / 100), 2),
                    'fixed' => (float) ($tt->commission_fixed ?? 0),
                    'both' => round($basePrice * (($tt->commission_rate ?? 0) / 100), 2) + (float) ($tt->commission_fixed ?? 0),
                    default => round($basePrice * ($commissionRate / 100), 2),
                };
            } else {
                $commPerTicket = round($basePrice * ($commissionRate / 100), 2);
            }

            if ($remaining > 0) {
                $items[] = [
                    'ticket_type_id' => $tt->id,
                    'ticket_type_name' => is_array($tt->name) ? ($tt->name['ro'] ?? $tt->name['en'] ?? '') : $tt->name,
                    'available' => $remaining,
                    'unit_price' => $basePrice,
                    'commission_per_ticket' => $commPerTicket,
                    'qty' => $remaining, // default: all remaining tickets
                ];
            }
        }

        $set('payout_tickets', $items);
    }

    /**
     * Same logic as populatePayoutTicketsFromEvent but without the Filament Set
     * dependency — returns the items array directly. Used by automated payout
     * generation from the "Evenimente încheiate" modal.
     */
    public function buildTicketBreakdownForEvent(Event $event): array
    {
        $commissionRate = $event->getEffectiveCommissionRate();

        $ticketCounts = \App\Models\Ticket::whereHas('ticketType', fn ($q) => $q->where('event_id', $event->id))
            ->whereIn('status', ['valid', 'used'])
            ->select('ticket_type_id', \DB::raw('COUNT(*) as cnt'))
            ->groupBy('ticket_type_id')
            ->pluck('cnt', 'ticket_type_id')
            ->toArray();

        $organizer = $event->marketplaceOrganizer;
        $alreadyPaidPerType = [];
        if ($organizer) {
            $previousPayouts = MarketplacePayout::where('marketplace_organizer_id', $organizer->id)
                ->where('event_id', $event->id)
                ->whereIn('status', ['completed', 'processing', 'approved', 'pending'])
                ->whereNotNull('ticket_breakdown')
                ->get();

            foreach ($previousPayouts as $pp) {
                foreach ($pp->ticket_breakdown ?? [] as $tb) {
                    $ttId = $tb['ticket_type_id'] ?? null;
                    if ($ttId) {
                        $alreadyPaidPerType[$ttId] = ($alreadyPaidPerType[$ttId] ?? 0) + (int) ($tb['qty'] ?? 0);
                    }
                }
            }
        }

        $items = [];
        foreach ($event->ticketTypes as $tt) {
            $totalSold = $ticketCounts[$tt->id] ?? 0;
            if ($totalSold <= 0) continue;

            $alreadyPaid = $alreadyPaidPerType[$tt->id] ?? 0;
            $remaining = max(0, $totalSold - $alreadyPaid);
            if ($remaining <= 0) continue;

            $basePrice = (float) ($tt->sale_price_cents ? $tt->sale_price_cents / 100 : ($tt->price_cents ? $tt->price_cents / 100 : 0));

            if ($tt->commission_type && $tt->commission_type !== '') {
                $commPerTicket = match ($tt->commission_type) {
                    'percentage' => round($basePrice * (($tt->commission_rate ?? 0) / 100), 2),
                    'fixed' => (float) ($tt->commission_fixed ?? 0),
                    'both' => round($basePrice * (($tt->commission_rate ?? 0) / 100), 2) + (float) ($tt->commission_fixed ?? 0),
                    default => round($basePrice * ($commissionRate / 100), 2),
                };
            } else {
                $commPerTicket = round($basePrice * ($commissionRate / 100), 2);
            }

            $items[] = [
                'ticket_type_id' => $tt->id,
                'ticket_type_name' => is_array($tt->name) ? ($tt->name['ro'] ?? $tt->name['en'] ?? '') : $tt->name,
                'available' => $remaining,
                'unit_price' => $basePrice,
                'commission_per_ticket' => $commPerTicket,
                'qty' => $remaining,
            ];
        }

        return $items;
    }



    /**
     * Calculate event financials: gross, commission (per-ticket-type aware), net.
     * Single source of truth for all payout calculations.
     */
    public static function calculateEventFinancials(Event $event): array
    {
        $organizer = $event->marketplaceOrganizer;
        if (!$organizer) return ['gross' => 0, 'commission' => 0, 'net' => 0, 'refunds' => 0, 'paid' => 0, 'pending' => 0, 'balance' => 0];

        $commissionMode = $event->getEffectiveCommissionMode();
        $commissionRate = $event->getEffectiveCommissionRate();

        // Load orders. Exclude pos_app: POS/app sales don't flow through marketplace
        // (organizer collects cash on their own), so they must not appear in payout
        // gross/commission/net totals. POS commission is billed separately.
        $completedOrders = Order::where(function ($q) use ($event) {
                $q->where('event_id', $event->id)->orWhere('marketplace_event_id', $event->id);
            })
            ->whereIn('status', ['paid', 'confirmed', 'completed'])
            ->where('source', '!=', 'test_order')
            ->where('source', '!=', 'pos_app')
            ->with(['items.ticketType'])
            ->get();

        $grossRevenue = (float) $completedOrders->sum('total');

        // Load event ticket types for commission info
        $eventTicketTypes = $event->ticketTypes()->get()->keyBy('id');

        // Per-ticket-type commission calculation
        $totalCommission = 0;
        foreach ($completedOrders as $order) {
            if ($order->items->isNotEmpty()) {
                foreach ($order->items as $item) {
                    // Prefer event's ticket type (has commission info) over eager-loaded
                    $tt = ($item->ticket_type_id ? $eventTicketTypes->get($item->ticket_type_id) : null) ?? $item->ticketType;
                    $itemTotal = (float) $item->total;
                    $itemQty = (int) $item->quantity;
                    $itemBasePerUnit = $itemQty > 0 ? $itemTotal / $itemQty : $itemTotal;

                    $ttCommType = $tt?->commission_type;
                    $ttCommMode = $tt?->commission_mode ?: $commissionMode;

                    if ($ttCommType && $ttCommType !== '') {
                        $ttCommRate = (float) ($tt->commission_rate ?? 0);
                        $ttCommFixed = (float) ($tt->commission_fixed ?? 0);

                        // For ON TOP: item total includes commission. Extract base first.
                        // For INCLUDED: item total = base price. Commission deducted from organizer.
                        if ($ttCommMode === 'added_on_top') {
                            $base = match ($ttCommType) {
                                'percentage' => round($itemTotal / (1 + $ttCommRate / 100), 2),
                                'fixed' => $itemTotal - round($ttCommFixed * $itemQty, 2),
                                'both' => round(($itemTotal - $ttCommFixed * $itemQty) / (1 + $ttCommRate / 100), 2),
                                default => round($itemTotal / (1 + $commissionRate / 100), 2),
                            };
                            $totalCommission += round($itemTotal - $base, 2);
                        } else {
                            $totalCommission += match ($ttCommType) {
                                'percentage' => round($itemTotal * ($ttCommRate / 100), 2),
                                'fixed' => round($ttCommFixed * $itemQty, 2),
                                'both' => round($itemTotal * ($ttCommRate / 100), 2) + round($ttCommFixed * $itemQty, 2),
                                default => round($itemTotal * ($commissionRate / 100), 2),
                            };
                        }
                    } else {
                        // No per-ticket commission — use event-level
                        if ($commissionMode === 'added_on_top') {
                            $base = round($itemTotal / (1 + $commissionRate / 100), 2);
                            $totalCommission += round($itemTotal - $base, 2);
                        } else {
                            $totalCommission += round($itemTotal * ($commissionRate / 100), 2);
                        }
                    }
                }
            } else {
                // Orders without items — look up ticket records for ticket_type commission info
                $orderTickets = \App\Models\Ticket::where('order_id', $order->id)->get();
                if ($orderTickets->isNotEmpty()) {
                    foreach ($orderTickets->groupBy('ticket_type_id') as $ttId => $tickets) {
                        $tt = $eventTicketTypes->get($ttId);
                        $ticketPrice = (float) ($tickets->first()->price ?? 0);
                        $ticketQty = $tickets->count();

                        if ($tt && $tt->commission_type && $tt->commission_type !== '') {
                            $ttCommRate = (float) ($tt->commission_rate ?? 0);
                            $ttCommFixed = (float) ($tt->commission_fixed ?? 0);
                            $ttCommMode = $tt->commission_mode ?: $commissionMode;

                            // price_cents is BASE price. Ticket.price may be base or base+commission.
                            $basePrice = (float) ($tt->price_cents ? $tt->price_cents / 100 : $ticketPrice);

                            if ($ttCommMode === 'added_on_top') {
                                // Commission is calculated on base price, added to what customer pays
                                $comm = match ($tt->commission_type) {
                                    'percentage' => round($basePrice * ($ttCommRate / 100) * $ticketQty, 2),
                                    'fixed' => round($ttCommFixed * $ticketQty, 2),
                                    'both' => round($basePrice * ($ttCommRate / 100) * $ticketQty, 2) + round($ttCommFixed * $ticketQty, 2),
                                    default => round($basePrice * ($commissionRate / 100) * $ticketQty, 2),
                                };
                            } else {
                                $comm = match ($tt->commission_type) {
                                    'percentage' => round($basePrice * ($ttCommRate / 100) * $ticketQty, 2),
                                    'fixed' => round($ttCommFixed * $ticketQty, 2),
                                    'both' => round($basePrice * ($ttCommRate / 100) * $ticketQty, 2) + round($ttCommFixed * $ticketQty, 2),
                                    default => round($basePrice * ($commissionRate / 100) * $ticketQty, 2),
                                };
                            }
                            $totalCommission += $comm;
                        } else {
                            // No per-type commission, use event-level
                            $basePrice = (float) ($tt?->price_cents ? $tt->price_cents / 100 : $ticketPrice);
                            $totalCommission += round($basePrice * ($commissionRate / 100) * $ticketQty, 2);
                        }
                    }
                } else {
                    // No items AND no tickets — last resort event-level
                    $orderTotal = (float) $order->total;
                    if ($commissionMode === 'added_on_top') {
                        $base = round($orderTotal / (1 + $commissionRate / 100), 2);
                        $totalCommission += round($orderTotal - $base, 2);
                    } else {
                        $totalCommission += round($orderTotal * ($commissionRate / 100), 2);
                    }
                }
            }
        }

        // Refunds (also exclude pos_app for same reason as above)
        $refundedAmount = (float) Order::where(function ($q) use ($event) {
                $q->where('event_id', $event->id)->orWhere('marketplace_event_id', $event->id);
            })
            ->where('status', 'refunded')
            ->where('source', '!=', 'pos_app')
            ->sum(\DB::raw('COALESCE(refund_amount, total)'));

        $netRevenue = $grossRevenue - $totalCommission - $refundedAmount;

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
        // CRITICAL: when commission is ON TOP, $data['total'] includes the commission.
        // We must extract the base price first: base = total / (1 + rate/100) for percentage
        // When INCLUDED, $data['total'] IS the base — commission is deducted from it.
        $totalCommission = 0;
        foreach ($ticketBreakdown as $name => &$data) {
            $ttCommType = $data['commission_type'] ?? null;
            $ttCommRate = (float) ($data['commission_rate'] ?? 0);
            $ttCommFixed = (float) ($data['commission_fixed'] ?? 0);
            $ttCommMode = $data['commission_mode'] ?? null;
            $effectiveMode = $ttCommMode ?: $commissionMode;
            $data['effective_mode'] = $effectiveMode;

            if ($ttCommType && $ttCommType !== '') {
                // Per-ticket custom commission
                if ($effectiveMode === 'added_on_top') {
                    // Total INCLUDES commission on top — extract base
                    $baseTotal = match ($ttCommType) {
                        'percentage' => round($data['total'] / (1 + $ttCommRate / 100), 2),
                        'fixed' => $data['total'] - round($ttCommFixed * $data['quantity'], 2),
                        'both' => round(($data['total'] - $ttCommFixed * $data['quantity']) / (1 + $ttCommRate / 100), 2),
                        default => round($data['total'] / (1 + $commissionRate / 100), 2),
                    };
                    $comm = round($data['total'] - $baseTotal, 2);
                    $data['base_total'] = $baseTotal;
                    $data['base_unit_price'] = $data['quantity'] > 0 ? round($baseTotal / $data['quantity'], 2) : 0;
                } else {
                    // Commission INCLUDED — total is what customer paid, commission deducted from organizer
                    $comm = match ($ttCommType) {
                        'percentage' => round($data['total'] * ($ttCommRate / 100), 2),
                        'fixed' => round($ttCommFixed * $data['quantity'], 2),
                        'both' => round($data['total'] * ($ttCommRate / 100), 2) + round($ttCommFixed * $data['quantity'], 2),
                        default => round($data['total'] * ($commissionRate / 100), 2),
                    };
                    $data['base_total'] = $data['total'];
                    $data['base_unit_price'] = $data['unit_price'];
                }
                $data['calculated_commission'] = $comm;

                $modeLabel = $effectiveMode === 'added_on_top' ? 'on top' : 'inclus';
                $data['commission_label'] = match ($ttCommType) {
                    'percentage' => number_format($ttCommRate, 2) . '% ' . $modeLabel,
                    'fixed' => number_format($ttCommFixed, 2) . ' RON/bilet ' . $modeLabel,
                    'both' => number_format($ttCommRate, 2) . '% + ' . number_format($ttCommFixed, 2) . ' RON/bilet ' . $modeLabel,
                    default => number_format($commissionRate, 2) . '% ' . $modeLabel,
                };
            } else {
                // Use event-level commission
                if ($commissionMode === 'added_on_top') {
                    $baseTotal = round($data['total'] / (1 + $commissionRate / 100), 2);
                    $comm = round($data['total'] - $baseTotal, 2);
                    $data['base_total'] = $baseTotal;
                    $data['base_unit_price'] = $data['quantity'] > 0 ? round($baseTotal / $data['quantity'], 2) : 0;
                } else {
                    $comm = round($data['total'] * ($commissionRate / 100), 2);
                    $data['base_total'] = $data['total'];
                    $data['base_unit_price'] = $data['unit_price'];
                }
                $data['calculated_commission'] = $comm;
                $modeLabel = $commissionMode === 'added_on_top' ? 'on top' : 'inclus';
                $data['commission_label'] = number_format($commissionRate, 2) . '% ' . $modeLabel . ' (eveniment)';
            }
            $totalCommission += $data['calculated_commission'];
        }
        unset($data);

        // Net revenue = gross - commission - refunds
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
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Preț bilet</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Qty</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Total bilete</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Total încasat</th>';
            $html .= '<th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-400">Comision</th>';
            $html .= '</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/5">';

            foreach ($ticketBreakdown as $name => $data) {
                $baseUnitPrice = $data['base_unit_price'] ?? $data['unit_price'];
                $baseTotal = $data['base_total'] ?? $data['total'];

                $html .= '<tr>';
                $html .= '<td class="px-3 py-1.5 text-gray-900 dark:text-white">' . e($name) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-600 dark:text-gray-400">' . number_format($baseUnitPrice, 2) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-600 dark:text-gray-400">' . $data['quantity'] . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-900 dark:text-white">' . number_format($baseTotal, 2) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right font-mono text-gray-500 dark:text-gray-400">' . number_format($data['total'], 2) . '</td>';
                $html .= '<td class="px-3 py-1.5 text-right text-gray-500 dark:text-gray-400">';
                $html .= '<span class="font-mono">' . number_format($data['calculated_commission'], 2) . '</span>';
                $html .= '<br><span class="text-xs text-gray-400">' . $data['commission_label'] . '</span>';
                $html .= '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table>';
        }

        // Summary section
        $totalBase = collect($ticketBreakdown)->sum(fn ($d) => $d['base_total'] ?? $d['total']);
        $html .= '<div class="border-t border-gray-200 dark:border-white/10 px-3 py-2 space-y-1 bg-gray-50 dark:bg-white/5">';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total bilete vândute:</span><span class="font-medium">' . number_format($totalTicketsSold) . '</span></div>';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total bilete (fără comision):</span><span class="font-mono font-medium">' . number_format($totalBase, 2) . ' RON</span></div>';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total încasat:</span><span class="font-mono font-medium">' . number_format($grossRevenue, 2) . ' RON</span></div>';
        $html .= '<div class="flex justify-between"><span class="text-gray-600 dark:text-gray-400">Total comision:</span><span class="font-mono font-medium text-amber-600 dark:text-amber-400">-' . number_format($totalCommission, 2) . ' RON</span></div>';

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
                $refLabel = $pp->reference ? '<span class="font-mono text-gray-400">' . e($pp->reference) . '</span> · ' : '';
                $html .= '<span class="text-gray-500">' . $refLabel . $pp->created_at->format('d.m.Y') . ' ' . $statusBadge . '</span>';
                $html .= '<span class="font-mono font-medium text-gray-700 dark:text-gray-300">' . number_format($pp->amount, 2) . ' RON</span>';
                $html .= '</div>';
            }
            if ($totalPreviouslyPaid > 0) {
                $html .= '<div class="flex justify-between text-xs font-semibold mt-1 pt-1 border-t border-gray-100 dark:border-white/5"><span>Total achitat anterior:</span><span class="font-mono">' . number_format($totalPreviouslyPaid, 2) . ' RON</span></div>';
            }
            $html .= '</div>';
        }

        // Net balance line
        $html .= '<div class="flex justify-between pt-1 border-t border-gray-200 dark:border-white/10 font-semibold">';
        $html .= '<span>Sold disponibil</span>';
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
