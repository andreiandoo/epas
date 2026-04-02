<?php

namespace App\Http\Controllers\Api\Cashless;

use App\Enums\TopUpChannel;
use App\Enums\TopUpMethod;
use App\Enums\CashoutChannel;
use App\Enums\CashoutMethod;
use App\Http\Controllers\Controller;
use App\Models\Cashless\CashlessAccount;
use App\Services\Cashless\CashlessAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(
        private CashlessAccountService $accountService,
    ) {}

    /**
     * Look up account by wristband UID or account number.
     */
    public function lookup(Request $request): JsonResponse
    {
        $request->validate([
            'uid'            => 'required_without:account_number|string',
            'account_number' => 'required_without:uid|string',
            'edition_id'     => 'required|integer',
        ]);

        if ($request->uid) {
            $account = CashlessAccount::whereHas('wristband', fn ($q) => $q->where('uid', $request->uid))
                ->where('festival_edition_id', $request->edition_id)
                ->with('customer:id,first_name,last_name,email,date_of_birth')
                ->first();
        } else {
            $account = CashlessAccount::where('account_number', $request->account_number)
                ->where('festival_edition_id', $request->edition_id)
                ->with('customer:id,first_name,last_name,email,date_of_birth')
                ->first();
        }

        if (! $account) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        return response()->json([
            'account' => [
                'id'                    => $account->id,
                'account_number'        => $account->account_number,
                'balance_cents'         => $account->balance_cents,
                'total_topped_up_cents' => $account->total_topped_up_cents,
                'total_spent_cents'     => $account->total_spent_cents,
                'total_cashed_out_cents' => $account->total_cashed_out_cents,
                'currency'              => $account->currency,
                'status'                => $account->status->value,
                'customer'              => $account->customer,
                'wristband_id'          => $account->wristband_id,
            ],
        ]);
    }

    /**
     * Top up an account (physical stand operation).
     */
    public function topUp(Request $request, int $accountId): JsonResponse
    {
        $request->validate([
            'amount_cents'      => 'required|integer|min:1',
            'method'            => 'required|in:card,cash,bank_transfer,voucher',
            'topup_location_id' => 'nullable|integer|exists:topup_locations,id',
            'operator'          => 'nullable|string',
        ]);

        $account = CashlessAccount::findOrFail($accountId);

        try {
            $transaction = $this->accountService->topUp(
                $account,
                $request->amount_cents,
                TopUpChannel::Physical,
                TopUpMethod::from($request->method),
                $request->topup_location_id,
                operator: $request->operator,
            );

            return response()->json([
                'transaction'   => $this->formatTransaction($transaction),
                'balance_cents' => $account->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Cashout from an account.
     */
    public function cashout(Request $request, int $accountId): JsonResponse
    {
        $request->validate([
            'amount_cents' => 'nullable|integer|min:1',
            'channel'      => 'required|in:online,physical',
            'method'       => 'required|in:bank_transfer,cash,card_refund',
            'operator'     => 'nullable|string',
            'reference'    => 'nullable|string',
        ]);

        $account = CashlessAccount::findOrFail($accountId);

        try {
            $transaction = $this->accountService->cashout(
                $account,
                $request->amount_cents,
                CashoutChannel::from($request->channel),
                CashoutMethod::from($request->method),
                $request->operator,
                $request->reference,
            );

            return response()->json([
                'transaction'   => $this->formatTransaction($transaction),
                'balance_cents' => $account->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Transfer between accounts.
     */
    public function transfer(Request $request): JsonResponse
    {
        $request->validate([
            'from_account_id'    => 'required|integer',
            'to_account_number'  => 'required|string',
            'amount_cents'       => 'required|integer|min:1',
            'operator'           => 'nullable|string',
        ]);

        $source = CashlessAccount::findOrFail($request->from_account_id);
        $target = CashlessAccount::where('account_number', $request->to_account_number)->first();

        if (! $target) {
            return response()->json(['message' => 'Target account not found.'], 404);
        }

        try {
            $transaction = $this->accountService->transfer(
                $source,
                $target,
                $request->amount_cents,
                $request->operator,
            );

            return response()->json([
                'transaction'          => $this->formatTransaction($transaction),
                'source_balance_cents' => $source->balance_cents,
                'target_balance_cents' => $target->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Freeze/unfreeze an account.
     */
    public function toggleFreeze(int $accountId): JsonResponse
    {
        $account = CashlessAccount::findOrFail($accountId);

        try {
            if ($account->status->value === 'frozen') {
                $this->accountService->unfreeze($account);
                $message = 'Account unfrozen.';
            } else {
                $this->accountService->freeze($account);
                $message = 'Account frozen.';
            }

            return response()->json([
                'message' => $message,
                'status'  => $account->fresh()->status->value,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Transaction history for an account.
     */
    public function history(Request $request, int $accountId): JsonResponse
    {
        $account = CashlessAccount::findOrFail($accountId);

        $query = $account->transactions()->orderByDesc('created_at');

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $transactions = $query->paginate($request->input('per_page', 50));

        return response()->json($transactions);
    }

    /**
     * Redeem a voucher code.
     */
    public function redeemVoucher(Request $request, int $accountId): JsonResponse
    {
        $request->validate([
            'code'             => 'required|string',
            'topup_amount_cents' => 'nullable|integer|min:0',
        ]);

        $account = CashlessAccount::findOrFail($accountId);

        try {
            $transaction = $this->accountService->redeemVoucher(
                $account,
                $request->code,
                $request->input('topup_amount_cents', 0),
            );

            return response()->json([
                'transaction'   => $this->formatTransaction($transaction),
                'balance_cents' => $account->balance_cents,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    private function formatTransaction($transaction): array
    {
        return [
            'id'                   => $transaction->id,
            'transaction_type'     => $transaction->transaction_type,
            'amount_cents'         => $transaction->amount_cents,
            'balance_before_cents' => $transaction->balance_before_cents,
            'balance_after_cents'  => $transaction->balance_after_cents,
            'currency'             => $transaction->currency,
            'description'          => $transaction->description,
            'created_at'           => $transaction->created_at->toIso8601String(),
        ];
    }
}
