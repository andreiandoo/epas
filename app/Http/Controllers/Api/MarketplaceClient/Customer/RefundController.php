<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Customer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceRefundRequest;
use App\Models\MarketplaceCustomer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class RefundController extends BaseController
{
    /**
     * Get customer's refund requests
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $requests = MarketplaceRefundRequest::where('marketplace_customer_id', $customer->id)
            ->with(['order:id,total,currency,created_at'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->success([
            'refund_requests' => $requests->map(function ($req) {
                return [
                    'id' => $req->id,
                    'request_number' => $req->request_number,
                    'order_id' => $req->order_id,
                    'order_total' => $req->order?->total,
                    'type' => $req->type,
                    'reason' => $req->reason,
                    'reason_label' => $req->reason_label,
                    'requested_amount' => $req->requested_amount,
                    'approved_amount' => $req->approved_amount,
                    'status' => $req->status,
                    'customer_notes' => $req->customer_notes,
                    'admin_notes' => $req->admin_notes,
                    'created_at' => $req->created_at->toIso8601String(),
                    'processed_at' => $req->processed_at?->toIso8601String(),
                ];
            }),
            'pagination' => [
                'current_page' => $requests->currentPage(),
                'last_page' => $requests->lastPage(),
                'per_page' => $requests->perPage(),
                'total' => $requests->total(),
            ],
        ]);
    }

    /**
     * Get single refund request
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $refundRequest = MarketplaceRefundRequest::where('marketplace_customer_id', $customer->id)
            ->with(['order.tickets.ticketType', 'order.event'])
            ->findOrFail($id);

        return $this->success([
            'refund_request' => [
                'id' => $refundRequest->id,
                'request_number' => $refundRequest->request_number,
                'type' => $refundRequest->type,
                'reason' => $refundRequest->reason,
                'reason_label' => $refundRequest->reason_label,
                'customer_notes' => $refundRequest->customer_notes,
                'requested_amount' => $refundRequest->requested_amount,
                'approved_amount' => $refundRequest->approved_amount,
                'status' => $refundRequest->status,
                'admin_notes' => $refundRequest->admin_notes,
                'refund_method' => $refundRequest->refund_method,
                'refund_reference' => $refundRequest->refund_reference,
                'created_at' => $refundRequest->created_at->toIso8601String(),
                'processed_at' => $refundRequest->processed_at?->toIso8601String(),
                'order' => [
                    'id' => $refundRequest->order->id,
                    'total' => $refundRequest->order->total,
                    'currency' => $refundRequest->order->currency,
                    'event_name' => $refundRequest->order->event?->name,
                    'tickets_count' => $refundRequest->order->tickets->count(),
                ],
            ],
        ]);
    }

    /**
     * Create refund request
     */
    public function store(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $marketplace = $this->getMarketplace($request);

        $validated = $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'type' => 'required|in:full_refund,partial_refund,cancellation',
            'reason' => 'required|in:' . implode(',', array_keys(MarketplaceRefundRequest::REASONS)),
            'customer_notes' => 'nullable|string|max:1000',
            'requested_amount' => 'required_if:type,partial_refund|nullable|numeric|min:0.01',
            'ticket_ids' => 'nullable|array',
            'ticket_ids.*' => 'integer|exists:tickets,id',
        ]);

        // Verify order belongs to customer
        $order = Order::where('id', $validated['order_id'])
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $marketplace->id)
            ->firstOrFail();

        // Check if order can be refunded
        if (!in_array($order->status, ['completed', 'paid'])) {
            return $this->error('This order cannot be refunded', 422);
        }

        // Check for existing pending request
        $existingRequest = MarketplaceRefundRequest::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'under_review', 'approved', 'processing'])
            ->first();

        if ($existingRequest) {
            return $this->error('A refund request already exists for this order', 422);
        }

        // Determine amount
        $requestedAmount = $validated['type'] === 'partial_refund'
            ? $validated['requested_amount']
            : $order->total;

        // Create refund request
        $refundRequest = MarketplaceRefundRequest::create([
            'marketplace_client_id' => $marketplace->id,
            'marketplace_organizer_id' => $order->marketplace_organizer_id,
            'marketplace_customer_id' => $customer->id,
            'order_id' => $order->id,
            'type' => $validated['type'],
            'reason' => $validated['reason'],
            'customer_notes' => $validated['customer_notes'] ?? null,
            'requested_amount' => $requestedAmount,
            'status' => 'pending',
        ]);

        // Associate specific tickets if provided
        if (!empty($validated['ticket_ids'])) {
            $order->tickets()
                ->whereIn('id', $validated['ticket_ids'])
                ->update(['refund_request_id' => $refundRequest->id]);
        }

        // TODO: Send notification to marketplace admins
        // TODO: Send confirmation email to customer

        return $this->success([
            'refund_request' => [
                'id' => $refundRequest->id,
                'request_number' => $refundRequest->request_number,
                'status' => $refundRequest->status,
                'requested_amount' => $refundRequest->requested_amount,
                'created_at' => $refundRequest->created_at->toIso8601String(),
            ],
            'message' => 'Refund request submitted successfully. We will review it and get back to you soon.',
        ], 'Refund request created', 201);
    }

    /**
     * Cancel a pending refund request
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $customer = $this->requireCustomer($request);

        $refundRequest = MarketplaceRefundRequest::where('marketplace_customer_id', $customer->id)
            ->where('status', 'pending')
            ->findOrFail($id);

        $refundRequest->update([
            'status' => 'cancelled',
            'admin_notes' => 'Cancelled by customer',
        ]);

        // Remove ticket associations
        $refundRequest->tickets()->update(['refund_request_id' => null]);

        return $this->success([
            'refund_request' => [
                'id' => $refundRequest->id,
                'request_number' => $refundRequest->request_number,
                'status' => 'cancelled',
            ],
        ], 'Refund request cancelled');
    }

    /**
     * Get available reasons for refund
     */
    public function reasons(Request $request): JsonResponse
    {
        return $this->success([
            'reasons' => collect(MarketplaceRefundRequest::REASONS)->map(function ($label, $key) {
                return [
                    'value' => $key,
                    'label' => $label,
                ];
            })->values(),
        ]);
    }

    /**
     * Check if order is eligible for refund
     */
    public function checkEligibility(Request $request): JsonResponse
    {
        $customer = $this->requireCustomer($request);
        $marketplace = $this->getMarketplace($request);

        $orderId = $request->input('order_id');

        $order = Order::where('id', $orderId)
            ->where('marketplace_customer_id', $customer->id)
            ->where('marketplace_client_id', $marketplace->id)
            ->with('event')
            ->first();

        if (!$order) {
            return $this->error('Order not found', 404);
        }

        $eligible = in_array($order->status, ['completed', 'paid']);

        // Check for existing requests
        $existingRequest = MarketplaceRefundRequest::where('order_id', $order->id)
            ->whereIn('status', ['pending', 'under_review', 'approved', 'processing'])
            ->first();

        if ($existingRequest) {
            return $this->success([
                'eligible' => false,
                'reason' => 'A refund request already exists for this order',
                'existing_request' => [
                    'id' => $existingRequest->id,
                    'request_number' => $existingRequest->request_number,
                    'status' => $existingRequest->status,
                ],
            ]);
        }

        // Check event date (optional policy: can't refund after event)
        $eventPassed = $order->event && $order->event->start_date && $order->event->start_date->isPast();

        return $this->success([
            'eligible' => $eligible,
            'reason' => $eligible ? null : 'Order status does not allow refunds',
            'warnings' => $eventPassed ? ['The event has already passed. Refund approval may be at the organizer\'s discretion.'] : [],
            'order' => [
                'id' => $order->id,
                'total' => $order->total,
                'currency' => $order->currency,
                'status' => $order->status,
            ],
        ]);
    }

    /**
     * Require authenticated customer
     */
    protected function requireCustomer(Request $request): MarketplaceCustomer
    {
        $customer = $request->user();

        if (!$customer instanceof MarketplaceCustomer) {
            abort(401, 'Unauthorized');
        }

        return $customer;
    }
}
