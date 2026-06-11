<?php

namespace Database\Seeders\Demo;

use App\Models\Cashless\VendorStand;
use App\Models\Vendor;
use App\Models\VendorEdition;
use App\Models\VendorEmployee;
use App\Models\VendorPosDevice;
use App\Models\VendorProduct;
use App\Models\VendorProductCategory;
use Illuminate\Support\Str;

class DemoVendorSeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenantId = $this->parent->tenantId;
        $edition = $this->parent->refs['edition'];

        $vendorsData = [
            [
                'slug' => 'demo-burger-brothers', 'name' => 'Burger Brothers',
                'email' => 'demo-contact@burgerbrothers.ro', 'company_name' => 'BURGER BROTHERS SRL',
                'cui' => 'DEMO12345678', 'reg_com' => 'J12/1234/2018', 'cod_caen' => '5610',
                'fiscal_name' => 'BURGER BROTHERS SRL', 'fiscal_address' => 'Str. Libertatii nr. 10, Cluj-Napoca',
                'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'is_vat_payer' => true, 'vat_since' => '2019-01-01',
                'is_active_fiscal' => true, 'bank_name' => 'Banca Transilvania', 'iban' => 'RO49BTRL01301205NDEMO01',
                'contact_person' => 'Ion Popescu', 'phone' => '+40 744 123 456',
                'vendor_type' => 'food', 'commission' => 15.00, 'zone' => 'A',
            ],
            [
                'slug' => 'demo-cocktail-paradise', 'name' => 'Cocktail Paradise',
                'email' => 'demo-office@cocktailparadise.ro', 'company_name' => 'COCKTAIL PARADISE SRL-D',
                'cui' => 'DEMO23456789', 'reg_com' => 'J40/5678/2020', 'cod_caen' => '5630',
                'fiscal_name' => 'COCKTAIL PARADISE SRL-D', 'fiscal_address' => 'Bd. Unirii nr. 25, Bucuresti',
                'county' => 'Bucuresti', 'city' => 'Bucuresti', 'is_vat_payer' => false,
                'is_active_fiscal' => true, 'bank_name' => 'ING Bank', 'iban' => 'RO49INGB01301205NDEMO02',
                'contact_person' => 'Maria Ionescu', 'phone' => '+40 755 234 567',
                'vendor_type' => 'drink', 'commission' => 12.00, 'zone' => 'B',
            ],
            [
                'slug' => 'demo-festival-merch', 'name' => 'Festival Merch Official',
                'email' => 'demo-merch@alphafest.ro', 'company_name' => 'ALPHA MERCH SRL',
                'cui' => 'DEMO34567890', 'reg_com' => 'J12/9012/2022', 'cod_caen' => '4771',
                'fiscal_name' => 'ALPHA MERCHANDISE SRL', 'fiscal_address' => 'Str. Fabricii nr. 5, Cluj-Napoca',
                'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'is_vat_payer' => true, 'vat_since' => '2022-06-01',
                'is_active_fiscal' => true, 'bank_name' => 'BCR', 'iban' => 'RO49RNCB01301205NDEMO03',
                'contact_person' => 'Andrei Marin', 'phone' => '+40 766 345 678',
                'vendor_type' => 'merch', 'commission' => 10.00, 'zone' => 'C',
            ],
            [
                'slug' => 'demo-coffee-snacks', 'name' => 'Coffee & Snacks Corner',
                'email' => 'demo-office@coffeesnacks.ro', 'company_name' => 'COFFEE SNACKS SRL',
                'cui' => 'DEMO45678901', 'reg_com' => 'J12/3456/2021', 'cod_caen' => '5610',
                'fiscal_name' => 'COFFEE SNACKS SRL', 'fiscal_address' => 'Str. Dorobantilor nr. 15, Cluj-Napoca',
                'county' => 'Cluj', 'city' => 'Cluj-Napoca', 'is_vat_payer' => true, 'vat_since' => '2021-03-01',
                'is_active_fiscal' => true, 'bank_name' => 'Banca Transilvania', 'iban' => 'RO49BTRL01301205NDEMO04',
                'contact_person' => 'Catalina Moldovan', 'phone' => '+40 744 456 789',
                'vendor_type' => 'food', 'commission' => 15.00, 'zone' => 'A',
            ],
        ];

        $vendors = [];
        foreach ($vendorsData as $idx => $vd) {
            $vendorType = $vd['vendor_type'];
            $commission = $vd['commission'];
            $zone = $vd['zone'];
            $slug = $vd['slug'];
            unset($vd['vendor_type'], $vd['commission'], $vd['zone'], $vd['slug']);

            $vendor = Vendor::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $slug],
                array_merge($vd, [
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'password' => bcrypt('vendor123'),
                    'status' => 'active',
                ])
            );

            VendorEdition::firstOrCreate(
                ['vendor_id' => $vendor->id, 'festival_edition_id' => $edition->id],
                [
                    'vendor_type' => $vendorType,
                    'commission_rate' => $commission,
                    'commission_mode' => 'percentage',
                    'location' => 'Zona ' . $zone . ', Stand ' . ($idx + 1),
                    'status' => 'confirmed',
                ]
            );

            VendorStand::firstOrCreate(
                ['festival_edition_id' => $edition->id, 'slug' => $slug . '-stand'],
                [
                    'tenant_id' => $tenantId,
                    'vendor_id' => $vendor->id,
                    'name' => $vendor->name . ' - Stand',
                    'location' => 'Zona ' . $zone . ', Stand ' . ($idx + 1),
                    'zone' => strtolower($zone),
                    'status' => 'active',
                    'operating_hours' => ['open' => '12:00', 'close' => '04:00'],
                ]
            );

            // POS Devices
            for ($d = 1; $d <= 2; $d++) {
                VendorPosDevice::firstOrCreate(
                    ['tenant_id' => $tenantId, 'device_uid' => "POS-{$slug}-{$d}"],
                    [
                        'vendor_id' => $vendor->id,
                        'festival_edition_id' => $edition->id,
                        'name' => "POS #{$d} " . $vendor->name,
                        'status' => 'active',
                    ]
                );
            }

            $vendors[] = $vendor;
        }
        $this->parent->refs['vendors'] = $vendors;

        // ── Employees ──
        $employeesData = [
            [0, 'Demo Ion Popescu', '9901', 'admin', ['sell', 'refund', 'void', 'reports']],
            [0, 'Demo Ana Matei', '9902', 'operator', ['sell', 'refund']],
            [0, 'Demo Mihai Radu', '9903', 'operator', ['sell']],
            [1, 'Demo Maria Ionescu', '9911', 'admin', ['sell', 'refund', 'void', 'reports']],
            [1, 'Demo Alex Dumitru', '9912', 'operator', ['sell', 'refund']],
            [1, 'Demo Elena Vasile', '9913', 'operator', ['sell']],
            [2, 'Demo Andrei Marin', '9921', 'admin', ['sell', 'refund', 'void', 'reports']],
            [2, 'Demo Diana Stan', '9922', 'operator', ['sell', 'refund']],
            [3, 'Demo Catalina Moldovan', '9931', 'admin', ['sell', 'refund', 'void', 'reports']],
            [3, 'Demo Rares Bogdan', '9932', 'operator', ['sell', 'refund']],
            [3, 'Demo Oana Ilie', '9933', 'operator', ['sell']],
        ];

        $employees = [];
        foreach ($employeesData as $ed) {
            $emp = VendorEmployee::firstOrCreate(
                ['vendor_id' => $vendors[$ed[0]]->id, 'pin' => $ed[2]],
                [
                    'tenant_id' => $tenantId,
                    'name' => $ed[1],
                    'full_name' => $ed[1],
                    'role' => $ed[3],
                    'status' => 'active',
                    'permissions' => $ed[4],
                ]
            );
            $employees[] = $emp;
        }
        $this->parent->refs['employees'] = $employees;

        // ── Product Categories + Products ──
        $this->seedProducts($vendors, $edition);
    }

    protected function seedProducts(array $vendors, $edition): void
    {
        $tenantId = $this->parent->tenantId;
        $allProducts = [];

        // Vendor 0: Burger Brothers
        $catBurgeri = $this->cat($vendors[0], $edition, 'demo-burgeri', 'Burgeri', 1, '#e74c3c');
        $catGarnituri = $this->cat($vendors[0], $edition, 'demo-garnituri', 'Garnituri', 2, '#f39c12');
        $catBauturiBB = $this->cat($vendors[0], $edition, 'demo-bauturi-bb', 'Bauturi', 3, '#3498db');

        $allProducts = array_merge($allProducts, [
            $this->prod($vendors[0], $edition, $catBurgeri, 'demo-classic-burger', 'Classic Burger', 2500, 'food'),
            $this->prod($vendors[0], $edition, $catBurgeri, 'demo-cheeseburger', 'Cheeseburger', 2800, 'food'),
            $this->prod($vendors[0], $edition, $catBurgeri, 'demo-truffle-burger', 'Truffle Burger', 4500, 'food', ['premium']),
            $this->prod($vendors[0], $edition, $catBurgeri, 'demo-wagyu-smash', 'Wagyu Smash', 5500, 'food', ['premium']),
            $this->prod($vendors[0], $edition, $catGarnituri, 'demo-cartofi-prajiti', 'Cartofi prajiti', 1200, 'food', ['vegan']),
            $this->prod($vendors[0], $edition, $catGarnituri, 'demo-coleslaw', 'Coleslaw', 800, 'food', ['vegan']),
            $this->prod($vendors[0], $edition, $catBauturiBB, 'demo-cola-bb', 'Cola 330ml', 800, 'drink'),
            $this->prod($vendors[0], $edition, $catBauturiBB, 'demo-apa-bb', 'Apa plata 500ml', 500, 'drink'),
        ]);

        // Vendor 1: Cocktail Paradise
        $catCocktails = $this->cat($vendors[1], $edition, 'demo-cocktails', 'Cocktails', 1, '#9b59b6');
        $catBere = $this->cat($vendors[1], $edition, 'demo-bere', 'Bere', 2, '#f39c12');
        $catNonAlc = $this->cat($vendors[1], $edition, 'demo-non-alcoolice', 'Non-alcoolice', 3, '#2ecc71');

        $allProducts = array_merge($allProducts, [
            $this->prod($vendors[1], $edition, $catCocktails, 'demo-mojito', 'Mojito', 3500, 'alcohol', [], true, 18),
            $this->prod($vendors[1], $edition, $catCocktails, 'demo-aperol-spritz', 'Aperol Spritz', 3200, 'alcohol', [], true, 18),
            $this->prod($vendors[1], $edition, $catCocktails, 'demo-long-island', 'Long Island', 4000, 'alcohol', [], true, 18),
            $this->prod($vendors[1], $edition, $catBere, 'demo-heineken-draft', 'Heineken Draft 500ml', 2000, 'alcohol', [], true, 18),
            $this->prod($vendors[1], $edition, $catBere, 'demo-ursus-draft', 'Ursus Draft 500ml', 1500, 'alcohol', [], true, 18),
            $this->prod($vendors[1], $edition, $catNonAlc, 'demo-limonada', 'Limonada fresh', 1500, 'drink'),
            $this->prod($vendors[1], $edition, $catNonAlc, 'demo-virgin-mojito', 'Virgin Mojito', 2000, 'drink'),
            $this->prod($vendors[1], $edition, $catNonAlc, 'demo-apa-cp', 'Apa plata 500ml', 500, 'drink'),
        ]);

        // Vendor 2: Festival Merch
        $catTricouri = $this->cat($vendors[2], $edition, 'demo-tricouri', 'Tricouri', 1, '#1abc9c');
        $catAccesorii = $this->cat($vendors[2], $edition, 'demo-accesorii', 'Accesorii', 2, '#e91e63');

        $allProducts = array_merge($allProducts, [
            $this->prod($vendors[2], $edition, $catTricouri, 'demo-tricou-fest', 'Tricou Alpha Fest 2026', 8000, 'merch'),
            $this->prod($vendors[2], $edition, $catTricouri, 'demo-tricou-lineup', 'Tricou Line-up', 9000, 'merch'),
            $this->prod($vendors[2], $edition, $catAccesorii, 'demo-sapca', 'Sapca Alpha Fest', 4500, 'merch'),
            $this->prod($vendors[2], $edition, $catAccesorii, 'demo-breloc', 'Breloc Alpha Fest', 1500, 'merch'),
            $this->prod($vendors[2], $edition, $catAccesorii, 'demo-rucsac', 'Rucsac festival', 12000, 'merch'),
        ]);

        // Vendor 3: Coffee & Snacks
        $catCafea = $this->cat($vendors[3], $edition, 'demo-cafea', 'Cafea', 1, '#795548');
        $catSnacks = $this->cat($vendors[3], $edition, 'demo-snacks', 'Snacks', 2, '#ff9800');

        $allProducts = array_merge($allProducts, [
            $this->prod($vendors[3], $edition, $catCafea, 'demo-espresso', 'Espresso', 1000, 'drink'),
            $this->prod($vendors[3], $edition, $catCafea, 'demo-cappuccino', 'Cappuccino', 1400, 'drink'),
            $this->prod($vendors[3], $edition, $catCafea, 'demo-latte', 'Caffe Latte', 1600, 'drink'),
            $this->prod($vendors[3], $edition, $catCafea, 'demo-ice-coffee', 'Ice Coffee', 1800, 'drink'),
            $this->prod($vendors[3], $edition, $catSnacks, 'demo-croissant', 'Croissant unt', 1000, 'food'),
            $this->prod($vendors[3], $edition, $catSnacks, 'demo-brownie', 'Brownie ciocolata', 1200, 'food'),
            $this->prod($vendors[3], $edition, $catSnacks, 'demo-pretzel', 'Pretzel sarat', 800, 'food'),
        ]);

        $this->parent->refs['products'] = $allProducts;
    }

    protected function cat($vendor, $edition, string $slug, string $name, int $sort, string $color = '#333'): VendorProductCategory
    {
        return VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendor->id, 'festival_edition_id' => $edition->id, 'slug' => $slug],
            ['name' => $name, 'sort_order' => $sort, 'color' => $color, 'is_active' => true]
        );
    }

    protected function prod($vendor, $edition, $category, string $slug, string $name, int $priceCents, string $type, array $tags = [], bool $ageRestricted = false, ?int $minAge = null): VendorProduct
    {
        return VendorProduct::firstOrCreate(
            ['vendor_id' => $vendor->id, 'festival_edition_id' => $edition->id, 'slug' => $slug],
            [
                'vendor_product_category_id' => $category->id,
                'name' => $name,
                'type' => $type,
                'price_cents' => $priceCents,
                'base_price_cents' => $priceCents,
                'currency' => 'RON',
                'vat_rate' => 19.00,
                'vat_included' => true,
                'is_available' => true,
                'is_age_restricted' => $ageRestricted,
                'min_age' => $minAge ?? 0,
                'sort_order' => 1,
                'tags' => $tags,
                'sgr_cents' => 0,
            ]
        );
    }

    public function cleanup(): void
    {
        $tenantId = $this->parent->tenantId;
        $vendorIds = Vendor::where('tenant_id', $tenantId)->where('slug', 'like', 'demo-%')->pluck('id');

        VendorProduct::whereIn('vendor_id', $vendorIds)->where('slug', 'like', 'demo-%')->delete();
        VendorProductCategory::whereIn('vendor_id', $vendorIds)->where('slug', 'like', 'demo-%')->delete();
        VendorPosDevice::where('tenant_id', $tenantId)->where('device_uid', 'like', 'POS-demo-%')->delete();
        VendorEmployee::where('tenant_id', $tenantId)->whereIn('vendor_id', $vendorIds)->delete();

        $edition = \App\Models\FestivalEdition::where('tenant_id', $tenantId)->where('slug', 'demo-alpha-fest-2026')->first();
        if ($edition) {
            VendorStand::where('festival_edition_id', $edition->id)->where('slug', 'like', 'demo-%')->delete();
            VendorEdition::where('festival_edition_id', $edition->id)->whereIn('vendor_id', $vendorIds)->delete();
        }

        Vendor::whereIn('id', $vendorIds)->delete();
    }
}
