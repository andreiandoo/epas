<?php

namespace App\Http\Controllers\Api\Festival;

use App\Http\Controllers\Controller;
use App\Models\Vendor;
use App\Models\VendorEdition;
use App\Models\VendorProduct;
use App\Models\VendorSaleItem;
use App\Models\Wristband;
use App\Models\WristbandTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WristbandController extends Controller
{
    /**
     * Import a batch of wristband UIDs (from manufacturer CSV).
     */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tenant_id'           => 'required|integer|exists:tenants,id',
            'festival_edition_id' => 'required|integer|exists:festival_editions,id',
            'wristband_type'      => 'required|in:nfc,qr,rfid,hybrid',
            'currency'            => 'string|size:3',
            'uids'                => 'required|array|min:1',
            'uids.*'              => 'required|string|max:100',
        ]);

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

            Wristband::create([
                'tenant_id'           => $data['tenant_id'],
                'festival_edition_id' => $data['festival_edition_id'],
                'uid'                 => $uid,
                'wristband_type'      => $data['wristband_type'],
                'status'              => 'unassigned',
                'balance_cents'       => 0,
                'currency'            => $data['currency'] ?? 'RON',
            ]);
            $created++;
        }

        return response()->json([
            'created' => $created,
            'skipped' => $skipped,
            'errors'  => $errors,
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

        $purchase = \App\Models\FestivalPassPurchase::findOrFail($data['festival_pass_purchase_id']);
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

        return response()->json([
            'uid'           => $wristband->uid,
            'status'        => $wristband->status,
            'balance_cents' => $wristband->balance_cents,
            'balance'       => $wristband->balance,
            'currency'      => $wristband->currency,
            'access_zones'  => $wristband->access_zones,
            'activated_at'  => $wristband->activated_at,
        ]);
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
     */
    public function charge(Request $request, string $uid): JsonResponse
    {
        $wristband = Wristband::where('uid', $uid)->firstOrFail();

        if (!$wristband->isActive()) {
            return response()->json(['message' => 'Wristband is not active.'], 422);
        }

        $data = $request->validate([
            'vendor_id'         => 'required|integer|exists:vendors,id',
            'pos_device_id'     => 'nullable|integer|exists:vendor_pos_devices,id',
            'operator'          => 'nullable|string|max:100',
            'items'             => 'required|array|min:1',
            'items.*.product_id' => 'nullable|integer|exists:vendor_products,id',
            'items.*.name'      => 'required|string|max:200',
            'items.*.category'  => 'nullable|string|max:100',
            'items.*.variant'   => 'nullable|string|max:100',
            'items.*.quantity'  => 'required|integer|min:1',
            'items.*.unit_price_cents' => 'required|integer|min:0',
        ]);

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

        return DB::transaction(function () use ($wristband, $vendor, $vendorEdition, $data, $totalCents) {
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

            return response()->json([
                'transaction'   => $transaction,
                'total_charged' => $totalCents,
                'new_balance'   => $wristband->balance_cents,
            ]);
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

        $transaction = $wristband->refund(
            $data['amount_cents'],
            $data['description'] ?? null,
            $data['operator'] ?? null,
        );

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

        $transaction = $source->transferTo($target, $data['operator'] ?? null);

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
}
