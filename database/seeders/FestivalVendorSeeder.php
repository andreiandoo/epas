<?php

namespace Database\Seeders;

use App\Models\FestivalEdition;
use App\Models\MerchandiseAllocation;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseSupplier;
use App\Models\Tenant;
use App\Models\Vendor;
use App\Models\VendorEdition;
use App\Models\VendorEmployee;
use App\Models\VendorPosDevice;
use App\Models\VendorProductCategory;
use App\Models\VendorProduct;
use App\Models\Wristband;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FestivalVendorSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'alpha-events'],
            [
                'name'     => 'Alpha Events',
                'domain'   => 'alpha.local',
                'status'   => 'active',
                'plan'     => 'A',
                'settings' => [],
            ]
        );

        // ── Festival Edition ──

        $edition = FestivalEdition::firstOrCreate(
            ['tenant_id' => $tenant->id, 'slug' => 'alpha-fest-2026'],
            [
                'name'           => 'Alpha Fest 2026',
                'year'           => 2026,
                'edition_number' => 3,
                'start_date'     => '2026-07-15',
                'end_date'       => '2026-07-19',
                'status'         => 'active',
                'currency'       => 'RON',
            ]
        );

        // ── Vendors (societati) ──

        $vendorsData = [
            [
                'name'          => 'Burger Brothers',
                'email'         => 'contact@burgerbrothers.ro',
                'company_name'  => 'BURGER BROTHERS SRL',
                'cui'           => '12345678',
                'reg_com'       => 'J12/1234/2018',
                'cod_caen'      => '5610',
                'fiscal_name'   => 'BURGER BROTHERS SRL',
                'fiscal_address'=> 'Str. Libertatii nr. 10, Cluj-Napoca',
                'county'        => 'Cluj',
                'city'          => 'Cluj-Napoca',
                'is_vat_payer'  => true,
                'vat_since'     => '2019-01-01',
                'is_active_fiscal' => true,
                'bank_name'     => 'Banca Transilvania',
                'iban'          => 'RO49BTRL01301205N12345XX',
                'contact_person'=> 'Ion Popescu',
                'phone'         => '+40 744 123 456',
                'vendor_type'   => 'food',
                'commission'    => 15,
            ],
            [
                'name'          => 'Cocktail Lab',
                'email'         => 'office@cocktaillab.ro',
                'company_name'  => 'COCKTAIL LAB SRL-D',
                'cui'           => '23456789',
                'reg_com'       => 'J40/5678/2020',
                'cod_caen'      => '5630',
                'fiscal_name'   => 'COCKTAIL LAB SRL-D',
                'fiscal_address'=> 'Bd. Unirii nr. 25, Bucuresti Sector 3',
                'county'        => 'Bucuresti',
                'city'          => 'Bucuresti Sector 3',
                'is_vat_payer'  => false,
                'is_active_fiscal' => true,
                'bank_name'     => 'ING Bank',
                'iban'          => 'RO49INGB01301205N67890XX',
                'contact_person'=> 'Maria Ionescu',
                'phone'         => '+40 755 234 567',
                'vendor_type'   => 'drink',
                'commission'    => 12,
            ],
            [
                'name'          => 'Festival Merch Store',
                'email'         => 'merch@alphafest.ro',
                'company_name'  => 'ALPHA MERCH SRL',
                'cui'           => '34567890',
                'reg_com'       => 'J12/9012/2022',
                'cod_caen'      => '4771',
                'fiscal_name'   => 'ALPHA MERCHANDISE SRL',
                'fiscal_address'=> 'Str. Fabricii nr. 5, Cluj-Napoca',
                'county'        => 'Cluj',
                'city'          => 'Cluj-Napoca',
                'is_vat_payer'  => true,
                'vat_since'     => '2022-06-01',
                'is_active_fiscal' => true,
                'bank_name'     => 'BCR',
                'iban'          => 'RO49RNCB01301205N11111XX',
                'contact_person'=> 'Andrei Marin',
                'phone'         => '+40 766 345 678',
                'vendor_type'   => 'merch',
                'commission'    => 10,
            ],
        ];

        $vendors = [];
        foreach ($vendorsData as $vd) {
            $vendorType = $vd['vendor_type'];
            $commission = $vd['commission'];
            unset($vd['vendor_type'], $vd['commission']);

            $slug = Str::slug($vd['name']);
            $vendor = Vendor::firstOrCreate(
                ['tenant_id' => $tenant->id, 'slug' => $slug],
                array_merge($vd, [
                    'tenant_id' => $tenant->id,
                    'slug'      => $slug,
                    'password'  => bcrypt('vendor123'),
                    'status'    => 'active',
                ])
            );

            VendorEdition::firstOrCreate(
                ['vendor_id' => $vendor->id, 'festival_edition_id' => $edition->id],
                [
                    'vendor_type'     => $vendorType,
                    'commission_rate' => $commission,
                    'commission_mode' => 'percentage',
                    'location'        => 'Zona ' . strtoupper(chr(64 + count($vendors) + 1)) . ', Stand ' . (count($vendors) + 1),
                    'status'          => 'confirmed',
                ]
            );

            $vendors[] = $vendor;
        }

        // ── Wristbands ──

        for ($i = 1; $i <= 10; $i++) {
            Wristband::firstOrCreate(
                ['tenant_id' => $tenant->id, 'uid' => sprintf('WB-AF26-%04d', $i)],
                [
                    'festival_edition_id' => $edition->id,
                    'wristband_type'      => 'nfc',
                    'status'              => $i <= 5 ? 'active' : 'unassigned',
                    'balance_cents'       => $i <= 5 ? rand(5000, 30000) : 0,
                    'currency'            => 'RON',
                    'activated_at'        => $i <= 5 ? now() : null,
                ]
            );
        }

        // ── Merchandise — marfa importata de festival ──

        $supplier = MerchandiseSupplier::firstOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Ambalaje Eco SRL'],
            [
                'cui'            => '44556677',
                'contact_person' => 'Vasile Popa',
                'phone'          => '+40 733 111 222',
                'email'          => 'comenzi@ambalaje-eco.ro',
            ]
        );

        $merchandiseData = [
            ['name' => 'Pahar personalizat 500ml', 'type' => 'consumable', 'unit' => 'buc', 'quantity' => 5000,  'price' => 350, 'invoice' => 'FE-2026-001'],
            ['name' => 'Pahar shot 50ml',           'type' => 'consumable', 'unit' => 'buc', 'quantity' => 3000,  'price' => 150, 'invoice' => 'FE-2026-001'],
            ['name' => 'Farfurie carton',           'type' => 'packaging',  'unit' => 'buc', 'quantity' => 10000, 'price' => 80,  'invoice' => 'FE-2026-002'],
            ['name' => 'Tacamuri biodegradabile',   'type' => 'consumable', 'unit' => 'set', 'quantity' => 8000,  'price' => 120, 'invoice' => 'FE-2026-002'],
            ['name' => 'Servetele pachet 100buc',   'type' => 'consumable', 'unit' => 'buc', 'quantity' => 500,   'price' => 450, 'invoice' => 'FE-2026-003'],
        ];

        $items = [];
        foreach ($merchandiseData as $md) {
            $item = MerchandiseItem::firstOrCreate(
                ['tenant_id' => $tenant->id, 'festival_edition_id' => $edition->id, 'name' => $md['name']],
                [
                    'merchandise_supplier_id' => $supplier->id,
                    'type'                    => $md['type'],
                    'unit'                    => $md['unit'],
                    'quantity'                => $md['quantity'],
                    'acquisition_price_cents' => $md['price'],
                    'currency'                => 'RON',
                    'vat_rate'                => 19,
                    'invoice_number'          => $md['invoice'],
                    'invoice_date'            => '2026-07-01',
                ]
            );
            $items[] = $item;
        }

        // ── Alocari marfa catre vendori ──

        // Burger Brothers primeste farfurii, tacamuri, servetele
        MerchandiseAllocation::firstOrCreate(
            ['merchandise_item_id' => $items[2]->id, 'vendor_id' => $vendors[0]->id],
            [
                'tenant_id'          => $tenant->id,
                'festival_edition_id'=> $edition->id,
                'quantity_allocated'  => 4000,
                'quantity_returned'   => 0,
                'allocated_at'       => '2026-07-14 10:00:00',
                'status'             => 'allocated',
            ]
        );
        MerchandiseAllocation::firstOrCreate(
            ['merchandise_item_id' => $items[3]->id, 'vendor_id' => $vendors[0]->id],
            [
                'tenant_id'          => $tenant->id,
                'festival_edition_id'=> $edition->id,
                'quantity_allocated'  => 3000,
                'quantity_returned'   => 0,
                'allocated_at'       => '2026-07-14 10:00:00',
                'status'             => 'allocated',
            ]
        );

        // Cocktail Lab primeste pahare
        MerchandiseAllocation::firstOrCreate(
            ['merchandise_item_id' => $items[0]->id, 'vendor_id' => $vendors[1]->id],
            [
                'tenant_id'          => $tenant->id,
                'festival_edition_id'=> $edition->id,
                'quantity_allocated'  => 2500,
                'quantity_returned'   => 0,
                'allocated_at'       => '2026-07-14 10:00:00',
                'status'             => 'allocated',
            ]
        );
        MerchandiseAllocation::firstOrCreate(
            ['merchandise_item_id' => $items[1]->id, 'vendor_id' => $vendors[1]->id],
            [
                'tenant_id'          => $tenant->id,
                'festival_edition_id'=> $edition->id,
                'quantity_allocated'  => 1500,
                'quantity_returned'   => 0,
                'allocated_at'       => '2026-07-14 10:00:00',
                'status'             => 'allocated',
            ]
        );

        // Merch Store primeste servetele
        MerchandiseAllocation::firstOrCreate(
            ['merchandise_item_id' => $items[4]->id, 'vendor_id' => $vendors[2]->id],
            [
                'tenant_id'          => $tenant->id,
                'festival_edition_id'=> $edition->id,
                'quantity_allocated'  => 200,
                'quantity_returned'   => 0,
                'allocated_at'       => '2026-07-14 10:00:00',
                'status'             => 'allocated',
            ]
        );

        // ── POS Devices ──

        foreach ($vendors as $i => $vendor) {
            for ($d = 1; $d <= 2; $d++) {
                VendorPosDevice::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'device_uid' => "POS-{$vendor->slug}-{$d}"],
                    [
                        'vendor_id'           => $vendor->id,
                        'festival_edition_id' => $edition->id,
                        'name'                => "POS #{$d} " . $vendor->name,
                        'status'              => 'active',
                    ]
                );
            }
        }

        // ── Angajati vendori ──

        $employeesData = [
            // Burger Brothers
            ['vendor' => 0, 'name' => 'Ion Popescu',    'pin' => '1234', 'role' => 'admin'],
            ['vendor' => 0, 'name' => 'Ana Matei',      'pin' => '1235', 'role' => 'operator'],
            ['vendor' => 0, 'name' => 'Mihai Radu',     'pin' => '1236', 'role' => 'operator'],
            // Cocktail Lab
            ['vendor' => 1, 'name' => 'Maria Ionescu',  'pin' => '2234', 'role' => 'admin'],
            ['vendor' => 1, 'name' => 'Alex Dumitru',   'pin' => '2235', 'role' => 'operator'],
            ['vendor' => 1, 'name' => 'Elena Vasile',   'pin' => '2236', 'role' => 'operator'],
            ['vendor' => 1, 'name' => 'Cosmin Barbu',   'pin' => '2237', 'role' => 'operator'],
            // Merch Store
            ['vendor' => 2, 'name' => 'Andrei Marin',   'pin' => '3234', 'role' => 'admin'],
            ['vendor' => 2, 'name' => 'Diana Stan',     'pin' => '3235', 'role' => 'operator'],
        ];

        foreach ($employeesData as $ed) {
            $v = $vendors[$ed['vendor']];
            VendorEmployee::firstOrCreate(
                ['vendor_id' => $v->id, 'pin' => $ed['pin']],
                [
                    'tenant_id' => $tenant->id,
                    'name'      => $ed['name'],
                    'role'      => $ed['role'],
                    'status'    => 'active',
                    'permissions' => $ed['role'] === 'operator'
                        ? ['sell', 'refund']
                        : null,
                ]
            );
        }

        // ── Categorii produse cu subcategorii ──

        // Burger Brothers
        $catBurgeri = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[0]->id, 'festival_edition_id' => $edition->id, 'slug' => 'burgeri'],
            ['name' => 'Burgeri', 'sort_order' => 1, 'icon' => 'fire', 'color' => '#e74c3c']
        );
        $catGarnituri = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[0]->id, 'festival_edition_id' => $edition->id, 'slug' => 'garnituri'],
            ['name' => 'Garnituri', 'sort_order' => 2, 'icon' => 'star', 'color' => '#f39c12']
        );
        $catBauturi = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[0]->id, 'festival_edition_id' => $edition->id, 'slug' => 'bauturi-bb'],
            ['name' => 'Bauturi', 'sort_order' => 3, 'icon' => 'cup', 'color' => '#3498db']
        );
        // Subcategorie: Burgeri → Clasici, Premium
        $subClasic = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[0]->id, 'festival_edition_id' => $edition->id, 'slug' => 'burgeri-clasici'],
            ['name' => 'Clasici', 'parent_id' => $catBurgeri->id, 'sort_order' => 1]
        );
        $subPremium = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[0]->id, 'festival_edition_id' => $edition->id, 'slug' => 'burgeri-premium'],
            ['name' => 'Premium', 'parent_id' => $catBurgeri->id, 'sort_order' => 2]
        );

        // Produse Burger Brothers
        $burgerProducts = [
            ['cat' => $subClasic->id,  'name' => 'Classic Burger',    'price' => 2500, 'tags' => []],
            ['cat' => $subClasic->id,  'name' => 'Cheeseburger',      'price' => 2800, 'tags' => []],
            ['cat' => $subPremium->id, 'name' => 'Truffle Burger',    'price' => 4500, 'tags' => ['premium']],
            ['cat' => $subPremium->id, 'name' => 'Wagyu Smash',       'price' => 5500, 'tags' => ['premium']],
            ['cat' => $catGarnituri->id, 'name' => 'Cartofi prajiti', 'price' => 1200, 'tags' => ['vegan']],
            ['cat' => $catGarnituri->id, 'name' => 'Coleslaw',        'price' => 800,  'tags' => ['vegan']],
            ['cat' => $catBauturi->id, 'name' => 'Cola 330ml',        'price' => 800,  'tags' => []],
            ['cat' => $catBauturi->id, 'name' => 'Apa plata 500ml',   'price' => 500,  'tags' => []],
        ];

        foreach ($burgerProducts as $idx => $p) {
            VendorProduct::firstOrCreate(
                ['vendor_id' => $vendors[0]->id, 'festival_edition_id' => $edition->id, 'slug' => Str::slug($p['name'])],
                [
                    'vendor_product_category_id' => $p['cat'],
                    'name'         => $p['name'],
                    'price_cents'  => $p['price'],
                    'currency'     => 'RON',
                    'is_available' => true,
                    'sort_order'   => $idx + 1,
                    'tags'         => $p['tags'],
                ]
            );
        }

        // Cocktail Lab — categorii
        $catCocktails = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[1]->id, 'festival_edition_id' => $edition->id, 'slug' => 'cocktails'],
            ['name' => 'Cocktails', 'sort_order' => 1, 'icon' => 'beaker', 'color' => '#9b59b6']
        );
        $catShots = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[1]->id, 'festival_edition_id' => $edition->id, 'slug' => 'shots'],
            ['name' => 'Shots', 'sort_order' => 2, 'icon' => 'bolt', 'color' => '#e67e22']
        );
        $catNonAlc = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[1]->id, 'festival_edition_id' => $edition->id, 'slug' => 'non-alcoolice'],
            ['name' => 'Non-alcoolice', 'sort_order' => 3, 'icon' => 'heart', 'color' => '#2ecc71']
        );

        $drinkProducts = [
            ['cat' => $catCocktails->id, 'name' => 'Mojito',          'price' => 3500],
            ['cat' => $catCocktails->id, 'name' => 'Aperol Spritz',   'price' => 3200],
            ['cat' => $catCocktails->id, 'name' => 'Long Island',     'price' => 4000],
            ['cat' => $catShots->id,     'name' => 'Tequila Shot',    'price' => 1500],
            ['cat' => $catShots->id,     'name' => 'Jagermeister',    'price' => 1800],
            ['cat' => $catNonAlc->id,    'name' => 'Limonada fresh',  'price' => 1500],
            ['cat' => $catNonAlc->id,    'name' => 'Virgin Mojito',   'price' => 2000],
        ];

        foreach ($drinkProducts as $idx => $p) {
            VendorProduct::firstOrCreate(
                ['vendor_id' => $vendors[1]->id, 'festival_edition_id' => $edition->id, 'slug' => Str::slug($p['name'])],
                [
                    'vendor_product_category_id' => $p['cat'],
                    'name'         => $p['name'],
                    'price_cents'  => $p['price'],
                    'currency'     => 'RON',
                    'is_available' => true,
                    'sort_order'   => $idx + 1,
                ]
            );
        }

        // Merch Store — categorii
        $catTricouri = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[2]->id, 'festival_edition_id' => $edition->id, 'slug' => 'tricouri'],
            ['name' => 'Tricouri', 'sort_order' => 1, 'icon' => 'tag', 'color' => '#1abc9c']
        );
        $catAccesorii = VendorProductCategory::firstOrCreate(
            ['vendor_id' => $vendors[2]->id, 'festival_edition_id' => $edition->id, 'slug' => 'accesorii'],
            ['name' => 'Accesorii', 'sort_order' => 2, 'icon' => 'sparkles', 'color' => '#e91e63']
        );

        $merchProducts = [
            ['cat' => $catTricouri->id,  'name' => 'Tricou Alpha Fest 2026',    'price' => 8000],
            ['cat' => $catTricouri->id,  'name' => 'Tricou Line-up',            'price' => 9000],
            ['cat' => $catAccesorii->id, 'name' => 'Sapca Alpha Fest',          'price' => 4500],
            ['cat' => $catAccesorii->id, 'name' => 'Breloc Alpha Fest',         'price' => 1500],
            ['cat' => $catAccesorii->id, 'name' => 'Rucsac festival',           'price' => 12000],
        ];

        foreach ($merchProducts as $idx => $p) {
            VendorProduct::firstOrCreate(
                ['vendor_id' => $vendors[2]->id, 'festival_edition_id' => $edition->id, 'slug' => Str::slug($p['name'])],
                [
                    'vendor_product_category_id' => $p['cat'],
                    'name'         => $p['name'],
                    'price_cents'  => $p['price'],
                    'currency'     => 'RON',
                    'is_available' => true,
                    'sort_order'   => $idx + 1,
                ]
            );
        }
    }
}
