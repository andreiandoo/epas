<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupBooking;
use App\Models\GroupBookingMember;
use App\Services\GroupBooking\GroupBookingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class GroupBookingController extends Controller
{
    public function __construct(protected GroupBookingService $service) {}

    /**
     * Create group booking
     * POST /api/group-bookings
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'event_id' => 'required|exists:events,id',
            'organizer_customer_id' => 'required|exists:customers,id',
            'group_name' => 'required|string|max:255',
            'group_type' => 'nullable|in:corporate,school,club,family',
            'total_tickets' => 'required|integer|min:2',
            'ticket_price' => 'required|numeric|min:0',
            'payment_type' => 'nullable|in:full,split,invoice',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->service->create($request->all());
        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Get booking details
     * GET /api/group-bookings/{id}
     */
    public function show(int $id): JsonResponse
    {
        $booking = GroupBooking::with(['members', 'event', 'organizer'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'booking' => $booking,
            'payment_progress' => $booking->getPaymentProgress(),
        ]);
    }

    /**
     * Add members
     * POST /api/group-bookings/{id}/members
     */
    public function addMembers(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'members' => 'required|array|min:1',
            'members.*.name' => 'required|string',
            'members.*.email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $booking = GroupBooking::findOrFail($id);
        $result = $this->service->addMembers($booking, $request->members);

        return response()->json($result, $result['success'] ? 201 : 400);
    }

    /**
     * Import members from CSV
     * POST /api/group-bookings/{id}/import
     */
    public function importMembers(Request $request, int $id): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt']);

        $booking = GroupBooking::findOrFail($id);
        $csvData = array_map('str_getcsv', file($request->file('file')->getPathname()));
        array_shift($csvData); // Remove header

        $result = $this->service->importMembers($booking, $csvData);
        return response()->json($result);
    }

    /**
     * Confirm booking
     * POST /api/group-bookings/{id}/confirm
     */
    public function confirm(int $id): JsonResponse
    {
        $booking = GroupBooking::findOrFail($id);
        $result = $this->service->confirm($booking);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Process member payment
     * POST /api/group-bookings/members/{memberId}/pay
     */
    public function processMemberPayment(Request $request, int $memberId): JsonResponse
    {
        $request->validate(['amount' => 'required|numeric|min:0']);

        $member = GroupBookingMember::findOrFail($memberId);
        $result = $this->service->processMemberPayment($member, $request->amount);

        return response()->json($result);
    }

    /**
     * Get statistics
     * GET /api/group-bookings/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $stats = $this->service->getStats($request->tenant_id);
        return response()->json(['success' => true, 'stats' => $stats]);
    }
}
