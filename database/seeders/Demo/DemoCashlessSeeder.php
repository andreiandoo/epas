<?php

namespace Database\Seeders\Demo;

use App\Enums\AccountStatus;
use App\Enums\SaleStatus;
use App\Models\Cashless\CashlessAccount;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\CashlessSettings;
use App\Models\Cashless\CashlessVoucher;
use App\Models\Cashless\CashlessVoucherRedemption;
use App\Models\Cashless\TopUpLocation;
use App\Models\VendorSaleItem;
use App\Models\Wristband;
use App\Models\WristbandTransaction;
use Carbon\Carbon;

class DemoCashlessSeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenantId = $this->parent->tenantId;
        $edition = $this->parent->refs['edition'];
        $customers = $this->parent->refs['customers'];
        $passPurchases = $this->parent->refs['passPurchases'] ?? [];
        $vendors = $this->parent->refs['vendors'] ?? [];
        $products = $this->parent->refs['products'] ?? [];
        $employees = $this->parent->refs['employees'] ?? [];

        // ── Cashless Settings ──
        CashlessSettings::firstOrCreate(
            ['tenant_id' => $tenantId, 'festival_edition_id' => $edition->id],
            [
                'min_topup_cents' => 2000,
                'max_topup_cents' => 100000,
                'max_balance_cents' => 200000,
                'daily_topup_limit_cents' => 200000,
                'allow_online_cashout' => true,
                'allow_physical_cashout' => true,
                'min_cashout_cents' => 1000,
                'cashout_fee_cents' => 0,
                'cashout_fee_percentage' => 0.00,
                'auto_cashout_after_festival' => true,
                'auto_cashout_delay_days' => 14,
                'auto_cashout_method' => 'bank_transfer',
                'allow_account_transfer' => true,
                'max_transfer_cents' => 50000,
                'transfer_fee_cents' => 0,
                'require_pin_above_cents' => 50000,
                'max_charge_cents' => 100000,
                'charge_cooldown_seconds' => 5,
                'enforce_age_verification' => true,
                'age_verification_method' => 'wristband_flag',
                'currency' => 'RON',
                'currency_symbol' => 'lei',
                'display_decimals' => 2,
                'low_balance_threshold_cents' => 2000,
                'send_receipt_on_purchase' => true,
                'send_daily_summary' => false,
                'allow_tipping' => true,
                'tip_presets' => [5, 10, 15, 20],
                'max_tip_cents' => 5000,
            ]
        );

        // ── TopUp Locations ──
        $topupLocations = [];
        $locData = [
            ['code' => 'DEMO-TOPUP-GATE', 'name' => 'Intrare Principala', 'zone' => 'general'],
            ['code' => 'DEMO-TOPUP-A', 'name' => 'Zona A - Food Court', 'zone' => 'a'],
            ['code' => 'DEMO-TOPUP-B', 'name' => 'Zona B - Stage Area', 'zone' => 'b'],
            ['code' => 'DEMO-TOPUP-VIP', 'name' => 'Zona VIP', 'zone' => 'vip'],
        ];
        foreach ($locData as $loc) {
            $topupLocations[] = TopUpLocation::firstOrCreate(
                ['festival_edition_id' => $edition->id, 'location_code' => $loc['code']],
                ['tenant_id' => $tenantId, 'name' => $loc['name'], 'zone' => $loc['zone'], 'is_active' => true]
            );
        }

        // ── Wristbands + CashlessAccounts (20 active + 5 unassigned) ──
        $wristbands = [];
        $accounts = [];

        // Get POS devices for transactions
        $posDevices = \App\Models\VendorPosDevice::where('tenant_id', $tenantId)
            ->where('device_uid', 'like', 'POS-demo-%')->get();

        for ($i = 0; $i < 25; $i++) {
            $uid = sprintf('WB-DEMO-AF26-%04d', $i + 1);
            $isActive = $i < 20;
            $customer = $isActive && isset($customers[$i]) ? $customers[$i] : null;
            $pp = $isActive && isset($passPurchases[$i]) ? $passPurchases[$i] : null;

            $wristband = Wristband::firstOrCreate(
                ['tenant_id' => $tenantId, 'uid' => $uid],
                [
                    'festival_edition_id' => $edition->id,
                    'festival_pass_purchase_id' => $pp?->id,
                    'customer_id' => $customer?->id,
                    'wristband_type' => 'hybrid',
                    'status' => $isActive ? 'activated' : 'unassigned',
                    'balance_cents' => 0, // Will be updated after transactions
                    'currency' => 'RON',
                    'activated_at' => $isActive ? Carbon::parse('2026-07-15 12:00:00')->addMinutes($i * 15) : null,
                    'assigned_at' => $isActive ? Carbon::parse('2026-07-15 10:00:00') : null,
                ]
            );
            $wristbands[] = $wristband;

            if ($isActive && $customer) {
                $account = CashlessAccount::firstOrCreate(
                    ['festival_edition_id' => $edition->id, 'customer_id' => $customer->id],
                    [
                        'tenant_id' => $tenantId,
                        'wristband_id' => $wristband->id,
                        'festival_pass_purchase_id' => $pp?->id,
                        'account_number' => sprintf('CA-DEMO-%05d', $i + 1),
                        'balance_cents' => 0,
                        'total_topped_up_cents' => 0,
                        'total_spent_cents' => 0,
                        'total_cashed_out_cents' => 0,
                        'currency' => 'RON',
                        'status' => AccountStatus::Active,
                        'activated_at' => $wristband->activated_at,
                    ]
                );
                $accounts[] = $account;
            }
        }

        $this->parent->refs['wristbands'] = $wristbands;
        $this->parent->refs['accounts'] = $accounts;

        // ── Transactions ──
        // Build transaction ledger per wristband, then seed all
        $txnSeq = 1;
        $saleSeq = 1;
        $festivalDates = [
            Carbon::parse('2026-07-15'),
            Carbon::parse('2026-07-16'),
            Carbon::parse('2026-07-17'),
            Carbon::parse('2026-07-18'),
            Carbon::parse('2026-07-19'),
        ];

        // Group products by vendor for sale generation
        $productsByVendor = [];
        foreach ($products as $p) {
            $productsByVendor[$p->vendor_id][] = $p;
        }

        // Get vendor employee map: vendor_id => [employees]
        $empByVendor = [];
        foreach ($employees as $emp) {
            $empByVendor[$emp->vendor_id][] = $emp;
        }

        // Deterministic random seed for reproducibility
        mt_srand(42);

        foreach ($accounts as $accIdx => $account) {
            $wb = $wristbands[$accIdx];
            $balance = 0;
            $totalTopUp = 0;
            $totalSpent = 0;
            $totalCashedOut = 0;

            // Each account gets 1-3 topups and 2-5 payments
            $numTopups = mt_rand(1, 3);
            $numPayments = mt_rand(2, 5);

            // ── Topups ──
            for ($t = 0; $t < $numTopups; $t++) {
                $amount = mt_rand(3, 15) * 1000; // 30-150 RON
                $day = $festivalDates[min($t, 4)];
                $hour = mt_rand(12, 18);
                $txnTime = $day->copy()->setHour($hour)->setMinute(mt_rand(0, 59));
                $locIdx = mt_rand(0, count($topupLocations) - 1);
                $payMethod = ['card', 'cash', 'card'][mt_rand(0, 2)];

                $balanceBefore = $balance;
                $balance += $amount;
                $totalTopUp += $amount;

                $ref = sprintf('DEMO-TXN-%06d', $txnSeq++);
                WristbandTransaction::firstOrCreate(
                    ['reference' => $ref],
                    [
                        'wristband_id' => $wb->id,
                        'tenant_id' => $tenantId,
                        'festival_edition_id' => $edition->id,
                        'customer_id' => $account->customer_id,
                        'cashless_account_id' => $account->id,
                        'transaction_type' => 'topup',
                        'amount_cents' => $amount,
                        'balance_before_cents' => $balanceBefore,
                        'balance_after_cents' => $balance,
                        'balance_snapshot_cents' => $balance,
                        'currency' => 'RON',
                        'description' => "Top-up {$payMethod}",
                        'payment_method' => $payMethod,
                        'topup_location_id' => $topupLocations[$locIdx]->id,
                        'channel' => $payMethod === 'card' ? 'online' : 'physical',
                        'topup_method' => $payMethod,
                        'operator' => 'demo-topup-operator',
                        'sync_source' => 'online',
                        'is_reconciled' => true,
                        'created_at' => $txnTime,
                        'updated_at' => $txnTime,
                    ]
                );
            }

            // ── Payments (purchases at vendors) ──
            for ($p = 0; $p < $numPayments; $p++) {
                if ($balance <= 0) break;

                // Pick a random vendor
                $vendorIdx = mt_rand(0, count($vendors) - 1);
                $vendor = $vendors[$vendorIdx];
                $vendorProducts = $productsByVendor[$vendor->id] ?? [];
                $vendorEmps = $empByVendor[$vendor->id] ?? [];
                $vendorPosDevs = $posDevices->where('vendor_id', $vendor->id)->values();

                if (empty($vendorProducts)) continue;

                // Pick 1-3 products
                $numItems = min(mt_rand(1, 3), count($vendorProducts));
                $saleTotal = 0;
                $saleItems = [];
                for ($s = 0; $s < $numItems; $s++) {
                    $prod = $vendorProducts[mt_rand(0, count($vendorProducts) - 1)];
                    $qty = mt_rand(1, 2);
                    $itemTotal = $prod->price_cents * $qty;
                    $saleTotal += $itemTotal;
                    $saleItems[] = ['product' => $prod, 'qty' => $qty, 'total' => $itemTotal];
                }

                // Don't exceed balance
                if ($saleTotal > $balance) {
                    $saleTotal = $saleItems[0]['total']; // Just first item
                    $saleItems = [$saleItems[0]];
                    if ($saleTotal > $balance) continue;
                }

                $day = $festivalDates[min($p + 1, 4)];
                $hour = mt_rand(14, 23);
                $txnTime = $day->copy()->setHour($hour)->setMinute(mt_rand(0, 59));
                $emp = !empty($vendorEmps) ? $vendorEmps[mt_rand(0, count($vendorEmps) - 1)] : null;
                $pos = $vendorPosDevs->isNotEmpty() ? $vendorPosDevs[mt_rand(0, $vendorPosDevs->count() - 1)] : null;

                // Tip (20% chance)
                $tipCents = (mt_rand(1, 5) === 1) ? mt_rand(2, 10) * 100 : 0;
                $totalWithTip = $saleTotal + $tipCents;
                if ($totalWithTip > $balance) {
                    $tipCents = 0;
                    $totalWithTip = $saleTotal;
                }

                $balanceBefore = $balance;
                $balance -= $totalWithTip;
                $totalSpent += $totalWithTip;

                // Get VendorEdition for commission rate
                $ve = \App\Models\VendorEdition::where('vendor_id', $vendor->id)
                    ->where('festival_edition_id', $edition->id)->first();
                $commRate = $ve?->commission_rate ?? 10;
                $commCents = (int) round($saleTotal * $commRate / 100);
                $taxCents = (int) round($saleTotal * 19 / 119); // 19% VAT included

                $payRef = sprintf('DEMO-TXN-%06d', $txnSeq++);
                $txn = WristbandTransaction::firstOrCreate(
                    ['reference' => $payRef],
                    [
                        'wristband_id' => $wb->id,
                        'tenant_id' => $tenantId,
                        'festival_edition_id' => $edition->id,
                        'customer_id' => $account->customer_id,
                        'cashless_account_id' => $account->id,
                        'transaction_type' => 'payment',
                        'amount_cents' => $totalWithTip,
                        'balance_before_cents' => $balanceBefore,
                        'balance_after_cents' => $balance,
                        'balance_snapshot_cents' => $balance,
                        'currency' => 'RON',
                        'description' => "Cumparare la {$vendor->name}",
                        'vendor_name' => $vendor->name,
                        'vendor_id' => $vendor->id,
                        'vendor_pos_device_id' => $pos?->id,
                        'payment_method' => 'cashless',
                        'operator' => $emp?->name ?? 'demo-operator',
                        'sync_source' => 'online',
                        'is_reconciled' => true,
                        'created_at' => $txnTime,
                        'updated_at' => $txnTime,
                    ]
                );

                // ── CashlessSale ──
                $saleNum = sprintf('SALE-DEMO-%04d', $saleSeq++);
                $sale = CashlessSale::firstOrCreate(
                    ['sale_number' => $saleNum],
                    [
                        'tenant_id' => $tenantId,
                        'festival_edition_id' => $edition->id,
                        'vendor_id' => $vendor->id,
                        'cashless_account_id' => $account->id,
                        'customer_id' => $account->customer_id,
                        'wristband_transaction_id' => $txn->id,
                        'vendor_employee_id' => $emp?->id,
                        'vendor_pos_device_id' => $pos?->id,
                        'subtotal_cents' => $saleTotal,
                        'tax_cents' => $taxCents,
                        'total_cents' => $saleTotal,
                        'commission_cents' => $commCents,
                        'tip_cents' => $tipCents,
                        'tip_percentage' => $tipCents > 0 ? round($tipCents / $saleTotal * 100, 2) : 0,
                        'total_with_tip_cents' => $totalWithTip,
                        'currency' => 'RON',
                        'items_count' => count($saleItems),
                        'status' => SaleStatus::Completed,
                        'sold_at' => $txnTime,
                    ]
                );

                // ── VendorSaleItems ──
                foreach ($saleItems as $si) {
                    $itemComm = (int) round($si['total'] * $commRate / 100);
                    $itemTax = (int) round($si['total'] * 19 / 119);
                    VendorSaleItem::create([
                        'cashless_sale_id' => $sale->id,
                        'vendor_id' => $vendor->id,
                        'festival_edition_id' => $edition->id,
                        'vendor_product_id' => $si['product']->id,
                        'vendor_pos_device_id' => $pos?->id,
                        'vendor_employee_id' => $emp?->id,
                        'product_name' => $si['product']->name,
                        'category_name' => $si['product']->category?->name ?? 'General',
                        'quantity' => $si['qty'],
                        'unit_price_cents' => $si['product']->price_cents,
                        'total_cents' => $si['total'],
                        'tax_cents' => $itemTax,
                        'sgr_cents' => 0,
                        'product_type' => $si['product']->type instanceof \BackedEnum ? $si['product']->type->value : ($si['product']->type ?? 'food'),
                        'currency' => 'RON',
                        'commission_cents' => $itemComm,
                        'commission_rate' => $commRate,
                        'operator' => $emp?->name ?? 'demo-operator',
                        'created_at' => $txnTime,
                        'updated_at' => $txnTime,
                    ]);
                }
            }

            // ── Refund for some accounts (every 5th) ──
            if ($accIdx % 5 === 4 && $totalSpent > 0) {
                $refundAmount = mt_rand(500, 2000);
                $balanceBefore = $balance;
                $balance += $refundAmount;
                $totalSpent -= $refundAmount;

                $refRef = sprintf('DEMO-TXN-%06d', $txnSeq++);
                WristbandTransaction::firstOrCreate(
                    ['reference' => $refRef],
                    [
                        'wristband_id' => $wb->id,
                        'tenant_id' => $tenantId,
                        'festival_edition_id' => $edition->id,
                        'customer_id' => $account->customer_id,
                        'cashless_account_id' => $account->id,
                        'transaction_type' => 'refund',
                        'amount_cents' => $refundAmount,
                        'balance_before_cents' => $balanceBefore,
                        'balance_after_cents' => $balance,
                        'balance_snapshot_cents' => $balance,
                        'currency' => 'RON',
                        'description' => 'Rambursare produs',
                        'operator' => 'demo-admin',
                        'sync_source' => 'online',
                        'is_reconciled' => true,
                        'created_at' => Carbon::parse('2026-07-18 15:00:00'),
                        'updated_at' => Carbon::parse('2026-07-18 15:00:00'),
                    ]
                );
            }

            // ── Cashout for last 2 accounts ──
            if ($accIdx >= 18 && $balance > 0) {
                $cashoutAmount = $balance;
                $balanceBefore = $balance;
                $balance = 0;
                $totalCashedOut += $cashoutAmount;

                $coRef = sprintf('DEMO-TXN-%06d', $txnSeq++);
                WristbandTransaction::firstOrCreate(
                    ['reference' => $coRef],
                    [
                        'wristband_id' => $wb->id,
                        'tenant_id' => $tenantId,
                        'festival_edition_id' => $edition->id,
                        'customer_id' => $account->customer_id,
                        'cashless_account_id' => $account->id,
                        'transaction_type' => 'cashout',
                        'amount_cents' => $cashoutAmount,
                        'balance_before_cents' => $balanceBefore,
                        'balance_after_cents' => 0,
                        'balance_snapshot_cents' => 0,
                        'currency' => 'RON',
                        'description' => 'Cashout final festival',
                        'cashout_channel' => 'physical',
                        'cashout_method' => 'cash',
                        'cashout_status' => 'processed',
                        'cashout_processed_at' => Carbon::parse('2026-07-19 22:00:00'),
                        'operator' => 'demo-cashier',
                        'sync_source' => 'online',
                        'is_reconciled' => true,
                        'created_at' => Carbon::parse('2026-07-19 22:00:00'),
                        'updated_at' => Carbon::parse('2026-07-19 22:00:00'),
                    ]
                );
            }

            // Update final balances
            $wb->update(['balance_cents' => $balance]);
            $account->update([
                'balance_cents' => $balance,
                'total_topped_up_cents' => $totalTopUp,
                'total_spent_cents' => $totalSpent,
                'total_cashed_out_cents' => $totalCashedOut,
            ]);
        }

        // ── Transfers (3 pairs) ──
        for ($tr = 0; $tr < 3; $tr++) {
            $fromIdx = $tr * 2;
            $toIdx = $tr * 2 + 1;
            if (!isset($accounts[$fromIdx], $accounts[$toIdx])) break;

            $transferAmount = 2000;
            $fromWb = $wristbands[$fromIdx];
            $toWb = $wristbands[$toIdx];
            $fromAcc = $accounts[$fromIdx];
            $toAcc = $accounts[$toIdx];
            $txnTime = Carbon::parse('2026-07-17 16:00:00')->addMinutes($tr * 30);

            if ($fromWb->balance_cents < $transferAmount) continue;

            $fromBefore = $fromWb->balance_cents;
            $toBefore = $toWb->balance_cents;

            $outRef = sprintf('DEMO-TXN-%06d', $txnSeq++);
            WristbandTransaction::firstOrCreate(
                ['reference' => $outRef],
                [
                    'wristband_id' => $fromWb->id, 'tenant_id' => $tenantId,
                    'festival_edition_id' => $edition->id, 'customer_id' => $fromAcc->customer_id,
                    'cashless_account_id' => $fromAcc->id,
                    'transaction_type' => 'transfer_out', 'amount_cents' => $transferAmount,
                    'balance_before_cents' => $fromBefore, 'balance_after_cents' => $fromBefore - $transferAmount,
                    'balance_snapshot_cents' => $fromBefore - $transferAmount,
                    'currency' => 'RON', 'description' => "Transfer catre {$toWb->uid}",
                    'related_wristband_id' => $toWb->id,
                    'operator' => 'demo-system', 'sync_source' => 'online', 'is_reconciled' => true,
                    'created_at' => $txnTime, 'updated_at' => $txnTime,
                ]
            );

            $inRef = sprintf('DEMO-TXN-%06d', $txnSeq++);
            WristbandTransaction::firstOrCreate(
                ['reference' => $inRef],
                [
                    'wristband_id' => $toWb->id, 'tenant_id' => $tenantId,
                    'festival_edition_id' => $edition->id, 'customer_id' => $toAcc->customer_id,
                    'cashless_account_id' => $toAcc->id,
                    'transaction_type' => 'transfer_in', 'amount_cents' => $transferAmount,
                    'balance_before_cents' => $toBefore, 'balance_after_cents' => $toBefore + $transferAmount,
                    'balance_snapshot_cents' => $toBefore + $transferAmount,
                    'currency' => 'RON', 'description' => "Transfer de la {$fromWb->uid}",
                    'related_wristband_id' => $fromWb->id,
                    'operator' => 'demo-system', 'sync_source' => 'online', 'is_reconciled' => true,
                    'created_at' => $txnTime, 'updated_at' => $txnTime,
                ]
            );

            $fromWb->update(['balance_cents' => $fromBefore - $transferAmount]);
            $toWb->update(['balance_cents' => $toBefore + $transferAmount]);
            $fromAcc->update([
                'balance_cents' => $fromBefore - $transferAmount,
                'total_spent_cents' => $fromAcc->total_spent_cents + $transferAmount,
            ]);
            $toAcc->update([
                'balance_cents' => $toBefore + $transferAmount,
                'total_topped_up_cents' => $toAcc->total_topped_up_cents + $transferAmount,
            ]);
        }

        // ── Vouchers ──
        $vouchers = [];
        $voucherData = [
            ['code' => 'DEMO-WELCOME50', 'name' => 'Welcome 50 RON', 'type' => 'fixed_credit', 'amount' => 5000, 'max' => 100, 'used' => 5],
            ['code' => 'DEMO-SPONSOR100', 'name' => 'Sponsor BT 100 RON', 'type' => 'fixed_credit', 'amount' => 10000, 'max' => 50, 'used' => 3, 'sponsor' => 'Banca Transilvania'],
            ['code' => 'DEMO-TOPUP10', 'name' => 'Top-up Bonus 10%', 'type' => 'topup_bonus', 'amount' => 0, 'bonus_pct' => 10.00, 'min_topup' => 5000, 'max_bonus' => 5000, 'max' => 200, 'used' => 8],
        ];

        foreach ($voucherData as $vd) {
            $vouchers[] = CashlessVoucher::firstOrCreate(
                ['festival_edition_id' => $edition->id, 'code' => $vd['code']],
                [
                    'tenant_id' => $tenantId,
                    'name' => $vd['name'],
                    'voucher_type' => $vd['type'],
                    'amount_cents' => $vd['amount'],
                    'bonus_percentage' => $vd['bonus_pct'] ?? null,
                    'min_topup_cents' => $vd['min_topup'] ?? null,
                    'max_bonus_cents' => $vd['max_bonus'] ?? null,
                    'sponsor_name' => $vd['sponsor'] ?? null,
                    'total_budget_cents' => $vd['amount'] * ($vd['max'] ?? 100),
                    'used_budget_cents' => $vd['amount'] * ($vd['used'] ?? 0),
                    'max_redemptions' => $vd['max'],
                    'current_redemptions' => $vd['used'],
                    'max_per_customer' => 1,
                    'valid_from' => '2026-07-15 00:00:00',
                    'valid_until' => '2026-07-19 23:59:59',
                    'is_active' => true,
                ]
            );
        }
        $this->parent->refs['vouchers'] = $vouchers;

        // ── Voucher Redemptions (5 for WELCOME50) ──
        if (count($accounts) >= 5 && !empty($vouchers[0])) {
            for ($r = 0; $r < 5; $r++) {
                CashlessVoucherRedemption::firstOrCreate(
                    ['cashless_voucher_id' => $vouchers[0]->id, 'cashless_account_id' => $accounts[$r]->id],
                    [
                        'customer_id' => $accounts[$r]->customer_id,
                        'amount_cents' => 5000,
                        'redeemed_at' => Carbon::parse('2026-07-15 14:00:00')->addMinutes($r * 20),
                    ]
                );
            }
        }

        mt_srand(); // Reset random seed
    }

    public function cleanup(): void
    {
        $tenantId = $this->parent->tenantId;
        $edition = \App\Models\FestivalEdition::where('tenant_id', $tenantId)->where('slug', 'demo-alpha-fest-2026')->first();
        if (!$edition) return;

        // Voucher redemptions + vouchers
        $voucherIds = CashlessVoucher::where('festival_edition_id', $edition->id)->where('code', 'like', 'DEMO-%')->pluck('id');
        CashlessVoucherRedemption::whereIn('cashless_voucher_id', $voucherIds)->delete();
        CashlessVoucher::whereIn('id', $voucherIds)->delete();

        // Sale items + sales
        $saleIds = CashlessSale::where('festival_edition_id', $edition->id)->where('sale_number', 'like', 'SALE-DEMO-%')->pluck('id');
        VendorSaleItem::whereIn('cashless_sale_id', $saleIds)->delete();
        CashlessSale::whereIn('id', $saleIds)->delete();

        // Transactions
        WristbandTransaction::where('festival_edition_id', $edition->id)->where('reference', 'like', 'DEMO-TXN-%')->delete();

        // Accounts + Wristbands
        CashlessAccount::where('festival_edition_id', $edition->id)->where('account_number', 'like', 'CA-DEMO-%')->delete();
        Wristband::where('tenant_id', $tenantId)->where('uid', 'like', 'WB-DEMO-%')->delete();

        // TopUp locations + settings
        TopUpLocation::where('festival_edition_id', $edition->id)->where('location_code', 'like', 'DEMO-%')->delete();
        CashlessSettings::where('tenant_id', $tenantId)->where('festival_edition_id', $edition->id)->delete();
    }
}
