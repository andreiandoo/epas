<?php

namespace App\Http\Controllers\Api\MarketplaceClient\Organizer;

use App\Http\Controllers\Api\MarketplaceClient\BaseController;
use App\Models\MarketplaceOrganizer;
use App\Models\MarketplaceRefundRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class RefundReportController extends BaseController
{
    /**
     * Get refund requests for organizer's events
     */
    public function index(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $query = MarketplaceRefundRequest::where('marketplace_organizer_id', $organizer->id)
            ->with(['customer:id,first_name,last_name,email', 'order:id,total,currency,created_at']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by date range
        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->input('from_date'));
        }
        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->input('to_date'));
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return $this->success([
            'refund_requests' => $requests->map(function ($req) {
                return [
                    'id' => $req->id,
                    'request_number' => $req->request_number,
                    'customer' => [
                        'name' => $req->customer?->full_name,
                        'email' => $req->customer?->email,
                    ],
                    'order_id' => $req->order_id,
                    'order_total' => $req->order?->total,
                    'type' => $req->type,
                    'reason' => $req->reason,
                    'reason_label' => $req->reason_label,
                    'requested_amount' => $req->requested_amount,
                    'approved_amount' => $req->approved_amount,
                    'status' => $req->status,
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
     * Get refund statistics for organizer
     */
    public function statistics(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);
        $year = $request->input('year', now()->year);
        $month = $request->input('month');

        $query = MarketplaceRefundRequest::where('marketplace_organizer_id', $organizer->id)
            ->whereYear('created_at', $year);

        if ($month) {
            $query->whereMonth('created_at', $month);
        }

        $all = $query->get();

        $stats = [
            'total_requests' => $all->count(),
            'pending' => $all->where('status', 'pending')->count(),
            'under_review' => $all->where('status', 'under_review')->count(),
            'approved' => $all->where('status', 'approved')->count(),
            'rejected' => $all->where('status', 'rejected')->count(),
            'refunded' => $all->whereIn('status', ['refunded', 'partially_refunded'])->count(),
            'total_requested_amount' => (float) $all->sum('requested_amount'),
            'total_approved_amount' => (float) $all->whereIn('status', ['approved', 'refunded', 'partially_refunded'])->sum('approved_amount'),
            'total_refunded_amount' => (float) $all->whereIn('status', ['refunded', 'partially_refunded'])->sum('approved_amount'),
        ];

        // Breakdown by reason
        $byReason = $all->groupBy('reason')->map(function ($items, $reason) {
            return [
                'reason' => $reason,
                'reason_label' => MarketplaceRefundRequest::REASONS[$reason] ?? $reason,
                'count' => $items->count(),
                'total_amount' => (float) $items->sum('requested_amount'),
            ];
        })->values();

        // Monthly breakdown (if viewing year)
        $monthlyBreakdown = [];
        if (!$month) {
            for ($m = 1; $m <= 12; $m++) {
                $monthData = $all->filter(function ($req) use ($m) {
                    return $req->created_at->month === $m;
                });

                $monthlyBreakdown[] = [
                    'month' => $m,
                    'month_name' => Carbon::create($year, $m, 1)->format('F'),
                    'requests' => $monthData->count(),
                    'requested_amount' => (float) $monthData->sum('requested_amount'),
                    'refunded_amount' => (float) $monthData->whereIn('status', ['refunded', 'partially_refunded'])->sum('approved_amount'),
                ];
            }
        }

        return $this->success([
            'period' => $month ? Carbon::create($year, $month, 1)->format('F Y') : "Year {$year}",
            'statistics' => $stats,
            'by_reason' => $byReason,
            'monthly_breakdown' => $monthlyBreakdown,
        ]);
    }

    /**
     * Get single refund request details
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $refundRequest = MarketplaceRefundRequest::where('marketplace_organizer_id', $organizer->id)
            ->with(['customer', 'order.tickets.ticketType', 'order.event'])
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
                'customer' => [
                    'name' => $refundRequest->customer->full_name,
                    'email' => $refundRequest->customer->email,
                    'phone' => $refundRequest->customer->phone,
                ],
                'order' => [
                    'id' => $refundRequest->order->id,
                    'total' => $refundRequest->order->total,
                    'currency' => $refundRequest->order->currency,
                    'created_at' => $refundRequest->order->created_at->toIso8601String(),
                    'event' => [
                        'id' => $refundRequest->order->event?->id,
                        'name' => $refundRequest->order->event?->name,
                    ],
                    'tickets' => $refundRequest->order->tickets->map(function ($ticket) {
                        return [
                            'id' => $ticket->id,
                            'code' => $ticket->code,
                            'ticket_type' => $ticket->ticketType?->name,
                            'price' => $ticket->price,
                            'is_cancelled' => $ticket->is_cancelled,
                        ];
                    }),
                ],
            ],
        ]);
    }

    /**
     * Export refund report
     */
    public function export(Request $request): JsonResponse
    {
        $organizer = $this->requireOrganizer($request);

        $validated = $request->validate([
            'format' => 'required|in:json,csv',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
        ]);

        $requests = MarketplaceRefundRequest::where('marketplace_organizer_id', $organizer->id)
            ->whereBetween('created_at', [$validated['from_date'], $validated['to_date']])
            ->with(['customer:id,first_name,last_name,email', 'order:id,total,currency'])
            ->orderBy('created_at', 'desc')
            ->get();

        $data = $requests->map(function ($req) {
            return [
                'request_number' => $req->request_number,
                'date' => $req->created_at->format('Y-m-d H:i'),
                'customer_name' => $req->customer?->full_name,
                'customer_email' => $req->customer?->email,
                'order_id' => $req->order_id,
                'order_total' => $req->order?->total,
                'type' => $req->type,
                'reason' => $req->reason_label,
                'requested_amount' => $req->requested_amount,
                'approved_amount' => $req->approved_amount,
                'status' => $req->status,
                'processed_at' => $req->processed_at?->format('Y-m-d H:i'),
            ];
        });

        if ($validated['format'] === 'csv') {
            // Return CSV-ready format
            return $this->success([
                'format' => 'csv',
                'headers' => array_keys($data->first() ?? []),
                'rows' => $data->values(),
                'filename' => "refunds-{$validated['from_date']}-{$validated['to_date']}.csv",
            ]);
        }

        return $this->success([
            'format' => 'json',
            'period' => [
                'from' => $validated['from_date'],
                'to' => $validated['to_date'],
            ],
            'total_records' => $requests->count(),
            'data' => $data,
        ]);
    }

    /**
     * Require authenticated organizer
     */
    protected function requireOrganizer(Request $request): MarketplaceOrganizer
    {
        $organizer = $request->user();

        if (!$organizer instanceof MarketplaceOrganizer) {
            abort(401, 'Unauthorized');
        }

        return $organizer;
    }
}
