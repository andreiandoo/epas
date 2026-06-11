<?php

namespace Tests\Unit\Leisure;

use App\Models\Leisure\TicketTypeCapacity;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for derived attributes on TicketTypeCapacity (remaining + status).
 * In-memory only — no DB.
 */
class TicketTypeCapacityTest extends TestCase
{
    private function make(array $attrs): TicketTypeCapacity
    {
        $row = new TicketTypeCapacity();
        $row->setRawAttributes(array_merge([
            'capacity' => 100,
            'sold' => 0,
            'reserved' => 0,
            'is_closed' => false,
            'capacity_date' => '2026-07-15',
        ], $attrs), true);
        return $row;
    }

    public function test_remaining_full_capacity(): void
    {
        $row = $this->make(['capacity' => 100, 'sold' => 0, 'reserved' => 0]);
        $this->assertSame(100, $row->remaining);
    }

    public function test_remaining_subtracts_sold_and_reserved(): void
    {
        $row = $this->make(['capacity' => 100, 'sold' => 30, 'reserved' => 10]);
        $this->assertSame(60, $row->remaining);
    }

    public function test_remaining_is_clamped_to_zero(): void
    {
        $row = $this->make(['capacity' => 50, 'sold' => 60, 'reserved' => 5]);
        $this->assertSame(0, $row->remaining);
    }

    public function test_status_available_when_full_pool(): void
    {
        $row = $this->make(['capacity' => 100, 'sold' => 10, 'reserved' => 0]);
        $this->assertSame('available', $row->status);
    }

    public function test_status_limited_under_20_percent(): void
    {
        // 100 - 85 = 15 remaining, threshold 20 → limited
        $row = $this->make(['capacity' => 100, 'sold' => 85, 'reserved' => 0]);
        $this->assertSame('limited', $row->status);
    }

    public function test_status_sold_out_at_zero_remaining(): void
    {
        $row = $this->make(['capacity' => 100, 'sold' => 100, 'reserved' => 0]);
        $this->assertSame('sold_out', $row->status);
    }

    public function test_status_closed_takes_precedence_over_remaining(): void
    {
        $row = $this->make(['capacity' => 100, 'sold' => 0, 'reserved' => 0, 'is_closed' => true]);
        $this->assertSame('closed', $row->status);
    }

    public function test_status_unavailable_when_capacity_is_zero(): void
    {
        $row = $this->make(['capacity' => 0, 'sold' => 0, 'reserved' => 0]);
        $this->assertSame('unavailable', $row->status);
    }
}
