<?php

namespace App\Services\Shorts;

use App\Models\MarketplaceCustomer;
use App\Models\Short;
use App\Models\ShortReminder;
use App\Models\TicketType;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
     * Setting a reminder is also an interest signal — mirror it into the
     * watchlist the rest of the product already reads.
     */
    protected function addToWatchlist(Short $short, MarketplaceCustomer $customer): void
    {
        if (! $short->event_id) {
            return;
        }

        try {
            DB::table('marketplace_customer_watchlist')->updateOrInsert(
                [
                    'marketplace_customer_id' => $customer->id,
                    'event_id' => $short->event_id,
                ],
                ['updated_at' => now(), 'created_at' => now()],
            );
        } catch (\Throwable $e) {
            // The watchlist is a nice-to-have here; never fail the reminder for it.
            Log::debug('Shorts: could not mirror reminder into watchlist', ['error' => $e->getMessage()]);
        }
    }
}
