<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\WalletPass;
use App\Services\Wallet\WalletService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

/**
 * Wallet Controller
 *
 * Handles mobile wallet pass generation and Apple Wallet web service endpoints
 */
class WalletController extends Controller
{
    protected WalletService $walletService;

    public function __construct(WalletService $walletService)
    {
        $this->walletService = $walletService;
    }

    /**
     * Generate wallet pass for a ticket
     * POST /api/wallet/generate
     */
    public function generate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'ticket_id' => 'required|exists:tickets,id',
            'platform' => 'required|in:apple,google,both',
            'options' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $ticket = Ticket::findOrFail($request->ticket_id);
        $platform = $request->platform;
        $options = $request->options ?? [];

        if ($platform === 'both') {
            $result = $this->walletService->generateAllPasses($ticket, $options);

            return response()->json([
                'success' => true,
                'passes' => $result,
            ]);
        }

        $result = $this->walletService->generatePass($ticket, $platform, $options);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Failed to generate pass',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'pass_id' => $result['pass']->id,
            'pass_url' => $result['pass_url'],
            'already_exists' => $result['already_exists'] ?? false,
        ], $result['already_exists'] ? 200 : 201);
    }

    /**
     * Get Apple Wallet pass download
     * GET /api/wallet/download/{passId}/apple
     */
    public function downloadApple(int $passId): Response
    {
        $pass = WalletPass::where('id', $passId)
            ->where('platform', 'apple')
            ->firstOrFail();

        $filename = "wallet/apple/{$pass->tenant_id}/{$pass->serial_number}.pkpass";

        if (!Storage::disk('public')->exists($filename)) {
            abort(404, 'Pass file not found');
        }

        $content = Storage::disk('public')->get($filename);

        return response($content, 200)
            ->header('Content-Type', 'application/vnd.apple.pkpass')
            ->header('Content-Disposition', 'attachment; filename="ticket.pkpass"');
    }

    /**
     * Get Google Wallet pass URL
     * GET /api/wallet/download/{passId}/google
     */
    public function downloadGoogle(int $passId): JsonResponse
    {
        $pass = WalletPass::where('id', $passId)
            ->where('platform', 'google')
            ->firstOrFail();

        // Regenerate the JWT URL
        $ticket = $pass->ticket;
        $result = $this->walletService->generatePass($ticket, 'google');

        return response()->json([
            'success' => true,
            'save_url' => $result['pass_url'],
        ]);
    }

    /**
     * Get pass status
     * GET /api/wallet/passes/{passId}
     */
    public function show(int $passId): JsonResponse
    {
        $pass = WalletPass::with(['ticket', 'order'])->findOrFail($passId);

        return response()->json([
            'success' => true,
            'pass' => $pass,
        ]);
    }

    /**
     * Void a pass
     * DELETE /api/wallet/passes/{passId}
     */
    public function void(int $passId): JsonResponse
    {
        $pass = WalletPass::findOrFail($passId);

        $result = $this->walletService->voidPass($pass);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'error' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pass voided successfully',
        ]);
    }

    /**
     * Get wallet statistics for tenant
     * GET /api/wallet/stats
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant_id');

        $stats = $this->walletService->getStatistics($tenantId);

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    // ===========================================
    // Apple Wallet Web Service Endpoints
    // ===========================================

    /**
     * Register device for push notifications
     * POST /api/wallet/apple/devices/{deviceLibraryId}/registrations/{passTypeId}/{serialNumber}
     */
    public function registerDevice(
        Request $request,
        string $deviceLibraryId,
        string $passTypeId,
        string $serialNumber
    ): Response {
        $pushToken = $request->input('pushToken');

        if (!$pushToken) {
            return response('', 400);
        }

        $result = $this->walletService->registerDevice(
            $deviceLibraryId,
            $passTypeId,
            $serialNumber,
            $pushToken
        );

        if ($result['success']) {
            return response('', 201);
        }

        return response('', 401);
    }

    /**
     * Unregister device
     * DELETE /api/wallet/apple/devices/{deviceLibraryId}/registrations/{passTypeId}/{serialNumber}
     */
    public function unregisterDevice(
        string $deviceLibraryId,
        string $passTypeId,
        string $serialNumber
    ): Response {
        $result = $this->walletService->unregisterDevice($deviceLibraryId, $serialNumber);

        if ($result['success']) {
            return response('', 200);
        }

        return response('', 401);
    }

    /**
     * Get serial numbers for device
     * GET /api/wallet/apple/devices/{deviceLibraryId}/registrations/{passTypeId}
     */
    public function getSerialNumbers(
        Request $request,
        string $deviceLibraryId,
        string $passTypeId
    ): JsonResponse {
        $passesUpdatedSince = $request->query('passesUpdatedSince');

        $serialNumbers = $this->walletService->getPassesForDevice($deviceLibraryId, $passTypeId);

        if (empty($serialNumbers)) {
            return response()->json([], 204);
        }

        return response()->json([
            'serialNumbers' => $serialNumbers,
            'lastUpdated' => now()->timestamp,
        ]);
    }

    /**
     * Get latest version of pass
     * GET /api/wallet/apple/passes/{passTypeId}/{serialNumber}
     */
    public function getLatestPass(
        Request $request,
        string $passTypeId,
        string $serialNumber
    ): Response {
        $pass = WalletPass::where('serial_number', $serialNumber)->first();

        if (!$pass) {
            return response('', 404);
        }

        // Check authorization
        $authToken = str_replace('ApplePass ', '', $request->header('Authorization', ''));

        if ($authToken !== $pass->auth_token) {
            return response('', 401);
        }

        $filename = "wallet/apple/{$pass->tenant_id}/{$pass->serial_number}.pkpass";

        if (!Storage::disk('public')->exists($filename)) {
            return response('', 404);
        }

        $content = Storage::disk('public')->get($filename);
        $lastModified = $pass->last_updated_at ?? $pass->created_at;

        return response($content, 200)
            ->header('Content-Type', 'application/vnd.apple.pkpass')
            ->header('Last-Modified', $lastModified->toRfc7231String());
    }

    /**
     * Log messages from Apple Wallet
     * POST /api/wallet/apple/log
     */
    public function log(Request $request): Response
    {
        $logs = $request->input('logs', []);

        foreach ($logs as $log) {
            \Log::info('Apple Wallet Log', ['message' => $log]);
        }

        return response('', 200);
    }
}
