<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplacePayout;
use App\Services\EventAlertsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Soldurile din trecut ale organizatorilor AmBilet: evenimentele încheiate
 * înainte de mutarea pe Tixello au fost decontate și plătite în sistemul
 * vechi, dar aici au rămas cu deconturi deschise (ciornele automate din
 * martie–mai 2026). Sistemul vechi nu are evidența plăților, așa că
 * presupunerea e că s-a plătit tot: soldul acelor evenimente se închide la 0.
 *
 * analyze() doar citește. settle() reface analiza și, dacă e curată, trece
 * deconturile în „plătit” fără fluxul normal (fără email, PDF, avans,
 * ledger). Starea veche se păstrează în jurnalul de activitate, pe
 * organizator, ca revert() să o poată pune la loc.
 */
class LegacySettlementService
{
    public const MARKETPLACE_ID = 1;

    /** Ultima zi de final a evenimentului (inclusiv, ora României). */
    public const CUTOFF_DATE = '2026-04-15';

    /** Peste această ajustare a ciornei verdictul e „atenție” și cere confirmare. */
    public const WARN_ADJUSTMENT = 500.0;

    public const REFERENCE = 'Decontat în sistemul anterior AmBilet';

    public const LOG_NAME = 'legacy-settlement';

    public const VERDICT_NONE = 'none';
    public const VERDICT_OK = 'ok';
    public const VERDICT_WARN = 'warn';
    public const VERDICT_BLOCKED = 'blocked';
    public const VERDICT_SETTLED = 'settled';

