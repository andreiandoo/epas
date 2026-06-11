<?php

namespace App\Services\Cashless;

use App\Enums\AccountStatus;
use App\Enums\CashoutChannel;
use App\Enums\CashoutMethod;
use App\Enums\CashoutStatus;
use App\Enums\TopUpChannel;
use App\Enums\TopUpMethod;
use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSettings;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\CashlessVoucher;
use App\Models\Cashless\CashlessVoucherRedemption;
use App\Models\Customer;
use App\Models\VendorSaleItem;
use App\Models\Wristband;
use App\Models\WristbandTransaction;
use Illuminate\Support\Facades\DB;

class CashlessAccountService
{
    /**
     * Create a new CashlessAccount for a customer at an edition.
     */
    public function createAccount(
        int $tenantId,
        int $editionId,
        int $customerId,
        ?int $wristbandId = null,
        ?int $passPurchaseId = null,
        string $currency = 'RON',
    ): CashlessAccount {
        return CashlessAccount::create([
            'tenant_id'                  => $tenantId,
            'festival_edition_id'        => $editionId,
            'customer_id'                => $customerId,
            'wristband_id'               => $wristbandId,
            'festival_pass_purchase_id'  => $passPurchaseId,
            'account_number'             => CashlessAccount::generateAccountNumber(),
            'balance_cents'              => 0,
            'currency'                   => $currency,
            'status'                     => AccountStatus::Active,
            'activated_at'               => now(),
        ]);
    }

    /**
     * Top up a CashlessAccount. Atomic with row-level locking.
     */
    public function topUp(
        CashlessAccount $account,
        int $amountCents,
        TopUpChannel $channel,
        ?TopUpMethod $method = null,
        ?int $topupLocationId = null,
        ?string $paymentMethod = null,
        ?string $operator = null,
        ?string $reference = null,
    ): WristbandTransaction {
        $this->ensureCanTransact($account);

        $settings = CashlessSettings::forEdition($account->festival_edition_id);
        if ($settings && ! $settings->isTopUpAllowed($amountCents)) {
            throw new \InvalidArgumentException(
                "Top-up amount ({$amountCents}) is outside allowed range [{$settings->min_topup_cents}, {$settings->max_topup_cents}]"
            );
        }

        return DB::transaction(function () use ($account, $amountCents, $channel, $method, $topupLocationId, $paymentMethod, $operator, $reference, $settings) {
            $locked = CashlessAccount::lockForUpdate()->find($account->id);

            // Check max balance
            if ($settings && ($locked->balance_cents + $amountCents) > $settings->max_balance_cents) {
                throw new \InvalidArgumentException(
                    "Top-up would exceed maximum balance ({$settings->max_balance_cents})"
                );
            }

            $balanceBefore = $locked->balance_cents;
            $locked->balance_cents = $balanceBefore + $amountCents;
            $locked->total_topped_up_cents += $amountCents;
            $locked->save();

            // Sync in-memory model
            $account->balance_cents = $locked->balance_cents;
            $account->total_topped_up_cents = $locked->total_topped_up_cents;

            // Sync wristband if linked
            $this->syncWristbandBalance($locked);

            $customer = Customer::find($locked->customer_id);

            return WristbandTransaction::create([
                'wristband_id'          => $locked->wristband_id,
                'tenant_id'             => $locked->tenant_id,
                'festival_edition_id'   => $locked->festival_edition_id,
                'customer_id'           => $locked->customer_id,
                'transaction_type'      => 'topup',
                'amount_cents'          => $amountCents,
                'balance_before_cents'  => $balanceBefore,
                'balance_after_cents'   => $locked->balance_cents,
                'currency'              => $locked->currency,
                'payment_method'        => $paymentMethod ?? $method?->value,
                'operator'              => $operator,
                'reference'             => $reference,
                'channel'               => $channel->value,
                'topup_method'          => $method?->value,
                'topup_location_id'     => $topupLocationId,
                'cashless_account_id'   => $locked->id,
                'balance_snapshot_cents' => $locked->balance_cents,
                'customer_email'        => $customer?->email,
                'customer_name'         => $customer?->name ?? $customer?->first_name,
            ]);
        });
    }

