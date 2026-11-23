<?php

namespace App\Support;

/**
 * Per-order key that unlocks the tickets of an order without logging in
 * (download button in the order email, thank-you page). Derived from the
 * app key, so nothing is stored and it can't be guessed from the order number.
 */
class OrderAccessKey
{
    public static function make(string $orderNumber): string
    {
        return substr(hash_hmac('sha256', 'order-tickets|' . $orderNumber, (string) config('app.key')), 0, 32);
    }

    public static function check(string $orderNumber, ?string $key): bool
    {
        return is_string($key) && $key !== '' && hash_equals(self::make($orderNumber), $key);
    }
}