    private const OPEN_STATUSES = ['pending', 'approved', 'processing'];

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
     * Situația organizatorului. Deconturile luate în calcul sunt cele deschise,
     * pe evenimente încheiate până la CUTOFF_DATE, unde fie decontul, fie
     * evenimentul aparține organizatorului (ca să prindă și cazurile în care
     * evenimentul a fost mutat după ce s-a creat ciorna).
     */
    public function analyze(MarketplaceOrganizer $organizer): array
    {
        $cutoff = self::cutoff();
        $orgId = (int) $organizer->id;

        $payouts = MarketplacePayout::where('marketplace_client_id', $organizer->marketplace_client_id)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('event_id')
            ->where(fn ($q) => $q->where('marketplace_organizer_id', $orgId)
                ->orWhereHas('event', fn ($e) => $e->where('marketplace_organizer_id', $orgId)))
            ->with(['event', 'organizer:id,name'])
            ->orderBy('id')
            ->get();

        $events = [];
        $issues = [];

        foreach ($payouts->groupBy('event_id') as $eventId => $rows) {
            /** @var Event $event */
            $event = $rows->first()->event;
            $end = $event?->getEffectiveEndDatetime();

            if (! $event) {
                continue;
            }
            if ($end && $end->gt($cutoff)) {
                continue;
            }

            $row = $this->analyzeEvent($event, $rows, $orgId, $end);
            $events[] = $row;
            foreach ($row['issues'] as $text) {
                $issues[] = ['event_id' => $event->id, 'event' => $row['title'], 'text' => $text];
            }
        }

        usort($events, fn ($a, $b) => strcmp($a['sort_date'], $b['sort_date']));

        $last = $this->lastRun($organizer);
        $verdict = match (true) {
            empty($events) => $last && $last->description === 'settled' ? self::VERDICT_SETTLED : self::VERDICT_NONE,
            ! empty($issues) => self::VERDICT_BLOCKED,
            collect($events)->contains(fn ($e) => $e['warn']) => self::VERDICT_WARN,
            default => self::VERDICT_OK,
        };

        $result = [
            'organizer_id' => $orgId,
            'organizer' => $organizer->name,
            'verdict' => $verdict,
            'events' => $events,
            'issues' => $issues,
            'totals' => [
                'events' => count($events),
                'payouts' => collect($events)->sum(fn ($e) => count($e['payouts'])),
                'draft_amount' => round(collect($events)->sum('draft_amount'), 2),
                'settle_amount' => round(collect($events)->sum('settle_amount'), 2),
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

    private function analyzeEvent(Event $event, Collection $rows, int $orgId, ?Carbon $end): array
    {
        $issues = [];
        $title = $this->title($event);

        if (! $end) {
            $issues[] = 'Evenimentul nu are dată de final, nu se poate stabili dacă e din sistemul vechi.';
        }

        foreach ($rows as $p) {
            if ((int) $p->marketplace_organizer_id !== (int) $event->marketplace_organizer_id) {
                $issues[] = "Decontul {$this->label($p)} e pe organizatorul #{$p->marketplace_organizer_id} ("
                    . ($p->organizer?->name ?? '?') . '), dar evenimentul e acum pe #' . $event->marketplace_organizer_id
                    . '. Evenimentul a fost mutat după crearea ciornei.';
            }
            if (in_array($p->status, ['approved', 'processing'], true)) {
                $issues[] = "Decontul {$this->label($p)} e aprobat (creat " . $p->created_at?->format('d.m.Y')
                    . '), nu e o ciornă veche. Dacă a fost plătit, finalizează-l din pagina decontului.';
            }
            if ($p->status === 'pending' && $p->created_at && $p->created_at->gte(Carbon::parse(MarketplacePayout::LEGACY_SETTLEMENT_FREEZE))) {
                $issues[] = "Ciorna {$this->label($p)} e creată după " . Carbon::parse(MarketplacePayout::LEGACY_SETTLEMENT_FREEZE)->format('d.m.Y')
                    . ', nu face parte din ciornele vechi.';
            }
        }

        $pending = $rows->where('status', 'pending');
        if ($pending->count() > 1) {
            $issues[] = 'Are ' . $pending->count() . ' ciorne deschise (' . $pending->map(fn ($p) => $this->label($p))->implode(', ')
                . '). Una e dublură.';
        }

        $completed = MarketplacePayout::where('event_id', $event->id)->where('status', 'completed')->get(['id', 'reference', 'decont_series', 'amount']);
        foreach ($pending as $p) {
            $twin = $completed->first(fn ($c) => abs((float) $c->amount - (float) $p->amount) < 0.01);
            if ($twin) {
                $issues[] = "Ciorna {$this->label($p)} repetă suma decontului deja plătit {$this->label($twin)} ("
                    . number_format((float) $twin->amount, 2, ',', '.') . ' lei).';
            }
        }

        // Netul evenimentului după rezolvare: ciorna e creată înainte de
        // LEGACY_SETTLEMENT_FREEZE, deci odată plătită vânzările importate
        // intră în net (MarketplacePayout::balanceBreakdown).
        $full = (float) ($this->breakdown->build($event, excludePos: true)['total_net'] ?? 0);
        $paid = (float) $completed->sum('amount');
        $openOther = (float) $rows->whereIn('status', ['approved', 'processing'])->sum('amount');
        // Soldul de acum: ciornele (pending) nu se numără nicăieri.
        $balanceBefore = round($this->currentNet($event, $full) - $paid - $openOther, 2);

        $draftAmount = (float) $pending->sum('amount');
        $target = round($full - $paid - $openOther, 2);

        if (empty($issues) && $pending->count() === 1 && $target < -0.004) {
            $issues[] = 'Pe eveniment s-a plătit deja mai mult decât netul (' . number_format($full, 2, ',', '.')
                . ' lei net, ' . number_format($paid, 2, ',', '.') . ' lei plătit).';
        }

        $settleAmount = $pending->count() === 1 ? max(0.0, $target) : $draftAmount;
        $adjustment = round($settleAmount - $draftAmount, 2);
        $date = $event->event_date ?? $event->range_start_date ?? $event->starts_at;

        return [
            'event_id' => $event->id,
            'title' => $title,
            'date' => $end?->format('d.m.Y'),
            'sort_date' => $date?->format('Y-m-d') ?? '0000-00-00',
            'event_organizer_id' => (int) $event->marketplace_organizer_id,
            'payouts' => $rows->map(fn ($p) => [
                'id' => $p->id,
                'label' => $this->label($p),
                'status' => $p->status,
                'amount' => (float) $p->amount,
                'organizer_id' => (int) $p->marketplace_organizer_id,
                'created_at' => $p->created_at?->format('d.m.Y'),
            ])->values()->all(),
            'net' => round($full, 2),
            'paid' => round($paid, 2),
            'draft_amount' => round($draftAmount, 2),
            'settle_amount' => round($settleAmount, 2),
            'adjustment' => $adjustment,
            'balance_before' => $balanceBefore,
            'balance_after' => empty($issues) ? round($full - $paid - $openOther - $settleAmount, 2) : $balanceBefore,
            'warn' => abs($adjustment) > self::WARN_ADJUSTMENT,
            'issues' => array_values(array_unique($issues)),
        ];
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
    // Rezolvare / anulare
    // ---------------------------------------------------------------

    /**
     * Reface analiza și rezolvă doar dacă verdictul e ok (sau atenție, cu
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
                throw new \RuntimeException('Ajustări peste ' . number_format(self::WARN_ADJUSTMENT, 0, ',', '.') . ' lei: rezolvarea cere confirmare.');
            }
            if (! in_array($analysis['verdict'], [self::VERDICT_OK, self::VERDICT_WARN], true)) {
                throw new \RuntimeException('Nu există deconturi vechi de rezolvat.');
            }

            $changes = [];
            $settledSum = 0.0;

            DB::transaction(function () use ($analysis, $organizer, &$changes, &$settledSum) {
                $today = now()->format('d.m.Y');

                foreach ($analysis['events'] as $ev) {
                    foreach ($ev['payouts'] as $info) {
                        $p = MarketplacePayout::whereKey($info['id'])->lockForUpdate()->first();
                        if (! $p || $p->status !== 'pending' || (int) $p->marketplace_organizer_id !== (int) $organizer->id) {
                            throw new \RuntimeException("Decontul {$info['label']} s-a schimbat între timp. Reanalizează.");
                        }

                        $old = array_intersect_key($p->getRawOriginal(), array_flip(['amount', 'status', 'approved_at', 'completed_at', 'payment_reference', 'admin_notes']));
                        $note = 'Marcat plătit ' . $today . ': decontat și plătit în sistemul anterior, înainte de mutarea pe Tixello.';
                        if (abs($ev['adjustment']) >= 0.01) {
                            $note .= ' Suma ciornei ' . number_format((float) $p->amount, 2, ',', '.') . ' lei a fost adusă la netul evenimentului, '
                                . number_format($ev['settle_amount'], 2, ',', '.') . ' lei.';
                        }

                        $new = [
                            'amount' => $ev['settle_amount'],
                            'status' => 'completed',
                            'approved_at' => $old['approved_at'] ?? $p->getRawOriginal('created_at'),
                            'completed_at' => $p->getRawOriginal('created_at'),
                            'payment_reference' => $p->payment_reference ?: self::REFERENCE,
                            'admin_notes' => trim(($p->admin_notes ? $p->admin_notes . "\n" : '') . $note),
                        ];

                        // Update pe query: fără evenimente de model (email,
                        // PDF de decont, compensare din avans).
                        MarketplacePayout::whereKey($p->id)->update($new);

                        $changes[] = ['payout_id' => $p->id, 'event_id' => $ev['event_id'], 'old' => $old, 'new' => $new];
                        $settledSum += (float) $ev['settle_amount'];
                    }
                }

                $organizer->increment('total_paid_out', round($settledSum, 2));
            });

            activity(self::LOG_NAME)
                ->performedOn($organizer)
                ->causedBy($admin)
                ->withProperties([
                    'cutoff' => self::CUTOFF_DATE,
                    'payouts' => $changes,
                    'paid_out_added' => round($settledSum, 2),
                    'totals' => $analysis['totals'],
                ])
                ->log('settled');

            $this->afterChange($organizer, collect($changes)->pluck('event_id')->unique()->all());
            $this->analyze($organizer->fresh());

            return $analysis;
        });
    }

    /** Pune deconturile ultimei rezolvări înapoi exact cum erau. */
    public function revert(MarketplaceOrganizer $organizer, ?Model $admin): int
    {
        return Cache::lock('legacy_settle:org:' . $organizer->id, 900)->block(5, function () use ($organizer, $admin) {
            $last = $this->lastRun($organizer);
            if (! $last || $last->description !== 'settled') {
                throw new \RuntimeException('Nu există o rezolvare de anulat.');
            }

            $rows = $last->properties['payouts'] ?? [];
            $restored = 0;

            DB::transaction(function () use ($rows, $organizer, $last, &$restored) {
                foreach ($rows as $r) {
                    $restored += MarketplacePayout::whereKey($r['payout_id'])->where('status', 'completed')->update($r['old']);
                }
                $organizer->decrement('total_paid_out', (float) ($last->properties['paid_out_added'] ?? 0));
            });

            activity(self::LOG_NAME)
                ->performedOn($organizer)
                ->causedBy($admin)
                ->withProperties(['reverted_activity_id' => $last->id, 'restored' => $restored])
                ->log('reverted');

            $this->afterChange($organizer, collect($rows)->pluck('event_id')->unique()->all());
            $this->analyze($organizer->fresh());

            return $restored;
        });
    }

    private function afterChange(MarketplaceOrganizer $organizer, array $eventIds): void
    {
        foreach ($eventIds as $id) {
            EventAlertsService::flushCache((int) $id);
        }
        Cache::forget('organizer_balance_page_events:v3:' . $organizer->id);
        $organizer->fresh()->recomputeBalances($this->breakdown);
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

    public static function verdictKey(int $organizerId): string
    {
        return 'legacy_settle:verdict:' . $organizerId;
    }

    private function storeVerdict(int $organizerId, array $result): void
    {
        Cache::put(self::verdictKey($organizerId), [
            'verdict' => $result['verdict'],
            'events' => $result['totals']['events'],
            'payouts' => $result['totals']['payouts'],
            'settle_amount' => $result['totals']['settle_amount'],
            'issues' => count($result['issues']),
            'at' => now()->toIso8601String(),
        ], now()->addDays(30));
    }

    public static function cachedVerdict(int $organizerId): ?array
    {
        return Cache::get(self::verdictKey($organizerId));
    }

    /** Organizatorii marketplace-ului care au cel puțin un decont deschis pe un eveniment vechi. */
    public static function candidateOrganizerIds(int $marketplaceId): array
    {
        $cutoff = self::cutoff();

        return MarketplacePayout::where('marketplace_client_id', $marketplaceId)
            ->whereIn('status', self::OPEN_STATUSES)
            ->whereNotNull('event_id')
            ->with('event')
            ->get()
            ->filter(fn ($p) => $p->event && (! ($end = $p->event->getEffectiveEndDatetime()) || $end->lte($cutoff)))
            ->flatMap(fn ($p) => [(int) $p->marketplace_organizer_id, (int) $p->event->marketplace_organizer_id])
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
