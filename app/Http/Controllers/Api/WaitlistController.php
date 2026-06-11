<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaitlistEntry;
use App\Models\ResaleListing;
use App\Models\Ticket;
use App\Models\Customer;
use App\Services\Waitlist\WaitlistService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class WaitlistController extends Controller
{
    public function __construct(protected WaitlistService $waitlistService) {}

    /**
     * Join waitlist
     * POST /api/waitlist/join
     */
    public function join(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'event_id' => 'required|exists:events,id',
            'ticket_type_id' => 'nullable|exists:ticket_types,id',
            'email' => 'required|email',
            'name' => 'nullable|string|max:255',
            'quantity' => 'nullable|integer|min:1|max:10',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->waitlistService->join($request->all());

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Get waitlist position
     * GET /api/waitlist/{entryId}/position
     */
    public function position(int $entryId): JsonResponse
    {
        $position = $this->waitlistService->getPosition($entryId);
        $entry = WaitlistEntry::findOrFail($entryId);

        return response()->json([
            'success' => true,
            'position' => $position,
            'status' => $entry->status,
        ]);
    }

    /**
     * Leave waitlist
     * DELETE /api/waitlist/{entryId}
     */
    public function leave(int $entryId): JsonResponse
    {
        $entry = WaitlistEntry::findOrFail($entryId);
        $entry->update(['status' => 'cancelled']);

        return response()->json(['success' => true]);
    }

    /**
     * List ticket for resale
     * POST /api/resale/list
     */
    public function listForResale(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ticket_id' => 'required|exists:tickets,id',
            'asking_price' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = Ticket::findOrFail($request->ticket_id);
        $seller = Customer::findOrFail($request->customer_id ?? $ticket->customer_id);

        $result = $this->waitlistService->listForResale($ticket, $request->asking_price, $seller);

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Browse resale listings
     * GET /api/resale/listings
     */
    public function browseResale(Request $request): JsonResponse
    {
        $listings = ResaleListing::with(['ticket.event', 'seller'])
            ->where('tenant_id', $request->tenant_id)
            ->when($request->event_id, fn($q) => $q->whereHas('ticket', fn($t) =>
                $t->where('event_id', $request->event_id)))
            ->active()
            ->orderBy('asking_price')
            ->paginate($request->per_page ?? 20);

        return response()->json(['success' => true, 'listings' => $listings]);
    }

    /**
     * Purchase resale listing
     * POST /api/resale/listings/{listingId}/purchase
     */
    public function purchaseResale(Request $request, int $listingId): JsonResponse
    {
        $listing = ResaleListing::findOrFail($listingId);
        $buyer = Customer::findOrFail($request->customer_id);

        $result = $this->waitlistService->purchaseResale($listing, $buyer);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get resale statistics
     * GET /api/resale/stats
     */
    public function resaleStats(Request $request): JsonResponse
    {
        $stats = $this->waitlistService->getResaleStats($request->tenant_id);
        return response()->json(['success' => true, 'stats' => $stats]);
    }
}
