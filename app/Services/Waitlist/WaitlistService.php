<?php

namespace App\Services\Waitlist;

use App\Models\WaitlistEntry;
use App\Models\ResaleListing;
use App\Models\ResaleTransaction;
use App\Models\Ticket;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class WaitlistService
{
    /**
     * Join waitlist for an event
     */
    public function join(array $data): array
    {
        // Check if already on waitlist
        $existing = WaitlistEntry::where('event_id', $data['event_id'])
            ->where('email', $data['email'])
            ->whereIn('status', ['waiting', 'notified'])
            ->first();

        if ($existing) {
            return [
                'success' => false,
                'error' => 'Already on waitlist',
                'entry' => $existing,
            ];
        }

        $entry = WaitlistEntry::create([
            'tenant_id' => $data['tenant_id'],
            'event_id' => $data['event_id'],
            'ticket_type_id' => $data['ticket_type_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'email' => $data['email'],
            'name' => $data['name'] ?? null,
            'quantity' => $data['quantity'] ?? 1,
            'priority' => $data['priority'] ?? 0,
            'status' => 'waiting',
        ]);

        return ['success' => true, 'entry' => $entry];
    }

    /**
     * Get position in waitlist
     */
    public function getPosition(int $entryId): int
    {
        $entry = WaitlistEntry::findOrFail($entryId);

        return WaitlistEntry::where('event_id', $entry->event_id)
            ->where('status', 'waiting')
            ->where(function ($query) use ($entry) {
                $query->where('priority', '>', $entry->priority)
                    ->orWhere(function ($q) use ($entry) {
                        $q->where('priority', $entry->priority)
                            ->where('created_at', '<', $entry->created_at);
                    });
            })
            ->count() + 1;
    }

    /**
     * Notify next people in waitlist
     */
    public function notifyNext(int $eventId, int $availableTickets): array
    {
        $entries = WaitlistEntry::forEvent($eventId)
            ->waiting()
            ->byPriority()
            ->get();

        $notified = 0;
        $remaining = $availableTickets;

        foreach ($entries as $entry) {
            if ($remaining < $entry->quantity) continue;

            $entry->update([
                'status' => 'notified',
                'notified_at' => now(),
                'expires_at' => now()->addHours(24),
            ]);

            // Send notification email
            $this->sendNotificationEmail($entry);

            $remaining -= $entry->quantity;
            $notified++;
        }

        return ['notified' => $notified, 'remaining_tickets' => $remaining];
    }

    /**
     * Process expired waitlist entries
     */
    public function processExpired(): int
    {
        return WaitlistEntry::where('status', 'notified')
            ->where('expires_at', '<=', now())
            ->update(['status' => 'expired']);
    }

    protected function sendNotificationEmail(WaitlistEntry $entry): void
    {
        // Mail::to($entry->email)->queue(new WaitlistNotification($entry));
        Log::info('Waitlist notification sent', ['entry_id' => $entry->id, 'email' => $entry->email]);
    }

    /**
     * List ticket for resale
     */
    public function listForResale(Ticket $ticket, float $askingPrice, Customer $seller): array
    {
        // Validate ticket can be resold
        if ($ticket->status !== 'sold' || $ticket->customer_id !== $seller->id) {
            return ['success' => false, 'error' => 'Cannot resell this ticket'];
        }

        // Check existing listing
        if (ResaleListing::where('ticket_id', $ticket->id)->active()->exists()) {
            return ['success' => false, 'error' => 'Ticket already listed'];
        }

        // Get policy and validate price
        $maxPrice = $ticket->price * 1.2; // 120% max markup
        if ($askingPrice > $maxPrice) {
            return ['success' => false, 'error' => "Price exceeds maximum ({$maxPrice})"];
        }

        $listing = ResaleListing::create([
            'tenant_id' => $ticket->tenant_id,
            'ticket_id' => $ticket->id,
            'seller_customer_id' => $seller->id,
            'original_price' => $ticket->price,
            'asking_price' => $askingPrice,
            'max_allowed_price' => $maxPrice,
            'status' => 'active',
            'listed_at' => now(),
        ]);

        return ['success' => true, 'listing' => $listing];
    }

    /**
     * Purchase a resale listing
     */
    public function purchaseResale(ResaleListing $listing, Customer $buyer): array
    {
        if (!$listing->isActive()) {
            return ['success' => false, 'error' => 'Listing no longer available'];
        }

        if ($listing->seller_customer_id === $buyer->id) {
            return ['success' => false, 'error' => 'Cannot buy your own listing'];
        }

        DB::beginTransaction();
        try {
            $platformFee = $listing->asking_price * 0.03; // 3% fee
            $sellerPayout = $listing->asking_price - $platformFee;

            // Update listing
            $listing->update([
                'status' => 'sold',
                'sold_at' => now(),
                'buyer_customer_id' => $buyer->id,
                'platform_fee' => $platformFee,
                'seller_payout' => $sellerPayout,
            ]);

            // Create transaction
            $transaction = ResaleTransaction::create([
                'listing_id' => $listing->id,
                'buyer_customer_id' => $buyer->id,
                'seller_customer_id' => $listing->seller_customer_id,
                'sale_price' => $listing->asking_price,
                'platform_fee' => $platformFee,
                'seller_payout' => $sellerPayout,
                'payout_status' => 'pending',
            ]);

            // Transfer ticket ownership
            $listing->ticket->update(['customer_id' => $buyer->id]);

            DB::commit();

            return ['success' => true, 'transaction' => $transaction];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get resale statistics
     */
    public function getResaleStats(string $tenantId): array
    {
        return [
            'active_listings' => ResaleListing::forTenant($tenantId)->active()->count(),
            'total_sold' => ResaleListing::forTenant($tenantId)->where('status', 'sold')->count(),
            'total_revenue' => ResaleTransaction::whereHas('listing', fn($q) =>
                $q->where('tenant_id', $tenantId))->sum('platform_fee'),
        ];
    }
}
