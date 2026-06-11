<?php

namespace Database\Factories;

use App\Models\FestivalEdition;
use App\Models\MerchandiseItem;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MerchandiseItemFactory extends Factory
{
    protected $model = MerchandiseItem::class;

    public function definition(): array
    {
        return [
            'tenant_id'               => Tenant::factory(),
            'festival_edition_id'     => FestivalEdition::factory(),
            'name'                    => fake()->randomElement([
                'Pahar personalizat 500ml', 'Pahar shot 50ml', 'Farfurie carton',
                'Tacamuri biodegradabile set', 'Servetele pachet', 'Sac gunoi 120L',
                'Manusi latex cutie', 'Sort bucatar', 'Prosop hartie rola',
            ]),
            'type'                    => fake()->randomElement(['consumable', 'equipment', 'packaging']),
            'unit'                    => 'buc',
            'quantity'                => fake()->numberBetween(100, 10000),
            'acquisition_price_cents' => fake()->numberBetween(50, 2000),
            'currency'                => 'RON',
            'vat_rate'                => 19,
        ];
    }
}
