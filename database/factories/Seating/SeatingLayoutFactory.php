<?php

namespace Database\Factories\Seating;

use App\Models\Seating\SeatingLayout;
use Illuminate\Database\Eloquent\Factories\Factory;

class SeatingLayoutFactory extends Factory
{
    protected $model = SeatingLayout::class;

    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'venue_id' => \App\Models\Venue::factory(),
            'name' => $this->faker->words(3, true) . ' Layout',
            'description' => $this->faker->optional()->sentence(),
            'status' => 'draft',
            'canvas_width' => 800,
            'canvas_height' => 600,
            'background_image_path' => null,
            'version' => 1,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
        ]);
    }
}
