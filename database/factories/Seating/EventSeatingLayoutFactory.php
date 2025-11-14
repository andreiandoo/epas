<?php

namespace Database\Factories\Seating;

use App\Models\Seating\EventSeatingLayout;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventSeatingLayoutFactory extends Factory
{
    protected $model = EventSeatingLayout::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'event_id' => \App\Models\Event::factory(),
            'seating_layout_id' => \App\Models\Seating\SeatingLayout::factory(),
            'status' => 'draft',
            'geometry' => [
                'canvas' => [
                    'width' => 800,
                    'height' => 600,
                ],
                'sections' => [],
            ],
            'published_at' => null,
            'archived_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
