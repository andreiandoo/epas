<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Models\Order;
use App\Services\EventAlertsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Ciornele de decont rămase „În așteptare” pe evenimentele AmBilet încheiate
 * înainte de mutarea pe Tixello (create automat în martie–mai 2026). Se aduc
 * în „Aprobat”, ca ownerul să le finalizeze apoi plata manual, din pagina de
 * sold a organizatorului.
 *
 * La aprobare, fiecare ciornă:
 *  - trece pe organizatorul actual al evenimentului (evenimentul a fost mutat
 *    după crearea ciornei; comenzile l-au urmat deja);
 *  - primește suma = netul evenimentului minus ce e deja plătit / în procesare,
 *    ca soldul evenimentului să iasă 0 după plată;
 *  - nu generează PDF și nu consumă avansuri (update direct, fără evenimente
 *    de model). PDF-ul se generează la finalizarea plății.
 * Dublurile (a doua ciornă pe același eveniment, ciorna identică unui decont
 * deja plătit) se sar și rămân „În așteptare”.
 *
 * analyze() doar citește. settle() reface analiza și aprobă. revert() pune
 * înapoi exact starea dinainte (din jurnalul de activitate, pe organizator).
 */
class LegacySettlementService
{
    public const MARKETPLACE_ID = 1;

    /** Ultima zi de final a evenimentului (inclusiv, ora României). */
    public const CUTOFF_DATE = '2026-04-15';

    /** Peste această ajustare a ciornei verdictul e „atenție” și cere confirmare. */
    public const WARN_ADJUSTMENT = 500.0;

    public const NOTE = 'ciornă din sistemul anterior AmBilet (eveniment decontat înainte de mutarea pe Tixello), fără dovadă a plății înregistrată în sistem';

    public const LOG_NAME = 'legacy-settlement';

    public const VERDICT_NONE = 'none';
    public const VERDICT_OK = 'ok';
    public const VERDICT_WARN = 'warn';
    public const VERDICT_BLOCKED = 'blocked';
    public const VERDICT_SETTLED = 'settled';

    public function __construct(private SalesBreakdownService $breakdown)
    {
    }

    public static function appliesTo(?int $marketplaceId): bool
    {
        return $marketplaceId === self::MARKETPLACE_ID;
    }

    public static function cutoff(): Carbon
    {
        return Carbon::parse(self::CUTOFF_DATE . ' 23:59:59', 'Europe/Bucharest');
    }

    // ---------------------------------------------------------------
    // Analiză
    // ---------------------------------------------------------------

    /**
     * Ciornele de pe evenimentele organizatorului (indiferent pe ce
     * organizator a rămas ciorna), pentru evenimentele încheiate până la
     * CUTOFF_DATE.
     */
    public function analyze(MarketplaceOrganizer $organizer): array
    {
        $cutoff = self::cutoff();
        $orgId = (int) $organizer->id;

        $drafts = MarketplacePayout::where('marketplace_client_id', $organizer->marketplace_client_id)
            ->where('status', 'pending')
            ->whereNotNull('event_id')
            ->whereHas('event', fn ($e) => $e->where('marketplace_organizer_id', $orgId))
            ->with(['event', 'organizer:id,name'])
            ->orderBy('id')
            ->get();

        $events = [];
        $issues = [];

        foreach ($drafts->groupBy('event_id') as $rows) {
            /** @var Event $event */
            $event = $rows->first()->event;
            $end = $event->getEffectiveEndDatetime();
            if ($end && $end->gt($cutoff)) {
                continue;
            }

            $row = $this->analyzeEvent($event, $rows, $end);
            $events[] = $row;
            foreach ($row['issues'] as $text) {
                $issues[] = ['event_id' => $event->id, 'event' => $row['title'], 'text' => $text];
            }
        }

        usort($events, fn ($a, $b) => strcmp($a['sort_date'], $b['sort_date']));

        $toApprove = collect($events)->filter(fn ($e) => empty($e['issues']) && $e['approve']);
        $last = $this->lastRun($organizer);
        $verdict = match (true) {
            $toApprove->isEmpty() && empty($issues) => $last && $last->description === 'settled' ? self::VERDICT_SETTLED : self::VERDICT_NONE,
            ! empty($issues) => self::VERDICT_BLOCKED,
            $toApprove->contains(fn ($e) => $e['warn']) => self::VERDICT_WARN,
            default => self::VERDICT_OK,
        };

        $result = [
            'organizer_id' => $orgId,
            'organizer' => $organizer->name,
            'verdict' => $verdict,
            'events' => $events,
            'issues' => $issues,
            'totals' => [
                'events' => $toApprove->count(),
                'payouts' => $toApprove->count(),
                'moved' => $toApprove->filter(fn ($e) => $e['approve']['from_organizer_id'] !== $orgId)->count(),
                'skipped' => collect($events)->sum(fn ($e) => count($e['skipped'])),
                'draft_amount' => round($toApprove->sum(fn ($e) => $e['approve']['draft_amount']), 2),
                'settle_amount' => round($toApprove->sum(fn ($e) => $e['approve']['amount']), 2),
                'balance_before' => round(collect($events)->sum('balance_before'), 2),
                'balance_after' => round(collect($events)->sum('balance_after'), 2),
            ],
            'balance' => [
                'available' => (float) $organizer->available_balance,
                'pending' => (float) $organizer->pending_balance,
                'paid' => (float) $organizer->total_paid_out,
            ],
            'last_run' => $last ? [
                'action' => $last->description,
                'at' => $last->created_at?->format('d.m.Y H:i'),
                'by' => $last->causer?->name,
            ] : null,
            'analyzed_at' => now()->format('d.m.Y H:i'),
        ];

        $this->storeVerdict($orgId, $result);

        return $result;
    }

