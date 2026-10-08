<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\EventStatsCache;
use App\Services\Marketplace\SeriesAllocator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Muta bilete deja vandute de pe un tip de bilet pe altul, in acelasi eveniment.
 *
 * Caz: tipuri "Presale" ramase la vanzare dupa expirarea reducerii — biletele
 * s-au vandut la pretul intreg, deci apartin de fapt categoriei finale.
 *
 * Pentru fiecare bilet:
 *  - tickets.ticket_type_id            → tipul nou (tipul vechi ramane in
 *    meta.moved_from_ticket_type_id, pentru audit si anulare)
 *  - order_items.ticket_type_id + name → tipul nou
 *  - orders.meta.commission_details    → numele tipului nou (comisionul per
 *    bilet se cauta dupa nume, vezi Ticket::getCommissionPerUnit)
 *  - ticket_types.quota_sold           → scade pe tipul vechi, creste pe cel nou
 * La final resincronizeaza seriile fiscale si goleste cache-ul de statistici.
 *
 * Refuza mutarea daca tipurile au pret sau comision diferit (totalurile nu ar
 * mai iesi la fel), daca tipul nou nu are stoc sau daca o linie de comanda ar
 * fi mutata doar partial.
 *
 * NU atinge deconturile: ticket_breakdown tine cantitati pe tip, deci un decont
 * care include deja biletele trebuie corectat separat — comanda doar le listeaza.
 *
 * Scrie prin DB::table ca sa nu declanseze observere (email, CAPI). Ruleaza
 * intai cu --dry-run.
 *
 *   php artisan tickets:move-type --event=4720 --tickets=376180,376181 --map=11907:11910 --dry-run
 */
class MoveTicketsToTicketTypeCommand extends Command
{
    protected $signature = 'tickets:move-type
        {--event= : ID-ul evenimentului}
        {--tickets= : ID-urile biletelor, separate prin virgula}
        {--map=* : Pereche tip_vechi:tip_nou (se poate repeta)}
        {--dry-run : Arata ce s-ar schimba, fara sa scrie}';

