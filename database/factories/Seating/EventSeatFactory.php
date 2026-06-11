<?php

namespace Database\Factories\Seating;

use App\Models\Seating\EventSeat;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventSeatFactory extends Factory
{
    protected $model = EventSeat::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'event_seating_id' => \App\Models\Seating\EventSeatingLayout::factory(),
            'seat_uid' => 'SEAT-' . $this->faker->unique()->numerify('####'),
            'section_code' => $this->faker->randomElement(['A', 'B', 'C', 'VIP']),
            'row_label' => 'Row ' . $this->faker->numberBetween(1, 20),
            'seat_number' => (string) $this->faker->numberBetween(1, 50),
            'price_tier_id' => null,
            'price_cents_override' => null,
            'status' => 'available',
            'version' => 1,
            'last_change_at' => now(),
            'order_reference' => null,
        ];
    }

    public function held(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'held',
            'version' => $attributes['version'] + 1,
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'sold',
            'order_reference' => 'ORDER-' . $this->faker->numerify('######'),
            'version' => $attributes['version'] + 1,
        ]);
    }
}
