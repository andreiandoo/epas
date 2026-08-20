<?php

namespace App\Console\Commands;

use App\Models\TicketType;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Audit + repair ticket_types whose denormalised quota_sold counter has drifted
 * from the real number of active tickets (valid/used/pending).
 *
 * Two directions, treated differently:
 *   - UNDER-count (quota_sold < active): dangerous. The checkout cap is
 *     `quota_sold + qty <= quota_total`, so a too-low quota_sold lets the event
 *     oversell. Raising quota_sold to the real active-ticket count is ALWAYS
 *     safe (active tickets are real rows — quota_sold must be at least that),
 *     so this is auto-fixed.
 *   - OVER-count (quota_sold > active): phantom-sold, blocks legit buyers, but
 *     NOT an oversell risk. Lowering it is risky for imported/legacy types whose
 *     quota_sold was set directly with fewer (or no) ticket rows, so it is only
 *     REPORTED unless --fix-overcount is passed.
 *
 * Only ticket types that HAVE active tickets are considered (the join), so
 * pure-import types with a quota_sold but no ticket rows are never touched.
 *
 * Usage:
 *   php artisan tickets:audit-quota-sold --dry-run
 *   php artisan tickets:audit-quota-sold
 *   php artisan tickets:audit-quota-sold --event=4748
 *   php artisan tickets:audit-quota-sold --fix-overcount
 */
class AuditQuotaSold extends Command
{
    protected $signature = 'tickets:audit-quota-sold
        {--dry-run : Report only, do not write}
        {--event= : Limit to a single event id}
        {--fix-overcount : Also lower quota_sold where it exceeds active tickets (review first — risky for imports)}';

    protected $description = 'Find/repair ticket types whose quota_sold drifted from the real active-ticket count';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');

        $active = DB::table('tickets')
            ->select('ticket_type_id', DB::raw('count(*) as active'))
            ->whereIn('status', ['valid', 'used', 'pending'])
            ->groupBy('ticket_type_id');

        $query = DB::table('ticket_types as tt')
            ->joinSub($active, 'a', 'a.ticket_type_id', '=', 'tt.id')
            ->whereColumn('tt.quota_sold', '!=', 'a.active');

        if ($eventId = $this->option('event')) {
            $query->where('tt.event_id', (int) $eventId);
        }

        $rows = $query
            ->select('tt.id', 'tt.event_id', 'tt.name', 'tt.quota_total', 'tt.quota_sold', 'a.active')
            ->get();

        $under = $rows->filter(fn ($r) => $r->quota_sold < $r->active)->values();
        $over = $rows->filter(fn ($r) => $r->quota_sold > $r->active)->values();

        $label = function ($name) {
            $decoded = json_decode((string) $name, true);
            if (is_array($decoded)) {
                return (string) ($decoded['ro'] ?? reset($decoded) ?: '');
            }
            return (string) $name;
        };

        $this->info('UNDER-count (quota_sold < active) — OVERSELL RISK: ' . $under->count());
        foreach ($under as $r) {
            $nm = $label($r->name);
            $this->line("  tt={$r->id} ev={$r->event_id} \"{$nm}\" quota_total={$r->quota_total} quota_sold={$r->quota_sold} -> active={$r->active}");
        }

        $this->comment('OVER-count (quota_sold > active) — phantom sold, may be legit import: ' . $over->count());
        foreach ($over as $r) {
            $nm = $label($r->name);
            $this->line("  tt={$r->id} ev={$r->event_id} \"{$nm}\" quota_total={$r->quota_total} quota_sold={$r->quota_sold} -> active={$r->active}");
        }

        if ($dry) {
            $this->comment('Dry run — nothing written.');
            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($under as $r) {
            TicketType::where('id', $r->id)->update(['quota_sold' => $r->active]);
            $fixed++;
        }
        $this->info("Fixed {$fixed} under-count type(s): quota_sold raised to the real active count.");

        if ($this->option('fix-overcount')) {
            $overFixed = 0;
            foreach ($over as $r) {
                TicketType::where('id', $r->id)->update(['quota_sold' => $r->active]);
                $overFixed++;
            }
            $this->warn("Also lowered {$overFixed} over-count type(s) (--fix-overcount).");
        } elseif ($over->isNotEmpty()) {
            $this->comment('Over-count types were NOT changed. Review them, then re-run with --fix-overcount if correct.');
        }

        return self::SUCCESS;
    }
}
