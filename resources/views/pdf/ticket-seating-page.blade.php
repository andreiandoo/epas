{{--
    Seating-map second page for ticket PDFs.

    Rendered either as a standalone view (when included from a Blade) or
    through SeatingPdfInjector::renderPageFor() for custom-template PDFs.

    Sizing — adapts to whatever the host PDF page is. $pageWidthPt and
    $pageHeightPt arrive from the caller (A4 = 595×842, thermal = 226×567,
    etc.). Padding and font sizes scale lightly so the result is legible
    on both a full A4 and a small thermal layout.

    Failure modes — when the renderer returns null we emit nothing here.
    Half-broken pages never reach a customer.
--}}
@php
    $seatingPdfPayload = app(\App\Services\SeatingMapPdfRenderer::class)->render($ticket, $event ?? null);

    $pageW = $pageWidthPt ?? 595;
    $pageH = $pageHeightPt ?? 842;
    $isCompact = $pageW < 360 || $pageH < 500;
    $padding = $isCompact ? 16 : 30;
    $titleFontSize = $isCompact ? 14 : 20;
    $subtitleFontSize = $isCompact ? 8 : 11;
    $footerLabelFontSize = $isCompact ? 7 : 9;
    $footerValueFontSize = $isCompact ? 11 : 15;
    $svgBoxHeight = max(180, (int) ($pageH * 0.65));
@endphp

@if($seatingPdfPayload)
<div style="page-break-before: always; padding: {{ $padding }}pt; font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; color: #111827; width: {{ $pageW - 2*$padding }}pt;">
    <div style="text-align: center; margin-bottom: {{ $isCompact ? 8 : 16 }}pt;">
        <div style="margin: 0; font-size: {{ $titleFontSize }}pt; color: #111827; font-weight: bold;">Locul tău pe hartă</div>
        <div style="margin: {{ $isCompact ? 3 : 6 }}pt 0 0; font-size: {{ $subtitleFontSize }}pt; color: #6b7280;">
            Cercul roșu mare îți marchează locul.
        </div>
    </div>

    <div style="width: 100%; height: {{ $svgBoxHeight }}pt; border: 1pt solid #e5e7eb; padding: 4pt; background: #ffffff;">
        {!! $seatingPdfPayload['svg'] !!}
    </div>

    <div style="margin-top: {{ $isCompact ? 8 : 14 }}pt; padding: {{ $isCompact ? 8 : 12 }}pt; background: #fef2f2; border: 1pt solid #fecaca;">
        <div style="font-size: {{ $footerLabelFontSize }}pt; text-transform: uppercase; letter-spacing: 0.5pt; color: #991b1b; font-weight: bold; margin-bottom: {{ $isCompact ? 2 : 4 }}pt;">
            Locul tău
        </div>
        <div style="font-size: {{ $footerValueFontSize }}pt; color: #111827; font-weight: bold;">
            @php
                $parts = array_filter([
                    $seatingPdfPayload['section_name'] ?? null,
                    !empty($seatingPdfPayload['row_label']) ? ('Rând ' . $seatingPdfPayload['row_label']) : null,
                    !empty($seatingPdfPayload['seat_label']) ? ('Loc ' . $seatingPdfPayload['seat_label']) : null,
                ]);
            @endphp
            {{ $parts ? implode(' · ', $parts) : ($seatingPdfPayload['seat_label'] ?? '—') }}
        </div>
        @unless($seatingPdfPayload['found'])
            <div style="margin-top: 4pt; font-size: {{ $footerLabelFontSize }}pt; color: #b45309;">
                Notă: locul nu a putut fi localizat exact pe hartă.
            </div>
        @endunless
    </div>
</div>
@endif
