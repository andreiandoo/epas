<?php

namespace Tests\Unit\Leisure;

use App\Models\TicketType;
use App\Services\Leisure\RentalService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for pure-function logic in RentalService:
 *   - computeOvertimeMinutes
 *   - computeSurchargeCents
 * Tests the I/O behaviour without touching the DB.
 */
class RentalServiceTest extends TestCase
{
    private RentalService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new RentalService();
    }

    private function ticketTypeWithSurcharge(int $surchargeCents, int $intervalMin): TicketType
    {
        $tt = new TicketType();
        $tt->setRawAttributes([
            'leisure_is_overtime_chargeable' => true,
            'leisure_overtime_surcharge_cents' => $surchargeCents,
            'leisure_overtime_interval_minutes' => $intervalMin,
        ], true);
        return $tt;
    }

    public function test_overtime_minutes_zero_when_on_time(): void
    {
        $planned = Carbon::parse('2026-07-15 10:00');
        $ended = Carbon::parse('2026-07-15 10:00');
        $this->assertSame(0, $this->svc->computeOvertimeMinutes($planned, $ended));
    }

    public function test_overtime_minutes_zero_when_returned_early(): void
    {
        $planned = Carbon::parse('2026-07-15 10:00');
        $ended = Carbon::parse('2026-07-15 09:50');
        $this->assertSame(0, $this->svc->computeOvertimeMinutes($planned, $ended));
    }

    public function test_overtime_minutes_correct_when_overdue(): void
    {
        $planned = Carbon::parse('2026-07-15 10:00');
        $ended = Carbon::parse('2026-07-15 10:30');
        $this->assertSame(30, $this->svc->computeOvertimeMinutes($planned, $ended));
    }

    public function test_overtime_handles_nulls(): void
    {
        $this->assertSame(0, $this->svc->computeOvertimeMinutes(null, Carbon::now()));
        $this->assertSame(0, $this->svc->computeOvertimeMinutes(Carbon::now(), null));
    }

    public function test_surcharge_zero_when_ticket_type_null(): void
    {
        $this->assertSame(0, $this->svc->computeSurchargeCents(null, 30));
    }

    public function test_surcharge_zero_when_not_chargeable(): void
    {
        $tt = new TicketType();
        $tt->setRawAttributes(['leisure_is_overtime_chargeable' => false], true);
        $this->assertSame(0, $this->svc->computeSurchargeCents($tt, 30));
    }

    public function test_surcharge_zero_when_no_overtime(): void
    {
        $tt = $this->ticketTypeWithSurcharge(500, 30);
        $this->assertSame(0, $this->svc->computeSurchargeCents($tt, 0));
    }

    public function test_surcharge_single_interval(): void
    {
        // 30 min overtime, interval 30 min, 5 RON → 5 RON
        $tt = $this->ticketTypeWithSurcharge(500, 30);
        $this->assertSame(500, $this->svc->computeSurchargeCents($tt, 30));
    }

    public function test_surcharge_partial_interval_rounds_up(): void
    {
        // 1 min overtime, interval 30 min, 5 RON → still 5 RON (ceil 1 interval)
        $tt = $this->ticketTypeWithSurcharge(500, 30);
        $this->assertSame(500, $this->svc->computeSurchargeCents($tt, 1));
    }

    public function test_surcharge_multiple_intervals(): void
    {
        // 45 min overtime, interval 30 min, 5 RON → 2 intervals = 10 RON
        $tt = $this->ticketTypeWithSurcharge(500, 30);
        $this->assertSame(1000, $this->svc->computeSurchargeCents($tt, 45));
    }

    public function test_surcharge_full_two_intervals(): void
    {
        // 60 min overtime, interval 30 min, 5 RON → 2 intervals = 10 RON
        $tt = $this->ticketTypeWithSurcharge(500, 30);
        $this->assertSame(1000, $this->svc->computeSurchargeCents($tt, 60));
    }

    public function test_surcharge_zero_interval_returns_zero(): void
    {
        $tt = $this->ticketTypeWithSurcharge(500, 0);
        $this->assertSame(0, $this->svc->computeSurchargeCents($tt, 30));
    }

    public function test_surcharge_zero_per_interval_returns_zero(): void
    {
        $tt = $this->ticketTypeWithSurcharge(0, 30);
        $this->assertSame(0, $this->svc->computeSurchargeCents($tt, 30));
    }
}
