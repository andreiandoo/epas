<?php

namespace App\Http\Controllers\Api\Festival;

use App\Http\Controllers\Controller;
use App\Models\FestivalEdition;
use App\Models\FestivalPassPurchase;
use App\Models\Vendor;
use App\Models\VendorEdition;
use App\Models\VendorProduct;
use App\Models\VendorSaleItem;
use App\Models\Wristband;
use App\Models\WristbandTransaction;
use App\Services\NfcSyncService;
use App\Services\WristbandSecurityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WristbandController extends Controller
{
    public function __construct(
        private WristbandSecurityService $security,
        private NfcSyncService $nfcSync,
    ) {}

    /**
     * Import a batch of wristband UIDs (from manufacturer CSV).
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id'           => 'required|integer|exists:tenants,id',
            'festival_edition_id' => 'required|integer|exists:festival_editions,id',
            'wristband_type'      => 'nullable|in:nfc,qr,rfid,hybrid',
            'currency'            => 'string|size:3',
            'uids'                => 'required|array|min:1',
            'uids.*'              => 'required|string|max:100',
        ]);

        $edition = FestivalEdition::findOrFail($data['festival_edition_id']);

        // Determine wristband type based on edition's cashless mode
        if ($edition->isNfcMode()) {
            $wristbandType = 'nfc';
        } elseif ($edition->isQrMode()) {
            $wristbandType = 'qr';
        } else {
            // Hybrid — accept from request, default to nfc
            $wristbandType = $data['wristband_type'] ?? 'nfc';
        }

        $created = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($data['uids'] as $uid) {
            $exists = Wristband::where('uid', $uid)->exists();
            if ($exists) {
                $skipped++;
                $errors[] = "UID {$uid} already exists";
                continue;
            }

            $wristband = Wristband::create([
                'tenant_id'           => $data['tenant_id'],
                'festival_edition_id' => $data['festival_edition_id'],
                'uid'                 => $uid,
                'wristband_type'      => $wristbandType,
                'status'              => 'unassigned',
                'currency'            => $data['currency'] ?? 'RON',
            ]);

            // Auto-generate QR payload for QR wristbands
            if ($wristbandType === 'qr') {
                $payload = $this->security->generateQrPayload($wristband);
                $wristband->update(['qr_payload' => $payload]);
            }

            $created++;
        }

        return response()->json([
            'created'        => $created,
            'skipped'        => $skipped,
            'errors'         => $errors,
            'wristband_type' => $wristbandType,
            'cashless_mode'  => $edition->cashless_mode->value,
        ], 201);
    }

    /**
     * Assign wristband to a pass purchase at check-in.
     */
    public function assign(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        if ($wristband->status !== 'unassigned') {
            return response()->json(['message' => 'Wristband is already assigned.'], 422);
        }

        $data = $request->validate([
            'festival_pass_purchase_id' => 'required|integer|exists:festival_pass_purchases,id',
        ]);

        $purchase = FestivalPassPurchase::findOrFail($data['festival_pass_purchase_id']);
        $wristband->assignTo($purchase);
        $wristband->activate();

        return response()->json([
            'wristband' => $wristband->fresh(),
        ]);
    }

    /**
     * Get wristband balance and info.
     */
    public function show(string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        $response = [
            'uid'            => $wristband->uid,
            'status'         => $wristband->status,
            'wristband_type' => $wristband->wristband_type,
            'balance_cents'  => $wristband->balance_cents,
            'balance'        => $wristband->balance,
            'currency'       => $wristband->currency,
            'access_zones'   => $wristband->access_zones,
            'activated_at'   => $wristband->activated_at,
        ];

        if ($wristband->festival_edition_id) {
            $response['cashless_mode'] = $wristband->edition?->cashless_mode?->value;
        }

        return response()->json($response);
    }

    /**
     * Top-up wristband balance.
     */
    public function topUp(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        if (!$wristband->isActive()) {
            return response()->json(['message' => 'Wristband is not active.'], 422);
        }

        $data = $request->validate([
            'amount_cents'   => 'required|integer|min:100',
            'payment_method' => 'nullable|in:cash,card,online',
            'operator'       => 'nullable|string|max:100',
        ]);

        $transaction = $wristband->topUp(
            $data['amount_cents'],
            $data['payment_method'] ?? null,
            $data['operator'] ?? null,
        );

        return response()->json([
            'transaction'   => $transaction,
            'new_balance'   => $wristband->balance_cents,
        ]);
    }

    /**
     * Charge wristband (vendor sale with line items).
     * Includes rate limiting, optional PIN validation, and fraud detection.
     */
    public function charge(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        if (!$wristband->isActive()) {
            return response()->json(['message' => 'Wristband is not active.'], 422);
        }

        // Rate limiting — max 1 charge per 10 seconds per wristband
        if ($this->security->isRateLimited($uid, 'charge', 10)) {
            return response()->json(['message' => 'Too many transactions. Please wait.'], 429);
        }

        $data = $request->validate([
            'vendor_id'         => 'required|integer|exists:vendors,id',
            'pos_device_id'     => 'nullable|integer|exists:vendor_pos_devices,id',
            'pos_device_uid'    => 'nullable|string|max:100',
            'employee_id'       => 'nullable|integer|exists:vendor_employees,id',
            'shift_id'          => 'nullable|integer|exists:vendor_shifts,id',
            'pin'               => 'nullable|string|max:6',
            'operator'          => 'nullable|string|max:100',
            'items'             => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:vendor_products,id',
            'items.*.name'      => 'required|string|max:200',
            'items.*.category'  => 'nullable|string|max:100',
            'items.*.variant'   => 'nullable|string|max:100',
            'items.*.quantity'  => 'required|integer|min:1',
            'items.*.unit_price_cents' => 'required|integer|min:0',
        ]);

        // PIN validation (if wristband has PIN set)
        if (! $this->security->validatePin($wristband, $data['pin'] ?? null)) {
            return response()->json(['message' => 'Invalid PIN.', 'pin_required' => true], 403);
        }

        // Fraud detection — concurrent usage from different POS devices
        $conflictingDevice = $this->security->detectConcurrentUsage(
            $uid,
            $data['pos_device_uid'] ?? null
        );
        if ($conflictingDevice) {
            Log::warning('Wristband concurrent usage detected', [
                'uid'              => $uid,
                'current_device'   => $data['pos_device_uid'] ?? null,
                'previous_device'  => $conflictingDevice,
            ]);
            // Log but don't block — flag in response for POS app to show warning
        }

        $totalCents = 0;
        foreach ($data['items'] as $item) {
            $totalCents += $item['quantity'] * $item['unit_price_cents'];
        }

        if ($wristband->balance_cents < $totalCents) {
            return response()->json([
                'message'       => 'Insufficient balance.',
                'required'      => $totalCents,
                'available'     => $wristband->balance_cents,
            ], 422);
        }

        $vendor      = Vendor::findOrFail($data['vendor_id']);
        $vendorEdition = VendorEdition::where('vendor_id', $vendor->id)
            ->where('festival_edition_id', $wristband->festival_edition_id)
            ->first();

        return DB::transaction(function () use ($wristband, $vendor, $vendorEdition, $data, $totalCents, $conflictingDevice) {
            $transaction = $wristband->charge(
                $totalCents,
                $vendor->name,
                $vendorEdition?->location,
                'POS sale',
                $vendor->id,
                $data['pos_device_id'] ?? null,
            );

            if ($transaction === false) {
                return response()->json(['message' => 'Charge failed — race condition.'], 409);
            }

            // Create sale line items
            foreach ($data['items'] as $item) {
                $lineTotal      = $item['quantity'] * $item['unit_price_cents'];
                $commissionRate = $vendorEdition?->commission_rate ?? 0;
                $commissionCents = $vendorEdition ? $vendorEdition->calculateCommission($lineTotal) : 0;

                VendorSaleItem::create([
                    'vendor_id'                => $vendor->id,
                    'festival_edition_id'      => $wristband->festival_edition_id,
                    'vendor_product_id'        => $item['product_id'] ?? null,
                    'wristband_transaction_id' => $transaction->id,
                    'vendor_pos_device_id'     => $data['pos_device_id'] ?? null,
                    'vendor_employee_id'       => $data['employee_id'] ?? null,
                    'vendor_shift_id'          => $data['shift_id'] ?? null,
                    'product_name'             => $item['name'],
                    'category_name'            => $item['category'] ?? null,
                    'variant_name'             => $item['variant'] ?? null,
                    'quantity'                 => $item['quantity'],
                    'unit_price_cents'         => $item['unit_price_cents'],
                    'total_cents'              => $lineTotal,
                    'currency'                 => $wristband->currency,
                    'commission_cents'         => $commissionCents,
                    'commission_rate'          => $commissionRate,
                    'operator'                 => $data['operator'] ?? null,
                ]);
            }

            // Update POS heartbeat
            if (isset($data['pos_device_id'])) {
                \App\Models\VendorPosDevice::find($data['pos_device_id'])?->heartbeat();
            }

            // Track last transaction for fraud detection
            $wristband->update([
                'last_transaction_at'  => now(),
                'last_pos_device_uid'  => $data['pos_device_uid'] ?? null,
            ]);

            // Update shift sales counters
            if (! empty($data['shift_id'])) {
                \App\Models\VendorShift::find($data['shift_id'])?->recordSale($totalCents);
            }

            $response = [
                'transaction'   => $transaction,
                'total_charged' => $totalCents,
                'new_balance'   => $wristband->balance_cents,
            ];

            if ($conflictingDevice) {
                $response['warning'] = 'concurrent_usage_detected';
                $response['conflicting_device'] = $conflictingDevice;
            }

            return response()->json($response);
        });
    }

    /**
     * Refund a charge.
     */
    public function refund(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        $data = $request->validate([
            'amount_cents' => 'required|integer|min:1',
            'description'  => 'nullable|string|max:500',
            'operator'     => 'nullable|string|max:100',
        ]);

        try {
            $transaction = $wristband->refund(
                $data['amount_cents'],
                $data['description'] ?? null,
                $data['operator'] ?? null,
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'transaction' => $transaction,
            'new_balance' => $wristband->balance_cents,
        ]);
    }

    /**
     * Transfer balance from one wristband to another.
     */
    public function transfer(Request $request, string $uid): JsonResponse
    {
        $source = Wristband::where('uid', $uid)->firstOrFail();

        $data = $request->validate([
            'target_uid' => 'required|string|exists:wristbands,uid',
            'operator'   => 'nullable|string|max:100',
        ]);

        $target = Wristband::where('uid', $data['target_uid'])->firstOrFail();

        if ($source->balance_cents <= 0) {
            return response()->json(['message' => 'Source wristband has no balance.'], 422);
        }

        if (!$target->isActive()) {
            return response()->json(['message' => 'Target wristband is not active.'], 422);
        }

        try {
            $transaction = $source->transferTo($target, $data['operator'] ?? null);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'transaction'          => $transaction,
            'source_new_balance'   => $source->balance_cents,
            'target_new_balance'   => $target->balance_cents,
        ]);
    }

    /**
     * Cashout remaining balance (end of festival).
     */
    public function cashout(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        if ($wristband->balance_cents <= 0) {
            return response()->json(['message' => 'No balance to cash out.'], 422);
        }

        $data = $request->validate([
            'operator' => 'nullable|string|max:100',
        ]);

        $transaction = $wristband->cashout($data['operator'] ?? null);

        return response()->json([
            'transaction' => $transaction,
            'refunded'    => $transaction->amount_cents,
        ]);
    }

    /**
     * Transaction history for a wristband.
     */
    public function transactions(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        $transactions = $wristband->transactions()
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 50));

        return response()->json($transactions);
    }

    /**
     * Disable a wristband.
     */
    public function disable(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        $data = $request->validate([
            'reason' => 'nullable|string|max:200',
        ]);

        $wristband->disable($data['reason'] ?? 'manual');

        return response()->json(['wristband' => $wristband->fresh()]);
    }

    /**
     * Generate signed QR payload for a wristband.
     */
    public function generateQr(string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        $payload = $this->security->generateQrPayload($wristband);

        $wristband->update(['qr_payload' => $payload]);

        return response()->json([
            'uid'        => $wristband->uid,
            'qr_payload' => $payload,
        ]);
    }

    /**
     * Resolve a wristband from a signed QR payload (used by POS app).
     */
    public function resolveQr(Request $request): JsonResponse
    {
        $data = $request->validate([
            'qr_payload' => 'required|string',
            'tenant_id'  => 'required|integer',
        ]);

        $parsed = $this->security->validateQrPayload($data['qr_payload'], $data['tenant_id']);

        if (! $parsed) {
            return response()->json(['message' => 'Invalid or tampered QR code.'], 403);
        }

        $wristband = Wristband::where('uid', $parsed['uid'])->first();

        if (! $wristband) {
            return response()->json(['message' => 'Wristband not found.'], 404);
        }

        return response()->json([
            'uid'            => $wristband->uid,
            'status'         => $wristband->status,
            'wristband_type' => $wristband->wristband_type,
            'balance_cents'  => $wristband->balance_cents,
            'currency'       => $wristband->currency,
            'has_pin'        => (bool) $wristband->pin_hash,
            'cashless_mode'  => $wristband->edition?->cashless_mode?->value,
        ]);
    }

    /**
     * Set or change wristband PIN.
     */
    public function setPin(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        $data = $request->validate([
            'pin'         => 'required|string|min:4|max:6',
            'current_pin' => 'nullable|string',
        ]);

        // If PIN already set, require current PIN
        if ($wristband->pin_hash && ! $this->security->validatePin($wristband, $data['current_pin'] ?? null)) {
            return response()->json(['message' => 'Current PIN is incorrect.'], 403);
        }

        $this->security->setPin($wristband, $data['pin']);

        return response()->json(['message' => 'PIN set successfully.']);
    }

    /**
     * Sync offline NFC transactions from a terminal.
     * Used when cashless_mode is 'nfc' or 'hybrid'.
     */
    public function syncOfflineTransactions(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id'              => 'required|integer|exists:tenants,id',
            'festival_edition_id'    => 'required|integer|exists:festival_editions,id',
            'transactions'           => 'required|array|min:1|max:500',
            'transactions.*.offline_ref'       => 'required|string|max:100',
            'transactions.*.uid'               => 'required|string|max:100',
            'transactions.*.transaction_type'  => 'required|in:payment,topup,refund',
            'transactions.*.amount_cents'      => 'required|integer|min:1',
            'transactions.*.balance_after_cents' => 'required|integer|min:0',
            'transactions.*.transacted_at'     => 'required|date',
            'transactions.*.vendor_id'         => 'nullable|integer',
            'transactions.*.vendor_name'       => 'nullable|string|max:200',
            'transactions.*.vendor_location'   => 'nullable|string|max:200',
            'transactions.*.pos_device_id'     => 'nullable|integer',
            'transactions.*.pos_device_uid'    => 'nullable|string|max:100',
            'transactions.*.employee_id'       => 'nullable|integer',
            'transactions.*.shift_id'          => 'nullable|integer',
            'transactions.*.operator'          => 'nullable|string|max:100',
            'transactions.*.payment_method'    => 'nullable|in:cash,card,online',
            'transactions.*.description'       => 'nullable|string|max:500',
            'transactions.*.items'             => 'nullable|array',
            'transactions.*.items.*.product_id'      => 'nullable|integer',
            'transactions.*.items.*.name'            => 'required|string|max:200',
            'transactions.*.items.*.category'        => 'nullable|string|max:100',
            'transactions.*.items.*.variant'         => 'nullable|string|max:100',
            'transactions.*.items.*.quantity'        => 'required|integer|min:1',
            'transactions.*.items.*.unit_price_cents' => 'required|integer|min:0',
        ]);

        $edition = FestivalEdition::findOrFail($data['festival_edition_id']);

        if (! $edition->supportsNfc()) {
            return response()->json([
                'message' => 'This edition does not support NFC mode. Cashless mode is: ' . $edition->cashless_mode->value,
            ], 422);
        }

        $results = $this->nfcSync->syncBatch(
            $data['transactions'],
            $data['festival_edition_id'],
            $data['tenant_id'],
        );

        $synced = collect($results)->where('status', 'synced')->count();
        $duplicates = collect($results)->where('status', 'duplicate')->count();
        $errors = collect($results)->whereNotIn('status', ['synced', 'duplicate'])->count();

        return response()->json([
            'summary' => [
                'total'      => count($results),
                'synced'     => $synced,
                'duplicates' => $duplicates,
                'errors'     => $errors,
            ],
            'results' => $results,
        ]);
    }

    /**
     * Remove wristband PIN (requires current PIN or operator override).
     */
    public function removePin(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        $data = $request->validate([
            'current_pin' => 'nullable|string',
            'operator'    => 'nullable|string|max:100',
        ]);

        // Either provide correct PIN or operator override
        $pinValid = $this->security->validatePin($wristband, $data['current_pin'] ?? null);
        $hasOperator = ! empty($data['operator']);

        if (! $pinValid && ! $hasOperator) {
            return response()->json(['message' => 'Current PIN or operator override required.'], 403);
        }

        $wristband->update(['pin_hash' => null]);

        return response()->json(['message' => 'PIN removed.']);
    }
}
