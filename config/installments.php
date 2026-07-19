<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform (Tixello) fee
    |--------------------------------------------------------------------------
    | Tixello's per-order commission. Flexible-payment orders (installments /
    | BNPL) are charged the higher `installments` rate instead of `default`.
    | This fee is collected FROM the marketplace (B2B settlement), never added
    | to what the customer pays.
    */
    'platform_fee_percent_default' => (float) env('FLEX_PLATFORM_FEE_DEFAULT', 1.0),
    'platform_fee_percent_installments' => (float) env('FLEX_PLATFORM_FEE_INSTALLMENTS', 2.0),

    /*
    |--------------------------------------------------------------------------
    | Hard limits (legal / risk)
    |--------------------------------------------------------------------------
    | Keeping installment plans <= 3 months keeps the marketplace in the RO
    | consumer-credit exemption zone (OUG 50/2010). BNPL is a single deferred
    | charge capped at 30 days. The last payment must always fall at least
    | `min_days_before_event` before the event start (never on event day).
    */
    'max_installment_duration_days' => (int) env('FLEX_MAX_DURATION_DAYS', 93),
    'bnpl_max_horizon_days' => (int) env('FLEX_BNPL_MAX_DAYS', 30),
    'min_days_before_event' => (int) env('FLEX_MIN_DAYS_BEFORE_EVENT', 1),

    /*
    |--------------------------------------------------------------------------
    | BNPL card capture
    |--------------------------------------------------------------------------
    | Nominal amount charged at checkout to capture the card + mandate (SCA).
    | Deducted from the single BNPL charge later. In bani (100 = 1 RON).
    */
    'bnpl_card_capture_cents' => (int) env('FLEX_BNPL_CAPTURE_CENTS', 100),

    /*
    |--------------------------------------------------------------------------
    | Dunning / retries (defaults; a plan can override via default_policy)
    |--------------------------------------------------------------------------
    */
    'dunning' => [
        'grace_days' => 3,
        'max_retries' => 3,
        'retry_backoff_days' => [1, 3, 5],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reminders
    |--------------------------------------------------------------------------
    | Days before each due date to send a reminder email.
    */
    'reminder_days_before' => [7, 3, 1],

    /*
    |--------------------------------------------------------------------------
    | Delegated pay
    |--------------------------------------------------------------------------
    */
    'delegated_hold_hours' => (int) env('FLEX_DELEGATED_HOLD_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Tokenization-capable providers (v1)
    |--------------------------------------------------------------------------
    | Only these providers can offer installments / BNPL. Others hide the
    | flexible-payment methods in checkout.
    */
    'tokenizable_providers' => ['stripe', 'netopia'],

    /*
    |--------------------------------------------------------------------------
    | Fake processor (E2E testing ONLY)
    |--------------------------------------------------------------------------
    | When true, ProcessorResolver returns MockTokenizableProcessor instead of the
    | marketplace's real gateway — no real Netopia/Stripe transactions. Use on
    | staging to test the full lifecycle. MUST be false in production.
    */
    'fake_processor' => (bool) env('FLEX_FAKE_PROCESSOR', false),
];
