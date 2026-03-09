<?php

namespace Database\Factories;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FestivalEditionFactory extends Factory
{
    protected $model = FestivalEdition::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2027);
        $name = fake()->words(2, true) . ' Festival ' . $year;
        $start = fake()->dateTimeBetween($year . '-06-01', $year . '-08-31');

        return [
            'tenant_id'      => Tenant::factory(),
            'name'           => $name,
            'slug'           => Str::slug($name),
            'year'           => $year,
            'edition_number' => fake()->numberBetween(1, 15),
            'start_date'     => $start,
            'end_date'       => (clone $start)->modify('+' . fake()->numberBetween(2, 5) . ' days'),
            'status'         => 'draft',
            'currency'       => 'RON',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed']);
    }
}
