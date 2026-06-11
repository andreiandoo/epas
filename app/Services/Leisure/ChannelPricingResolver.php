<?php

namespace App\Services\Leisure;

use App\Models\TicketType;

/**
 * Resolves the effective price for a given sales channel. Channel prices are
 * stored as ABSOLUTE values (cents) per channel — operator readability beats
 * delta-based modifier math when staff is editing 4+ channels.
 *
 * Resolution order:
 *   1. ticket_types.channel_pricing[channel]  → that value
 *   2. fallback to base price_cents
 *
 * Composing with LeisurePricingResolver: this resolver returns the BASE
 * channel price; LeisurePricingResolver then applies date/duration/season
 * rules ON TOP. Call signature mirrors the leisure resolver's.
 */
class ChannelPricingResolver
{
    public const CHANNEL_ONLINE = 'online';
    public const CHANNEL_POS_FIXED = 'pos_fixed';
    public const CHANNEL_POS_MOBILE = 'pos_mobile';
    public const CHANNEL_EMBED = 'embed';
    public const CHANNEL_PARTNER_APP = 'partner_app';

    public const CHANNELS = [
        self::CHANNEL_ONLINE => 'Online',
        self::CHANNEL_POS_FIXED => 'POS (punct fix)',
        self::CHANNEL_POS_MOBILE => 'POS (mobil)',
        self::CHANNEL_EMBED => 'Embed widget',
        self::CHANNEL_PARTNER_APP => 'Aplicație parteneri',
    ];

    /**
     * Returns the base price (cents) for the given ticket type on the given
     * channel. If the channel is not configured, falls back to default.
     */
    public function basePriceForChannel(TicketType $ticketType, string $channel): int
    {
        $map = $ticketType->channel_pricing;
        if (is_array($map) && isset($map[$channel]) && is_numeric($map[$channel])) {
            return (int) $map[$channel];
        }

        // Default fallback: price_cents raw column.
        $cents = $ticketType->getAttributes()['price_cents']
            ?? $ticketType->getRawOriginal('price_cents')
            ?? null;
        return $cents !== null && is_numeric($cents) ? (int) $cents : 0;
    }

    /**
     * Validates that a channel identifier is one of the known channels.
     */
    public function isValidChannel(string $channel): bool
    {
        return array_key_exists($channel, self::CHANNELS);
    }
}
