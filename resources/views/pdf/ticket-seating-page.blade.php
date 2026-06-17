{{--
    Seating-map second page for ticket PDFs.

    This partial is INTENTIONALLY EMPTY at E0. It's included by `pdf/ticket.blade.php`
    only when `SeatingPdfGate::shouldRenderFor($ticket)` returns true — which is
    impossible while `config('seating-pdf.enabled') === false` (the default).

    E1 will fill it with the SVG renderer + page-break logic. Keeping the file
    here from E0 means @include is a no-op rather than a missing-view error,
    and lets us validate the wiring on live before any visible change.
--}}