    protected $description = 'Muta bilete vandute de pe un tip de bilet pe altul (acelasi eveniment, acelasi pret).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $eventId = (int) $this->option('event');
        $ticketIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $this->option('tickets'))))));

        $map = [];
        foreach ((array) $this->option('map') as $pair) {
            if (!preg_match('/^(\d+):(\d+)$/', trim((string) $pair), $m) || $m[1] === $m[2]) {
                $this->error("Pereche invalida: {$pair} (format tip_vechi:tip_nou)");

                return self::FAILURE;
            }
            $map[(int) $m[1]] = (int) $m[2];
        }

        if (!$eventId || empty($ticketIds) || empty($map)) {
            $this->error('Sunt obligatorii --event, --tickets si cel putin un --map.');

            return self::FAILURE;
        }

        $types = DB::table('ticket_types')
            ->whereIn('id', array_merge(array_keys($map), array_values($map)))
            ->get()
            ->keyBy('id');

        foreach ($map as $fromId => $toId) {
            $from = $types->get($fromId);
            $to = $types->get($toId);
            if (!$from || !$to) {
                $this->error("Tip de bilet inexistent in perechea {$fromId}:{$toId}.");

                return self::FAILURE;
            }
            if ((int) $from->event_id !== $eventId || (int) $to->event_id !== $eventId) {
                $this->error("Perechea {$fromId}:{$toId} nu apartine evenimentului {$eventId}.");

                return self::FAILURE;
            }
            foreach (['price_cents', 'currency', 'commission_type', 'commission_rate', 'commission_fixed', 'commission_mode'] as $col) {
                if ((string) ($from->{$col} ?? '') !== (string) ($to->{$col} ?? '')) {
                    $this->error("Perechea {$fromId}:{$toId} difera la {$col} ({$from->{$col}} vs {$to->{$col}}) — mutarea ar schimba totalurile.");

                    return self::FAILURE;
                }
            }
        }

        $tickets = DB::table('tickets')->whereIn('id', $ticketIds)->orderBy('id')->get();
        $missing = array_diff($ticketIds, $tickets->pluck('id')->map(fn ($id) => (int) $id)->all());
        if (!empty($missing)) {
            $this->error('Bilete inexistente: ' . implode(', ', $missing));

            return self::FAILURE;
        }

        $orders = DB::table('orders')->whereIn('id', $tickets->pluck('order_id')->filter()->unique())->get()->keyBy('id');

        $rows = [];
        $perType = [];
        foreach ($tickets as $t) {
            $fromId = (int) $t->ticket_type_id;
            if (!isset($map[$fromId])) {
                $this->error("Biletul {$t->id} este pe tipul {$fromId}, care nu apare in --map.");

                return self::FAILURE;
            }
            if (!in_array($t->status, ['valid', 'used'], true)) {
                $this->error("Biletul {$t->id} are status {$t->status} — se muta doar bilete valide.");

                return self::FAILURE;
            }
            if (!$t->order_id || !$orders->has($t->order_id)) {
                $this->error("Biletul {$t->id} nu are comanda.");

                return self::FAILURE;
            }
            $perType[$fromId] = ($perType[$fromId] ?? 0) + 1;
            $order = $orders->get($t->order_id);
            $rows[] = [
                $t->id,
                $t->code,
                $order->order_number ?? $order->id,
                $order->status,
                $t->price,
                $this->label($types->get($fromId)->name),
                $this->label($types->get($map[$fromId])->name),
            ];
        }

        // O linie de comanda se muta intreaga: order_items tine un singur tip.
        $items = [];
        foreach ($tickets->groupBy(fn ($t) => $t->order_id . '|' . $t->ticket_type_id) as $group) {
            $first = $group->first();
            $itemIds = $group->pluck('order_item_id')->filter()->unique();
            $itemQuery = DB::table('order_items')->where('order_id', $first->order_id)->where('ticket_type_id', $first->ticket_type_id);
            if ($itemIds->isNotEmpty()) {
                $itemQuery->whereIn('id', $itemIds);
            }
            foreach ($itemQuery->get() as $item) {
                $others = DB::table('tickets')
                    ->where('order_item_id', $item->id)
                    ->whereNotIn('id', $ticketIds)
                    ->count();
                if ($others > 0) {
                    $this->error("Linia de comanda {$item->id} (comanda {$first->order_id}) are {$others} bilete care nu sunt in lista — mutarea partiala nu e suportata.");

                    return self::FAILURE;
                }
                $items[(int) $item->id] = $map[(int) $first->ticket_type_id];
            }
        }

        foreach ($perType as $fromId => $n) {
            $from = $types->get($fromId);
            $to = $types->get($map[$fromId]);
            if ((int) ($from->quota_sold ?? 0) < $n) {
                $this->error("Tipul {$fromId} are quota_sold {$from->quota_sold}, mai mic decat cele {$n} bilete mutate.");

                return self::FAILURE;
            }
            if ($to->quota_total !== null && (int) $to->quota_total >= 0 && (int) ($to->quota_sold ?? 0) + $n > (int) $to->quota_total) {
                $this->error("Tipul {$to->id} nu are stoc pentru {$n} bilete ({$to->quota_sold}/{$to->quota_total}).");

                return self::FAILURE;
            }
        }

        $this->table(['Bilet', 'Cod', 'Comanda', 'Status comanda', 'Pret', 'De pe', 'Pe'], $rows);

        $stock = [];
        foreach ($perType as $fromId => $n) {
            $from = $types->get($fromId);
            $to = $types->get($map[$fromId]);
            $stock[] = [$this->label($from->name), $from->quota_sold, (int) $from->quota_sold - $n, $this->label($to->name), $to->quota_sold, (int) $to->quota_sold + $n];
        }
        $this->table(['Tip vechi', 'Vandute acum', 'Dupa', 'Tip nou', 'Vandute acum', 'Dupa'], $stock);
        $this->line('Linii de comanda mutate: ' . count($items));

        $this->reportPayouts($eventId, array_keys($perType));

        if ($dryRun) {
            $this->comment('Dry run — nu s-a scris nimic.');

            return self::SUCCESS;
        }

        $now = now();
        DB::transaction(function () use ($tickets, $orders, $items, $map, $types, $perType, $now) {
            foreach ($tickets as $t) {
                $meta = json_decode((string) ($t->meta ?? ''), true);
                $meta = is_array($meta) ? $meta : [];
                $meta['moved_from_ticket_type_id'] = (int) $t->ticket_type_id;
                $meta['moved_at'] = $now->toIso8601String();
                DB::table('tickets')->where('id', $t->id)->update([
                    'ticket_type_id' => $map[(int) $t->ticket_type_id],
                    'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]);
            }

            foreach ($items as $itemId => $toId) {
                DB::table('order_items')->where('id', $itemId)->update([
                    'ticket_type_id' => $toId,
                    'name' => $this->label($types->get($toId)->name),
                    'updated_at' => $now,
                ]);
            }

            foreach ($tickets->groupBy('order_id') as $orderId => $group) {
                $meta = json_decode((string) ($orders->get($orderId)->meta ?? ''), true);
                if (!is_array($meta) || empty($meta['commission_details']) || !is_array($meta['commission_details'])) {
                    continue;
                }
                $changed = false;
                foreach ($group->pluck('ticket_type_id')->unique() as $fromId) {
                    $fromName = $this->label($types->get((int) $fromId)->name);
                    $toName = $this->label($types->get($map[(int) $fromId])->name);
                    foreach ($meta['commission_details'] as $i => $cd) {
                        if (is_array($cd) && $this->label($cd['ticket_type'] ?? '') === $fromName) {
                            $meta['commission_details'][$i]['ticket_type'] = $toName;
                            $changed = true;
                        }
                    }
                }
                if ($changed) {
                    DB::table('orders')->where('id', $orderId)->update([
                        'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            foreach ($perType as $fromId => $n) {
                DB::table('ticket_types')->where('id', $fromId)->update(['quota_sold' => DB::raw('quota_sold - ' . (int) $n)]);
                DB::table('ticket_types')->where('id', $map[$fromId])->update(['quota_sold' => DB::raw('COALESCE(quota_sold, 0) + ' . (int) $n)]);
            }
        });

        Log::channel('marketplace')->info('tickets:move-type', [
            'event_id' => $eventId,
            'map' => $map,
            'ticket_ids' => $ticketIds,
            'order_item_ids' => array_keys($items),
        ]);

        try {
            $event = Event::find($eventId);
            if ($event) {
                app(SeriesAllocator::class)->syncForEvent($event);
            }
        } catch (\Throwable $e) {
            $this->warn('Seriile fiscale nu s-au resincronizat: ' . $e->getMessage());
        }
        EventStatsCache::forget($eventId);

        $this->info('Mutate ' . count($ticketIds) . ' bilete.');

        return self::SUCCESS;
    }

    /**
     * Deconturile active care au deja randuri pe tipurile vechi: cantitatile lor
     * raman pe tipul vechi si trebuie corectate separat.
     */
    private function reportPayouts(int $eventId, array $fromTypeIds): void
    {
        $payouts = DB::table('marketplace_payouts')
            ->where('event_id', $eventId)
            ->whereIn('status', ['completed', 'processing', 'approved', 'pending'])
            ->whereNotNull('ticket_breakdown')
            ->orderBy('id')
            ->get(['id', 'status', 'amount', 'ticket_breakdown']);

        $rows = [];
        foreach ($payouts as $p) {
            $breakdown = json_decode((string) $p->ticket_breakdown, true);
            foreach ((is_array($breakdown) ? $breakdown : []) as $row) {
                $ttId = (int) ($row['ticket_type_id'] ?? 0);
                if (in_array($ttId, $fromTypeIds, true)) {
                    $rows[] = [$p->id, $p->status, $p->amount, $ttId, (int) ($row['quantity'] ?? $row['qty'] ?? 0)];
                }
            }
        }

        if (!empty($rows)) {
            $this->warn('Deconturi care contin deja tipurile vechi (de corectat separat):');
            $this->table(['Decont', 'Status', 'Suma', 'Tip bilet', 'Cantitate'], $rows);
        }
    }

    private function label($name): string
    {
        if (is_string($name)) {
            $decoded = json_decode($name, true);
            if (is_array($decoded)) {
                $name = $decoded;
            }
        }
        if (is_array($name)) {
            return (string) ($name['ro'] ?? $name['en'] ?? (reset($name) ?: ''));
        }

        return (string) $name;
    }
}