    private function analyzeEvent(Event $event, Collection $drafts, ?Carbon $end): array
    {
        $issues = [];
        $skipped = [];
        $title = $this->title($event);

        if (! $end) {
            $issues[] = 'Evenimentul nu are dată de final, nu se poate stabili dacă e din sistemul vechi.';
        }

        $freeze = Carbon::parse(MarketplacePayout::LEGACY_SETTLEMENT_FREEZE);
        foreach ($drafts as $p) {
            if ($p->created_at && $p->created_at->gte($freeze)) {
                $issues[] = "Ciorna {$this->label($p)} e creată după " . $freeze->format('d.m.Y') . ', nu face parte din ciornele vechi.';
            }
        }

        $completed = MarketplacePayout::where('event_id', $event->id)->where('status', 'completed')->get(['id', 'reference', 'decont_series', 'amount']);
        $inFlight = MarketplacePayout::where('event_id', $event->id)->whereIn('status', ['approved', 'processing'])->get(['id', 'reference', 'decont_series', 'amount']);

        // Dublurile se sar: ciorna identică unui decont deja plătit, apoi
        // orice ciornă în plus pe același eveniment (rămâne cea mai veche).
        $candidates = collect();
        foreach ($drafts as $p) {
            $twin = $completed->first(fn ($c) => abs((float) $c->amount - (float) $p->amount) < 0.01);
            if ($twin) {
                $skipped[] = "{$this->label($p)}: repetă decontul deja plătit {$this->label($twin)}";

                continue;
            }
            if ($candidates->isNotEmpty()) {
                $skipped[] = "{$this->label($p)}: a doua ciornă pe eveniment (dublura lui {$this->label($candidates->first())})";

                continue;
            }
            $candidates->push($p);
        }

        // Netul după aprobare: ciorna e creată înainte de
        // LEGACY_SETTLEMENT_FREEZE, deci odată aprobată vânzările importate
        // intră în net (MarketplacePayout::balanceBreakdown).
        $now = $this->breakdown->build($event, excludePos: true);
        $full = (float) ($now['total_net'] ?? 0);
        $paid = (float) $completed->sum('amount');
        $open = (float) $inFlight->sum('amount');
        $balanceBefore = round($this->currentNet($event, $full) - $paid - $open, 2);
        $target = round($full - $paid - $open, 2);

        $approve = null;
        if ($draft = $candidates->first()) {
            if ($target < -0.004) {
                $issues[] = 'Pe eveniment s-a plătit / aprobat deja mai mult decât netul (' . number_format($full, 2, ',', '.')
                    . ' lei net, ' . number_format($paid + $open, 2, ',', '.') . ' lei plătit sau în procesare).';
            } else {
                $approve = [
                    'id' => $draft->id,
                    'label' => $this->label($draft),
                    'draft_amount' => round((float) $draft->amount, 2),
                    'amount' => max(0.0, $target),
                    'adjustment' => round(max(0.0, $target) - (float) $draft->amount, 2),
                    'from_organizer_id' => (int) $draft->marketplace_organizer_id,
                    'from_organizer' => $draft->organizer?->name,
                    'created_at' => $draft->created_at?->format('d.m.Y'),
                ];
            }
        }

        $compare = $approve && abs($approve['adjustment']) >= 0.01
            ? $this->compare($event, $candidates->first(), $now, $paid, $open, $approve['amount'])
            : null;

        $date = $event->event_date ?? $event->range_start_date ?? $event->starts_at;

        return [
            'event_id' => $event->id,
            'title' => $title,
            'date' => $end?->format('d.m.Y'),
            'sort_date' => $date?->format('Y-m-d') ?? '0000-00-00',
            'event_organizer_id' => (int) $event->marketplace_organizer_id,
            'net' => round($full, 2),
            'paid' => round($paid, 2),
            'in_flight' => round($open, 2),
            'approve' => $approve,
            'compare' => $compare,
            'skipped' => $skipped,
            'balance_before' => $balanceBefore,
            'balance_after' => $approve && empty($issues) ? round($target - $approve['amount'], 2) : $balanceBefore,
            'warn' => $approve && abs($approve['adjustment']) > self::WARN_ADJUSTMENT,
            'issues' => array_values(array_unique($issues)),
        ];
    }