    /**
     * Charge (payment) from a CashlessAccount. Returns false if insufficient balance.
     */
    public function charge(
        CashlessAccount $account,
        int $amountCents,
        ?string $vendorName = null,
        ?string $vendorLocation = null,
        ?string $description = null,
        ?int $vendorId = null,
        ?int $posDeviceId = null,
        ?string $operator = null,
    ): bool|WristbandTransaction {
        $this->ensureCanTransact($account);

        return DB::transaction(function () use ($account, $amountCents, $vendorName, $vendorLocation, $description, $vendorId, $posDeviceId, $operator) {
            $locked = CashlessAccount::lockForUpdate()->find($account->id);

            if ($locked->balance_cents < $amountCents) {
                return false;
            }

            $balanceBefore = $locked->balance_cents;
            $locked->balance_cents = $balanceBefore - $amountCents;
            $locked->total_spent_cents += $amountCents;
            $locked->save();

            $account->balance_cents = $locked->balance_cents;
            $account->total_spent_cents = $locked->total_spent_cents;

            $this->syncWristbandBalance($locked);

            $customer = Customer::find($locked->customer_id);

            return WristbandTransaction::create([
                'wristband_id'          => $locked->wristband_id,
                'tenant_id'             => $locked->tenant_id,
                'festival_edition_id'   => $locked->festival_edition_id,
                'customer_id'           => $locked->customer_id,
                'transaction_type'      => 'payment',
                'amount_cents'          => $amountCents,
                'balance_before_cents'  => $balanceBefore,
                'balance_after_cents'   => $locked->balance_cents,
                'currency'              => $locked->currency,
                'vendor_name'           => $vendorName,
                'vendor_location'       => $vendorLocation,
                'vendor_id'             => $vendorId,
                'vendor_pos_device_id'  => $posDeviceId,
                'description'           => $description,
                'operator'              => $operator,
                'cashless_account_id'   => $locked->id,
                'balance_snapshot_cents' => $locked->balance_cents,
                'customer_email'        => $customer?->email,
                'customer_name'         => $customer?->name ?? $customer?->first_name,
            ]);
        });
    }

    /**
     * Cashout (partial or full) from a CashlessAccount.
     */
    public function cashout(
        CashlessAccount $account,
        ?int $amountCents = null,
        CashoutChannel $channel = CashoutChannel::Physical,
        CashoutMethod $method = CashoutMethod::Cash,
        ?string $operator = null,
        ?string $reference = null,
    ): WristbandTransaction {
        $this->ensureCanTransact($account);

        return DB::transaction(function () use ($account, $amountCents, $channel, $method, $operator, $reference) {
            $locked = CashlessAccount::lockForUpdate()->find($account->id);

            // If no amount specified, cashout entire balance
            $cashoutAmount = $amountCents ?? $locked->balance_cents;

            if ($cashoutAmount <= 0) {
                throw new \InvalidArgumentException('Nothing to cash out.');
            }

            if ($locked->balance_cents < $cashoutAmount) {
                throw new \InvalidArgumentException(
                    "Cashout amount ({$cashoutAmount}) exceeds balance ({$locked->balance_cents})"
                );
            }

            $settings = CashlessSettings::forEdition($locked->festival_edition_id);
            if ($settings && ! $settings->isCashoutAllowed($cashoutAmount)) {
                throw new \InvalidArgumentException(
                    "Cashout amount below minimum ({$settings->min_cashout_cents})"
                );
            }

            // Calculate fee
            $feeCents = $settings ? $settings->calculateCashoutFee($cashoutAmount) : 0;
            $netCashout = $cashoutAmount - $feeCents;

            $balanceBefore = $locked->balance_cents;
            $locked->balance_cents = $balanceBefore - $cashoutAmount;
            $locked->total_cashed_out_cents += $netCashout;
            $locked->save();

            $account->balance_cents = $locked->balance_cents;
            $account->total_cashed_out_cents = $locked->total_cashed_out_cents;

            $this->syncWristbandBalance($locked);

            $isInstant = $channel === CashoutChannel::Physical;

            return WristbandTransaction::create([
                'wristband_id'          => $locked->wristband_id,
                'tenant_id'             => $locked->tenant_id,
                'festival_edition_id'   => $locked->festival_edition_id,
                'customer_id'           => $locked->customer_id,
                'transaction_type'      => 'cashout',
                'amount_cents'          => $cashoutAmount,
                'balance_before_cents'  => $balanceBefore,
                'balance_after_cents'   => $locked->balance_cents,
                'currency'              => $locked->currency,
                'description'           => $feeCents > 0
                    ? "Cashout {$cashoutAmount} (fee: {$feeCents}, net: {$netCashout})"
                    : "Cashout {$cashoutAmount}",
                'operator'              => $operator,
                'cashless_account_id'   => $locked->id,
                'balance_snapshot_cents' => $locked->balance_cents,
                'cashout_channel'       => $channel->value,
                'cashout_method'        => $method->value,
                'cashout_reference'     => $reference,
                'cashout_processed_at'  => $isInstant ? now() : null,
                'cashout_status'        => $isInstant ? CashoutStatus::Completed->value : CashoutStatus::Pending->value,
            ]);
        });
    }

