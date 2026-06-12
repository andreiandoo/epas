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

    /*
    |--------------------------------------------------------------------------
    | Send-Time Throttle
    |--------------------------------------------------------------------------
    |
    | SendNewsletterJob processes recipients in batches and re-dispatches
    | itself between batches. Tightening the knob below limits how many
    | emails leave the queue per minute, which protects the shared
    | database / PHP-FPM pool from being saturated mid-blast.
    |
    | At 25 emails per 8 seconds (~187/min, ~11k/hour) a 22k newsletter
    | finishes in ~2h and a 65k newsletter in ~6h, both without spiking
    | DB load enough to time out the public ambilet.ro nav cache fetches
    | (3s connect timeout). Raise for dedicated infra; lower for shared.
    |
    | Per-marketplace override (settings.newsletter_throttle) wins over
    | the global default — see MarketplaceClient::getNewsletterThrottle.
    |
    */

    'throttle' => [
        // Emails per dispatch batch.
        'batch_size' => env('NEWSLETTER_BATCH_SIZE', 25),
        // Seconds to wait between consecutive batches.
        'batch_delay_seconds' => env('NEWSLETTER_BATCH_DELAY', 8),
    ],

];