    /**
     * De unde vine diferența dintre ciornă și suma de aprobat. Ciorna (martie)
     * a luat totalul comenzilor plătite de atunci, cu POS, minus un procent fix
     * al evenimentului; netul de acum se face pe bilete valide, fără POS, cu
     * comisionul și reducerile din setările actuale.
     */
    private function compare(Event $event, MarketplacePayout $draft, array $now, float $paid, float $open, float $target): array
    {
        $pct = fn (?float $v) => $v === null ? '—' : rtrim(rtrim(number_format($v, 2, ',', ''), '0'), ',') . '%';
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');

        $rows = is_array($draft->ticket_breakdown) ? $draft->ticket_breakdown : [];
        $d = [
            'qty' => (int) collect($rows)->sum(fn ($r) => (int) ($r['quantity'] ?? $r['qty'] ?? 0)),
            'gross' => round((float) $draft->gross_amount, 2),
            'commission' => round((float) $draft->commission_amount, 2),
            'net' => round((float) $draft->amount, 2),
            'created_at' => $draft->created_at?->format('d.m.Y'),
        ];
        $d['rate'] = $d['gross'] > 0 ? round($d['commission'] / $d['gross'] * 100, 2) : null;

        $types = collect($now['per_type'] ?? []);
        $n = [
            'qty' => (int) $types->sum('qty'),
            'gross' => round((float) ($now['total_revenue'] ?? 0), 2),
            'commission' => round((float) ($now['total_commission'] ?? 0), 2),
            'discount' => round((float) ($now['total_discount'] ?? 0), 2),
            'net' => round((float) ($now['total_net'] ?? 0), 2),
            'rates' => $types->map(fn ($r) => ($r['commission_mode'] ?? '') === 'added_on_top'
                ? 'peste preț'
                : (isset($r['commission_rate']) ? $pct((float) $r['commission_rate']) : 'sumă fixă'))
                ->unique()->values()->all(),
        ];
        $n['rate'] = $n['gross'] > 0 ? round($n['commission'] / $n['gross'] * 100, 2) : null;

        $withPos = $this->breakdown->build($event, excludePos: false);
        $pos = [
            'qty' => (int) collect($withPos['per_type'] ?? [])->sum('qty') - $n['qty'],
            'net' => round((float) ($withPos['total_net'] ?? 0) - $n['net'], 2),
        ];

        $paidStatuses = ['paid', 'confirmed', 'completed'];
        $lostStatuses = ['refunded', 'partially_refunded', 'cancelled'];
        $q = fn (array $statuses) => Order::where('event_id', $event->id)
            ->whereIn('status', $statuses)
            ->tap(fn ($x) => \App\Support\TestPos::excludeOrders($x));
        $orders = [
            'count' => $q($paidStatuses)->count(),
            'total' => round((float) $q($paidStatuses)->sum('total'), 2),
            'pos_count' => $q($paidStatuses)->whereIn('source', SalesBreakdownService::POS_SOURCES)->count(),
            'pos_total' => round((float) $q($paidStatuses)->whereIn('source', SalesBreakdownService::POS_SOURCES)->sum('total'), 2),
            'lost_count' => $q($lostStatuses)->count(),
            'lost_total' => round((float) $q($lostStatuses)->sum('total'), 2),
        ];

        $why = [];
        if (abs($orders['total'] - $d['gross']) >= 1) {
            $why[] = 'Comenzile plătite pe eveniment însumează acum ' . $fmt($orders['total']) . ' lei, iar ciorna a pornit de la ' . $fmt($d['gross'])
                . ' lei (diferență ' . $fmt($orders['total'] - $d['gross']) . '): după crearea ciornei s-au adăugat, mutat, anulat sau rambursat comenzi.';
        }
        if ($pos['net'] >= 0.01) {
            $why[] = 'Ciorna includea și vânzările POS (' . $pos['qty'] . ' bilete, ' . $fmt($pos['net']) . ' lei net). Acum POS nu se mai decontează, banii au rămas la organizator.';
        }
        if ($d['rate'] !== null && $n['rate'] !== null && abs($d['rate'] - $n['rate']) >= 0.5) {
            $why[] = 'Comisionul ciornei a fost ' . $pct($d['rate']) . ' din total. Acum, din setările biletelor (' . implode(', ', $n['rates'])
                . '), iese ' . $pct($n['rate']) . ', adică ' . $fmt($n['commission']) . ' lei.';
        }
        if ($n['discount'] >= 0.01) {
            $why[] = 'Acum se scad și reducerile alocate biletelor: ' . $fmt($n['discount']) . ' lei.';
        }
        if ($paid + $open >= 0.01) {
            $why[] = 'Din net se scade ce e deja plătit sau în procesare pe eveniment: ' . $fmt($paid + $open) . ' lei.';
        }
        if ($target < 0.004 && $n['net'] - $paid - $open < -0.004) {
            $why[] = 'Netul de acum e mai mic decât ce s-a plătit deja, așa că ciorna se aprobă cu 0.';
        }

        return ['draft' => $d, 'now' => $n, 'pos' => $pos, 'orders' => $orders, 'paid' => round($paid + $open, 2), 'target' => $target, 'why' => $why];
    }

