<?php

namespace App\Support;

/**
 * Routing helper for marketplace emails.
 *
 * Two categories: TRANSACTIONAL templates (account flows, tickets, invoices,
 * deconturi, refunds, alerts...) and BULK/NEWSLETTER templates (mass marketing
 * campaigns sent through the Newsletters component). Everything that ISN'T
 * explicitly bulk is considered transactional and routed through the
 * platform-owned transactional provider when configured.
 *
 * When a marketplace has not configured a transactional provider, the
 * transport methods on MarketplaceClient transparently fall back to the
 * primary provider, so existing send paths keep working unchanged.
 */
final class EmailRouting
{
    /**
     * Slugs that must NEVER route through the transactional provider.
     * These are bulk/marketing emails authored in the Newsletters UI.
     */
    public const BULK_SLUGS = [
        'newsletter',
        'newsletter_test',
    ];

    /**
     * Explicit list of transactional slugs. Kept as a denylist-friendly default:
     * if a slug isn't listed here AND isn't in BULK_SLUGS, the safer choice is
     * to treat it as transactional (see isTransactional()).
     *
     * Covers: ticket delivery (all variants), order/refund flows, account
     * verification & password resets, invitations, invoices, deconturi,
     * organizer/artist account flows, admin notifications.
     */
    public const TRANSACTIONAL_SLUGS = [
        // Order / ticket delivery
        'ticket_purchase',
        'order_confirmation',
        'ticket_delivery',
        'beneficiary_ticket',
        'pos_tickets',
        'invitation',

        // Account flows (customer / organizer / artist)
        'welcome',
        'account_created',
        'email_verification',
        'password_reset',
        'referral_welcome',
        'newsletter_welcome',
        'organizer_email_verification',
        'organizer_password_reset',
        'artist_email_verification',
        'artist_password_reset',
        'artist_account_approved',
        'artist_account_rejected',
        'admin_customer_password_changed',

        // Refunds / cancellations
        'refund_approved',
        'refund_rejected',
        'refund_processed',
        'order_refunded',
        'ticket_cancelled',

        // Event lifecycle (organizer-facing)
        'organizer_event_approved',
        'organizer_event_rejected',
        'event_approved',
        'event_rejected',
        'admin_event_cancelled',
        'admin_event_postponed',

        // Financial documents
        'invoice_send',
        'decont_send',

        // Operational alerts
        'stock_low_alert',
    ];

    /**
     * A slug is transactional unless it is on the explicit bulk denylist.
     *
     * Defaulting unknown slugs to "transactional" keeps the platform-owned
     * provider as the safer side: a forgotten new template won't accidentally
     * be sent through the marketing-rate-limited Brevo plan.
     */
    public static function isTransactional(?string $slug): bool
    {
        if ($slug === null || $slug === '') {
            return false;
        }

        if (in_array($slug, self::BULK_SLUGS, true)) {
            return false;
        }

        // Anything not explicitly bulk is treated as transactional.
        return true;
    }
}
