<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnafQueue;
use App\Services\EFactura\EFacturaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * eFactura API Controller
 *
 * Endpoints for managing automatic submission of invoices to ANAF SPV.
 */
class EFacturaController extends Controller
{
    protected EFacturaService $eFacturaService;

    public function __construct(EFacturaService $eFacturaService)
    {
        $this->eFacturaService = $eFacturaService;
    }

    /**
     * Queue invoice for eFactura submission
     *
     * POST /api/efactura/submit
     *
     * Body:
     * {
     *   "tenant": "tenant_123",
     *   "invoice_id": 456,
     *   "invoice": {
     *     "invoice_number": "FAC-2025-001",
     *     "issue_date": "2025-11-16",
     *     "seller": {...},
     *     "buyer": {...},
     *     "lines": [...]
     *   }
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "queue_id": 1,
     *   "status": "queued",
     *   "message": "Invoice queued for eFactura submission"
     * }
     */
    public function submit(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'tenant' => 'required|string',
            'invoice_id' => 'required|integer',
            'invoice' => 'required|array',
            'invoice.invoice_number' => 'required|string',
            'invoice.seller' => 'required|array',
            'invoice.buyer' => 'required|array',
            'invoice.lines' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->eFacturaService->queueInvoice(
            $request->tenant,
            $request->invoice_id,
            $request->invoice
        );

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    /**
     * Manual retry for failed submission
     *
     * POST /api/efactura/retry
     *
     * Body:
     * {
     *   "queue_id": 1
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "remote_id": "ANAF-ABC123",
     *   "message": "Submitted to ANAF"
     * }
     */
    public function retry(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'queue_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->eFacturaService->retry($request->queue_id);

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    /**
     * Get queue entry status
     *
     * GET /api/efactura/status/{queueId}
     *
     * Response:
     * {
     *   "queue_id": 1,
     *   "status": "accepted",
     *   "invoice_id": 456,
     *   "anaf_ids": {...},
     *   "attempts": 1,
     *   "created_at": "2025-11-16T10:00:00Z"
     * }
     */
    public function status(int $queueId): JsonResponse
    {
        $queue = AnafQueue::find($queueId);

        if (!$queue) {
            return response()->json([
                'success' => false,
                'message' => 'Queue entry not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'queue_id' => $queue->id,
            'status' => $queue->status,
            'invoice_id' => $queue->invoice_id,
            'anaf_ids' => $queue->anaf_ids,
            'error_message' => $queue->error_message,
            'attempts' => $queue->attempts,
            'max_attempts' => $queue->max_attempts,
            'submitted_at' => $queue->submitted_at?->toIso8601String(),
            'accepted_at' => $queue->accepted_at?->toIso8601String(),
            'rejected_at' => $queue->rejected_at?->toIso8601String(),
            'created_at' => $queue->created_at->toIso8601String(),
        ]);
    }

    /**
     * Poll ANAF for status update
     *
     * POST /api/efactura/poll
     *
     * Body:
     * {
     *   "queue_id": 1
     * }
     *
     * Response:
     * {
     *   "success": true,
     *   "status": "accepted",
     *   "message": "Invoice accepted by ANAF"
     * }
     */
    public function poll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'queue_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $queue = AnafQueue::find($request->queue_id);

        if (!$queue) {
            return response()->json([
                'success' => false,
                'message' => 'Queue entry not found',
            ], 404);
        }

        $result = $this->eFacturaService->pollStatus($queue);

        return response()->json($result);
    }

    /**
     * Download ANAF receipt/confirmation
     *
     * GET /api/efactura/download/{queueId}
     *
     * Response: PDF file download
     */
    public function download(int $queueId)
    {
        $result = $this->eFacturaService->downloadReceipt($queueId);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 404);
        }

        return response($result['content'])
            ->header('Content-Type', $result['mime_type'])
            ->header('Content-Disposition', 'attachment; filename="' . $result['filename'] . '"');
    }

    /**
     * Get eFactura statistics for tenant
     *
     * GET /api/efactura/stats/{tenantId}
     *
     * Response:
     * {
     *   "queued": 5,
     *   "submitted": 3,
     *   "accepted": 20,
     *   "rejected": 2,
     *   "error": 1
     * }
     */
    public function stats(string $tenantId): JsonResponse
    {
        $stats = $this->eFacturaService->getStats($tenantId);

        return response()->json([
            'success' => true,
            'tenant_id' => $tenantId,
            'stats' => $stats,
        ]);
    }

    /**
     * List queue entries for tenant
     *
     * GET /api/efactura/queue/{tenantId}?status=error&limit=50
     *
     * Response:
     * {
     *   "success": true,
     *   "entries": [...]
     * }
     */
    public function queueList(Request $request, string $tenantId): JsonResponse
    {
        $query = AnafQueue::forTenant($tenantId);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Pagination
        $limit = min($request->get('limit', 50), 200);
        $offset = $request->get('offset', 0);

        $entries = $query->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'entries' => $entries->map(function ($queue) {
                return [
                    'queue_id' => $queue->id,
                    'invoice_id' => $queue->invoice_id,
                    'status' => $queue->status,
                    'error_message' => $queue->error_message,
                    'attempts' => $queue->attempts,
                    'created_at' => $queue->created_at->toIso8601String(),
                ];
            }),
        ]);
    }
}