    /** Netul evenimentului așa cum îl vede soldul acum (regula legacy_import). */
    private function currentNet(Event $event, float $full): float
    {
        if (MarketplacePayout::eventHasLegacySettlement($event->id)) {
            return $full;
        }

        return (float) (MarketplacePayout::balanceBreakdown($event, $this->breakdown)['total_net'] ?? 0);
    }

    // ---------------------------------------------------------------
    // Aprobare / anulare
    // ---------------------------------------------------------------

    /**
     * Reface analiza și aprobă ciornele dacă verdictul e ok (sau atenție, cu
     * confirmare). Întoarce analiza folosită; aruncă excepție dacă nu se poate.
     */
    public function settle(MarketplaceOrganizer $organizer, ?Model $admin, bool $confirmWarn = false): array
    {
        return Cache::lock('legacy_settle:org:' . $organizer->id, 900)->block(5, function () use ($organizer, $admin, $confirmWarn) {
            $analysis = $this->analyze($organizer);

            if ($analysis['verdict'] === self::VERDICT_BLOCKED) {
                throw new \RuntimeException('Organizatorul are probleme de lămurit: ' . collect($analysis['issues'])->pluck('text')->first());
            }
            if ($analysis['verdict'] === self::VERDICT_WARN && ! $confirmWarn) {
                throw new \RuntimeException('Ajustări peste ' . number_format(self::WARN_ADJUSTMENT, 0, ',', '.') . ' lei: aprobarea cere confirmare.');
            }
            if (! in_array($analysis['verdict'], [self::VERDICT_OK, self::VERDICT_WARN], true)) {
                throw new \RuntimeException('Nu există ciorne vechi de aprobat.');
            }

            $changes = [];
            $touchedOrganizers = [(int) $organizer->id];

            DB::transaction(function () use ($analysis, $organizer, $admin, &$changes, &$touchedOrganizers) {
                $today = now()->format('d.m.Y');

                foreach ($analysis['events'] as $ev) {
                    $info = $ev['approve'];
                    if (! $info || ! empty($ev['issues'])) {
                        continue;
                    }

                    $p = MarketplacePayout::whereKey($info['id'])->lockForUpdate()->first();
                    if (! $p || $p->status !== 'pending') {
                        throw new \RuntimeException("Ciorna {$info['label']} s-a schimbat între timp. Reanalizează.");
                    }

                    $old = array_intersect_key($p->getRawOriginal(), array_flip([
                        'marketplace_organizer_id', 'amount', 'status', 'approved_at', 'approved_by', 'admin_notes',
                    ]));

                    $note = "Aprobat {$today}: " . self::NOTE . '.';
                    if (abs($info['adjustment']) >= 0.01) {
                        $note .= ' Suma ciornei ' . number_format($info['draft_amount'], 2, ',', '.') . ' lei a fost adusă la netul evenimentului, '
                            . number_format($info['amount'], 2, ',', '.') . ' lei.';
                    }
                    if ($info['from_organizer_id'] !== (int) $organizer->id) {
                        $note .= " Ciorna era pe organizatorul #{$info['from_organizer_id']} (" . ($info['from_organizer'] ?? '?')
                            . '); evenimentul a fost mutat ulterior pe acest organizator.';
                        $touchedOrganizers[] = $info['from_organizer_id'];
                    }

                    $new = [
                        'marketplace_organizer_id' => (int) $organizer->id,
                        'amount' => $info['amount'],
                        'status' => 'approved',
                        'approved_at' => now(),
                        'approved_by' => $admin?->getKey(),
                        'admin_notes' => trim(($p->admin_notes ? $p->admin_notes . "\n" : '') . $note),
                    ];

                    // Update pe query: fără evenimente de model (PDF de
                    // decont, compensare din avans).
                    MarketplacePayout::whereKey($p->id)->update($new);

                    $changes[] = ['payout_id' => $p->id, 'event_id' => $ev['event_id'], 'old' => $old, 'new' => array_merge($new, ['approved_at' => (string) $new['approved_at']])];
                }
            });

            activity(self::LOG_NAME)
                ->performedOn($organizer)
                ->causedBy($admin)
                ->withProperties([
                    'mode' => 'approve',
                    'cutoff' => self::CUTOFF_DATE,
                    'payouts' => $changes,
                    'organizers' => array_values(array_unique($touchedOrganizers)),
                    'totals' => $analysis['totals'],
                ])
                ->log('settled');

            $this->afterChange(array_unique($touchedOrganizers), collect($changes)->pluck('event_id')->unique()->all());

            return $analysis;
        });
    }

