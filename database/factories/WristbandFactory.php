<?php

namespace Database\Factories;

use App\Models\FestivalEdition;
use App\Models\Tenant;
use App\Models\Wristband;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WristbandFactory extends Factory
{
    protected $model = Wristband::class;

    public function definition(): array
    {
        return [
            'tenant_id'            => Tenant::factory(),
            'festival_edition_id'  => FestivalEdition::factory(),
            'uid'                  => strtoupper(Str::random(12)),
            'wristband_type'       => fake()->randomElement(['nfc', 'qr', 'rfid']),
            'status'               => 'unassigned',
            'balance_cents'        => 0,
            'currency'             => 'RON',
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status'       => 'active',
            'activated_at' => now(),
            'balance_cents' => fake()->numberBetween(0, 50000),
        ]);
    }
}
