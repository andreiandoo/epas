{{--
    Seating-map second page for ticket PDFs.

    Included by pdf/ticket.blade.php behind SeatingPdfGate::shouldRenderFor().
    Both env flags must be on (SEATING_PDF_ENABLED + SEATING_PDF_TEST_EVENT_IDS)
    AND the ticket must have a seat_uid for this template to execute.

    The renderer itself returns null on any error — when that happens we emit
    nothing here, NOT a half-broken page. Worst case = the buyer gets the
    original PDF, never a partial render.
--}}
@php
    $seatingPdfPayload = app(\App\Services\SeatingMapPdfRenderer::class)->render($ticket, $event ?? null);
@endphp

@if($seatingPdfPayload)
<div style="page-break-before: always; padding: 40px 30px 30px; font-family: Helvetica, Arial, sans-serif; color: #111827;">
    <div style="text-align: center; margin-bottom: 18px;">
        <h2 style="margin: 0; font-size: 22px; color: #111827;">Locul tău pe hartă</h2>
        <p style="margin: 6px 0 0; font-size: 12px; color: #6b7280;">
            Caută cercul roșu mare. E unde stai tu.
        </p>
    </div>

    <div style="width: 100%; height: 620px; border: 1px solid #e5e7eb; border-radius: 8px; padding: 8px; background: #ffffff;">
        {!! $seatingPdfPayload['svg'] !!}
    </div>

    <div style="margin-top: 18px; padding: 12px 16px; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px;">
        <div style="font-size: 11px; text-transform: uppercase; letter-spacing: 0.05em; color: #991b1b; font-weight: bold; margin-bottom: 4px;">
            Locul tău
        </div>
        <div style="font-size: 16px; color: #111827; font-weight: 600;">
            @php
                $parts = array_filter([
                    $seatingPdfPayload['section_name'] ?? null,
                    $seatingPdfPayload['row_label'] ? ('Rând ' . $seatingPdfPayload['row_label']) : null,
                    $seatingPdfPayload['seat_label'] ? ('Loc ' . $seatingPdfPayload['seat_label']) : null,
                ]);
            @endphp
            {{ $parts ? implode(' · ', $parts) : ($seatingPdfPayload['seat_label'] ?? '—') }}
        </div>
        @unless($seatingPdfPayload['found'])
            <div style="margin-top: 6px; font-size: 10px; color: #b45309;">
                Notă: locul nu a putut fi localizat exact pe hartă. Mai sus ai informația textuală.
            </div>
        @endunless
    </div>
</div>
@endif
