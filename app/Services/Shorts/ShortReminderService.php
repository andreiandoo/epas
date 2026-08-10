<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortReminder;
use App\Models\TicketType;
use Illuminate\Support\Carbon;

/**
 * "Remind me when tickets drop" (D2).
 *
 * When an event's tickets are not on sale yet, the short's CTA becomes a
 * reminder instead of a dead buy button — the highest-intent moment in the feed
 * is the one where you cannot yet sell.
 */
class ShortReminderService
{
    /**
     * Whether the short should show a countdown instead of a buy CTA, and when.
     *
     * @return array{on_sale_at: Carbon|null, ticket_type_id: int|null, pending: bool}
     */
    public function saleWindow(Short $short): array
    {
        $ticketType = $this->resolveTicketType($short);

        if (! $ticketType) {
            return ['on_sale_at' => null, 'ticket_type_id' => null, 'pending' => false];
        }

        $startsAt = $ticketType->sales_start_at;

        return [
            'on_sale_at' => $startsAt,
            'ticket_type_id' => $ticketType->id,
            'pending' => $startsAt !== null && $startsAt->isFuture(),
        ];
    }

    /**
     * Idempotent: tapping "remind me" twice must not create two reminders.
     */
    public function remind(Short $short, MarketplaceCustomer $customer): ShortReminder
    {
        $window = $this->saleWindow($short);

        $reminder = ShortReminder::query()->updateOrCreate(
            [
                'marketplace_customer_id' => $customer->id,
                'short_id' => $short->id,
            ],
            [
                'event_id' => $short->event_id,
                'ticket_type_id' => $window['ticket_type_id'],
                // Copied, not resolved at fire time: the customer was promised
                // *this* moment, and the ticket type may be edited or deleted.
                'remind_at' => $window['on_sale_at'],
                'notified_at' => null,
            ],
        );

        $this->addToWatchlist($short, $customer);

        return $reminder;
    }

    public function forget(Short $short, MarketplaceCustomer $customer): bool
    {
        return ShortReminder::query()
            ->where('marketplace_customer_id', $customer->id)
            ->where('short_id', $short->id)
            ->delete() > 0;
    }

    public function isReminded(Short $short, MarketplaceCustomer $customer): bool
    {
        return ShortReminder::query()
            ->where('marketplace_customer_id', $customer->id)
            ->where('short_id', $short->id)
            ->exists();
    }

    /**
     * The ticket type whose sale window governs this short: the one the CTA
     * points at, otherwise the earliest-opening one on the event.
     */
    protected function resolveTicketType(Short $short): ?TicketType
    {
        if ($short->cta_ticket_type_id) {
            $explicit = TicketType::find($short->cta_ticket_type_id);

            if ($explicit) {
                return $explicit;
            }
        }

        if (! $short->event_id) {
            return null;
        }

        return TicketType::query()
            ->where('event_id', $short->event_id)
            ->orderByRaw('sales_start_at IS NULL')
            ->orderBy('sales_start_at')
            ->first();
    }

    /**
     * Setting a reminder is also an interest signal, and mirroring it into
     * `marketplace_customer_watchlist` would let the rest of the product see it.
     *
     * TODO(owner): not wired, because it cannot be. That table requires
     * marketplace_event_id NOT NULL (the migration that added event_id says
     * outright it could not relax the existing FK and would "handle it in code"),
     * and a short only carries a core event_id. Making this work needs either a
     * migration relaxing marketplace_event_id, or a marketplace_event_id on
     * shorts — a schema decision, not something to guess at.
     *
     * Deliberately left as a no-op rather than an insert wrapped in a catch:
     * code that always throws and swallows looks like a working feature.
     */
    protected function addToWatchlist(Short $short, MarketplaceCustomer $customer): void
    {
        // Intentionally empty — see the note above.
    }
}
