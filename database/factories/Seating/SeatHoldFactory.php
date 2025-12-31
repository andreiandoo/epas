<?php

namespace Database\Factories\Seating;

use App\Models\Seating\SeatHold;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeatHoldFactory extends Factory
{
    protected $model = SeatHold::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'event_seating_id' => \App\Models\Seating\EventSeatingLayout::factory(),
            'seat_uid' => 'SEAT-' . $this->faker->unique()->numerify('####'),
            'session_uid' => 'sess_' . $this->faker->unique()->uuid(),
            'expires_at' => now()->addMinutes(10),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subMinutes(1),
        ]);
    }
}
