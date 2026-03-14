<?php

namespace Database\Factories;

use App\Models\Vendor;
use App\Models\VendorEmployee;
use Illuminate\Database\Eloquent\Factories\Factory;

class VendorEmployeeFactory extends Factory
{
    protected $model = VendorEmployee::class;

    public function definition(): array
    {
        return [
            'vendor_id'  => Vendor::factory(),
            'tenant_id'  => fn (array $attrs) => Vendor::find($attrs['vendor_id'])?->tenant_id,
            'name'       => fake()->name(),
            'phone'      => fake()->phoneNumber(),
            'email'      => fake()->safeEmail(),
            'pin'        => (string) fake()->unique()->numberBetween(1000, 9999),
            'role'       => fake()->randomElement(['admin', 'operator', 'operator', 'operator']),
            'status'     => 'active',
            'permissions'=> ['sell', 'refund'],
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role'        => 'admin',
            'permissions' => null,
        ]);
    }
}
