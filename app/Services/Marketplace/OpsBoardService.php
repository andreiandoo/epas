<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\Invoice;
use App\Models\MarketplaceClient;
use App\Models\MarketplacePayout;
use App\Models\MarketplaceTaxRegistry;
use App\Support\MarketplaceTz;
use App\Support\TestPos;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the operations board ("Tablă operațiuni", microservice `ops-board`):
 * one row per event, one cell per task (cerere vizare, decont, factură,
 * impozit, PV distrugere).
 *
 * Read-only. Every state is derived from existing records — nobody moves a
 * card by hand, so nothing can be marked done before it actually is.
 *
 * Deadlines:
 *   - cerere vizare: event start minus CERERE_DAYS_BEFORE calendar days
 *   - decont + factură: next working day after the event ends (operators
 *     don't work weekends)
 *   - impozit + PV distrugere: the first 10th after the event (same month
 *     when the event is on the 1st–9th, next month otherwise)
 *
 * The three fiscal documents are on the board only for organizers whose
 * documents the marketplace handles (marketplace_manages_documents).
 *
 * A fiscal document is closed only once it is filed with the city hall
 * (see EventDocumentFilingService); regenerating it afterwards reopens it.
 */
class OpsBoardService
{
    public const SLUG = 'ops-board';

    public const CERERE_DAYS_BEFORE = 2;

    public const TAX_DUE_DAY = 10;

    /** Column order of the board. */
    public const TASKS = [
        'cerere' => 'Cerere vizare',
        'decont' => 'Decont',
        'factura' => 'Factură',
        'impozit' => 'Impozit',
        'pv' => 'PV distrugere',
    ];

    protected const DOC_TYPES = [
        'cerere' => 'cerere_avizare',
        'impozit' => 'declaratie_impozite',
        'pv' => 'pv_distrugere',
    ];

    /** Payout statuses that count as "a decont exists" (same list as buildRemainingTicketsItems). */
    protected const ACTIVE_PAYOUT_STATUSES = ['pending', 'approved', 'processing', 'completed'];

    /** How far back a multi-day event may have started and still reach the window. */
    protected const MULTI_DAY_LOOKBACK_DAYS = 60;

    protected string $tz;

    protected Carbon $today;

    /**
     * @param  Carbon  $from  first day of the period
     * @param  Carbon  $to  last day of the period
     * @param  Carbon  $trackFrom  events that ended before this day are never shown as backlog
     * @return array{backlog: array, period: array, upcoming: array, counts: array}
     */
    public function build(MarketplaceClient $client, Carbon $from, Carbon $to, Carbon $trackFrom): array
    {
        $this->tz = MarketplaceTz::tz($client);
        $this->today = Carbon::now($this->tz)->startOfDay();

        $from = Carbon::parse($from->format('Y-m-d'), $this->tz);
        $to = Carbon::parse($to->format('Y-m-d'), $this->tz);
        $trackFrom = Carbon::parse($trackFrom->format('Y-m-d'), $this->tz);

        $zones = ['backlog' => [], 'period' => [], 'upcoming' => []];
        $candidates = [];

        foreach ($this->fetchEvents($client, $from, $to, $trackFrom) as $event) {
            $event->setRelation('marketplaceClient', $client);

            $start = $this->startDate($event);
            if (! $start) {
                continue;
            }
            $end = $this->endDate($event, $start);

            if ($start->lte($to) && $end->gte($from)) {
                $zone = 'period';
            } elseif ($end->lt($from) && $end->gte($trackFrom)) {
                $zone = 'backlog';
            } elseif ($start->gt($to) && $start->copy()->subDays(self::CERERE_DAYS_BEFORE)->lte($to)) {
                $zone = 'upcoming';
            } else {
                continue;
            }

            $candidates[] = compact('event', 'start', 'end', 'zone');
        }

        $facts = $this->loadFacts(collect($candidates)->map(fn ($c) => $c['event']->id)->all());
        $facts['registries'] = MarketplaceTaxRegistry::query()
            ->whereIn('id', collect($candidates)->map(fn ($c) => $c['event']->marketplace_tax_registry_id)->filter()->unique()->values())
            ->get()
            ->keyBy('id')
            ->all();

        foreach ($candidates as $c) {
            $row = $this->buildRow($c['event'], $c['start'], $c['end'], $facts);

            // Earlier events stay on the board only while something is open;
            // later ones only for the cerere whose deadline falls in the period.
            if ($c['zone'] === 'backlog' && $row['open_count'] === 0) {
                continue;
            }
            if ($c['zone'] === 'upcoming' && ! $row['cells']['cerere']['open']) {
                continue;
            }

            $zones[$c['zone']][] = $row;
        }

        foreach ($zones as $zone => $rows) {
            usort($rows, fn ($a, $b) => [$a['sort'], $a['id']] <=> [$b['sort'], $b['id']]);
            $zones[$zone] = $rows;
        }

        return $zones + ['counts' => $this->counts($zones)];
    }

    /**
     * Published or already-ended events of the marketplace whose dates can
     * reach the window. The SQL filter is deliberately loose; build() does
     * the precise placement on effective (postponement-aware) dates.
     */
    protected function fetchEvents(MarketplaceClient $client, Carbon $from, Carbon $to, Carbon $trackFrom): Collection
    {
        $a = ($trackFrom->lt($from) ? $trackFrom : $from)->toDateString();
        $b = $to->copy()->addDays(self::CERERE_DAYS_BEFORE)->toDateString();
        $multiFrom = Carbon::parse($a)->subDays(self::MULTI_DAY_LOOKBACK_DAYS)->toDateString();
        $firstSlot = DB::getDriverName() === 'pgsql'
            ? "multi_slots->0->>'date'"
            : "JSON_UNQUOTE(JSON_EXTRACT(multi_slots, '$[0].date'))";

        return Event::query()
            ->where('marketplace_client_id', $client->id)
            ->whereNull('parent_id')
            ->where(function ($q) {
                $q->where('is_published', true)->orWhere('status', 'archived');
            })
            ->where(function ($q) use ($a, $b, $multiFrom, $firstSlot) {
                $q->where(function ($q2) use ($a, $b) {
                    $q2->where('duration_mode', 'single_day')->whereBetween('event_date', [$a, $b]);
                })
                    ->orWhere(function ($q2) use ($a, $b) {
                        $q2->where('duration_mode', 'range')
                            ->where('range_start_date', '<=', $b)
                            ->where('range_end_date', '>=', $a);
                    })
                    ->orWhere(function ($q2) use ($b, $multiFrom, $firstSlot) {
                        $q2->where('duration_mode', 'multi_day')
                            ->whereRaw("{$firstSlot} <= ?", [$b])
                            ->whereRaw("{$firstSlot} >= ?", [$multiFrom]);
                    })
                    ->orWhere(function ($q2) use ($a, $b) {
                        $q2->where('duration_mode', 'recurring')->whereBetween('recurring_start_date', [$a, $b]);
                    })
                    ->orWhere(function ($q2) use ($a, $b) {
                        $q2->where('is_postponed', true)->whereBetween('postponed_date', [$a, $b]);
                    });
            })
            ->with(['marketplaceOrganizer', 'venue:id,name,city'])
            ->get();
    }

    /**
     * Everything the cells need, loaded once for the whole set of events.
     */
    protected function loadFacts(array $eventIds): array
    {
        $facts = ['payouts' => [], 'invoiced' => [], 'docs' => [], 'filings' => [], 'tickets_changed' => [], 'sold' => []];
        if (empty($eventIds)) {
            return $facts;
        }

        $facts['filings'] = app(EventDocumentFilingService::class)->currentFilings($eventIds);

        $payouts = MarketplacePayout::query()
            ->whereIn('event_id', $eventIds)
            ->whereIn('status', self::ACTIVE_PAYOUT_STATUSES)
            ->get(['id', 'event_id', 'status', 'commission_amount', 'ticket_breakdown', 'created_at', 'completed_at']);
        $facts['payouts'] = $payouts->groupBy('event_id')->all();

        if ($payouts->isNotEmpty()) {
            $facts['invoiced'] = Invoice::query()
                ->whereIn('marketplace_payout_id', $payouts->pluck('id'))
                ->pluck('marketplace_payout_id')
                ->flip()
                ->all();
        }

        // A document generated by an admin lives in event_generated_documents
        // (and is mirrored in organizer_documents); one generated by the
        // organizer only in organizer_documents. Read both, keep the latest.
        $types = array_values(self::DOC_TYPES);
        $docRows = DB::table('event_generated_documents as d')
            ->join('marketplace_tax_templates as t', 't.id', '=', 'd.marketplace_tax_template_id')
            ->whereIn('d.event_id', $eventIds)
            ->whereIn('t.type', $types)
            ->groupBy('d.event_id', 't.type')
            ->selectRaw('d.event_id as event_id, t.type as type, MAX(d.created_at) as last_at')
            ->get()
            ->concat(
                DB::table('organizer_documents')
                    ->whereIn('event_id', $eventIds)
                    ->whereIn('document_type', $types)
                    ->groupBy('event_id', 'document_type')
                    ->selectRaw('event_id, document_type as type, MAX(created_at) as last_at')
                    ->get()
            );
        foreach ($docRows as $r) {
            $current = $facts['docs'][$r->event_id][$r->type] ?? null;
            if (! $current || (string) $r->last_at > $current) {
                $facts['docs'][$r->event_id][$r->type] = (string) $r->last_at;
            }
        }

        $facts['tickets_changed'] = DB::table('ticket_types')
            ->whereIn('event_id', $eventIds)
            ->groupBy('event_id')
            ->selectRaw('event_id, MAX(updated_at) as last_at')
            ->pluck('last_at', 'event_id')
            ->all();

        // Sold tickets per type, only where every decont is already paid —
        // the one case where "is anything left out of the deconts?" matters.
        $paidOff = $payouts->groupBy('event_id')
            ->filter(fn ($group) => $group->every(fn ($p) => $p->status === 'completed'))
            ->keys()
            ->all();
        if (! empty($paidOff)) {
            // Same ticket set as MarketplacePayout::buildRemainingTicketsItems().
            $soldRows = DB::table('tickets')
                ->join('ticket_types', 'ticket_types.id', '=', 'tickets.ticket_type_id')
                ->leftJoin('orders', 'orders.id', '=', 'tickets.order_id')
                ->whereIn('ticket_types.event_id', $paidOff)
                ->whereRaw(TestPos::notTypeSql('ticket_types'))
                ->whereIn('tickets.status', ['valid', 'used'])
                ->where(function ($q) {
                    $q->whereNull('tickets.order_id')
                        ->orWhere(function ($q2) {
                            $q2->whereIn('orders.status', SalesBreakdownService::PAID_ORDER_STATUSES)
                                ->where('orders.source', '!=', 'external_import')
                                ->whereNotIn('orders.source', SalesBreakdownService::POS_SOURCES)
                                ->whereNotIn('orders.source', TestPos::ORDER_SOURCES);
                        });
                })
                ->groupBy('ticket_types.event_id', 'tickets.ticket_type_id')
                ->selectRaw('ticket_types.event_id as event_id, tickets.ticket_type_id as ticket_type_id, COUNT(*) as qty')
                ->get();
            foreach ($soldRows as $r) {
                $facts['sold'][$r->event_id][$r->ticket_type_id] = (int) $r->qty;
            }
        }

        return $facts;
    }

    protected function buildRow(Event $event, Carbon $start, Carbon $end, array $facts): array
    {
        $cancelled = (bool) $event->is_cancelled;
        $ended = $this->today->gt($end);
        $payouts = $facts['payouts'][$event->id] ?? collect();
        $docs = $facts['docs'][$event->id] ?? [];
        $filings = $facts['filings'][$event->id] ?? [];
        $docsUrl = "/marketplace/events/{$event->id}/edit?tab=documente";
        $decontDue = $this->nextWorkingDay($end);
        $taxDue = $this->taxDue($end);
        // Strictly false: an event without an organizer, or a database not
        // migrated yet, keeps the documents on the board.
        $ownDocuments = $event->marketplaceOrganizer?->marketplace_manages_documents === false;
        $theirs = $this->cell('na', 'La organizator');

        $cells = [
            'cerere' => $ownDocuments ? $theirs : $this->cerereCell($event, $start, $ended, $cancelled, $docs, $facts, $docsUrl),
            'decont' => $this->decontCell($event, $ended, $cancelled, $payouts, $facts, $decontDue),
            'factura' => $this->facturaCell($ended, $cancelled, $payouts, $facts, $decontDue),
            'impozit' => match (true) {
                $ownDocuments => $theirs,
                $cancelled => $this->cell('na', 'Nu se aplică', 'eveniment anulat'),
                default => $this->documentCell($docs[self::DOC_TYPES['impozit']] ?? null, $filings[self::DOC_TYPES['impozit']] ?? null, $ended, $taxDue, $docsUrl),
            },
            // A cancelled event still needs its tickets destroyed, right away.
            'pv' => $ownDocuments
                ? $theirs
                : $this->documentCell($docs[self::DOC_TYPES['pv']] ?? null, $filings[self::DOC_TYPES['pv']] ?? null, $ended || $cancelled, $taxDue, $docsUrl),
        ];

        return [
            'id' => $event->id,
            'title' => $this->text($event->title) ?: ('Eveniment #' . $event->id),
            'date_label' => $this->dateLabel($start, $end),
            'sort' => $start->toDateString(),
            'organizer' => $event->marketplaceOrganizer?->name,
            'place' => $event->venue?->city ?: $this->text($event->venue?->name),
            'cancelled' => $cancelled,
            'postponed' => (bool) $event->is_postponed,
            'url' => "/marketplace/events/{$event->id}/edit",
            'filing' => $ownDocuments ? null : $this->filing($event, $facts['registries'] ?? []),
            'cells' => $cells,
            'open_count' => count(array_filter($cells, fn ($c) => $c['open'])),
            'overdue_count' => count(array_filter($cells, fn ($c) => $c['overdue'])),
        ];
    }

    /**
     * Where and how the event's documents get filed, or what is missing to
     * know that.
     *
     * @return array{label: string, missing: bool, url: string}
     */
    protected function filing(Event $event, array $registries): array
    {
        $registry = $registries[$event->marketplace_tax_registry_id] ?? null;
        if (! $registry) {
            return ['label' => 'Fără registru fiscal', 'missing' => true, 'url' => "/marketplace/events/{$event->id}/edit"];
        }

        $url = "/marketplace/tax-registry/{$registry->id}/edit";
        $method = $registry->submissionLabel();

        return $method
            ? ['label' => $registry->name . ' · ' . $method, 'missing' => false, 'url' => $url]
            : ['label' => $registry->name . ' · metodă de depunere nesetată', 'missing' => true, 'url' => $url];
    }

    protected function cerereCell(Event $event, Carbon $start, bool $ended, bool $cancelled, array $docs, array $facts, string $url): array
    {
        if ($cancelled) {
            return $this->cell('na', 'Nu se aplică', 'eveniment anulat');
        }

        $due = $start->copy()->subDays(self::CERERE_DAYS_BEFORE);
        $generatedAt = $docs[self::DOC_TYPES['cerere']] ?? null;

        if (! $generatedAt) {
            return $this->todoCell($due, $url);
        }

        // Ticket types edited after the request was generated: the request no
        // longer matches what is on sale. Irrelevant once the event is over.
        $ticketsChangedAt = $facts['tickets_changed'][$event->id] ?? null;
        if (! $ended && $ticketsChangedAt && strtotime((string) $ticketsChangedAt) > strtotime($generatedAt)) {
            return $this->cell(
                'redo',
                'De refăcut',
                'bilete modificate după generare',
                $due,
                $this->today->gt($due),
                true,
                $url,
            );
        }

        return $this->filedCell($facts['filings'][$event->id][self::DOC_TYPES['cerere']] ?? null, $generatedAt, $due, $url);
    }

    protected function decontCell(Event $event, bool $ended, bool $cancelled, Collection $payouts, array $facts, Carbon $due): array
    {
        if ($payouts->isEmpty()) {
            if ($cancelled) {
                return $this->cell('na', 'Nu se aplică', 'eveniment anulat');
            }
            if (! $ended) {
                return $this->cell('waiting', 'După eveniment', 'scadent ' . $this->day($due));
            }

            return $this->todoCell($due, '/marketplace/payouts');
        }

        $url = $payouts->count() === 1
            ? '/marketplace/payouts/' . $payouts->first()->id
            : '/marketplace/payouts';

        $unpaid = $payouts->filter(fn ($p) => $p->status !== 'completed');
        if ($unpaid->isNotEmpty()) {
            $days = (int) Carbon::parse($unpaid->min('created_at'))->setTimezone($this->tz)->startOfDay()->diffInDays($this->today);
            $detail = ($unpaid->count() > 1 ? $unpaid->count() . ' deconturi · ' : '')
                . ($days > 0 ? 'de ' . $days . ($days === 1 ? ' zi' : ' zile') : 'de azi');

            return $this->cell('progress', 'Așteaptă plata', $detail, null, false, true, $url);
        }

        if (! $ended) {
            return $this->cell('waiting', 'Parțial achitat', 'decontul final după eveniment', null, false, false, $url);
        }

        $left = $this->ticketsLeftOut($event, $payouts, $facts);
        if ($left > 0) {
            return $this->cell(
                'warn',
                'Achitat parțial',
                $left . ($left === 1 ? ' bilet nedecontat' : ' bilete nedecontate'),
                null,
                false,
                true,
                $url,
            );
        }

        return $this->cell('done', 'Achitat', $this->stamp($payouts->max('completed_at')), null, false, false, $url);
    }

    protected function facturaCell(bool $ended, bool $cancelled, Collection $payouts, array $facts, Carbon $due): array
    {
        if ($payouts->isEmpty()) {
            return $cancelled
                ? $this->cell('na', 'Nu se aplică', 'eveniment anulat')
                : $this->cell('waiting', 'După decont');
        }

        // No commission, nothing to invoice.
        if ((float) $payouts->sum(fn ($p) => (float) $p->commission_amount) <= 0.01) {
            return $this->cell('na', 'Nu se aplică', 'comision 0');
        }

        $url = '/marketplace/organizer-invoices';
        if ($payouts->contains(fn ($p) => isset($facts['invoiced'][$p->id]))) {
            return $this->cell('done', 'Emisă', null, null, false, false, $url);
        }
        if (! $ended) {
            return $this->cell('waiting', 'După eveniment');
        }

        return $this->todoCell($due, $url);
    }

    protected function documentCell(?string $generatedAt, ?array $filing, bool $applies, Carbon $due, string $url): array
    {
        if ($generatedAt) {
            return $this->filedCell($filing, $generatedAt, $due, $url);
        }
        if (! $applies) {
            return $this->cell('waiting', 'După eveniment', 'scadent ' . $this->day($due));
        }

        return $this->todoCell($due, $url);
    }

    /**
     * A generated document: closed once filed, and open again when it was
     * regenerated after its filing.
     */
    protected function filedCell(?array $filing, string $generatedAt, Carbon $due, string $url): array
    {
        // Filings are not recorded yet on a database that was not migrated.
        if (! EventDocumentFilingService::available()) {
            return $this->cell('generated', 'Generat', $this->stamp($generatedAt), null, false, false, $url);
        }

        if ($filing && $filing['filed_at'] >= $generatedAt) {
            return $this->cell('done', 'Depus', $filing['method_label'] . ' · ' . $this->stamp($filing['filed_at']), null, false, false, $url);
        }

        $label = $filing ? 'De redepus' : 'De depus';
        $late = (int) $due->diffInDays($this->today, false);

        return $late > 0
            ? $this->cell('overdue', $label, 'restant de ' . $late . ($late === 1 ? ' zi' : ' zile'), $due, true, true, $url)
            : $this->cell('tofile', $label, $late === 0 ? 'scadent azi' : 'scadent ' . $this->day($due), $due, false, true, $url);
    }

    /**
     * Sold tickets not covered by any decont of the event.
     */
    protected function ticketsLeftOut(Event $event, Collection $payouts, array $facts): int
    {
        $covered = [];
        foreach ($payouts as $payout) {
            foreach ($payout->ticket_breakdown ?? [] as $line) {
                $typeId = $line['ticket_type_id'] ?? null;
                if ($typeId) {
                    $covered[$typeId] = ($covered[$typeId] ?? 0) + (int) ($line['qty'] ?? 0);
                }
            }
        }

        $left = 0;
        foreach ($facts['sold'][$event->id] ?? [] as $typeId => $sold) {
            $left += max(0, $sold - ($covered[$typeId] ?? 0));
        }

        return $left;
    }

    protected function todoCell(Carbon $due, ?string $url): array
    {
        $late = (int) $due->diffInDays($this->today, false);

        return $late > 0
            ? $this->cell('overdue', 'Restant', 'de ' . $late . ($late === 1 ? ' zi' : ' zile') . ' · scadent ' . $this->day($due), $due, true, true, $url)
            : $this->cell('todo', 'De făcut', $late === 0 ? 'scadent azi' : 'scadent ' . $this->day($due), $due, false, true, $url);
    }

    /**
     * @param  string  $state  na | waiting | todo | tofile | overdue | redo | progress | warn | generated | done
     * @param  bool  $open  still needs someone's attention (keeps earlier events on the board)
     */
    protected function cell(string $state, string $label, ?string $detail = null, ?Carbon $due = null, bool $overdue = false, bool $open = false, ?string $url = null): array
    {
        return [
            'state' => $state,
            'label' => $label,
            'detail' => $detail,
            'due' => $due?->toDateString(),
            'overdue' => $overdue,
            'open' => $open,
            'url' => $url,
        ];
    }

    protected function counts(array $zones): array
    {
        $counts = ['overdue' => 0, 'todo' => 0, 'awaiting_payment' => 0, 'events' => 0];
        foreach ($zones as $rows) {
            $counts['events'] += count($rows);
            foreach ($rows as $row) {
                foreach ($row['cells'] as $cell) {
                    if ($cell['overdue']) {
                        $counts['overdue']++;
                    } elseif (in_array($cell['state'], ['todo', 'tofile', 'redo', 'warn'], true)) {
                        $counts['todo']++;
                    } elseif ($cell['state'] === 'progress') {
                        $counts['awaiting_payment']++;
                    }
                }
            }
        }

        return $counts;
    }

    /**
     * First day of the event, on the postponed date when there is one.
     */
    protected function startDate(Event $event): ?Carbon
    {
        $date = ($event->is_postponed && $event->postponed_date) ? $event->postponed_date : $event->start_date;

        return $date ? Carbon::parse($date->format('Y-m-d'), $this->tz) : null;
    }

    /**
     * Last day of the event. A show that runs past midnight still belongs to
     * the day it started on, so the end never falls before the start.
     */
    protected function endDate(Event $event, Carbon $start): Carbon
    {
        $endAt = $event->getEffectiveEndDatetime();
        if (! $endAt) {
            return $start->copy();
        }
        $end = Carbon::parse($endAt->copy()->setTimezone($this->tz)->format('Y-m-d'), $this->tz);

        return $end->lt($start) ? $start->copy() : $end;
    }

    protected function nextWorkingDay(Carbon $date): Carbon
    {
        $day = $date->copy()->addDay();
        while ($day->isWeekend()) {
            $day->addDay();
        }

        return $day;
    }

    protected function taxDue(Carbon $date): Carbon
    {
        $due = $date->copy()->startOfMonth();
        if ($date->day >= self::TAX_DUE_DAY) {
            $due->addMonthNoOverflow();
        }

        return $due->day(self::TAX_DUE_DAY);
    }

    protected function dateLabel(Carbon $start, Carbon $end): string
    {
        if ($start->equalTo($end)) {
            return $start->locale('ro')->translatedFormat('D, j M');
        }

        return $start->locale('ro')->translatedFormat('j M') . ' – ' . $end->locale('ro')->translatedFormat('j M');
    }

    protected function day(Carbon $date): string
    {
        return $date->locale('ro')->translatedFormat('j M');
    }

    /** Timestamps are stored in UTC; show the day in the marketplace timezone. */
    protected function stamp(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $this->day(Carbon::parse((string) $value, 'UTC')->setTimezone($this->tz));
    }

    /** Translatable attributes come back as arrays keyed by locale. */
    protected function text(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = $value['ro'] ?? $value['en'] ?? (reset($value) ?: null);
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }
}
