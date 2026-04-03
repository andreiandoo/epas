<?php

namespace Database\Seeders\Demo;

use App\Enums\FeeType;
use App\Models\Cashless\CashlessSale;
use App\Models\Cashless\FinanceFeeRule;
use App\Models\Cashless\VendorFinanceSummary;
use App\Models\VendorShift;
use Carbon\Carbon;

class DemoFinanceSeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenantId = $this->parent->tenantId;
        $edition = $this->parent->refs['edition'];
        $vendors = $this->parent->refs['vendors'] ?? [];

        // ── Finance Fee Rules ──
        FinanceFeeRule::firstOrCreate(
            ['festival_edition_id' => $edition->id, 'name' => 'Demo Chirie stand zilnica'],
            [
                'tenant_id' => $tenantId, 'fee_type' => FeeType::FixedDaily,
                'amount_cents' => 50000, 'is_active' => true,
                'period_start' => '2026-07-15', 'period_end' => '2026-07-19',
                'notes' => '500 RON/zi chirie stand',
            ]
        );

        FinanceFeeRule::firstOrCreate(
            ['festival_edition_id' => $edition->id, 'name' => 'Demo Comision vanzari food'],
            [
                'tenant_id' => $tenantId, 'fee_type' => FeeType::PercentagePerCategory,
                'percentage' => 15.0000, 'category_filter' => ['food'],
                'is_active' => true, 'period_start' => '2026-07-15', 'period_end' => '2026-07-19',
            ]
        );

        FinanceFeeRule::firstOrCreate(
            ['festival_edition_id' => $edition->id, 'name' => 'Demo Comision vanzari drink'],
            [
                'tenant_id' => $tenantId, 'fee_type' => FeeType::PercentagePerCategory,
                'percentage' => 12.0000, 'category_filter' => ['drink', 'alcohol'],
                'is_active' => true, 'period_start' => '2026-07-15', 'period_end' => '2026-07-19',
            ]
        );

        FinanceFeeRule::firstOrCreate(
            ['festival_edition_id' => $edition->id, 'name' => 'Demo Taxa per tranzactie'],
            [
                'tenant_id' => $tenantId, 'fee_type' => FeeType::FixedPerTransaction,
                'amount_cents' => 50, 'is_active' => true,
                'period_start' => '2026-07-15', 'period_end' => '2026-07-19',
                'notes' => '0.50 RON per tranzactie',
            ]
        );

        // ── Vendor Finance Summaries (4 vendors × 5 days = 20) ──
        $festivalDays = [
            '2026-07-15', '2026-07-16', '2026-07-17', '2026-07-18', '2026-07-19',
        ];

        foreach ($vendors as $vendor) {
            foreach ($festivalDays as $day) {
                // Get actual sales data for this vendor/day
                $daySales = CashlessSale::where('festival_edition_id', $edition->id)
                    ->where('vendor_id', $vendor->id)
                    ->whereDate('sold_at', $day)
                    ->get();

                $gross = $daySales->sum('total_cents');
                $commission = $daySales->sum('commission_cents');
                $tax = $daySales->sum('tax_cents');
                $tips = $daySales->sum('tip_cents');
                $count = $daySales->count();

                // If no real data, generate realistic estimates
                if ($count === 0) {
                    $gross = mt_rand(50000, 300000);
                    $commission = (int) round($gross * 0.12);
                    $tax = (int) round($gross * 19 / 119);
                    $tips = mt_rand(1000, 10000);
                    $count = mt_rand(15, 60);
                }

                $fees = 50000 + ($count * 50); // Daily rent + per-txn fee
                $net = $gross - $commission;
                $payout = $net - $fees;

                VendorFinanceSummary::firstOrCreate(
                    ['festival_edition_id' => $edition->id, 'vendor_id' => $vendor->id, 'period_date' => $day],
                    [
                        'tenant_id' => $tenantId,
                        'gross_sales_cents' => $gross,
                        'net_sales_cents' => $net,
                        'commission_cents' => $commission,
                        'fees_cents' => $fees,
                        'tax_collected_cents' => $tax,
                        'sgr_collected_cents' => 0,
                        'tips_cents' => $tips,
                        'vendor_payout_cents' => max(0, $payout),
                        'transactions_count' => $count,
                    ]
                );
            }
        }

        // ── Vendor Shifts (4 vendors × 5 days × 2 shifts) ──
        $employees = $this->parent->refs['employees'] ?? [];
        $empByVendor = [];
        foreach ($employees as $emp) {
            $empByVendor[$emp->vendor_id][] = $emp;
        }

        $posDevices = \App\Models\VendorPosDevice::where('tenant_id', $tenantId)
            ->where('device_uid', 'like', 'POS-demo-%')->get()->groupBy('vendor_id');

        foreach ($vendors as $vendor) {
            $vEmps = $empByVendor[$vendor->id] ?? [];
            $vPos = $posDevices[$vendor->id] ?? collect();

            // Skip shifts if no employees or POS devices
            if (empty($vEmps) || $vPos->isEmpty()) continue;

            foreach ($festivalDays as $dayIdx => $day) {
                // Morning shift
                $morningEmp = $vEmps[0];
                $morningPos = $vPos->first();

                VendorShift::firstOrCreate(
                    ['tenant_id' => $tenantId, 'vendor_id' => $vendor->id, 'festival_edition_id' => $edition->id, 'started_at' => "{$day} 10:00:00"],
                    [
                        'vendor_employee_id' => $morningEmp->id,
                        'vendor_pos_device_id' => $morningPos->id,
                        'ended_at' => "{$day} 18:00:00",
                        'status' => 'completed',
                        'sales_count' => mt_rand(10, 40),
                        'sales_total_cents' => mt_rand(30000, 150000),
                    ]
                );

                // Evening shift
                $eveningEmp = $vEmps[1] ?? $vEmps[0];
                $eveningPos = $vPos->count() > 1 ? $vPos[1] : $vPos->first();

                VendorShift::firstOrCreate(
                    ['tenant_id' => $tenantId, 'vendor_id' => $vendor->id, 'festival_edition_id' => $edition->id, 'started_at' => "{$day} 18:00:00"],
                    [
                        'vendor_employee_id' => $eveningEmp->id,
                        'vendor_pos_device_id' => $eveningPos->id,
                        'ended_at' => Carbon::parse($day)->addDay()->format('Y-m-d') . ' 04:00:00',
                        'status' => 'completed',
                        'sales_count' => mt_rand(20, 60),
                        'sales_total_cents' => mt_rand(50000, 250000),
                    ]
                );
            }
        }
    }

    public function cleanup(): void
    {
        $tenantId = $this->parent->tenantId;
        $edition = \App\Models\FestivalEdition::where('tenant_id', $tenantId)->where('slug', 'demo-alpha-fest-2026')->first();
        if (!$edition) return;

        $vendorIds = \App\Models\Vendor::where('tenant_id', $tenantId)->where('slug', 'like', 'demo-%')->pluck('id');

        VendorShift::where('festival_edition_id', $edition->id)->whereIn('vendor_id', $vendorIds)->delete();
        VendorFinanceSummary::where('festival_edition_id', $edition->id)->whereIn('vendor_id', $vendorIds)->delete();
        FinanceFeeRule::where('festival_edition_id', $edition->id)->where('name', 'like', 'Demo %')->delete();
    }
}
