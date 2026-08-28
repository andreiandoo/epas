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
        {--fix-overcount : Also lower quota_sold where it exceeds active tickets (review first — risky for imports)}
        {--notify= : Email address to notify when drift is found. Silent when nothing to report — safe for scheduled runs.}';

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

        // Classify over-counts by import-safety. SAFE to lower only when every
        // unit counted in quota_sold has a ticket ROW (total tickets >=
        // quota_sold): then the excess over `active` is just cancelled/refunded/
        // expired rows whose release never decremented the counter (native
        // phantom-sold). When total tickets < quota_sold, some sold units have
        // NO row — a historical import set quota_sold directly — and lowering
        // there would raise availability and enable oversell, so leave it.
        foreach ($over as $r) {
            $r->total = (int) DB::table('tickets')->where('ticket_type_id', $r->id)->count();
            $r->safe = $r->total >= (int) $r->quota_sold;
        }
        $overSafe = $over->filter(fn ($r) => $r->safe)->values();
        $overRisky = $over->reject(fn ($r) => $r->safe)->values();

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

        $this->comment('OVER-count SAFE (native phantom — every sold unit has a ticket row): ' . $overSafe->count());
        foreach ($overSafe as $r) {
            $nm = $label($r->name);
            $this->line("  tt={$r->id} ev={$r->event_id} \"{$nm}\" quota_total={$r->quota_total} quota_sold={$r->quota_sold} -> active={$r->active} (total_tickets={$r->total})");
        }
        $this->comment('OVER-count RISKY (fewer ticket rows than quota_sold — likely import, LEFT ALONE): ' . $overRisky->count());
        foreach ($overRisky as $r) {
            $nm = $label($r->name);
            $this->line("  tt={$r->id} ev={$r->event_id} \"{$nm}\" quota_total={$r->quota_total} quota_sold={$r->quota_sold} active={$r->active} total_tickets={$r->total}");
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
            foreach ($overSafe as $r) {
                TicketType::where('id', $r->id)->update(['quota_sold' => $r->active]);
                $overFixed++;
            }
            $this->warn("Lowered {$overFixed} SAFE over-count type(s) to the real active count. {$overRisky->count()} RISKY (likely import) left untouched.");
        } elseif ($over->isNotEmpty()) {
            $this->comment('Over-count types NOT changed. Re-run with --fix-overcount to lower ONLY the SAFE (native) ones; RISKY/import types are never touched.');
        }

        // Optional notification for scheduled runs. Silent when there is
        // nothing to report so an hourly cron doesn't spam 24 empty emails
        // a day. Sends when we actually fixed under-counts OR when there are
        // over-counts left untouched that a human should decide about.
        if ($notifyEmail = $this->option('notify')) {
            $shouldNotify = $under->isNotEmpty() || $over->isNotEmpty();
            if ($shouldNotify) {
                $host = gethostname() ?: 'unknown-host';
                $subject = 'quota_sold drift on ' . $host . ': ' . $under->count() . ' under, ' . $over->count() . ' over';

                $body = "Automated quota_sold audit report\n";
                $body .= "Host: {$host}\n";
                $body .= "Time: " . now()->toIso8601String() . "\n\n";
                $body .= "UNDER-count (auto-fixed, was OVERSELL risk): {$under->count()}\n";
                foreach ($under as $r) {
                    $nm = $label($r->name);
                    $body .= "  tt={$r->id} ev={$r->event_id} \"{$nm}\" quota_total={$r->quota_total} quota_sold={$r->quota_sold} -> active={$r->active}\n";
                }
                $body .= "\nOVER-count SAFE (fixable with --fix-overcount): {$overSafe->count()}\n";
                foreach ($overSafe as $r) {
                    $nm = $label($r->name);
                    $body .= "  tt={$r->id} ev={$r->event_id} \"{$nm}\" quota_sold={$r->quota_sold} > active={$r->active} (total_tickets={$r->total})\n";
                }
                $body .= "\nOVER-count RISKY (import-suspected, NOT touched): {$overRisky->count()}\n";
                foreach ($overRisky as $r) {
                    $nm = $label($r->name);
                    $body .= "  tt={$r->id} ev={$r->event_id} \"{$nm}\" quota_sold={$r->quota_sold} active={$r->active} total_tickets={$r->total}\n";
                }
                $body .= "\nRun manually to review or apply --fix-overcount:\n";
                $body .= "  php artisan tickets:audit-quota-sold\n";

                try {
                    \Illuminate\Support\Facades\Mail::raw($body, function ($msg) use ($notifyEmail, $subject) {
                        $msg->to($notifyEmail)->subject($subject);
                    });
                    $this->info("Notification sent to {$notifyEmail}.");
                } catch (\Throwable $e) {
                    // Don't fail the whole command if mail is misconfigured
                    // — the fix already ran and the report is still in the log.
                    $this->error("Notification failed: " . $e->getMessage());
                }
            }
        }

        return self::SUCCESS;
    }
}
