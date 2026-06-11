<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email-Match Attribution Lookback
    |--------------------------------------------------------------------------
    |
    | When an order completes without a strict `nl=` URL attribution
    | (in-app browser sandbox, cross-device, cleared localStorage),
    | OrderObserver::tryEmailMatchAttribution looks up the customer email
    | against newsletter click events within this many days back.
    |
    | Set to 0 to disable the loose fallback entirely (strict URL flow
    | only). Larger windows surface more conversions but increase the
    | risk of crediting a newsletter the customer interacted with a long
    | time before their actual purchase.
    |
    */

    'email_match_lookback_days' => env('NEWSLETTER_EMAIL_MATCH_LOOKBACK_DAYS', 14),

];