    /**
     * Transfer balance between two CashlessAccounts (partial amount supported).
     */
    public function transfer(
        CashlessAccount $source,
        CashlessAccount $target,
        int $amountCents,
        ?string $operator = null,
    ): WristbandTransaction {
        $this->ensureCanTransact($source);
        $this->ensureCanTransact($target);

        if ($source->tenant_id !== $target->tenant_id) {
            throw new \InvalidArgumentException('Cannot transfer between accounts of different tenants.');
        }

        if ($source->festival_edition_id !== $target->festival_edition_id) {
            throw new \InvalidArgumentException('Cannot transfer between accounts of different editions.');
        }

        $settings = CashlessSettings::forEdition($source->festival_edition_id);
        if ($settings && ! $settings->allow_account_transfer) {
            throw new \InvalidArgumentException('Account transfers are disabled for this edition.');
        }

        if ($settings && $settings->max_transfer_cents && $amountCents > $settings->max_transfer_cents) {
            throw new \InvalidArgumentException(
                "Transfer amount ({$amountCents}) exceeds maximum ({$settings->max_transfer_cents})"
            );
        }

        $feeCents = $settings ? $settings->transfer_fee_cents : 0;

        return DB::transaction(function () use ($source, $target, $amountCents, $operator, $feeCents) {
            $lockedSource = CashlessAccount::lockForUpdate()->find($source->id);
            $lockedTarget = CashlessAccount::lockForUpdate()->find($target->id);

            $totalDeducted = $amountCents + $feeCents;

            if ($lockedSource->balance_cents < $totalDeducted) {
                throw new \InvalidArgumentException(
                    "Insufficient balance for transfer + fee ({$totalDeducted})"
                );
            }

            $sourceBefore = $lockedSource->balance_cents;
            $targetBefore = $lockedTarget->balance_cents;

            $lockedSource->balance_cents = $sourceBefore - $totalDeducted;
            $lockedSource->total_spent_cents += $feeCents;
            $lockedSource->save();

            $lockedTarget->balance_cents = $targetBefore + $amountCents;
            $lockedTarget->save();

            $source->balance_cents = $lockedSource->balance_cents;
            $target->balance_cents = $lockedTarget->balance_cents;

            $this->syncWristbandBalance($lockedSource);
            $this->syncWristbandBalance($lockedTarget);

            // Log outgoing
            WristbandTransaction::create([
                'wristband_id'          => $lockedSource->wristband_id,
                'tenant_id'             => $lockedSource->tenant_id,
                'festival_edition_id'   => $lockedSource->festival_edition_id,
                'customer_id'           => $lockedSource->customer_id,
                'transaction_type'      => 'transfer_out',
                'amount_cents'          => $totalDeducted,
                'balance_before_cents'  => $sourceBefore,
                'balance_after_cents'   => $lockedSource->balance_cents,
                'currency'              => $lockedSource->currency,
                'description'           => $feeCents > 0
                    ? "Transfer {$amountCents} to {$lockedTarget->account_number} (fee: {$feeCents})"
                    : "Transfer {$amountCents} to {$lockedTarget->account_number}",
                'operator'              => $operator,
                'cashless_account_id'   => $lockedSource->id,
                'balance_snapshot_cents' => $lockedSource->balance_cents,
            ]);

            // Log incoming
            return WristbandTransaction::create([
                'wristband_id'          => $lockedTarget->wristband_id,
                'tenant_id'             => $lockedTarget->tenant_id,
                'festival_edition_id'   => $lockedTarget->festival_edition_id,
                'customer_id'           => $lockedTarget->customer_id,
                'transaction_type'      => 'transfer_in',
                'amount_cents'          => $amountCents,
                'balance_before_cents'  => $targetBefore,
                'balance_after_cents'   => $lockedTarget->balance_cents,
                'currency'              => $lockedTarget->currency,
                'description'           => "Transfer from {$lockedSource->account_number}",
                'operator'              => $operator,
                'cashless_account_id'   => $lockedTarget->id,
                'balance_snapshot_cents' => $lockedTarget->balance_cents,
            ]);
        });
    }

    /**
     * Refund to a CashlessAccount.
     */
    public function refund(
        CashlessAccount $account,
        int $amountCents,
        ?string $description = null,
        ?string $operator = null,
    ): WristbandTransaction {
        return DB::transaction(function () use ($account, $amountCents, $description, $operator) {
            $locked = CashlessAccount::lockForUpdate()->find($account->id);

            $balanceBefore = $locked->balance_cents;
            $locked->balance_cents = $balanceBefore + $amountCents;
            $locked->total_spent_cents = max(0, $locked->total_spent_cents - $amountCents);
            $locked->save();

            $account->balance_cents = $locked->balance_cents;
            $account->total_spent_cents = $locked->total_spent_cents;

            $this->syncWristbandBalance($locked);

            return WristbandTransaction::create([
                'wristband_id'          => $locked->wristband_id,
                'tenant_id'             => $locked->tenant_id,
                'festival_edition_id'   => $locked->festival_edition_id,
                'customer_id'           => $locked->customer_id,
                'transaction_type'      => 'refund',
                'amount_cents'          => $amountCents,
                'balance_before_cents'  => $balanceBefore,
                'balance_after_cents'   => $locked->balance_cents,
                'currency'              => $locked->currency,
                'description'           => $description ?? 'Refund',
                'operator'              => $operator,
                'cashless_account_id'   => $locked->id,
                'balance_snapshot_cents' => $locked->balance_cents,
            ]);
        });
    }

