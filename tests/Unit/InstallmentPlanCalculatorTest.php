<?php

namespace Tests\Unit;

use App\Models\InstallmentPlan;
use App\Services\Installments\InstallmentPlanCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class InstallmentPlanCalculatorTest extends TestCase
{
    private function calc(): InstallmentPlanCalculator
    {
        return new InstallmentPlanCalculator();
    }

    private function plan(array $attrs = []): InstallmentPlan
    {
        return new InstallmentPlan(array_merge([
            'plan_type' => 'installments',
            'currency' => 'RON',
            'number_of_installments' => 3,
            'schedule_type' => 'interval',
            'interval_unit' => 'month',
            'interval_count' => 1,
            'distribution' => 'equal',
            'surcharge_percent' => 500,       // 5%
            'surcharge_fixed_cents' => 0,
            'days_before_event_fully_paid' => 1,
            'compress_schedule' => false,
            'max_duration_days' => 90,
        ], $attrs));
    }

    public function test_equal_split_sums_exactly_and_total_exceeds_direct(): void
    {
        // base 100.00 RON = 10000 bani; a value that doesn't divide evenly.
        $q = $this->calc()->quote($this->plan(), 10000, [
            'down_payment_type' => 'percent',
            'down_payment_value' => 2500, // 25%
            'start_date' => '2026-01-01',
            'event_start_date' => '2026-12-01',
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertTrue($q['eligible'], $q['reason'] ?? '');
        $this->assertSame(500, $q['surcharge_cents']);              // 5% of 10000
        $this->assertSame(10500, $q['customer_total_cents']);        // base + surcharge
        $this->assertGreaterThan($q['base_total_cents'], $q['customer_total_cents']);
        $this->assertSame(200, $q['platform_fee_cents']);            // 2% of 10000

        // down payment 25% of 10500 = 2625; financed = 7875
        $this->assertSame(2625, $q['down_payment_cents']);
        $this->assertSame(7875, $q['financed_cents']);

        // installments (exclude sequence 0) sum to financed exactly
        $instalments = array_filter($q['schedule'], fn ($r) => $r['sequence'] > 0);
        $this->assertSame(7875, array_sum(array_column($instalments, 'amount_cents')));
        $this->assertSame(3, count($instalments));

        // whole schedule (down + installments) sums to customer_total
        $this->assertSame(10500, array_sum(array_column($q['schedule'], 'amount_cents')));
    }

    public function test_last_payment_is_at_least_one_day_before_event(): void
    {
        $q = $this->calc()->quote($this->plan(['number_of_installments' => 3]), 30000, [
            'down_payment_type' => 'none',
            'start_date' => '2026-01-01',
            'event_start_date' => '2026-06-10',
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertTrue($q['eligible'], $q['reason'] ?? '');
        $last = end($q['schedule']);
        $this->assertTrue(
            Carbon::parse($last['due_date'])->lte(Carbon::parse('2026-06-09')),
            'Last payment must be at least one day before the event'
        );
    }

    public function test_ineligible_when_event_too_soon_without_compression(): void
    {
        // 3 monthly installments but event is in 20 days → does not fit.
        $q = $this->calc()->quote($this->plan(['number_of_installments' => 3]), 30000, [
            'start_date' => '2026-01-01',
            'event_start_date' => '2026-01-20',
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertFalse($q['eligible']);
        $this->assertSame('does_not_fit', $q['reason']);
    }

    public function test_compression_fits_before_deadline(): void
    {
        $q = $this->calc()->quote($this->plan([
            'number_of_installments' => 3,
            'compress_schedule' => true,
        ]), 30000, [
            'start_date' => '2026-01-01',
            'event_start_date' => '2026-02-01',
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertTrue($q['eligible'], $q['reason'] ?? '');
        $last = end($q['schedule']);
        $this->assertTrue(Carbon::parse($last['due_date'])->lte(Carbon::parse('2026-01-31')));
    }

    public function test_no_surcharge_is_ineligible(): void
    {
        $q = $this->calc()->quote($this->plan([
            'surcharge_percent' => 0,
            'surcharge_fixed_cents' => 0,
        ]), 10000, ['platform_fee_percent' => 2.0]);

        $this->assertFalse($q['eligible']);
        $this->assertSame('no_surcharge', $q['reason']);
    }

    public function test_custom_percent_split_sums_exactly(): void
    {
        $q = $this->calc()->quote($this->plan([
            'number_of_installments' => 3,
            'distribution' => 'custom_percent',
            'installments_percentages' => [50, 30, 20],
        ]), 10000, [
            'down_payment_type' => 'none',
            'start_date' => '2026-01-01',
            'event_start_date' => '2026-12-01',
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertTrue($q['eligible'], $q['reason'] ?? '');
        $instalments = array_filter($q['schedule'], fn ($r) => $r['sequence'] > 0);
        $this->assertSame($q['financed_cents'], array_sum(array_column($instalments, 'amount_cents')));
    }

    public function test_bnpl_single_payment_within_horizon(): void
    {
        $q = $this->calc()->quote($this->plan([
            'plan_type' => 'bnpl_single',
            'number_of_installments' => 1,
            'surcharge_fixed_cents' => 300,
            'surcharge_percent' => 0,
        ]), 20000, [
            'down_payment_type' => 'none',
            'start_date' => '2026-01-01',
            'event_start_date' => '2026-12-01',
            'bnpl_max_horizon_days' => 30,
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertTrue($q['eligible'], $q['reason'] ?? '');
        $this->assertSame(300, $q['surcharge_cents']);
        $this->assertSame(20300, $q['customer_total_cents']);
        $instalments = array_filter($q['schedule'], fn ($r) => $r['sequence'] > 0);
        $this->assertCount(1, $instalments);
        $this->assertSame(20300, array_sum(array_column($instalments, 'amount_cents')));
        $this->assertSame('2026-01-31', end($q['schedule'])['due_date']); // start + 30d
    }
}