    /** Pune ciornele ultimei aprobări înapoi exact cum erau (doar cele încă neplătite). */
    public function revert(MarketplaceOrganizer $organizer, ?Model $admin): int
    {
        return Cache::lock('legacy_settle:org:' . $organizer->id, 900)->block(5, function () use ($organizer, $admin) {
            $last = $this->lastRun($organizer);
            if (! $last || $last->description !== 'settled') {
                throw new \RuntimeException('Nu există o aprobare de anulat.');
            }

            $rows = $last->properties['payouts'] ?? [];
            $restored = 0;
            $stillPaid = 0;

            DB::transaction(function () use ($rows, &$restored, &$stillPaid) {
                foreach ($rows as $r) {
                    $n = MarketplacePayout::whereKey($r['payout_id'])->where('status', 'approved')->update($r['old']);
                    $restored += $n;
                    if (! $n) {
                        $stillPaid++;
                    }
                }
            });

            activity(self::LOG_NAME)
                ->performedOn($organizer)
                ->causedBy($admin)
                ->withProperties(['reverted_activity_id' => $last->id, 'restored' => $restored, 'not_restored' => $stillPaid])
                ->log('reverted');

            $orgs = array_unique(array_merge([(int) $organizer->id], (array) ($last->properties['organizers'] ?? [])));
            $this->afterChange($orgs, collect($rows)->pluck('event_id')->unique()->all());

            return $restored;
        });
    }

