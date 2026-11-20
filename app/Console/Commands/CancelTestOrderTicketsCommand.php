<?php

namespace App\Console\Commands;

use App\Models\Ticket;
use Illuminate\Console\Command;

/**
 * Anuleaza biletele din comenzile TEST- (link de test din admin, source = test_order).
 *
 * Comanda de test e completed cu total 0, dar biletele ies valid pe tipul real
 * de bilet. Le lasam valide cateva ore ca testul sa poata fi verificat (email,
 * bilet, cont), apoi le anulam ca sa nu mai poata intra nimeni cu ele.
 * La scanare sunt respinse oricum (TicketCheckin::attempt).
 *
 * Biletele Test POS (pos_test) nu se ating — cu ele se invata scanarea.
 */
class CancelTestOrderTicketsCommand extends Command
{
    protected $signature = 'tickets:cancel-test-orders
        {--hours=24 : Anuleaza biletele din comenzile de test mai vechi de atatea ore}
        {--dry-run : Doar afiseaza ce s-ar anula}';

    protected $description = 'Anuleaza biletele valide din comenzile TEST- (source test_order).';

    public function handle(): int
    {
        $hours = max(0, (int) $this->option('hours'));

        $query = Ticket::query()
            ->whereIn('status', ['valid', 'pending'])
            ->whereIn('order_id', fn ($q) => $q->select('id')->from('orders')
                ->where('source', 'test_order')
                ->where('created_at', '<=', now()->subHours($hours)));

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            foreach ((clone $query)->selectRaw('event_id, count(*) as c')->groupBy('event_id')->get() as $row) {
                $this->line("event {$row->event_id}: {$row->c}");
            }
            $this->info("{$count} bilete de test ar fi anulate.");

            return self::SUCCESS;
        }

        $done = 0;
        $query->select('id')->chunkById(500, function ($tickets) use (&$done) {
            // update pe model ca sa se goleasca si cache-ul statisticilor (TicketObserver)
            foreach (Ticket::whereIn('id', $tickets->pluck('id'))->get() as $ticket) {
                $ticket->forceFill([
                    'status' => 'cancelled',
                    'is_cancelled' => true,
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Comandă de test (TEST-)',
                ])->save();
                $done++;
            }
        });

        $this->info("{$done} bilete de test anulate.");

        return self::SUCCESS;
    }
}
