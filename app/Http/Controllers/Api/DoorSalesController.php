<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoorSale;
use App\Services\DoorSales\DoorSalesService;
use App\Events\DoorSaleCompleted;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DoorSalesController extends Controller
{
    public function __construct(protected DoorSalesService $service) {}

    /**
     * Get available events for door sales
     * GET /api/door-sales/events
     */
    public function events(Request $request): JsonResponse
    {
        $events = $this->service->getAvailableEvents($request->tenant_id);

        return response()->json([
            'success' => true,
            'events' => $events,
        ]);
    }

    /**
     * Get ticket types for an event
     * GET /api/door-sales/events/{eventId}/ticket-types
     */
    public function ticketTypes(int $eventId): JsonResponse
    {
        $ticketTypes = $this->service->getTicketTypes($eventId);

        return response()->json([
            'success' => true,
            'ticket_types' => $ticketTypes,
        ]);
    }

    /**
     * Calculate totals before payment
     * POST /api/door-sales/calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ticket_type_id' => 'required|exists:ticket_types,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $calculation = $this->service->calculate($request->all());

        return response()->json([
            'success' => true,
            'calculation' => $calculation,
        ]);
    }

    /**
     * Process door sale payment
     * POST /api/door-sales/process
     */
    public function process(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|exists:tenants,id',
            'event_id' => 'required|exists:events,id',
            'user_id' => 'required|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.ticket_type_id' => 'required|exists:ticket_types,id',
            'items.*.quantity' => 'required|integer|min:1|max:10',
            'payment_method' => 'required|in:card_tap,apple_pay,google_pay',
            'customer_email' => 'nullable|email',
            'customer_name' => 'nullable|string|max:255',
        ]);

        $result = $this->service->process($request->all());

        if ($result['success']) {
            event(new DoorSaleCompleted($result['door_sale']));

            return response()->json([
                'success' => true,
                'door_sale' => $result['door_sale'],
                'tickets_issued' => $result['tickets_issued'],
            ], 201);
        }

        return response()->json([
            'success' => false,
            'error' => $result['error'],
            'door_sale_id' => $result['door_sale_id'] ?? null,
        ], 400);
    }

    /**
     * Get door sale details
     * GET /api/door-sales/{id}
     */
    public function show(int $id): JsonResponse
    {
        $doorSale = DoorSale::with(['event', 'user', 'items.ticketType', 'order.tickets'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'door_sale' => $doorSale,
        ]);
    }

    /**
     * Get sales history
     * GET /api/door-sales/history
     */
    public function history(Request $request): JsonResponse
    {
        $history = $this->service->getHistory($request->tenant_id, $request->all());

        return response()->json([
            'success' => true,
            'history' => $history,
        ]);
    }

    /**
     * Get daily summary
     * GET /api/door-sales/summary
     */
    public function summary(Request $request): JsonResponse
    {
        $summary = $this->service->getDailySummary(
            $request->tenant_id,
            $request->event_id
        );

        return response()->json([
            'success' => true,
            'summary' => $summary,
        ]);
    }

    /**
     * Process refund
     * POST /api/door-sales/{id}/refund
     */
    public function refund(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'amount' => 'nullable|numeric|min:0.01',
        ]);

        $doorSale = DoorSale::findOrFail($id);
        $result = $this->service->refund($doorSale, $request->amount);

        if ($result['success']) {
            return response()->json($result);
        }

        return response()->json($result, 400);
    }

    /**
     * Resend tickets via email
     * POST /api/door-sales/{id}/resend
     */
    public function resend(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $doorSale = DoorSale::findOrFail($id);

        if ($doorSale->status !== DoorSale::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'error' => 'Can only resend for completed sales',
            ], 400);
        }

        $doorSale->update(['customer_email' => $request->email]);

        // Dispatch resend job
        // SendDoorSaleTicketsJob::dispatch($doorSale);

        return response()->json([
            'success' => true,
            'message' => 'Tickets will be sent to ' . $request->email,
        ]);
    }
}
