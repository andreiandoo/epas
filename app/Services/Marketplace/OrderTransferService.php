<?php

namespace App\Services\Marketplace;

use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEmailLog;
use App\Models\MarketplaceRefundRequest;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrderTransferService
{
    /**
     * Move an order from its current marketplace customer to another.
     *
     * Atomic: order row, refund requests, email logs (and optionally
     * tickets) all move in one DB transaction. Cached aggregates
     * (total_orders, total_spent) on both customers are recomputed
     * from the actual order rows after the move.
     *
     * Side-effects logged to:
     *  - Spatie activity log (Order has LogsActivity)
     *  - orders.metadata.transfers[] history array (for audit + undo UI)
     *
     * @param  Order                $order
     * @param  MarketplaceCustomer  $newCustomer
     * @param  string               $reason                 Required, persisted in activity log + metadata
     * @param  int|null             $performedByAdminId     Admin user id from auth() (optional)
     * @param  bool                 $rewriteTicketAttendee  When true, ticket attendee_name/email also move
     * @return array{from: ?int, to: int, tickets_updated: int, refund_requests_updated: int, email_logs_updated: int}
     */
    public function transfer(
        Order $order,
        MarketplaceCustomer $newCustomer,
        string $reason,
        ?int $performedByAdminId = null,
        bool $rewriteTicketAttendee = false
    ): array {
        $oldCustomerId = $order->marketplace_customer_id;
        $oldCustomer = $oldCustomerId ? MarketplaceCustomer::find($oldCustomerId) : null;

        $this->guardTransfer($order, $oldCustomer, $newCustomer);

        return DB::transaction(function () use ($order, $oldCustomer, $newCustomer, $reason, $performedByAdminId, $rewriteTicketAttendee) {
            // Snapshot for audit
            $before = [
                'marketplace_customer_id' => $order->marketplace_customer_id,
                'customer_email' => $order->customer_email,
                'customer_name' => $order->customer_name,
                'customer_phone' => $order->customer_phone,
            ];

            // 1. Order row
            $newName = trim(($newCustomer->first_name ?? '') . ' ' . ($newCustomer->last_name ?? ''));
            $order->fill([
                'marketplace_customer_id' => $newCustomer->id,
                'customer_email' => $newCustomer->email,
                'customer_name' => $newName !== '' ? $newName : $newCustomer->email,
                'customer_phone' => $newCustomer->phone,
            ]);

            $metadata = $order->metadata ?? [];
            $transfers = $metadata['transfers'] ?? [];
            $transfers[] = [
                'at' => now()->toIso8601String(),
                'by_admin_id' => $performedByAdminId,
                'from_customer_id' => $oldCustomer?->id,
                'from_email' => $oldCustomer?->email,
                'to_customer_id' => $newCustomer->id,
                'to_email' => $newCustomer->email,
                'reason' => $reason,
                'rewrote_tickets' => $rewriteTicketAttendee,
            ];
            $metadata['transfers'] = $transfers;
            $order->metadata = $metadata;
            $order->save();

            // 2. Tickets (optional — only if explicitly requested)
            $ticketsUpdated = 0;
            if ($rewriteTicketAttendee) {
                $ticketsUpdated = $order->tickets()->update([
                    'attendee_name' => $newName !== '' ? $newName : $newCustomer->email,
                    'attendee_email' => $newCustomer->email,
                ]);
            }

            // 3. Refund requests linked to this order
            $refundRequestsUpdated = MarketplaceRefundRequest::where('order_id', $order->id)
                ->update(['marketplace_customer_id' => $newCustomer->id]);

            // 4. Email logs linked to this order
            $emailLogsUpdated = MarketplaceEmailLog::where('order_id', $order->id)
                ->update(['marketplace_customer_id' => $newCustomer->id]);

            // 5. Recompute aggregates on both sides
            $oldCustomer?->updateStats();
            $newCustomer->refresh()->updateStats();

            // 6. Activity log (Spatie LogsActivity is on Order)
            activity('marketplace')
                ->performedOn($order)
                ->withProperties([
                    'marketplace_client_id' => $order->marketplace_client_id,
                    'before' => $before,
                    'after' => [
                        'marketplace_customer_id' => $newCustomer->id,
                        'customer_email' => $newCustomer->email,
                    ],
                    'reason' => $reason,
                    'performed_by_admin_id' => $performedByAdminId,
                    'rewrote_tickets' => $rewriteTicketAttendee,
                    'tickets_updated' => $ticketsUpdated,
                    'refund_requests_updated' => $refundRequestsUpdated,
                    'email_logs_updated' => $emailLogsUpdated,
                ])
                ->log("Order #{$order->id} transferred from {$oldCustomer?->email} to {$newCustomer->email}");

            return [
                'from' => $oldCustomer?->id,
                'to' => $newCustomer->id,
                'tickets_updated' => $ticketsUpdated,
                'refund_requests_updated' => $refundRequestsUpdated,
                'email_logs_updated' => $emailLogsUpdated,
            ];
        });
    }

    /**
     * Validate that the transfer is allowed. Throws on violation.
     */
    protected function guardTransfer(Order $order, ?MarketplaceCustomer $oldCustomer, MarketplaceCustomer $newCustomer): void
    {
        if ($newCustomer->trashed()) {
            throw new InvalidArgumentException('Destination customer is deleted.');
        }

        if ($order->marketplace_client_id && $newCustomer->marketplace_client_id !== $order->marketplace_client_id) {
            throw new InvalidArgumentException('Destination customer belongs to a different marketplace.');
        }

        // Block when source and destination share the same email — there is
        // nothing to transfer in that case and it usually means the operator
        // picked the wrong record.
        $oldEmail = $oldCustomer?->email ?? $order->customer_email;
        if ($oldEmail && strcasecmp($oldEmail, $newCustomer->email) === 0) {
            throw new InvalidArgumentException('Source and destination customers share the same email; nothing to transfer.');
        }
    }
}
