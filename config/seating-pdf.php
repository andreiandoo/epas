<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seating map PDF — second page feature flag
    |--------------------------------------------------------------------------
    |
    | When a ticket PDF is generated for an event that has a seating layout,
    | we can optionally append a SECOND page showing the layout with the
    | buyer's assigned seat highlighted (circled / colored).
    |
    | Two gates control activation:
    |   - `enabled` is the global kill-switch (default false). When false,
    |     the seating page is NEVER appended, no matter what.
    |   - `test_event_ids` is an allowlist of event ids. When non-empty,
    |     the seating page is appended ONLY for tickets belonging to one
    |     of these events. Other events still produce the old PDF.
    |
    | Even with both gates passed, the renderer wraps itself in a try/catch
    | so any error falls back to the original PDF — a malformed seating
    | layout can never break a customer's ticket.
    |
    */

    'enabled' => env('SEATING_PDF_ENABLED', false),

    'test_event_ids' => array_filter(array_map('intval', explode(',', (string) env('SEATING_PDF_TEST_EVENT_IDS', '')))),

];
