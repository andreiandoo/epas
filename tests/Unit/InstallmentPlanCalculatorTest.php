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

    public function test_fit_to_event_spreads_installments_to_the_window(): void
    {
        $plan = $this->plan(['schedule_type' => 'fit_to_event', 'number_of_installments' => 4]);

        // Event 3 months out → installments spread across ~3 months.
        $far = $this->calc()->quote($plan, 40000, [
            'down_payment_type' => 'percent', 'down_payment_value' => 2000,
            'start_date' => '2026-08-01', 'event_start_date' => '2026-11-01',
            'platform_fee_percent' => 2.0,
        ]);
        $this->assertTrue($far['eligible'], $far['reason'] ?? '');
        $lastFar = end($far['schedule']);
        $this->assertTrue(Carbon::parse($lastFar['due_date'])->lte(Carbon::parse('2026-10-31')));

        // Same plan, event only ~3 weeks out → still 4 installments, just closer
        // together, and still finishing before the event.
        $near = $this->calc()->quote($plan, 40000, [
            'down_payment_type' => 'percent', 'down_payment_value' => 2000,
            'start_date' => '2026-08-01', 'event_start_date' => '2026-08-22',
            'platform_fee_percent' => 2.0,
        ]);
        $this->assertTrue($near['eligible'], $near['reason'] ?? '');
        $nearInst = array_values(array_filter($near['schedule'], fn ($r) => $r['sequence'] > 0));
        $this->assertCount(4, $nearInst);
        $this->assertTrue(Carbon::parse(end($nearInst)['due_date'])->lte(Carbon::parse('2026-08-21')));
        // amounts still sum to financed exactly
        $this->assertSame($near['financed_cents'], array_sum(array_column($nearInst, 'amount_cents')));
    }

    public function test_fit_to_event_caps_far_events_at_three_months(): void
    {
        // Event 6 months out: the plan must NOT stretch installments to 6 months
        // (that would breach the 3-month cap) — it spreads over ~3 months instead.
        $plan = $this->plan(['schedule_type' => 'fit_to_event', 'number_of_installments' => 3]);
        $q = $this->calc()->quote($plan, 30000, [
            'down_payment_type' => 'none',
            'start_date' => '2026-08-01', 'event_start_date' => '2027-02-01', // ~6 months
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertTrue($q['eligible'], $q['reason'] ?? '');
        $last = end($q['schedule']);
        // Last installment within ~3 months of purchase, not near the event.
        $this->assertTrue(Carbon::parse($last['due_date'])->lte(Carbon::parse('2026-08-01')->addDays(93)));
        $this->assertTrue(Carbon::parse($last['due_date'])->lt(Carbon::parse('2026-12-01')));
    }

    public function test_fit_to_event_rejected_when_event_is_immediate(): void
    {
        $plan = $this->plan(['schedule_type' => 'fit_to_event', 'number_of_installments' => 4]);
        $q = $this->calc()->quote($plan, 40000, [
            'start_date' => '2026-08-01', 'event_start_date' => '2026-08-03', // 2 days, 4 rates impossible
            'platform_fee_percent' => 2.0,
        ]);
        $this->assertFalse($q['eligible']);
        $this->assertSame('event_too_soon', $q['reason']);
    }

    public function test_custom_schedule_offsets_and_percentages(): void
    {
        // "After the down payment, 60% at +30 days from the down payment, then
        // 40% at +14 days from the previous installment."
        $plan = $this->plan([
            'schedule_type' => 'custom',
            'custom_schedule' => [
                ['offset_days' => 30, 'offset_from' => 'start', 'percent' => 60],
                ['offset_days' => 14, 'offset_from' => 'previous', 'percent' => 40],
            ],
        ]);

        $q = $this->calc()->quote($plan, 40000, [
            'down_payment_type' => 'percent',
            'down_payment_value' => 2500, // 25%
            'start_date' => '2026-08-01',
            'event_start_date' => '2026-12-01',
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertTrue($q['eligible'], $q['reason'] ?? '');
        $this->assertSame(2, $q['number_of_installments']);

        $installments = array_values(array_filter($q['schedule'], fn ($r) => $r['sequence'] > 0));
        // dates: +30d from start, then +14d from the previous
        $this->assertSame('2026-08-31', $installments[0]['due_date']);
        $this->assertSame('2026-09-14', $installments[1]['due_date']);
        // amounts: 60% / 40% of financed, summing to financed exactly
        $financed = $q['financed_cents'];
        $this->assertSame((int) floor($financed * 60 / 100), $installments[0]['amount_cents']);
        $this->assertSame($financed, array_sum(array_column($installments, 'amount_cents')));
    }

    public function test_custom_schedule_rejected_when_it_overruns_the_event(): void
    {
        $plan = $this->plan([
            'schedule_type' => 'custom',
            'custom_schedule' => [
                ['offset_days' => 30, 'offset_from' => 'start', 'percent' => 50],
                ['offset_days' => 30, 'offset_from' => 'previous', 'percent' => 50],
            ],
        ]);

        $q = $this->calc()->quote($plan, 40000, [
            'start_date' => '2026-08-01',
            'event_start_date' => '2026-08-20', // event before the 2nd installment
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertFalse($q['eligible']);
        $this->assertSame('does_not_fit', $q['reason']);
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

    public function test_bnpl_horizon_shrinks_dynamically_for_a_near_event(): void
    {
        // Event only 12 days out: the 30-day BNPL horizon must shrink so the
        // single charge lands before the event (deadline = event − 1 day), not
        // at the blind day+30 (which would be AFTER the event).
        $bnpl = $this->plan([
            'plan_type' => 'bnpl_single',
            'number_of_installments' => 1,
            'surcharge_fixed_cents' => 300,
            'surcharge_percent' => 0,
        ]);
        $q = $this->calc()->quote($bnpl, 20000, [
            'down_payment_type' => 'none',
            'start_date' => '2026-01-01',
            'event_start_date' => '2026-01-13', // 12 days out
            'bnpl_max_horizon_days' => 30,
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertTrue($q['eligible'], $q['reason'] ?? '');
        // deadline = event − 1 day = 2026-01-12; charge clamped to it.
        $this->assertSame('2026-01-12', end($q['schedule'])['due_date']);
    }

    public function test_bnpl_ineligible_when_event_is_within_min_horizon(): void
    {
        $bnpl = $this->plan([
            'plan_type' => 'bnpl_single',
            'number_of_installments' => 1,
            'surcharge_fixed_cents' => 300,
            'surcharge_percent' => 0,
        ]);
        // Event tomorrow → deadline is today → no runway to defer the charge.
        $q = $this->calc()->quote($bnpl, 20000, [
            'down_payment_type' => 'none',
            'start_date' => '2026-01-01',
            'event_start_date' => '2026-01-02', // 1 day out, deadline = today
            'bnpl_max_horizon_days' => 30,
            'bnpl_min_horizon_days' => 1,
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertFalse($q['eligible']);
        $this->assertSame('event_too_soon', $q['reason']);
    }

    public function test_bnpl_respects_a_configured_horizon_above_thirty(): void
    {
        // A marketplace configured a 45-day BNPL ceiling; for a far event the
        // charge should be at day+45, proving the old hardcoded 30 cap is gone.
        $bnpl = $this->plan([
            'plan_type' => 'bnpl_single',
            'number_of_installments' => 1,
            'surcharge_fixed_cents' => 300,
            'surcharge_percent' => 0,
        ]);
        $q = $this->calc()->quote($bnpl, 20000, [
            'down_payment_type' => 'none',
            'start_date' => '2026-01-01',
            'event_start_date' => '2026-12-01',
            'bnpl_max_horizon_days' => 45,
            'platform_fee_percent' => 2.0,
        ]);

        $this->assertTrue($q['eligible'], $q['reason'] ?? '');
        $this->assertSame('2026-02-15', end($q['schedule'])['due_date']); // start + 45d
    }
}
