<?php

namespace App\Console\Commands;

use App\Support\TestPos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Repara vanzarile Test POS scrise gresit ca vanzari reale.
 *
 * Pana la fix, aplicatiile POS nu primeau meta.is_test si vindeau biletul
 * Test POS cu source 'pos_app'; ruta locatiei le scria 'venue_owner_pos'
 * inainte sa decida sursa pe server. Comenzile astea intrau in factura POS,
 * sold, dashboard si orice SUM(orders.total).
 *
 *  - comanda care are NUMAI bilete Test POS  → source = 'pos_test'
 *    (sursa veche se pastreaza in meta.original_source)
 *  - comanda mixta (test + real)              → doar listata, decizia e manuala
 *  - tipurile Test POS                        → is_independent_stock = true
 *    (nu mai consuma din capacitatea generala a evenimentului)
 *
 * Idempotenta. Scrie prin DB::table ca sa nu declanseze observerele de
 * comanda (email, CAPI, decont). Ruleaza intai cu --dry-run.
 */
class FixTestPosOrdersCommand extends Command
{
    protected $signature = 'test-tickets:fix-orders
        {--event= : Doar pentru un eveniment}
        {--dry-run : Arata ce s-ar schimba, fara sa scrie}';

    protected $description = 'Reclasifica drept pos_test comenzile care contin doar bilete Test POS.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $eventId = $this->option('event');

        $typeQuery = DB::table('ticket_types')->whereRaw(TestPos::typeSql('ticket_types'));
        if ($eventId) {
            $typeQuery->where('event_id', (int) $eventId);
        }
        $testTypeIds = $typeQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($testTypeIds)) {
            $this->info('Niciun tip de bilet Test POS.');

            return self::SUCCESS;
        }

        $orderIds = DB::table('tickets')
            ->whereIn('ticket_type_id', $testTypeIds)
            ->whereNotNull('order_id')
            ->distinct()
            ->pluck('order_id');

        $orders = DB::table('orders')
            ->whereIn('id', $orderIds)
            ->where(fn ($q) => $q->whereNull('source')->orWhereNotIn('source', TestPos::ORDER_SOURCES))
            ->orderBy('id')
            ->get(['id', 'order_number', 'event_id', 'source', 'status', 'total', 'meta']);

        $pure = [];
        $mixed = [];
        foreach ($orders as $o) {
            $real = DB::table('tickets')
                ->where('order_id', $o->id)
                ->where(fn ($q) => $q->whereNull('ticket_type_id')->orWhereNotIn('ticket_type_id', $testTypeIds))
                ->count();
            $row = [$o->id, $o->order_number, $o->event_id, $o->source ?? '-', $o->status, $o->total, $real];
            if ($real === 0) {
                $pure[] = $row;
            } else {
                $mixed[] = $row;
            }
        }

        $headers = ['id', 'comanda', 'eveniment', 'sursa', 'status', 'total', 'bilete reale'];

        $this->info(count($pure).' comenzi numai cu bilete Test POS → pos_test');
        if ($pure) {
            $this->table($headers, $pure);
        }
        if ($mixed) {
            $this->warn(count($mixed).' comenzi MIXTE (test + real) — neatinse, de rezolvat manual:');
            $this->table($headers, $mixed);
        }

        $typesToFix = DB::table('ticket_types')
            ->whereIn('id', $testTypeIds)
            ->where(fn ($q) => $q->whereNull('is_independent_stock')->orWhere('is_independent_stock', false))
            ->count();
        $this->info("{$typesToFix} tipuri Test POS → stoc independent");

        if ($dryRun) {
            $this->comment('--dry-run: nimic scris.');

            return self::SUCCESS;
        }

        $events = [];
        DB::transaction(function () use ($pure, $orders, $testTypeIds, &$events) {
            $byId = $orders->keyBy('id');
            foreach ($pure as $row) {
                $o = $byId[$row[0]];
                $meta = json_decode($o->meta ?? '[]', true);
                $meta = is_array($meta) ? $meta : [];
                $meta['original_source'] = $o->source;
                $meta['reclassified_test_pos_at'] = now()->toIso8601String();

                DB::table('orders')->where('id', $o->id)->update([
                    'source' => 'pos_test',
                    'meta' => json_encode($meta),
                    'updated_at' => now(),
                ]);
                if ($o->event_id) {
                    $events[(int) $o->event_id] = true;
                }
            }

            DB::table('ticket_types')
                ->whereIn('id', $testTypeIds)
                ->where(fn ($q) => $q->whereNull('is_independent_stock')->orWhere('is_independent_stock', false))
                ->update(['is_independent_stock' => true, 'updated_at' => now()]);
        });

        foreach (array_keys($events) as $id) {
            try {
                \App\Services\EventStatsCache::forget($id);
            } catch (\Throwable $e) {
                // cache-ul expira oricum
            }
        }

        $this->info('Gata: '.count($pure).' comenzi reclasificate.');

        return self::SUCCESS;
    }
}