    // ---------------------------------------------------------------
    // Realiniere după corectarea prețurilor (tickets:fix-legacy-prices)
    // ---------------------------------------------------------------

    /** Jurnal separat, ca lastRun()/revert() să vadă în continuare doar aprobările. */
    public const REALIGN_LOG = 'legacy-realign';

    /**
     * Ciornele vechi aprobate de aici (nota NOTE), încă neplătite, aduse la
     * netul actual al evenimentului: net − plătit − alte deconturi în procesare,
     * ca soldul evenimentului să fie din nou 0. Implicit doar pe evenimentele
     * cu prețuri de bilet corectate. Fără $apply nu scrie nimic.
     *
     * @return array{rows: array<int, array>, batch: ?string}
     */
    public function realign(bool $apply, bool $allEvents = false, ?Model $admin = null): array
    {
        $q = MarketplacePayout::where('marketplace_client_id', self::MARKETPLACE_ID)
            ->where('status', 'approved')
            ->whereNotNull('event_id')
            ->where('admin_notes', 'like', '%' . self::NOTE . '%')
            ->with('event');
        if (! $allEvents) {
            $q->whereIn('event_id', DB::table('legacy_ticket_price_fixes')->whereNull('reverted_at')->distinct()->select('event_id'));
        }

        $batch = $apply ? (string) \Illuminate\Support\Str::uuid() : null;
        $fmt = fn ($v) => number_format((float) $v, 2, ',', '.');
        $rows = [];
        $byOrg = [];

        foreach ($q->orderBy('id')->get()->groupBy('event_id') as $group) {
            $p = $group->first();
            $event = $p->event;
            if (! $event) {
                continue;
            }

            $net = (float) (MarketplacePayout::balanceBreakdown($event, $this->breakdown)['total_net'] ?? 0);
            $paid = (float) MarketplacePayout::where('event_id', $event->id)->where('status', 'completed')->sum('amount');
            $others = (float) MarketplacePayout::where('event_id', $event->id)->whereIn('status', ['approved', 'processing'])
                ->whereKeyNot($p->id)->sum('amount');
            $target = round($net - $paid - $others, 2);
            $old = round((float) $p->amount, 2);
            $new = max(0.0, $target);

            $notes = [];
            if ($group->count() > 1) {
                $notes[] = 'mai multe ciorne vechi aprobate pe eveniment; se realiniază doar prima';
            }
            if ($target < -0.004) {
                $notes[] = 'netul e sub ce e deja plătit / în procesare; ciorna rămâne la 0';
            }

            $row = [
                'event_id' => $event->id,
                'event' => $this->title($event),
                'organizer_id' => (int) $p->marketplace_organizer_id,
                'payout_id' => $p->id,
                'payout' => $this->label($p),
                'net' => round($net, 2),
                'paid' => round($paid, 2),
                'others' => round($others, 2),
                'old' => $old,
                'new' => $new,
                'diff' => round($new - $old, 2),
                'notes' => implode('; ', $notes),
                'result' => abs($new - $old) < 0.01 ? 'neschimbat' : ($apply ? 'realiniat' : 'de realiniat'),
            ];

            if ($apply && $row['result'] === 'realiniat') {
                $done = DB::transaction(function () use ($p, $old, $new, $fmt, &$byOrg, $event) {
                    $fresh = MarketplacePayout::whereKey($p->id)->lockForUpdate()->first();
                    if (! $fresh || $fresh->status !== 'approved' || abs((float) $fresh->amount - $old) >= 0.01) {
                        return false;
                    }
                    $oldNotes = $fresh->getRawOriginal('admin_notes');
                    $note = 'Realiniat ' . now()->format('d.m.Y') . ': prețurile biletelor importate au fost aduse la ce a plătit clientul; suma '
                        . $fmt($old) . ' → ' . $fmt($new) . ' lei.';
                    MarketplacePayout::whereKey($p->id)->update([
                        'amount' => $new,
                        'admin_notes' => trim(($oldNotes ? $oldNotes . "\n" : '') . $note),
                    ]);
                    $byOrg[(int) $p->marketplace_organizer_id][] = [
                        'payout_id' => $p->id,
                        'event_id' => $event->id,
                        'old' => ['amount' => $old, 'admin_notes' => $oldNotes],
                        'new' => ['amount' => $new],
                    ];

                    return true;
                });
                if (! $done) {
                    $row['result'] = 'sărit: ciorna s-a schimbat între timp';
                }
            }

            $rows[] = $row;
        }

        foreach ($byOrg as $orgId => $changes) {
            activity(self::REALIGN_LOG)
                ->performedOn(MarketplaceOrganizer::find($orgId))
                ->causedBy($admin)
                ->withProperties(['batch' => $batch, 'payouts' => $changes])
                ->log('realigned');
        }
        if ($byOrg) {
            $this->afterChange(array_keys($byOrg), collect($byOrg)->flatten(1)->pluck('event_id')->unique()->all());
        }

        return ['rows' => $rows, 'batch' => $batch];
    }

