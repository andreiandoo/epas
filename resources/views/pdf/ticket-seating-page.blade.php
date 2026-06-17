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

@php
    $seatParts = $seatingPdfPayload ? array_filter([
        $seatingPdfPayload['section_name'] ?? null,
        !empty($seatingPdfPayload['row_label']) ? ('Rând ' . $seatingPdfPayload['row_label']) : null,
        !empty($seatingPdfPayload['seat_label']) ? ('Loc ' . $seatingPdfPayload['seat_label']) : null,
    ]) : [];
    $seatSummary = $seatParts
        ? implode(' · ', $seatParts)
        : ($seatingPdfPayload['seat_label'] ?? '');
@endphp

@if($seatingPdfPayload)
<div style="page-break-before: always; padding: {{ $padding }}pt; font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; color: #111827; width: {{ $pageW - 2*$padding }}pt;">
    <div style="text-align: center; margin-bottom: {{ $isCompact ? 8 : 16 }}pt;">
        <div style="margin: 0; font-size: {{ $titleFontSize }}pt; color: #111827; font-weight: bold;">Locul tău pe hartă</div>
        @if($seatSummary !== '')
            <div style="margin: {{ $isCompact ? 3 : 6 }}pt 0 0; font-size: {{ $subtitleFontSize + 2 }}pt; color: #991b1b; font-weight: bold;">
                {{ $seatSummary }}
            </div>
        @endif
    </div>

    {{-- DomPDF 3 renders inline SVG as raw text. Encoded as a data URI on
         an <img>, the SVG renderer kicks in and the shapes draw properly.
         We give the img an explicit width in pt — percentage widths inside
         a constrained-width div confuse DomPDF too. --}}
    @php
        $svgImgWidth = $pageW - 2 * $padding - 8;
        $aspect = ($seatingPdfPayload['canvas_h'] ?? 1) / max(1, ($seatingPdfPayload['canvas_w'] ?? 1));
        $svgImgHeight = min($svgBoxHeight, (int) round($svgImgWidth * $aspect));
    @endphp
    <div style="width: 100%; border: 1pt solid #e5e7eb; padding: 4pt; background: #ffffff; text-align: center;">
        <img src="data:image/svg+xml;base64,{{ base64_encode($seatingPdfPayload['svg']) }}"
             style="width: {{ $svgImgWidth }}pt; height: {{ $svgImgHeight }}pt; display: block; margin: 0 auto;"
             width="{{ $svgImgWidth }}"
             height="{{ $svgImgHeight }}"
             alt="" />
    </div>

    @unless($seatingPdfPayload['found'])
        <div style="margin-top: 6pt; font-size: {{ $footerLabelFontSize }}pt; color: #b45309; text-align: center;">
            Notă: locul nu a putut fi localizat exact pe hartă.
        </div>
    @endunless
</div>
@endif
