<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\MarketplacePayout;
use App\Services\Marketplace\SalesBreakdownService;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Rebuilds the item-description text on existing invoices to the current
 * on-code format WITHOUT touching number, dates, amounts, Oblio refs, or
 * PDF. Only meta.items[*].description is overwritten.
 *
 * Two paths supported:
 * - organizer POS invoices (invoice_recipient_type='organizer' /
 *   meta.recipient_type='organizer' — the 4-block payload built by
 *   ViewPayout::generatePosInvoice): pulls the row sources again
 *   (posRows + refundedRows + onlineIncludedRows + keptRows), rebuilds
 *   items from scratch using the same helpers the controller does.
 * - general-client invoices (meta.recipient_type='general_client'): does
 *   a regex tail replace on the existing descriptions ("//Conform decont
 *   nr. X din data Y" → "// cf. decont X/Y"). No full rebuild because
 *   those invoices don't have a clean row-source to iterate.
 *
 * Usage:
 *   php artisan invoices:rebuild-item-descriptions 3050
 *   php artisan invoices:rebuild-item-descriptions 3049 3050 3080
 *   php artisan invoices:rebuild-item-descriptions --dry-run 3050
 */
class RebuildInvoiceItemDescriptions extends Command
{
    protected $signature = 'invoices:rebuild-item-descriptions {ids* : One or more Invoice IDs} {--dry-run : Print the diff without saving}';

    protected $description = 'Rebuild meta.items[*].description on existing invoices to the current format (no Oblio contact, no re-emission).';

    public function handle(): int
    {
        $ids = $this->argument('ids');
        $dryRun = (bool) $this->option('dry-run');

        foreach ($ids as $id) {
            $this->processOne((int) $id, $dryRun);
        }

        return self::SUCCESS;
    }

    protected function processOne(int $id, bool $dryRun): void
    {
        $invoice = Invoice::find($id);
        if (!$invoice) {
            $this->error("Invoice #{$id} not found.");
            return;
        }

        $meta = $invoice->meta ?? [];
        $recipient = $meta['recipient_type'] ?? 'organizer';

        $this->info("--- Invoice #{$id} (recipient={$recipient}) ---");

        if ($recipient === 'general_client') {
            $newItems = $this->rebuildGeneralClient($meta['items'] ?? []);
        } else {
            $newItems = $this->rebuildOrganizerPos($invoice);
            if ($newItems === null) {
                return;
            }
        }

        $this->table(
            ['#', 'name', 'description (NEW)', 'qty', 'unit', 'amount'],
            collect($newItems)->map(fn ($it, $i) => [
                $i + 1,
                $it['name'] ?? '',
                $it['description'] ?? '',
                $it['quantity'] ?? '',
                $it['unit_price'] ?? '',
                $it['amount'] ?? '',
            ])->all()
        );

        if ($dryRun) {
            $this->warn('  (dry-run — not saved)');
            return;
        }

        $meta['items'] = $newItems;
        $invoice->update(['meta' => $meta]);
        $this->info("  ✓ meta.items updated (Oblio + PDF NOT touched).");
    }

    /**
     * General-client: strip the decont-reference tail entirely from each
     * existing description. Handles both the historical formats:
     *   " //Conform decont nr. X din data Y" (pre-Aug 12)
     *   " // cf. decont X/Y" (Aug 12 → Aug 12-later)
     * Items themselves + amounts are preserved.
     */
    protected function rebuildGeneralClient(array $items): array
    {
        $legacyPattern = '#\s*//\s*Conform decont nr\.\s+\S+\s+din data\s+\S+\s*$#u';
        $shortPattern = '#\s*//\s*cf\.\s*decont\s+\S+\s*$#u';

        return collect($items)->map(function ($item) use ($legacyPattern, $shortPattern) {
            if (!empty($item['description'])) {
                $desc = $item['description'];
                $desc = preg_replace($legacyPattern, '', $desc);
                $desc = preg_replace($shortPattern, '', $desc);
                $item['description'] = trim($desc);
            }
            return $item;
        })->all();
    }

    /**
     * Organizer POS: fully rebuild items using the same 4-block source
     * as ViewPayout::generatePosInvoice(). Descriptions follow the
     * current on-code format (with ticket type name + contract fragment).
     * Returns null when the invoice can't be tied back to a payout.
     */
    protected function rebuildOrganizerPos(Invoice $invoice): ?array
    {
        $payout = $invoice->marketplace_payout_id
            ? MarketplacePayout::find($invoice->marketplace_payout_id)
            : null;

        if (!$payout) {
            $this->error("  Invoice has no marketplace_payout_id — cannot rebuild organizer POS items.");
            return null;
        }

        $organizer = $payout->organizer;
        $event = $payout->event;

        if (!$organizer || !$event) {
            $this->error("  Payout missing organizer or event.");
            return null;
        }

        $breakdown = app(SalesBreakdownService::class);
        $posRows = $breakdown->buildPosForPayout($event, null, null);
        $refundedRows = $payout->getRefundedCommissionRowsForPayout();
        $onlineIncludedRows = $payout->getOnlineIncludedCommissionRowsForPayout();
        $keptRows = $payout->getKeptCommissionRowsForPayout();

        $contractNumber = trim((string) ($organizer->contract_number_series ?? ''));
        $contractDate = $organizer->contract_date instanceof Carbon
            ? $organizer->contract_date->format('d.m.Y')
            : (is_string($organizer->contract_date) && $organizer->contract_date !== ''
                ? Carbon::parse($organizer->contract_date)->format('d.m.Y')
                : '');
        $contractFragment = ($contractNumber !== '' || $contractDate !== '')
            ? ', cf. ctr. nr ' . $contractNumber . '/' . $contractDate
            : '';

        if ($contractFragment === '') {
            $this->warn("  (!) Organizer #{$organizer->id} has no contract_number_series/contract_date — contract fragment will be missing from descriptions.");
        }

        $decontSeries = trim((string) ($payout->decont_series ?? ''));
        $decontDate = $payout->created_at instanceof Carbon ? $payout->created_at->format('d.m.Y') : '';
        $decontFragment = ($decontSeries !== '' || $decontDate !== '')
            ? ', cf. decont ' . $decontSeries . ($decontDate !== '' ? '/' . $decontDate : '')
            : '';

        $evName = '';
        $evDate = '';
        $title = $event->title;
        $evName = is_array($title)
            ? ($title['ro'] ?? $title['en'] ?? (reset($title) ?: ''))
            : ($title ?? '');
        if ($event->event_date) {
            $evDate = $event->event_date->format('d.m.Y');
        } elseif ($event->range_start_date) {
            $evDate = $event->range_start_date->format('d.m.Y');
        }
        $eventFragment = $evName !== ''
            ? ', "' . $evName . '"' . ($evDate !== '' ? ' / ' . $evDate : '')
            : '';

        $items = [];

        foreach ($posRows as $row) {
            $qty = (int) ($row['quantity'] ?? $row['tickets'] ?? $row['qty'] ?? 0);
            $comm = (float) ($row['commission_per_ticket'] ?? 0);
            if ($qty <= 0 || $comm <= 0) continue;
            $tt = (string) ($row['ticket_type_name'] ?? 'Bilet');
            $items[] = [
                'name' => 'Taxa ticketing (POS)',
                'description' => trim('Servicii ticketing POS invitatii/bilete "' . $tt . '"' . $contractFragment . $decontFragment . $eventFragment),
                'quantity' => $qty,
                'unit_price' => $comm,
                'amount' => round($qty * $comm, 2),
            ];
        }

        foreach ($refundedRows as $row) {
            $qty = (int) ($row['qty'] ?? 0);
            $comm = (float) ($row['commission_per_ticket'] ?? 0);
            $lt = round((float) ($row['commission_amount'] ?? ($qty * $comm)), 2);
            if ($qty <= 0 || $lt <= 0) continue;
            $tt = (string) ($row['ticket_type_name'] ?? 'Bilet');
            $items[] = [
                'name' => 'Comision bilet rambursat integral',
                'description' => trim('Servicii ticketing invitatii/bilete rambursate "' . $tt . '"' . $contractFragment . $decontFragment . $eventFragment),
                'quantity' => $qty,
                'unit_price' => $comm,
                'amount' => $lt,
            ];
        }

        foreach ($onlineIncludedRows as $row) {
            $qty = (int) ($row['qty'] ?? 0);
            $comm = (float) ($row['commission_per_ticket'] ?? 0);
            $lt = round((float) ($row['commission_amount'] ?? ($qty * $comm)), 2);
            if ($qty <= 0 || $lt <= 0) continue;
            $tt = (string) ($row['ticket_type_name'] ?? 'Bilet');
            $items[] = [
                'name' => 'Comision online inclus în preț bilet',
                'description' => trim('Servicii ticketing invitatii/bilete "' . $tt . '"' . $contractFragment . $decontFragment . $eventFragment),
                'quantity' => $qty,
                'unit_price' => $comm,
                'amount' => $lt,
            ];
        }

        foreach ($keptRows as $row) {
            $qty = (int) ($row['qty'] ?? 0);
            $comm = (float) ($row['commission_per_ticket'] ?? 0);
            $lt = round((float) ($row['commission_amount'] ?? ($qty * $comm)), 2);
            if ($qty <= 0 || $lt <= 0) continue;
            $tt = (string) ($row['ticket_type_name'] ?? 'Bilet');
            $items[] = [
                'name' => 'Storno comision reținut din rambursare parțială',
                'description' => trim('Storno servicii ticketing "' . $tt . '"' . $contractFragment . $decontFragment . $eventFragment),
                'quantity' => $qty,
                'unit_price' => -$comm,
                'amount' => -$lt,
            ];
        }

        return $items;
    }
}