    /** Pune înapoi suma și notele ciornelor dintr-un lot de realiniere (doar cele neatinse între timp). */
    public function revertRealign(string $batch, ?Model $admin = null): int
    {
        $activities = Activity::where('log_name', self::REALIGN_LOG)->where('description', 'realigned')
            ->where('properties->batch', $batch)->get();

        $restored = 0;
        $orgs = [];
        $events = [];
        DB::transaction(function () use ($activities, &$restored, &$orgs, &$events) {
            foreach ($activities as $a) {
                foreach ($a->properties['payouts'] ?? [] as $c) {
                    $restored += MarketplacePayout::whereKey($c['payout_id'])->where('status', 'approved')
                        ->where('amount', $c['new']['amount'])->update($c['old']);
                    $events[] = $c['event_id'];
                }
                $orgs[] = (int) $a->subject_id;
            }
        });

        foreach ($activities as $a) {
            activity(self::REALIGN_LOG)->performedOn($a->subject)->causedBy($admin)
                ->withProperties(['batch' => $batch, 'reverted_activity_id' => $a->id])->log('realign-reverted');
        }
        $this->afterChange(array_unique($orgs), array_unique($events));

        return $restored;
    }

    private function afterChange(array $organizerIds, array $eventIds): void
    {
        foreach ($eventIds as $id) {
            EventAlertsService::flushCache((int) $id);
        }
        foreach ($organizerIds as $orgId) {
            Cache::forget('organizer_balance_page_events:v3:' . $orgId);
            if ($org = MarketplaceOrganizer::find($orgId)) {
                $org->recomputeBalances($this->breakdown);
                $this->analyze($org);
            }
        }
    }

    public function lastRun(MarketplaceOrganizer $organizer): ?Activity
    {
        return Activity::where('log_name', self::LOG_NAME)
            ->where('subject_type', $organizer->getMorphClass())
            ->where('subject_id', $organizer->id)
            ->with('causer')
            ->latest('id')
            ->first();
    }

    // ---------------------------------------------------------------
    // Verdict păstrat pentru coloana „Trecut” din Balanțe
    // ---------------------------------------------------------------

    public const VERDICTS_TABLE = 'marketplace_legacy_settlement_verdicts';

    /** @var array<int, array>|null verdictele citite o dată pe request */
    private static ?array $verdictMemo = null;

    public static function tableReady(): bool
    {
        static $ready = null;

        return $ready ??= \Illuminate\Support\Facades\Schema::hasTable(self::VERDICTS_TABLE);
    }

