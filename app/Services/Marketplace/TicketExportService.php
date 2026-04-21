<?php

namespace App\Services\Marketplace;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

class TicketExportService
{
    /**
     * Build a CSV download Response of tickets matching the given query.
     *
     * Builds the file in-memory so it works reliably even when the route
     * lives inside a Filament panel context (where streamed responses
     * occasionally get wrapped or buffered by panel middleware).
     */
    public function buildCsvResponse(Builder $ticketsQuery, string $filename): Response
    {
        $columns = [
            'Eveniment',
            'Organizator',
            'Bilet (ID)',
            'Tip bilet',
            'Cod bilet',
            'Status bilet',
            'Comanda (Nr)',
            'Valoare comanda',
            'Valoare bruta bilet',
            'Valoare neta bilet',
            'Comision bilet',
            'Status comanda',
            'A folosit cod reducere',
            'Valoare reducere',
            'Cod reducere',
            'Data comanda',
            'Email client',
            'Nume client',
        ];

        $handle = fopen('php://temp', 'r+');

        // Excel-friendly UTF-8 BOM
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $columns, ',', '"', '');

        (clone $ticketsQuery)
            ->with([
                'ticketType:id,name,event_id,price_cents,sale_price_cents,commission_type,commission_rate,commission_fixed,commission_mode',
                'ticketType.event:id,title,marketplace_organizer_id',
                'ticketType.event.marketplaceOrganizer:id,name',
                'order:id,order_number,total,status,promo_code,promo_discount,discount_amount,customer_email,customer_name,created_at',
            ])
            ->orderBy('id')
            ->chunk(500, function ($tickets) use ($handle) {
                foreach ($tickets as $ticket) {
                    $tt = $ticket->ticketType;
                    $event = $tt?->event;
                    $organizer = $event?->marketplaceOrganizer;
                    $order = $ticket->order;

                    $eventTitle = $event
                        ? ($event->getTranslation('title', 'ro') ?? $event->getTranslation('title', 'en') ?? '')
                        : '';

                    $orgName = $organizer?->name ?? '';

                    $promoCode = $order?->promo_code;
                    $discountAmount = (float) ($order?->discount_amount ?? $order?->promo_discount ?? 0);
                    $hasDiscount = (!empty($promoCode) || $discountAmount > 0) ? 1 : 0;

                    [$gross, $net, $commission] = $this->computeTicketAmounts($tt);

                    fputcsv($handle, [
                        $eventTitle,
                        $orgName,
                        $ticket->id,
                        $tt?->name ?? '',
                        $ticket->code,
                        $ticket->status,
                        $order?->order_number ?? ($order?->id ? '#' . $order->id : ''),
                        $order ? number_format((float) $order->total, 2, '.', '') : '',
                        $gross !== null ? number_format($gross, 2, '.', '') : '',
                        $net !== null ? number_format($net, 2, '.', '') : '',
                        $commission !== null ? number_format($commission, 2, '.', '') : '',
                        $order?->status ?? '',
                        $hasDiscount,
                        $hasDiscount ? number_format($discountAmount, 2, '.', '') : '',
                        $promoCode ?? '',
                        $order?->created_at?->format('Y-m-d H:i:s') ?? '',
                        $order?->customer_email ?? '',
                        $order?->customer_name ?? '',
                    ], ',', '"', '');
                }
            });

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Build a CSV filename embedding the event title, date, venue and city
     * when available. Falls back to a generic timestamped name otherwise.
     */
    public function buildFilename(?\App\Models\Event $event): string
    {
        if (!$event) {
            return 'tickets-export-' . now()->format('Y-m-d-His') . '.csv';
        }

        $parts = [];

        $title = $event->getTranslation('title', 'ro')
            ?? $event->getTranslation('title', 'en')
            ?? '';
        if ($title !== '') $parts[] = $title;

        if ($event->event_date) $parts[] = $event->event_date->format('Y-m-d');

        $venue = $event->venue;
        $venueName = $venue
            ? ($venue->getTranslation('name', 'ro') ?? $venue->getTranslation('name', 'en') ?? '')
            : '';
        if ($venueName !== '') $parts[] = $venueName;

        $city = $venue?->city;
        if ($city) $parts[] = $city;

        $slug = \Illuminate\Support\Str::slug(implode(' ', $parts));
        if ($slug === '') {
            $slug = 'tickets-export-' . now()->format('Y-m-d-His');
        }

        return $slug . '.csv';
    }

    /**
     * Compute [gross, net, commission] in major units for a single ticket
     * based on its ticket type's commission settings.
     *
     * - sale_price_cents wins over price_cents when present (>0).
     * - commission_type: 'percentage' uses commission_rate, 'fixed' uses commission_fixed.
     * - commission_mode: 'included' means base IS the gross (commission carved out);
     *   'added_on_top' means commission is charged on top of base.
     */
    protected function computeTicketAmounts(?\App\Models\TicketType $tt): array
    {
        if (!$tt) return [null, null, null];

        $baseCents = ($tt->sale_price_cents !== null && (int) $tt->sale_price_cents > 0)
            ? (int) $tt->sale_price_cents
            : (int) ($tt->price_cents ?? 0);

        $commissionType = $tt->commission_type ?? 'percentage';
        $commissionMode = $tt->commission_mode ?? 'included';

        $commissionCents = $commissionType === 'fixed'
            ? (int) round((float) ($tt->commission_fixed ?? 0) * 100)
            : (int) round($baseCents * ((float) ($tt->commission_rate ?? 0) / 100));

        if ($commissionMode === 'added_on_top') {
            $grossCents = $baseCents + $commissionCents;
            $netCents = $baseCents;
        } else { // 'included'
            $grossCents = $baseCents;
            $netCents = max(0, $baseCents - $commissionCents);
        }

        return [$grossCents / 100, $netCents / 100, $commissionCents / 100];
    }
}
