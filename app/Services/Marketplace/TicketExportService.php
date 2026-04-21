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
            'Cod bilet',
            'Status bilet',
            'Comanda (Nr)',
            'Valoare comanda',
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
        fputcsv($handle, $columns);

        (clone $ticketsQuery)
            ->with([
                'ticketType:id,name,event_id',
                'ticketType.event:id,title,marketplace_organizer_id',
                'ticketType.event.marketplaceOrganizer:id,public_name,name',
                'order:id,order_number,total,status,promo_code,promo_discount,discount_amount,customer_email,customer_name,created_at',
            ])
            ->orderBy('id')
            ->chunk(500, function ($tickets) use ($handle) {
                foreach ($tickets as $ticket) {
                    $event = $ticket->ticketType?->event;
                    $organizer = $event?->marketplaceOrganizer;
                    $order = $ticket->order;

                    $eventTitle = $event
                        ? ($event->getTranslation('title', 'ro') ?? $event->getTranslation('title', 'en') ?? '')
                        : '';

                    $orgName = $organizer?->public_name ?: $organizer?->name ?: '';

                    $promoCode = $order?->promo_code;
                    $discountAmount = (float) ($order?->discount_amount ?? $order?->promo_discount ?? 0);
                    $hasDiscount = (!empty($promoCode) || $discountAmount > 0) ? 1 : 0;

                    fputcsv($handle, [
                        $eventTitle,
                        $orgName,
                        $ticket->id,
                        $ticket->code,
                        $ticket->status,
                        $order?->order_number ?? ($order?->id ? '#' . $order->id : ''),
                        $order ? number_format((float) $order->total, 2, '.', '') : '',
                        $order?->status ?? '',
                        $hasDiscount,
                        $hasDiscount ? number_format($discountAmount, 2, '.', '') : '',
                        $promoCode ?? '',
                        $order?->created_at?->format('Y-m-d H:i:s') ?? '',
                        $order?->customer_email ?? '',
                        $order?->customer_name ?? '',
                    ]);
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
}