    /**
     * Redeem a voucher code, crediting the account.
     */
    public function redeemVoucher(
        CashlessAccount $account,
        string $voucherCode,
        int $topupAmountCents = 0,
    ): WristbandTransaction {
        $this->ensureCanTransact($account);

        $voucher = CashlessVoucher::where('code', $voucherCode)
            ->where('festival_edition_id', $account->festival_edition_id)
            ->first();

        if (! $voucher) {
            throw new \InvalidArgumentException("Voucher code '{$voucherCode}' not found.");
        }

        if (! $voucher->canBeRedeemedBy($account->customer_id)) {
            throw new \InvalidArgumentException('Voucher cannot be redeemed.');
        }

        $bonusAmount = $voucher->calculateBonusAmount($topupAmountCents);
        if ($bonusAmount <= 0) {
            throw new \InvalidArgumentException('No bonus amount for this voucher.');
        }

        // Check budget
        if ($voucher->total_budget_cents !== null
            && ($voucher->used_budget_cents + $bonusAmount) > $voucher->total_budget_cents) {
            throw new \InvalidArgumentException('Voucher budget exhausted.');
        }

        return DB::transaction(function () use ($account, $voucher, $bonusAmount) {
            $locked = CashlessAccount::lockForUpdate()->find($account->id);

            $balanceBefore = $locked->balance_cents;
            $locked->balance_cents = $balanceBefore + $bonusAmount;
            $locked->total_topped_up_cents += $bonusAmount;
            $locked->save();

            $account->balance_cents = $locked->balance_cents;

            $this->syncWristbandBalance($locked);

            // Update voucher counters
            $voucher->increment('current_redemptions');
            $voucher->increment('used_budget_cents', $bonusAmount);

            $transaction = WristbandTransaction::create([
                'wristband_id'          => $locked->wristband_id,
                'tenant_id'             => $locked->tenant_id,
                'festival_edition_id'   => $locked->festival_edition_id,
                'customer_id'           => $locked->customer_id,
                'transaction_type'      => 'voucher_credit',
                'amount_cents'          => $bonusAmount,
                'balance_before_cents'  => $balanceBefore,
                'balance_after_cents'   => $locked->balance_cents,
                'currency'              => $locked->currency,
                'description'           => "Voucher: {$voucher->name} ({$voucher->code})",
                'cashless_account_id'   => $locked->id,
                'balance_snapshot_cents' => $locked->balance_cents,
            ]);

            CashlessVoucherRedemption::create([
                'cashless_voucher_id'      => $voucher->id,
                'cashless_account_id'      => $locked->id,
                'customer_id'              => $locked->customer_id,
                'amount_cents'             => $bonusAmount,
                'wristband_transaction_id' => $transaction->id,
                'redeemed_at'              => now(),
            ]);

            return $transaction;
        });
    }

    /**
     * Freeze an account (prevent transactions).
     */
    public function freeze(CashlessAccount $account): void
    {
        $account->update(['status' => AccountStatus::Frozen]);
    }

    /**
     * Unfreeze a frozen account.
     */
    public function unfreeze(CashlessAccount $account): void
    {
        if ($account->status !== AccountStatus::Frozen) {
            throw new \InvalidArgumentException('Account is not frozen.');
        }

        $account->update(['status' => AccountStatus::Active]);
    }

    /**
     * Close an account. Cashout must be done before closing.
     */
    public function close(CashlessAccount $account): void
    {
        if ($account->balance_cents > 0) {
            throw new \InvalidArgumentException('Cannot close account with remaining balance. Cashout first.');
        }

        $account->update([
            'status'    => AccountStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    // ── Private helpers ──

    private function ensureCanTransact(CashlessAccount $account): void
    {
        if (! $account->canTransact()) {
            throw new \InvalidArgumentException(
                "Account {$account->account_number} is {$account->status->value} and cannot transact."
            );
        }
    }

    /**
     * Sync the wristband balance to match the CashlessAccount balance.
     * CashlessAccount is source of truth; Wristband is mirror/cache.
     */
    private function syncWristbandBalance(CashlessAccount $account): void
    {
        if (! $account->wristband_id) {
            return;
        }

        Wristband::where('id', $account->wristband_id)
            ->update(['balance_cents' => $account->balance_cents]);
    }
}
