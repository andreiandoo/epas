<?php

namespace App\Services\Installments;

use App\Models\InstallmentPlan;
use Carbon\Carbon;

/**
 * Pure, stateless, cents-integer calculator for a flexible-payment plan.
 *
 * Given a base total (what the customer would pay directly), a plan, and an
 * event context, it produces the full quote: money breakdown + schedule, and
 * whether the plan is eligible (fits before the event, within limits, and
 * strictly more expensive than paying directly).
 *
 * No DB writes, no side effects. Deterministic rounding (floor/intdiv on
 * cents; the rounding remainder always lands on the LAST installment so the
 * schedule sums back to `financed_cents` exactly).
 */
class InstallmentPlanCalculator
{
    /**
     * @param array $ctx
     *   - down_payment_type: 'none'|'percent'|'fixed'
     *   - down_payment_value: int (percent*100 for percent, cents for fixed)
     *   - event_start_date: Carbon|string|null
     *   - platform_fee_percent: float (e.g. 2.0)
     *   - start_date: Carbon|string|null (checkout date; defaults to today)
     *   - bnpl_max_horizon_days: int|null (event override; else plan/30)
     *
     * @return array{
     *   eligible: bool, reason: string|null, base_total_cents: int,
     *   surcharge_cents: int, customer_total_cents: int, platform_fee_cents: int,
     *   platform_fee_percent: float, down_payment_cents: int, financed_cents: int,
     *   number_of_installments: int, schedule: array, total_extra_vs_direct_cents: int
     * }
     */
    public function quote(InstallmentPlan $plan, int $baseTotalCents, array $ctx = []): array
    {
        $start = $this->toDate($ctx['start_date'] ?? null) ?? Carbon::today();
        $eventStart = $this->toDate($ctx['event_start_date'] ?? null);
        $platformFeePercent = (float) ($ctx['platform_fee_percent'] ?? 2.0);

        // --- Money -----------------------------------------------------------
        $surcharge = (int) floor($baseTotalCents * $plan->surcharge_percent / 10000)
            + (int) $plan->surcharge_fixed_cents;
        $customerTotal = $baseTotalCents + $surcharge;
        $platformFee = (int) ceil($baseTotalCents * $platformFeePercent / 100);

        $result = [
            'eligible' => false,
            'reason' => null,
            'base_total_cents' => $baseTotalCents,
            'surcharge_cents' => $surcharge,
            'customer_total_cents' => $customerTotal,
            'platform_fee_cents' => $platformFee,
            'platform_fee_percent' => $platformFeePercent,
            'down_payment_cents' => 0,
            'financed_cents' => 0,
            'number_of_installments' => (int) $plan->number_of_installments,
            'schedule' => [],
            'total_extra_vs_direct_cents' => $surcharge,
        ];

        // --- Hard guarantees -------------------------------------------------
        if ($surcharge <= 0) {
            return $this->ineligible($result, 'no_surcharge'); // total must exceed direct
        }
        if ($baseTotalCents <= 0) {
            return $this->ineligible($result, 'invalid_amount');
        }
        if ($plan->min_order_cents !== null && $baseTotalCents < $plan->min_order_cents) {
            return $this->ineligible($result, 'below_min');
        }
        if ($plan->max_order_cents !== null && $baseTotalCents > $plan->max_order_cents) {
            return $this->ineligible($result, 'above_max');
        }

        // --- Down payment ----------------------------------------------------
        $downType = $ctx['down_payment_type'] ?? 'none';
        $downValue = (int) ($ctx['down_payment_value'] ?? 0);
        $downPayment = match ($downType) {
            'percent' => (int) floor($customerTotal * $downValue / 10000),
            'fixed' => $downValue,
            default => 0,
        };
        $downPayment = max(0, min($downPayment, $customerTotal));
        $financed = $customerTotal - $downPayment;

        $result['down_payment_cents'] = $downPayment;
        $result['financed_cents'] = $financed;

        // --- Deadline (never on event day) -----------------------------------
        $deadline = $eventStart
            ? $eventStart->copy()->subDays($plan->daysBeforeEvent())->startOfDay()
            : null;

        // --- Schedule dates --------------------------------------------------
        $n = max(1, (int) $plan->number_of_installments);
        if ($plan->isBnpl()) {
            $n = 1;
            $horizon = (int) ($ctx['bnpl_max_horizon_days'] ?? 30);
            $due = $start->copy()->addDays(min($horizon, 30));
            if ($deadline && $due->gt($deadline)) {
                $due = $deadline->copy();
            }
            if ($deadline && $due->lt($start)) {
                return $this->ineligible($result, 'event_too_soon');
            }
            $dueDates = [$due];
        } else {
            $dueDates = $this->installmentDueDates($plan, $start, $deadline, $n, $result);
            if ($dueDates === null) {
                return $this->ineligible($result, $result['reason'] ?? 'does_not_fit');
            }
        }

        // --- Max duration cap (≤ 3 months) -----------------------------------
        $lastDue = end($dueDates);
        if ($start->diffInDays($lastDue) > (int) $plan->max_duration_days) {
            return $this->ineligible($result, 'exceeds_max_duration');
        }

        // --- Amounts (deterministic; remainder on last) ----------------------
        $amounts = $this->splitAmounts($financed, $n, $plan);

        $schedule = [];
        // sequence 0 = down payment (charged at checkout)
        if ($downPayment > 0) {
            $schedule[] = [
                'sequence' => 0,
                'due_date' => $start->toDateString(),
                'amount_cents' => $downPayment,
                'is_down_payment' => true,
            ];
        }
        foreach ($dueDates as $i => $date) {
            $schedule[] = [
                'sequence' => $i + 1,
                'due_date' => $date->toDateString(),
                'amount_cents' => $amounts[$i],
                'is_down_payment' => false,
            ];
        }

        $result['eligible'] = true;
        $result['number_of_installments'] = $n;
        $result['schedule'] = $schedule;

        return $result;
    }