    /** Scrie verdictul (și scoate organizatorul din coadă). */
    public static function putVerdict(int $organizerId, string $verdict, int $events = 0, int $payouts = 0, float $settleAmount = 0, int $issues = 0): void
    {
        if (! self::tableReady()) {
            return;
        }

        DB::table(self::VERDICTS_TABLE)->updateOrInsert(
            ['marketplace_organizer_id' => $organizerId],
            [
                'verdict' => $verdict,
                'events' => $events,
                'payouts' => $payouts,
                'settle_amount' => round($settleAmount, 2),
                'issues' => $issues,
                'analyzed_at' => now(),
                'queued_at' => null,
                'queued_action' => null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        self::$verdictMemo = null;
    }

    private function storeVerdict(int $organizerId, array $result): void
    {
        self::putVerdict($organizerId, $result['verdict'], $result['totals']['events'], $result['totals']['payouts'],
            $result['totals']['settle_amount'], count($result['issues']));
    }

    /** Pune organizatorii în coadă (contorul din Balanțe se calculează de aici). */
    public static function markQueued(array $organizerIds, string $action): void
    {
        if (! self::tableReady()) {
            return;
        }

        foreach ($organizerIds as $id) {
            DB::table(self::VERDICTS_TABLE)->updateOrInsert(
                ['marketplace_organizer_id' => (int) $id],
                ['queued_at' => now(), 'queued_action' => $action, 'updated_at' => now()]
            );
        }
        self::$verdictMemo = null;
    }

    /** Jobul s-a terminat fără verdict nou (ex. eroare): iese din coadă. */
    public static function clearQueued(int $organizerId): void
    {
        if (self::tableReady()) {
            DB::table(self::VERDICTS_TABLE)->where('marketplace_organizer_id', $organizerId)
                ->update(['queued_at' => null, 'queued_action' => null]);
            self::$verdictMemo = null;
        }
    }

    public static function queuedCount(array $organizerIds): int
    {
        return self::tableReady()
            ? DB::table(self::VERDICTS_TABLE)->whereIn('marketplace_organizer_id', $organizerIds)->whereNotNull('queued_at')->count()
            : 0;
    }

    /** @return array<int, array{verdict: ?string, events: int, payouts: int, settle_amount: float, issues: int, at: ?string, queued: bool}> */
    public static function allVerdicts(): array
    {
        if (self::$verdictMemo !== null) {
            return self::$verdictMemo;
        }
        if (! self::tableReady()) {
            return self::$verdictMemo = [];
        }

        return self::$verdictMemo = DB::table(self::VERDICTS_TABLE)->get()
            ->mapWithKeys(fn ($r) => [(int) $r->marketplace_organizer_id => [
                'verdict' => $r->verdict,
                'events' => (int) $r->events,
                'payouts' => (int) $r->payouts,
                'settle_amount' => (float) $r->settle_amount,
                'issues' => (int) $r->issues,
                'at' => $r->analyzed_at,
                'queued' => $r->queued_at !== null,
            ]])->all();
    }

    public static function cachedVerdict(int $organizerId): ?array
    {
        $v = self::allVerdicts()[$organizerId] ?? null;

        return $v && $v['verdict'] !== null ? $v : null;
    }

    /** Organizatorii (ai evenimentului) care au cel puțin o ciornă „În așteptare” pe un eveniment vechi. */
    public static function candidateOrganizerIds(int $marketplaceId): array
    {
        $cutoff = self::cutoff();

        return MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->where('status', 'pending')
            ->whereNotNull('event_id')
            ->with('event')
            ->get()
            ->filter(fn ($p) => $p->event && $p->event->marketplace_organizer_id
                && (! ($end = $p->event->getEffectiveEndDatetime()) || $end->lte($cutoff)))
            ->map(fn ($p) => (int) $p->event->marketplace_organizer_id)
            ->unique()
            ->values()
            ->all();
    }

    // ---------------------------------------------------------------

    private function label(MarketplacePayout $p): string
    {
        return ($p->decont_series ?: $p->reference) . ' (#' . $p->id . ')';
    }

    private function title(Event $event): string
    {
        $t = $event->title;
        if (is_string($t) && str_starts_with(trim($t), '{')) {
            $t = json_decode($t, true) ?: $t;
        }
        if (is_array($t)) {
            $t = $t['ro'] ?? $t['en'] ?? (collect($t)->first() ?: null);
        }

        return $t ?: ('Eveniment #' . $event->id);
    }
}
