<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invite;
use App\Models\InviteBatch;
use App\Services\Invitations\InviteBatchService;
use App\Services\Invitations\InviteRenderService;
use App\Services\Invitations\InviteEmailService;
use App\Services\Invitations\InviteDownloadService;
use App\Services\Invitations\InviteTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class InviteController extends Controller
{
    public function __construct(
        protected InviteBatchService $batchService,
        protected InviteRenderService $renderService,
        protected InviteEmailService $emailService,
        protected InviteDownloadService $downloadService,
        protected InviteTrackingService $trackingService
    ) {}

    /**
     * POST /api/inv/batch
     * Create a new invitation batch
     */
    public function createBatch(Request $request): JsonResponse
    {
        try {
            $batch = $this->batchService->createBatch(
                $request->all(),
                $request->user()
            );

            return response()->json([
                'success' => true,
                'batch' => $batch,
                'stats' => $this->batchService->getBatchStats($batch),
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/inv/batch/import
     * Import recipients from CSV
     */
    public function importRecipients(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|uuid|exists:inv_batches,id',
            'csv_file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
            'mapping' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $batch = InviteBatch::findOrFail($request->batch_id);

            // Save uploaded CSV
            $csvPath = $request->file('csv_file')->store('temp');
            $fullPath = storage_path('app/' . $csvPath);

            $result = $this->batchService->importRecipients(
                $batch,
                $fullPath,
                $request->input('mapping', [])
            );

            // Clean up temp file
            unlink($fullPath);

            return response()->json([
                'success' => true,
                'imported' => $result['imported'],
                'errors' => $result['errors'],
                'batch' => $batch->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/inv/batch/render
     * Render PDFs for a batch
     */
    public function renderBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required|uuid|exists:inv_batches,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $batch = InviteBatch::findOrFail($request->batch_id);

            $rendered = $this->renderService->renderBatch($batch);

            return response()->json([
                'success' => true,
                'rendered' => $rendered,
                'batch' => $batch->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/inv/send
     * Send invitation emails
     */
    public function sendEmails(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required_without:invite_ids|uuid|exists:inv_batches,id',
            'invite_ids' => 'required_without:batch_id|array',
            'invite_ids.*' => 'uuid|exists:inv_invites,id',
            'mode' => 'nullable|in:email,link_only',
            'chunk_size' => 'nullable|integer|min:1|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            if ($request->has('batch_id')) {
                // Send entire batch
                $batch = InviteBatch::findOrFail($request->batch_id);
                $chunkSize = $request->input('chunk_size', 100);

                $result = $this->emailService->sendBatchEmails($batch, $chunkSize);

                return response()->json([
                    'success' => true,
                    'queued' => $result['queued'],
                    'failed' => $result['failed'],
                ]);
            } else {
                // Send specific invites
                $sent = 0;
                $failed = 0;

                foreach ($request->invite_ids as $inviteId) {
                    $invite = Invite::find($inviteId);
                    if ($invite && $this->emailService->sendInviteEmail($invite)) {
                        $sent++;
                    } else {
                        $failed++;
                    }
                }

                return response()->json([
                    'success' => true,
                    'sent' => $sent,
                    'failed' => $failed,
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/inv/:id
     * Get invitation details
     */
    public function getInvite(string $id): JsonResponse
    {
        $invite = Invite::with(['batch', 'tenant'])->find($id);

        if (!$invite) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'invite' => $invite,
            'tracking' => $this->trackingService->getTrackingSummary($invite),
        ]);
    }

    /**
     * GET /api/inv/batch/:id/export
     * Export batch as CSV
     */
    public function exportBatch(string $id): \Illuminate\Http\Response
    {
        $batch = InviteBatch::with('invites')->findOrFail($id);

        $csv = $this->generateCSVExport($batch);

        $filename = "invitations-{$batch->name}-" . now()->format('Y-m-d') . ".csv";

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * POST /api/inv/:id/void
     * Void an invitation
     */
    public function voidInvite(string $id, Request $request): JsonResponse
    {
        $invite = Invite::find($id);

        if (!$invite) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found',
            ], 404);
        }

        $reason = $request->input('reason');

        if ($this->trackingService->voidInvite($invite, $reason)) {
            return response()->json([
                'success' => true,
                'message' => 'Invitation voided successfully',
                'invite' => $invite->fresh(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Cannot void this invitation',
        ], 400);
    }

    /**
     * GET /api/inv/:id/download
     * Download invitation PDF (signed URL)
     */
    public function download(Request $request, string $id)
    {
        if (!$request->hasValidSignature()) {
            abort(403, 'Invalid or expired download link');
        }

        $code = $request->query('code');
        $invite = Invite::where('id', $id)
            ->where('invite_code', $code)
            ->first();

        if (!$invite) {
            abort(404, 'Invitation not found');
        }

        return $this->downloadService->downloadInvite(
            $invite,
            $request->ip(),
            $request->userAgent()
        );
    }

    /**
     * POST /api/inv/webhook/open
     * Track email open via pixel
     */
    public function trackOpen(Request $request): \Illuminate\Http\Response
    {
        $inviteCode = $request->query('code');

        if ($inviteCode) {
            $this->trackingService->trackOpen(
                $inviteCode,
                $request->ip(),
                $request->userAgent()
            );
        }

        // Return 1x1 transparent pixel
        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * GET /api/inv/batch/:id/download-zip
     * Download entire batch as ZIP
     */
    public function downloadBatchZip(string $id)
    {
        $batch = InviteBatch::findOrFail($id);

        return $this->downloadService->downloadBatchZip($batch);
    }

    /**
     * POST /api/inv/:id/resend
     * Resend invitation email
     */
    public function resend(string $id): JsonResponse
    {
        $invite = Invite::find($id);

        if (!$invite) {
            return response()->json([
                'success' => false,
                'message' => 'Invitation not found',
            ], 404);
        }

        if ($this->emailService->resendEmail($invite)) {
            return response()->json([
                'success' => true,
                'message' => 'Invitation resent successfully',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to resend invitation',
        ], 500);
    }

    /**
     * Generate CSV export for batch
     */
    protected function generateCSVExport(InviteBatch $batch): string
    {
        $output = fopen('php://temp', 'r+');

        // Header row
        fputcsv($output, [
            'Invite Code',
            'Recipient Name',
            'Email',
            'Phone',
            'Company',
            'Seat',
            'Status',
            'Email Status',
            'Download Status',
            'Check-in Status',
            'Gate',
            'Rendered At',
            'Emailed At',
            'Downloaded At',
            'Opened At',
            'Checked In At',
        ]);

        // Data rows
        foreach ($batch->invites as $invite) {
            fputcsv($output, [
                $invite->invite_code,
                $invite->getRecipientName(),
                $invite->getRecipientEmail(),
                $invite->getRecipientPhone(),
                $invite->getRecipientCompany(),
                $invite->seat_ref,
                $invite->status,
                $invite->delivery_status,
                $invite->downloaded_at ? 'Downloaded' : 'Not Downloaded',
                $invite->checked_in_at ? 'Checked In' : 'Not Checked In',
                $invite->gate_ref,
                $invite->rendered_at?->toDateTimeString(),
                $invite->emailed_at?->toDateTimeString(),
                $invite->downloaded_at?->toDateTimeString(),
                $invite->opened_at?->toDateTimeString(),
                $invite->checked_in_at?->toDateTimeString(),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
