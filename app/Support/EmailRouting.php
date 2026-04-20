<?php

namespace App\Support;

/**
 * Routing helper for marketplace emails.
 *
 * The 14 slugs below are the "transactional" templates that must always
 * be sent through the platform-owned (transactional) mail provider when
 * one is configured on the marketplace client. All other templates
 * continue to flow through the primary (organizer-configured) provider.
 *
 * When a marketplace has not configured a transactional provider, the
 * transport methods on MarketplaceClient transparently fall back to the
 * primary provider, so existing send paths keep working unchanged.
 */
final class EmailRouting
{
    public const TRANSACTIONAL_SLUGS = [
        'ticket_purchase',
        'order_confirmation',
        'welcome',
        'password_reset',
        'ticket_delivery',
        'refund_approved',
        'refund_rejected',
        'ticket_cancelled',
        'organizer_event_approved',
        'organizer_event_rejected',
        'admin_event_cancelled',
        'admin_event_postponed',
        'stock_low_alert',
        'refund_processed',
    ];

    public static function isTransactional(?string $slug): bool
    {
        return $slug !== null && $slug !== '' && in_array($slug, self::TRANSACTIONAL_SLUGS, true);
    }
}
