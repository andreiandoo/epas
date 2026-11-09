<?php

namespace App\Support;

use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Orders placed on an organizer's whitelabel site (e.g. bilete.qfeel.ro).
 *
 * The whitelabel thank-you page sends its browser Purchase with the id
 * `purchase_{order_id}_whitelabel` and lives on the organizer's domain, so the
 * server-side Purchase (Meta CAPI / TikTok) has to use the same id and URL,
 * otherwise the two are never deduplicated.
 */
class WhitelabelOrder
{
    public const CHANNEL = 'whitelabel';

    /**
     * Keys to merge into orders.meta at checkout. Empty unless the request was
     * authenticated with a whitelabel key.
     */
    public static function checkoutMeta(Request $request): array
    {
        $organizerId = $request->attributes->get('whitelabel_organizer_id');
        if (!$organizerId) {
            return [];
        }

        return [
            'channel' => self::CHANNEL,
            'whitelabel_organizer_id' => (int) $organizerId,
        ];
    }

    public static function is(Order $order): bool
    {
        return (($order->meta ?? [])['channel'] ?? null) === self::CHANNEL;
    }

    public static function purchaseEventId(Order $order): string
    {
        return 'purchase_' . $order->id . (self::is($order) ? '_whitelabel' : '');
    }

    /**
     * The thank-you page on the whitelabel site, as handed to the payment page.
     */
    public static function returnUrl(Order $order): ?string
    {
        if (!self::is($order)) {
            return null;
        }

        return self::cleanUrl(($order->meta ?? [])['return_url'] ?? null);
    }

    /**
     * An absolute http(s) URL, or null.
     */
    public static function cleanUrl(mixed $url): ?string
    {
        if (!is_string($url)) {
            return null;
        }
        $url = trim($url);
        if ($url === '' || strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