    /**
     * @return Carbon[]|null null when the schedule cannot fit before the deadline.
     */
    protected function installmentDueDates(InstallmentPlan $plan, Carbon $start, ?Carbon $deadline, int $n, array &$result): ?array
    {
        if ($plan->schedule_type === 'fixed_dates') {
            $dates = collect($plan->fixed_dates ?? [])
                ->map(fn ($d) => $this->toDate($d))
                ->filter()
                ->values();
            if ($dates->count() < $n) {
                $result['reason'] = 'insufficient_fixed_dates';
                return null;
            }
            $dueDates = $dates->take($n)->all();
            if ($deadline && end($dueDates)->gt($deadline)) {
                $result['reason'] = 'does_not_fit';
                return null;
            }
            return array_values($dueDates);
        }

        // interval mode
        $unit = $plan->interval_unit;
        $count = max(1, (int) $plan->interval_count);
        $dueDates = [];
        for ($i = 1; $i <= $n; $i++) {
            $dueDates[] = $this->addInterval($start, $unit, $count * $i);
        }

        if ($deadline && end($dueDates)->gt($deadline)) {
            if (! $plan->compress_schedule) {
                $result['reason'] = 'does_not_fit';
                return null;
            }
            // Compress: distribute N payments evenly across [start, deadline].
            $daysAvailable = $start->diffInDays($deadline);
            $step = intdiv($daysAvailable, $n);
            if ($step < 1) {
                $result['reason'] = 'event_too_soon';
                return null;
            }
            $dueDates = [];
            for ($i = 1; $i <= $n; $i++) {
                $dueDates[] = $start->copy()->addDays($step * $i);
            }
        }

        return $dueDates;
    }

    /**
     * @return int[] amounts per installment summing to $financed exactly.
     */
    protected function splitAmounts(int $financed, int $n, InstallmentPlan $plan): array
    {
        $amounts = [];
        if ($plan->distribution === 'custom_percent' && is_array($plan->installments_percentages)) {
            $pcts = array_slice($plan->installments_percentages, 0, $n);
            $sum = 0;
            foreach ($pcts as $pct) {
                $amt = (int) floor($financed * $pct / 100);
                $amounts[] = $amt;
                $sum += $amt;
            }
            while (count($amounts) < $n) {
                $amounts[] = 0;
            }
            // remainder on last
            $amounts[$n - 1] += $financed - array_sum($amounts);
            return $amounts;
        }

        // equal
        $each = intdiv($financed, $n);
        for ($i = 0; $i < $n; $i++) {
            $amounts[] = $each;
        }
        $amounts[$n - 1] += $financed - ($each * $n);
        return $amounts;
    }

    protected function addInterval(Carbon $start, string $unit, int $steps): Carbon
    {
        return match ($unit) {
            'day' => $start->copy()->addDays($steps),
            'week' => $start->copy()->addWeeks($steps),
            default => $start->copy()->addMonthsNoOverflow($steps),
        };
    }

    protected function toDate($value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->startOfDay();
        }
        if (is_string($value) && $value !== '') {
            return Carbon::parse($value)->startOfDay();
        }
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }
        return null;
    }

    protected function ineligible(array $result, string $reason): array
    {
        $result['eligible'] = false;
        $result['reason'] = $reason;
        return $result;
    }
}
