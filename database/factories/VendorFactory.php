<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        $name = fake()->company();

        return [
            'tenant_id'        => Tenant::factory(),
            'name'             => $name,
            'slug'             => Str::slug($name),
            'email'            => fake()->unique()->safeEmail(),
            'password'         => 'password',
            'phone'            => fake()->phoneNumber(),
            'company_name'     => $name . ' SRL',
            'cui'              => (string) fake()->numberBetween(10000000, 49999999),
            'reg_com'          => 'J' . fake()->numberBetween(1, 42) . '/' . fake()->numberBetween(100, 9999) . '/' . fake()->numberBetween(2015, 2025),
            'contact_person'   => fake()->name(),
            'status'           => 'active',
            'is_vat_payer'     => fake()->boolean(60),
            'is_active_fiscal' => true,
            'is_split_vat'     => false,
        ];
    }

    public function vatPayer(): static
    {
        return $this->state(fn () => [
            'is_vat_payer' => true,
            'vat_since'    => fake()->dateTimeBetween('-5 years', '-1 year'),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }
}
