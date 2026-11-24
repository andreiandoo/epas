<?php

namespace App\Services\Marketplace;

use App\Models\Event;
use App\Models\MarketplaceClient;
use App\Models\TicketType;

/**
 * "Cumperi X primești Y gratis" (ticket_types.bulk_discounts, rule buy_x_get_y).
 *
 * The paid tickets stay on their own type at full price. The free ones are
 * issued on the event's system "Invitatie" type (0 lei, not declarable), so
 * they never reach the fiscal documents or the commission. Each offer group
 * is tied together through tickets.meta.offer.group, which refunds use to
 * take the whole group back at once.
 *
 * Off unless the marketplace turns it on in Settings (bulk_offers.enabled).
 */
class BuyXGetYOffer
{
    /** @param MarketplaceClient|null $client */
    public static function enabledFor($client): bool
    {
        return (bool) data_get($client?->settings, 'bulk_offers.enabled', false);
    }

    /**
     * First valid buy_x_get_y rule of the type, normalised to ints.
     *
     * @return array{buy: int, get: int}|null
     */
    public static function ruleFor(?TicketType $tt): ?array
    {
        if (!$tt || !is_array($tt->bulk_discounts)) {
            return null;
        }
        $meta = is_array($tt->meta) ? $tt->meta : [];
        if (!empty($meta['is_invitation']) || $tt->isFreeWithCode()) {
            return null;
        }
        // Only on paid tickets.
        if ((int) ($tt->sale_price_cents ?? $tt->price_cents ?? 0) <= 0) {
            return null;
        }
        foreach ($tt->bulk_discounts as $rule) {
            if (!is_array($rule) || ($rule['rule_type'] ?? null) !== 'buy_x_get_y') {
                continue;
            }
            $buy = (int) ($rule['buy_qty'] ?? 0);
            $get = (int) ($rule['get_qty'] ?? 0);
            if ($buy >= 1 && $get >= 1) {
                return ['buy' => $buy, 'get' => $get];
            }
        }

        return null;
    }

    /** Free tickets earned by buying $paid tickets. */
    public static function freeFor(array $rule, int $paid): int
    {
        return intdiv(max(0, $paid), $rule['buy']) * $rule['get'];
    }

    /**
     * Seated: the customer picks every seat, paid and free. Inside each group
     * of X+Y seats the first X are paid and the last Y free; a partial group
     * is paid first. Returns [paid seat indices, free seat indices].
     *
     * @return array{0: int[], 1: int[]}
     */
    public static function splitSeats(array $rule, int $count): array
    {
        $size = $rule['buy'] + $rule['get'];
        $paid = [];
        $free = [];
        for ($i = 0; $i < $count; $i++) {
            if ($i % $size < $rule['buy']) {
                $paid[] = $i;
            } else {
                $free[] = $i;
            }
        }

        return [$paid, $free];
    }

    public static function label(array $rule): string
    {
        return $rule['buy'] . '+' . $rule['get'];
    }

    /** Name printed on the free ticket: "Invitatie – ofertă 2+1 Presale". */
    public static function freeTicketLabel(array $rule, string $paidTypeName): string
    {
        return trim('Invitatie – ofertă ' . self::label($rule) . ' ' . $paidTypeName);
    }

    /**
     * The event's system "Invitatie" type (same one the invitations module
     * uses), created on first need.
     */
    public static function invitationTicketType(Event $event): TicketType
    {
        $tt = TicketType::where('event_id', $event->id)
            ->where('name', 'Invitatie')
            ->first();

        if (!$tt) {
            $tt = TicketType::create([
                'event_id' => $event->id,
                'name' => 'Invitatie',
                'price_cents' => 0,
                'currency' => 'RON',
                'quota_total' => -1,
                'quota_sold' => 0,
                'meta' => ['is_invitation' => true],
                'is_declarable' => false,
            ]);
        }

        $meta = is_array($tt->meta) ? $tt->meta : [];
        $dirty = false;
        if (empty($meta['is_invitation'])) {
            $meta['is_invitation'] = true;
            $tt->meta = $meta;
            $dirty = true;
        }
        if (($tt->status ?? 'active') !== 'active') {
            $tt->status = 'active';
            $dirty = true;
        }
        if ($tt->is_declarable !== false) {
            $tt->is_declarable = false;
            $dirty = true;
        }
        if ($dirty) {
            $tt->save();
        }

        return $tt;
    }

    /** True for a ticket issued free by an offer. */
    public static function isFreeOfferTicket($ticket): bool
    {
        $meta = is_array($ticket->meta ?? null) ? $ticket->meta : [];

        return ($meta['offer']['role'] ?? null) === 'free';
    }

    /**
     * Name shown to the customer: "Invitatie – ofertă 2+1 Presale" for an
     * offer invitation, $fallback (the ticket type name) for anything else.
     */
    public static function displayName($ticket, $fallback)
    {
        $meta = is_array($ticket->meta ?? null) ? $ticket->meta : [];
        if (($meta['offer']['role'] ?? null) === 'free' && !empty($meta['label_override'])) {
            return (string) $meta['label_override'];
        }

        return $fallback;
    }

    public static function offerGroup($ticket): ?string
    {
        $meta = is_array($ticket->meta ?? null) ? $ticket->meta : [];
        $group = $meta['offer']['group'] ?? null;

        return is_string($group) && $group !== '' ? $group : null;
    }
}
