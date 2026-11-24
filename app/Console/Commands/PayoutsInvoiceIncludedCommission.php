<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\MarketplacePayout;
use Illuminate\Console\Command;

/**
 * Comision inclus în preț: decontul nu-l mai reține, organizatorul primește
 * brutul și comisionul i se facturează separat (factura organizator, cu TVA).
 * Deconturile noi se creează direct așa; comanda trece pe modelul nou
 * deconturile neplătite existente. Implicit doar listează; scrie cu --apply.
 */
class PayoutsInvoiceIncludedCommission extends Command
{
    protected $signature = 'payouts:invoice-included-commission
        {--ids= : Doar aceste deconturi (id-uri separate prin virgulă)}
        {--since= : Doar deconturile create de la această dată (Y-m-d)}
        {--apply : Scrie în DB}
        {--revert= : Readuce aceste deconturi pe modelul vechi (id-uri separate prin virgulă)}
        {--completed-report : Deconturile deja plătite (comision reținut) care au factură organizator neîncasată}';

    protected $description = 'Comision inclus facturat separat: deconturile neplătite primesc brutul (comisionul nu se mai reține).';

    public function handle(): int
    {
        if ($this->option('completed-report')) {
            return $this->completedReport();
        }

        if ($revert = $this->option('revert')) {
            $ids = $this->parseIds($revert);
            $n = MarketplacePayout::whereIn('id', $ids)->where('status', '!=', 'completed')
                ->update(['invoiced_commission_amount' => null]);
            $this->info("Readuse pe modelul vechi: {$n}.");

            return self::SUCCESS;
        }

        $q = MarketplacePayout::query()
            ->whereNull('invoiced_commission_amount')
            ->whereNull('net_override')
            ->whereNotNull('event_id')
            ->whereIn('status', ['approved', 'processing'])
            ->where(fn ($w) => $w->whereNull('commission_mode')->orWhere('commission_mode', '!=', 'added_on_top'))
            ->where(fn ($w) => $w->whereNull('source')->orWhere('source', '!=', MarketplacePayout::SOURCE_ADVANCE))
            ->orderBy('id');
        if ($ids = $this->option('ids')) {
            $q->whereIn('id', $this->parseIds($ids));
        }
        if ($since = $this->option('since')) {
            $q->where('created_at', '>=', $since);
        }

        $rows = [];
        $todo = [];
        foreach ($q->get() as $p) {
            $commission = $p->computeInvoicedCommission();
            if ($commission <= 0.005) {
                continue;
            }
            $todo[$p->id] = $commission;
            $rows[] = [
                $p->id,
                $p->decont_series ?: $p->reference,
                $p->marketplace_organizer_id,
                $p->event_id,
                $p->status,
                $p->created_at?->format('Y-m-d'),
                number_format((float) $p->amount, 2, '.', ''),
                number_format($commission, 2, '.', ''),
                number_format($p->cashAmount() + $commission, 2, '.', ''),
                $this->invoiceLabel($p->id),
            ];
        }

        if (empty($rows)) {
            $this->info('Niciun decont de trecut pe modelul nou.');

            return self::SUCCESS;
        }

        $this->table(['id', 'serie', 'org', 'eveniment', 'status', 'creat', 'amount (net)', 'comision', 'de transferat', 'factura organizator'], $rows);
        $this->line('Total comision nereținut: ' . number_format(array_sum($todo), 2, ',', '.') . ' (' . count($todo) . ' deconturi)');

        if (! $this->option('apply')) {
            $this->warn('Doar listare. Rulează cu --apply ca să scrii.');

            return self::SUCCESS;
        }

        foreach ($todo as $id => $commission) {
            MarketplacePayout::whereKey($id)->update(['invoiced_commission_amount' => $commission]);
        }
        $this->info('Scrise: ' . count($todo) . '. Anulare: --revert=' . implode(',', array_keys($todo)));
        $this->line('Regenerează PDF-ul decontului ca să apară suma nouă.');

        return self::SUCCESS;
    }

    protected function completedReport(): int
    {
        $invoices = Invoice::whereNotNull('marketplace_payout_id')
            ->where('status', 'outstanding')
            ->get()
            ->filter(fn ($i) => (bool) data_get($i->meta, 'is_pos_commission'))
            ->keyBy('marketplace_payout_id');

        $payouts = MarketplacePayout::whereIn('id', $invoices->keys())
            ->where('status', 'completed')
            ->whereNull('invoiced_commission_amount')
            ->orderBy('id')
            ->get();

        $rows = [];
        $totWithheld = 0.0;
        $totInvoice = 0.0;
        foreach ($payouts as $p) {
            $inv = $invoices[$p->id];
            $withheld = (float) ($p->commission_mode === 'added_on_top' ? 0 : $p->computeInvoicedCommission());
            $totWithheld += $withheld;
            $totInvoice += (float) $inv->amount;
            $rows[] = [
                $p->id,
                $p->decont_series ?: $p->reference,
                $p->marketplace_organizer_id,
                $p->event_id,
                $p->completed_at?->format('Y-m-d'),
                number_format($withheld, 2, '.', ''),
                $inv->number,
                number_format((float) $inv->subtotal, 2, '.', ''),
                number_format((float) $inv->vat_amount, 2, '.', ''),
                number_format((float) $inv->amount, 2, '.', ''),
            ];
        }

        $this->table(['id', 'serie', 'org', 'eveniment', 'plătit', 'comision online reținut', 'factura', 'fără TVA', 'TVA', 'total'], $rows);
        $this->line('Total comision online deja reținut: ' . number_format($totWithheld, 2, ',', '.')
            . ' | total facturi neîncasate: ' . number_format($totInvoice, 2, ',', '.'));
        $this->line('Restul facturii peste comisionul reținut = comision POS / rambursări + TVA, încă datorat de organizator.');

        return self::SUCCESS;
    }

    protected function invoiceLabel(int $payoutId): string
    {
        return Invoice::where('marketplace_payout_id', $payoutId)->get()
            ->map(fn ($i) => $i->number . ':' . $i->amount . ':' . $i->status)
            ->implode(', ') ?: '—';
    }

    protected function parseIds(string $raw): array
    {
        return array_values(array_filter(array_map('intval', explode(',', $raw))));
    }
}
