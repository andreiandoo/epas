<?php

namespace Database\Factories\Seating;

use App\Models\Seating\PriceTier;
use Illuminate\Database\Eloquent\Factories\Factory;

class PriceTierFactory extends Factory
{
    protected $model = PriceTier::class;

    public function definition(): array
    {
        $names = ['Standard', 'VIP', 'Premium', 'Balcony', 'Orchestra'];
        $codes = ['STD', 'VIP', 'PREM', 'BAL', 'ORCH'];

        $index = array_rand($names);

        return [
            'tenant_id' => 1,
            'name' => $names[$index],
            'tier_code' => $codes[$index] . '-' . $this->faker->unique()->numberBetween(1, 999),
            'price_cents' => $this->faker->numberBetween(2000, 20000),
            'color' => $this->faker->hexColor(),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 10),
        ];
    }
}
