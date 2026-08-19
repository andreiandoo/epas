<?php

namespace App\Services\Invoicing;

use App\Models\MarketplacePayout;
use App\Models\Ticket;
use App\Services\Marketplace\SalesBreakdownService;
use Carbon\Carbon;

/**
 * Builds the content of the organizer-billed invoice (items + subtotal +
 * vat + amount) from a payout snapshot. Extracted from ViewPayout so
 * both first-time generation and the "Regenerează factură" button on
 * the invoice edit page produce IDENTICAL content — the two callers
 * must never diverge or an operator regenerating an invoice would get
 * different math than the original.
 *
 * Contract:
 *   build(MarketplacePayout $payout): array
 *     - success: ['items' => [...], 'subtotal' => float, 'vat_rate' => float,
 *                 'vat_amount' => float, 'amount' => float]
 *     - nothing to bill: ['error' => 'Nu există comisioane de facturat']
 *
 * The four line categories mirror the four commission streams a POS-mode
 * organizer accumulates over an event:
 *   1. POS commission           (every POS sale)
 *   2. Refunded-ticket commission (full refunds with commission_refunded=true)
 *   3. Online commission included in ticket price (INCLUDED-mode events only)
 *   4. Kept-commission credit — negative (partial refunds without taxa)
 */
class PosInvoiceContentBuilder
{
    /**
     * @return array{items: array, subtotal: float, vat_rate: float, vat_amount: float, amount: float}|array{error: string}
     */
    public function build(MarketplacePayout $payout): array
    {
        $marketplace = $payout->marketplaceClient;
        if (!$marketplace) {
            return ['error' => 'Marketplace lipsă pentru acest payout.'];
        }
        if (!$payout->event) {
            return ['error' => 'Payout-ul nu este legat de un eveniment.'];
        }

        $posRows = $payout->filterRowsToIssuer(
            app(SalesBreakdownService::class)
                ->buildPosForPayout($payout->event, $payout->period_start, $payout->period_end)
        );
        $refundedRows = $payout->getRefundedCommissionRowsForPayout();
        $onlineIncludedRows = $payout->getOnlineIncludedCommissionRowsForPayout();
        $keptRows = $payout->getKeptCommissionRowsForPayout();

        if (empty($posRows) && empty($refundedRows) && empty($onlineIncludedRows) && empty($keptRows)) {
            return ['error' => 'Nu există comisioane de facturat'];
        }

        // Description fragments — appended to every line so the accountant
        // can identify the underlying contract / decont / event without
        // opening the invoice metadata.
        $organizer = $payout->organizer;
        $contractNumber = trim((string) ($organizer?->contract_number_series ?? ''));
        $contractDate = $organizer?->contract_date instanceof Carbon
            ? $organizer->contract_date->format('d.m.Y')
            : (is_string($organizer?->contract_date) && $organizer->contract_date !== ''
                ? Carbon::parse($organizer->contract_date)->format('d.m.Y')
                : '');
        $contractFragment = ($contractNumber !== '' || $contractDate !== '')
            ? ', cf. ctr. nr ' . $contractNumber . '/' . $contractDate
            : '';

        $decontSeries = trim((string) ($payout->decont_series ?? ''));
        $decontDate = $payout->created_at instanceof Carbon
            ? $payout->created_at->format('d.m.Y')
            : '';
        $decontFragment = ($decontSeries !== '' || $decontDate !== '')
            ? ', cf. decont ' . $decontSeries . ($decontDate !== '' ? '/' . $decontDate : '')
            : '';

        $eventCtx = $this->resolveEventContext($payout->event);
        $eventFragment = $eventCtx['name'] !== ''
            ? ', "' . $eventCtx['name'] . '"'
                . ($eventCtx['date'] !== '' ? ' / ' . $eventCtx['date'] : '')
            : '';

        // Leisure per-society invoice: count only PAID tickets in the qty
        // column. Package-component tickets (price 0) generate no commission,
        // so counting them inflated qty and produced a nonsensical "unit
        // price" like 241.45 / 1103 = 0.22 instead of the real 2.30 floor on
        // the 105 paid tickets. Non-leisure (issuing_company null) keeps raw
        // counts unchanged.
        $billablePos = collect();
        $billableOnline = collect();
        if ($payout->issuing_company) {
            $bFrom = $payout->period_start?->copy()->startOfDay();
            $bTo = $payout->period_end?->copy()->endOfDay();
            $bTtIds = collect($posRows)->pluck('ticket_type_id')
                ->merge(collect($onlineIncludedRows)->pluck('ticket_type_id'))
                ->filter()->unique()->values()->all();
            $billableCount = function (bool $pos) use ($bTtIds, $bFrom, $bTo) {
                if (empty($bTtIds)) {
                    return collect();
                }
                $dateCol = $pos ? 'created_at' : 'paid_at';

                return Ticket::query()
                    ->whereIn('ticket_type_id', $bTtIds)
                    ->whereIn('status', ['valid', 'used'])
                    ->where('price', '>', 0)
                    ->whereHas('order', function ($o) use ($pos, $bFrom, $bTo, $dateCol) {
                        $o->whereIn('status', SalesBreakdownService::PAID_ORDER_STATUSES)
                            ->whereNotIn('source', ['external_import', 'pos_test']);
                        if ($pos) {
                            $o->whereIn('source', SalesBreakdownService::POS_SOURCES);
                        } else {
                            $o->whereNotIn('source', array_merge(SalesBreakdownService::POS_SOURCES, ['test_order']));
                        }
                        if ($bFrom) {
                            $o->where($dateCol, '>=', $bFrom);
                        }
                        if ($bTo) {
                            $o->where($dateCol, '<=', $bTo);
                        }
                    })
                    ->selectRaw('ticket_type_id, count(*) as c')
                    ->groupBy('ticket_type_id')
                    ->pluck('c', 'ticket_type_id');
            };
            $billablePos = $billableCount(true);
            $billableOnline = $billableCount(false);
        }

        $items = [];
        $subtotal = 0.0;

        // (1) POS commission
        foreach ($posRows as $item) {
            $qty = (int) ($item['quantity'] ?? $item['tickets'] ?? $item['qty'] ?? 0);
            $commPerTicket = (float) ($item['commission_per_ticket'] ?? 0);
            if ($qty <= 0 || $commPerTicket <= 0) {
                continue;
            }
            $ticketTypeName = (string) ($item['ticket_type_name'] ?? 'Bilet');

            $displayQty = $qty;
            $displayUnit = $commPerTicket;
            if ($payout->issuing_company) {
                $billQty = (int) ($billablePos[(int) ($item['ticket_type_id'] ?? 0)] ?? 0);
                if ($billQty > 0) {
                    $displayQty = $billQty;
                    $displayUnit = round(round($qty * $commPerTicket, 2) / $billQty, 2);
                }
            }

            $lineTotal = round($displayQty * $displayUnit, 2);
            $subtotal += $lineTotal;

            $items[] = [
                'name' => 'Taxa ticketing (POS)',
                'description' => trim('Servicii ticketing POS invitatii/bilete "' . $ticketTypeName . '"'
                    . $contractFragment . $decontFragment . $eventFragment),
                'quantity' => $displayQty,
                'unit_price' => $displayUnit,
                'amount' => $lineTotal,
            ];
        }

        // (2) Refunded-ticket commission (full refunds)
        foreach ($refundedRows as $row) {
            $qty = (int) ($row['qty'] ?? 0);
            $commPerTicket = (float) ($row['commission_per_ticket'] ?? 0);
            $lineTotal = round((float) ($row['commission_amount'] ?? ($qty * $commPerTicket)), 2);
            if ($qty <= 0 || $lineTotal <= 0) {
                continue;
            }
            $subtotal += $lineTotal;
            $ticketTypeName = (string) ($row['ticket_type_name'] ?? 'Bilet');

            $items[] = [
                'name' => 'Comision bilet rambursat integral',
                'description' => trim('Servicii ticketing invitatii/bilete rambursate "' . $ticketTypeName . '"'
                    . $contractFragment . $decontFragment . $eventFragment),
                'quantity' => $qty,
                'unit_price' => $commPerTicket,
                'amount' => $lineTotal,
            ];
        }

        // (3) Online commission included in price
        foreach ($onlineIncludedRows as $row) {
            $qty = (int) ($row['qty'] ?? 0);
            $commPerTicket = (float) ($row['commission_per_ticket'] ?? 0);
            $rowCommission = round((float) ($row['commission_amount'] ?? ($qty * $commPerTicket)), 2);
            if ($qty <= 0 || $rowCommission <= 0) {
                continue;
            }
            $ticketTypeName = (string) ($row['ticket_type_name'] ?? 'Bilet');

            $displayQty = $qty;
            $displayUnit = $commPerTicket;
            if ($payout->issuing_company) {
                $billQty = (int) ($billableOnline[(int) ($row['ticket_type_id'] ?? 0)] ?? 0);
                if ($billQty > 0) {
                    $displayQty = $billQty;
                    $displayUnit = round($rowCommission / $billQty, 2);
                }
            }

            $lineTotal = round($displayQty * $displayUnit, 2);
            $subtotal += $lineTotal;

            $items[] = [
                'name' => 'Comision online inclus în preț bilet',
                'description' => trim('Servicii ticketing invitatii/bilete "' . $ticketTypeName . '"'
                    . $contractFragment . $decontFragment . $eventFragment),
                'quantity' => $displayQty,
                'unit_price' => $displayUnit,
                'amount' => $lineTotal,
            ];
        }

        // (4) Kept-commission credit (negative)
        foreach ($keptRows as $row) {
            $qty = (int) ($row['qty'] ?? 0);
            $commPerTicket = (float) ($row['commission_per_ticket'] ?? 0);
            $lineTotal = round((float) ($row['commission_amount'] ?? ($qty * $commPerTicket)), 2);
            if ($qty <= 0 || $lineTotal <= 0) {
                continue;
            }
            $subtotal -= $lineTotal;
            $ticketTypeName = (string) ($row['ticket_type_name'] ?? 'Bilet');

            $items[] = [
                'name' => 'Storno comision reținut din rambursare parțială',
                'description' => trim('Storno servicii ticketing "' . $ticketTypeName . '"'
                    . $contractFragment . $decontFragment . $eventFragment),
                'quantity' => $qty,
                'unit_price' => -$commPerTicket,
                'amount' => -$lineTotal,
            ];
        }

        if ($subtotal <= 0) {
            return ['error' => 'Nu există comisioane de facturat'];
        }

        $vatRate = $marketplace->vat_payer ? (float) data_get($marketplace->settings, 'tax.vat_rate', 21) : 0;
        $vatAmount = $vatRate > 0 ? round($subtotal * $vatRate / 100, 2) : 0;
        $total = round($subtotal + $vatAmount, 2);

        return [
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'vat_rate' => $vatRate,
            'vat_amount' => $vatAmount,
            'amount' => $total,
        ];
    }

    protected function resolveEventContext(?\App\Models\Event $event): array
    {
        if (!$event) {
            return ['name' => '', 'date' => '', 'venue' => '', 'city' => ''];
        }
        $title = $event->title;
        $name = is_array($title)
            ? ($title['ro'] ?? $title['en'] ?? (reset($title) ?: ''))
            : ($title ?? '');
        $date = '';
        if ($event->event_date) {
            $date = $event->event_date->format('d.m.Y');
        } elseif ($event->range_start_date) {
            $date = $event->range_start_date->format('d.m.Y');
        }
        return [
            'name' => (string) $name,
            'date' => (string) $date,
            'venue' => '',
            'city' => '',
        ];
    }
}
