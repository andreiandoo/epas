<?php

namespace App\Services\Tracking;

use App\Models\Order;
use App\Models\Platform\CoreCustomer;
use App\Models\Tracking\TxEvent;
use App\Models\Tracking\TxIdentityLink;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TxEventEmitter
{
    /**
     * Emit a server-side tracking event.
     */
    public function emit(
        string $eventName,
        int $tenantId,
        array $payload = [],
        array $entities = [],
        ?string $visitorId = null,
        ?string $sessionId = null,
        ?int $personId = null,
        ?string $sourceSystem = 'backend',
        ?string $idempotencyKey = null
    ): ?TxEvent {
        try {
            // Check idempotency
            if ($idempotencyKey) {
                $existing = TxEvent::findByIdempotencyKey($idempotencyKey);
                if ($existing) {
                    Log::debug('TxEventEmitter: Duplicate event skipped', ['idempotency_key' => $idempotencyKey]);
                    return $existing;
                }
            }

            // Build consent snapshot for server-side events
            $consentSnapshot = [
                'analytics' => true,
                'marketing' => false,
                'data_processing' => $payload['consent_data_processing'] ?? true,
            ];

            $event = TxEvent::create([
                'event_id' => Str::uuid(),
                'event_name' => $eventName,
                'event_version' => 1,
                'occurred_at' => now(),
                'received_at' => now(),
                'tenant_id' => $tenantId,
                'source_system' => $sourceSystem,
                'visitor_id' => $visitorId,
                'session_id' => $sessionId,
                'person_id' => $personId,
                'consent_snapshot' => $consentSnapshot,
                'context' => [],
                'entities' => $entities,
                'payload' => $payload,
                'idempotency_key' => $idempotencyKey,
            ]);

            Log::info('TxEventEmitter: Event emitted', [
                'event_id' => $event->event_id,
                'event_name' => $eventName,
                'tenant_id' => $tenantId,
            ]);

            return $event;

        } catch (\Exception $e) {
            Log::error('TxEventEmitter: Failed to emit event', [
                'event_name' => $eventName,
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Emit order_completed event and perform identity stitching.
     */
    public function emitOrderCompleted(Order $order): ?TxEvent
    {
        // Get visitor_id and session_id from order metadata
        $metadata = $order->metadata ?? $order->meta ?? [];
        $visitorId = $metadata['visitor_id'] ?? $metadata['_tx_visitor_id'] ?? null;
        $sessionId = $metadata['session_id'] ?? $metadata['_tx_session_id'] ?? null;

        // Find or create person_id
        $personId = $this->findOrCreatePerson($order);

        // Build event entities
        $entities = [
            'order_id' => (string) $order->id,
        ];

        // Add event_entity_id if available
        if ($order->event_id) {
            $entities['event_entity_id'] = (string) $order->event_id;
        } elseif ($order->marketplace_event_id) {
            $entities['event_entity_id'] = (string) $order->marketplace_event_id;
        }

        // Build payload
        $payload = [
            'order_number' => $order->order_number,
            'gross_amount' => (float) ($order->total ?? ($order->total_cents / 100)),
            'net_amount' => (float) ($order->subtotal ?? $order->total),
            'fees_amount' => (float) ($order->commission_amount ?? 0),
            'discount_total' => (float) ($order->promo_discount ?? 0),
            'currency' => $order->currency ?? 'RON',
            'items' => $this->buildOrderItems($order),
            'affiliate_id' => $metadata['affiliate_id'] ?? null,
            'promo_code' => $order->promo_code,
            'channel' => $order->source ?? 'web',
            'payment_processor' => $order->payment_processor,
            'consent_data_processing' => $metadata['consent_data_processing'] ?? true,
            'consent_marketing' => $metadata['consent_marketing'] ?? false,
        ];

        $event = $this->emit(
            'order_completed',
            $order->tenant_id,
            $payload,
            $entities,
            $visitorId,
            $sessionId,
            $personId,
            'backend',
            "order_completed_{$order->id}"
        );

        // Perform identity stitching if we have visitor_id and person_id
        if ($event && $visitorId && $personId) {
            $this->performIdentityStitching($order, $visitorId, $personId);
        }

        return $event;
    }

    /**
     * Emit payment_succeeded event.
     */
    public function emitPaymentSucceeded(
        Order $order,
        string $providerTxId,
        float $amount,
        string $currency,
        ?int $latencyMs = null
    ): ?TxEvent {
        $metadata = $order->metadata ?? $order->meta ?? [];
        $personId = $this->findPerson($order);

        return $this->emit(
            'payment_succeeded',
            $order->tenant_id,
            [
                'provider' => $order->payment_processor ?? 'unknown',
                'provider_tx_id' => $providerTxId,
                'amount' => $amount,
                'currency' => $currency,
                'latency_ms' => $latencyMs,
            ],
            ['order_id' => (string) $order->id],
            $metadata['visitor_id'] ?? null,
            $metadata['session_id'] ?? null,
            $personId,
            'payments',
            "payment_succeeded_{$providerTxId}"
        );
    }

    /**
     * Emit payment_failed event.
     */
    public function emitPaymentFailed(
        Order $order,
        string $errorCode,
        string $errorMessage,
        ?string $providerTxId = null
    ): ?TxEvent {
        $metadata = $order->metadata ?? $order->meta ?? [];
        $personId = $this->findPerson($order);

        return $this->emit(
            'payment_failed',
            $order->tenant_id,
            [
                'provider' => $order->payment_processor ?? 'unknown',
                'provider_tx_id' => $providerTxId,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ],
            ['order_id' => (string) $order->id],
            $metadata['visitor_id'] ?? null,
            $metadata['session_id'] ?? null,
            $personId,
            'payments',
            $providerTxId ? "payment_failed_{$providerTxId}" : null
        );
    }

    /**
     * Emit refund_completed event.
     */
    public function emitRefundCompleted(
        Order $order,
        float $refundAmount,
        string $reason,
        ?string $providerRefundId = null
    ): ?TxEvent {
        $metadata = $order->metadata ?? $order->meta ?? [];
        $personId = $this->findPerson($order);

        return $this->emit(
            'refund_completed',
            $order->tenant_id,
            [
                'refund_amount' => $refundAmount,
                'refund_reason' => $reason,
                'refund_type' => $refundAmount >= ($order->total ?? 0) ? 'full' : 'partial',
                'currency' => $order->currency ?? 'RON',
                'provider_refund_id' => $providerRefundId,
            ],
            ['order_id' => (string) $order->id],
            $metadata['visitor_id'] ?? null,
            $metadata['session_id'] ?? null,
            $personId,
            'payments',
            $providerRefundId ? "refund_{$providerRefundId}" : "refund_order_{$order->id}"
        );
    }

    /**
     * Emit ticket_delivered event.
     */
    public function emitTicketDelivered(
        int $tenantId,
        int $orderId,
        int $ticketId,
        string $channel, // email, sms, wallet, download
        ?int $personId = null
    ): ?TxEvent {
        return $this->emit(
            'ticket_delivered',
            $tenantId,
            [
                'channel' => $channel,
                'delivered_at' => now()->toIso8601String(),
            ],
            [
                'order_id' => (string) $orderId,
                'ticket_id' => (string) $ticketId,
            ],
            null,
            null,
            $personId,
            'backend',
            "ticket_delivered_{$ticketId}_{$channel}"
        );
    }

    /**
     * Find or create CoreCustomer (person) from order.
     */
    protected function findOrCreatePerson(Order $order): ?int
    {
        $email = $order->customer_email;
        $phone = $order->customer_phone;

        if (!$email && !$phone) {
            return null;
        }

        // Try to find existing CoreCustomer
        $query = CoreCustomer::query();

        if ($email) {
            $query->where('email', $email);
        }

        if ($phone && !$email) {
            $query->where('phone', $phone);
        }

        $person = $query->first();

        if ($person) {
            return $person->id;
        }

        // Create new CoreCustomer
        try {
            $person = CoreCustomer::create([
                'email' => $email,
                'phone' => $phone,
                'first_name' => $this->extractFirstName($order->customer_name),
                'last_name' => $this->extractLastName($order->customer_name),
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'total_orders' => 1,
                'total_spent' => $order->total ?? ($order->total_cents / 100),
                'marketing_consent' => $order->metadata['consent_marketing'] ?? false,
            ]);

            return $person->id;

        } catch (\Exception $e) {
            Log::error('TxEventEmitter: Failed to create CoreCustomer', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Find existing CoreCustomer from order.
     */
    protected function findPerson(Order $order): ?int
    {
        $email = $order->customer_email;
        $phone = $order->customer_phone;

        if (!$email && !$phone) {
            return null;
        }

        $query = CoreCustomer::query();

        if ($email) {
            $query->where('email', $email);
        } elseif ($phone) {
            $query->where('phone', $phone);
        }

        return $query->value('id');
    }

    /**
     * Perform identity stitching.
     */
    protected function performIdentityStitching(Order $order, string $visitorId, int $personId): void
    {
        try {
            // Create identity link
            $link = TxIdentityLink::linkIdentity(
                $order->tenant_id,
                $visitorId,
                $personId,
                'order_completed',
                $order->id,
                1.0,
                [
                    'order_number' => $order->order_number,
                    'email' => $order->customer_email,
                ]
            );

            // Backfill person_id on historical events for this visitor
            $backfilled = TxEvent::backfillPersonId(
                $order->tenant_id,
                $visitorId,
                $personId
            );

            Log::info('TxEventEmitter: Identity stitched', [
                'tenant_id' => $order->tenant_id,
                'visitor_id' => $visitorId,
                'person_id' => $personId,
                'order_id' => $order->id,
                'backfilled_events' => $backfilled,
            ]);

        } catch (\Exception $e) {
            Log::error('TxEventEmitter: Identity stitching failed', [
                'visitor_id' => $visitorId,
                'person_id' => $personId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build order items array for payload.
     */
    protected function buildOrderItems(Order $order): array
    {
        $items = [];

        // Try items relation first
        if ($order->relationLoaded('items') || $order->items()->exists()) {
            foreach ($order->items as $item) {
                $items[] = [
                    'ticket_type_id' => $item->ticket_type_id ?? $item->product_id ?? null,
                    'qty' => $item->quantity ?? 1,
                    'unit_price' => (float) ($item->unit_price ?? $item->price ?? 0),
                    'name' => $item->name ?? $item->description ?? null,
                ];
            }
        }

        // Try tickets relation
        if (empty($items) && ($order->relationLoaded('tickets') || $order->tickets()->exists())) {
            $ticketCounts = $order->tickets->groupBy('ticket_type_id')->map->count();
            foreach ($ticketCounts as $ticketTypeId => $qty) {
                $ticket = $order->tickets->firstWhere('ticket_type_id', $ticketTypeId);
                $items[] = [
                    'ticket_type_id' => $ticketTypeId,
                    'qty' => $qty,
                    'unit_price' => (float) ($ticket->price ?? 0),
                ];
            }
        }

        return $items;
    }

    /**
     * Extract first name from full name.
     */
    protected function extractFirstName(?string $fullName): ?string
    {
        if (!$fullName) return null;
        $parts = explode(' ', trim($fullName), 2);
        return $parts[0] ?? null;
    }

    /**
     * Extract last name from full name.
     */
    protected function extractLastName(?string $fullName): ?string
    {
        if (!$fullName) return null;
        $parts = explode(' ', trim($fullName), 2);
        return $parts[1] ?? null;
    }
}
