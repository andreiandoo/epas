<?php

namespace App\Http\Controllers\Api\Cashless;

use App\Enums\CashoutChannel;
use App\Enums\CashoutMethod;
use App\Enums\TopUpChannel;
use App\Enums\TopUpMethod;
use App\Http\Controllers\Controller;
use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSale;
use App\Services\Cashless\CashlessAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(
        private CashlessAccountService $accountService,
    ) {}

    /**
     * Get the authenticated customer's cashless account for an edition.
     */
    public function account(Request $request): JsonResponse
    {
        $request->validate([
            'edition_id' => 'required|integer',
        ]);

        $customer = $request->user();
        $account = CashlessAccount::where('customer_id', $customer->id)
            ->where('festival_edition_id', $request->edition_id)
            ->with('wristband:id,uid,status')
            ->first();

        if (! $account) {
            return response()->json(['message' => 'No cashless account for this edition.'], 404);
        }

        return response()->json([
            'account' => [
                'id'                     => $account->id,
                'account_number'         => $account->account_number,
                'balance_cents'          => $account->balance_cents,
                'total_topped_up_cents'  => $account->total_topped_up_cents,
                'total_spent_cents'      => $account->total_spent_cents,
                'total_cashed_out_cents' => $account->total_cashed_out_cents,
                'currency'               => $account->currency,
                'status'                 => $account->status->value,
                'wristband'              => $account->wristband?->only('id', 'uid', 'status'),
            ],
        ]);
    }

    /**
     * Transaction history for the authenticated customer.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'edition_id' => 'required|integer',
            'type'       => 'nullable|string',
        ]);

        $customer = $request->user();
        $account = CashlessAccount::where('customer_id', $customer->id)
            ->where('festival_edition_id', $request->edition_id)
            ->firstOrFail();

        $query = $account->transactions()->orderByDesc('created_at');

        if ($request->has('type')) {
            $types = match ($request->type) {
                'topup'    => ['topup', 'voucher_credit', 'promotional_credit'],
                'purchase' => ['payment'],
                'cashout'  => ['cashout'],
                'transfer' => ['transfer_in', 'transfer_out'],
                default    => [$request->type],
            };
            $query->whereIn('transaction_type', $types);
        }

        $transactions = $query->paginate($request->input('per_page', 30));

        return response()->json($transactions);
    }

    /**
     * Initiate an online top-up (returns intent for payment processing).
     */
    public function initiateTopUp(Request $request): JsonResponse
    {
        $request->validate([
            'edition_id'   => 'required|integer',
            'amount_cents' => 'required|integer|min:1',
        ]);

        $customer = $request->user();
        $account = CashlessAccount::where('customer_id', $customer->id)
            ->where('festival_edition_id', $request->edition_id)
            ->firstOrFail();

        // For now, process directly. In production, this would create a payment intent
        // and the actual top-up happens in confirmTopUp() after payment confirmation.
        try {
            $transaction = $this->accountService->topUp(
                $account,
                $request->amount_cents,
                TopUpChannel::Online,
                TopUpMethod::Card,
            );

            return response()->json([
                'transaction'   => [
                    'id'           => $transaction->id,
                    'amount_cents' => $transaction->amount_cents,
                    'created_at'   => $transaction->created_at->toIso8601String(),
                ],
                'balance_cents' => $account->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Request a cashout (online).
     */
    public function requestCashout(Request $request): JsonResponse
    {
        $request->validate([
            'edition_id'   => 'required|integer',
            'amount_cents' => 'nullable|integer|min:1',
            'method'       => 'required|in:bank_transfer,card_refund',
            'iban'         => 'required_if:method,bank_transfer|nullable|string',
        ]);

        $customer = $request->user();
        $account = CashlessAccount::where('customer_id', $customer->id)
            ->where('festival_edition_id', $request->edition_id)
            ->firstOrFail();

        try {
            $transaction = $this->accountService->cashout(
                $account,
                $request->amount_cents,
                CashoutChannel::Online,
                CashoutMethod::from($request->method),
                reference: $request->iban,
            );

            return response()->json([
                'transaction' => [
                    'id'              => $transaction->id,
                    'amount_cents'    => $transaction->amount_cents,
                    'cashout_status'  => $transaction->cashout_status,
                    'cashout_method'  => $transaction->cashout_method,
                    'created_at'      => $transaction->created_at->toIso8601String(),
                ],
                'balance_cents' => $account->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Transfer to another account.
     */
    public function transfer(Request $request): JsonResponse
    {
        $request->validate([
            'edition_id'        => 'required|integer',
            'to_account_number' => 'required|string',
            'amount_cents'      => 'required|integer|min:1',
        ]);

        $customer = $request->user();
        $source = CashlessAccount::where('customer_id', $customer->id)
            ->where('festival_edition_id', $request->edition_id)
            ->firstOrFail();

        $target = CashlessAccount::where('account_number', $request->to_account_number)
            ->where('festival_edition_id', $request->edition_id)
            ->first();

        if (! $target) {
            return response()->json(['message' => 'Target account not found.'], 404);
        }

        if ($source->id === $target->id) {
            return response()->json(['message' => 'Cannot transfer to yourself.'], 422);
        }

        try {
            $transaction = $this->accountService->transfer(
                $source,
                $target,
                $request->amount_cents,
            );

            return response()->json([
                'transaction'   => [
                    'id'           => $transaction->id,
                    'amount_cents' => $transaction->amount_cents,
                    'created_at'   => $transaction->created_at->toIso8601String(),
                ],
                'balance_cents' => $source->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * List purchases (CashlessSales) for the authenticated customer.
     */
    public function purchases(Request $request): JsonResponse
    {
        $request->validate([
            'edition_id' => 'required|integer',
        ]);

        $customer = $request->user();
        $account = CashlessAccount::where('customer_id', $customer->id)
            ->where('festival_edition_id', $request->edition_id)
            ->firstOrFail();

        $sales = CashlessSale::where('cashless_account_id', $account->id)
            ->with(['vendor:id,name', 'items'])
            ->orderByDesc('sold_at')
            ->paginate($request->input('per_page', 20));

        return response()->json($sales);
    }

    /**
     * Purchase detail with items (receipt).
     */
    public function purchaseDetail(Request $request, int $saleId): JsonResponse
    {
        $customer = $request->user();

        $sale = CashlessSale::where('customer_id', $customer->id)
            ->with(['vendor:id,name,company_name', 'items', 'employee:id,name,full_name'])
            ->findOrFail($saleId);

        return response()->json(['sale' => $sale]);
    }

    /**
     * Spending breakdown by category.
     */
    public function spendingBreakdown(Request $request): JsonResponse
    {
        $request->validate([
            'edition_id' => 'required|integer',
        ]);

        $customer = $request->user();
        $account = CashlessAccount::where('customer_id', $customer->id)
            ->where('festival_edition_id', $request->edition_id)
            ->firstOrFail();

        $breakdown = \App\Models\VendorSaleItem::whereHas('cashlessSale', fn ($q) => $q->where('cashless_account_id', $account->id)->where('status', 'completed'))
            ->selectRaw('COALESCE(product_category_name, category_name, \'Other\') as category, SUM(total_cents) as total_cents, SUM(quantity) as quantity')
            ->groupBy(\Illuminate\Support\Facades\DB::raw('COALESCE(product_category_name, category_name, \'Other\')'))
            ->orderByDesc('total_cents')
            ->get();

        return response()->json([
            'total_spent_cents' => $account->total_spent_cents,
            'breakdown'         => $breakdown,
        ]);
    }

    /**
     * Redeem a voucher code.
     */
    public function redeemVoucher(Request $request): JsonResponse
    {
        $request->validate([
            'edition_id'         => 'required|integer',
            'code'               => 'required|string',
            'topup_amount_cents' => 'nullable|integer|min:0',
        ]);

        $customer = $request->user();
        $account = CashlessAccount::where('customer_id', $customer->id)
            ->where('festival_edition_id', $request->edition_id)
            ->firstOrFail();

        try {
            $transaction = $this->accountService->redeemVoucher(
                $account,
                $request->code,
                $request->input('topup_amount_cents', 0),
            );

            return response()->json([
                'message'       => 'Voucher redeemed successfully.',
                'amount_cents'  => $transaction->amount_cents,
                'balance_cents' => $account->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
