<?php

namespace App\Services\Marketplace;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketExportService
{
    /**
     * Stream a CSV of tickets matching the given query, scoped to a marketplace.
     */
    public function streamCsv(Builder $ticketsQuery, string $filename): StreamedResponse
    {
        $headers = [
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

        $query = (clone $ticketsQuery)
            ->with([
                'ticketType:id,name,event_id',
                'ticketType.event:id,title,marketplace_organizer_id',
                'ticketType.event.marketplaceOrganizer:id,public_name,name',
                'order:id,order_number,total,status,promo_code,promo_discount,discount_amount,customer_email,customer_name,created_at',
            ])
            ->orderBy('id');

        return response()->streamDownload(function () use ($query, $headers) {
            $handle = fopen('php://output', 'w');

            // Excel-friendly UTF-8 BOM
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);

            $query->chunk(500, function ($tickets) use ($handle) {
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

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
