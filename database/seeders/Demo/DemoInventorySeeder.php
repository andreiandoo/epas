<?php

namespace Database\Seeders\Demo;

use App\Enums\ProductType;
use App\Enums\StockMovementType;
use App\Models\Cashless\InventoryMovement;
use App\Models\Cashless\InventoryStock;
use App\Models\Cashless\SupplierBrand;
use App\Models\Cashless\SupplierProduct;
use App\Models\MerchandiseAllocation;
use App\Models\MerchandiseItem;
use App\Models\MerchandiseSupplier;
use Carbon\Carbon;

class DemoInventorySeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenantId = $this->parent->tenantId;
        $edition = $this->parent->refs['edition'];
        $vendors = $this->parent->refs['vendors'] ?? [];

        // ── Merchandise Suppliers ──
        $supplierFood = MerchandiseSupplier::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Demo Fresh Foods SRL'],
            [
                'company_name' => 'FRESH FOODS SRL', 'cui' => 'DEMO55556666',
                'contact_person' => 'Vasile Popa', 'phone' => '+40 733 111 222',
                'email' => 'demo-comenzi@freshfoods.ro', 'city' => 'Cluj-Napoca',
                'county' => 'Cluj', 'country' => 'RO', 'is_vat_payer' => true,
                'bank_name' => 'BCR', 'iban' => 'RO49RNCB0130DEMO001',
                'status' => 'active',
            ]
        );

        $supplierDrinks = MerchandiseSupplier::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Demo Drinks Distribution SRL'],
            [
                'company_name' => 'DRINKS DISTRIBUTION SRL', 'cui' => 'DEMO66667777',
                'contact_person' => 'Gheorghe Apa', 'phone' => '+40 733 333 444',
                'email' => 'demo-orders@drinksdist.ro', 'city' => 'Bucuresti',
                'county' => 'Bucuresti', 'country' => 'RO', 'is_vat_payer' => true,
                'bank_name' => 'ING Bank', 'iban' => 'RO49INGB0130DEMO002',
                'status' => 'active',
            ]
        );

        // ── Supplier Brands ──
        $brands = [];
        $brandsData = [
            ['supplier' => $supplierDrinks, 'slug' => 'demo-coca-cola', 'name' => 'Coca-Cola', 'cat' => 'beverage'],
            ['supplier' => $supplierDrinks, 'slug' => 'demo-pepsi', 'name' => 'Pepsi', 'cat' => 'beverage'],
            ['supplier' => $supplierDrinks, 'slug' => 'demo-heineken', 'name' => 'Heineken', 'cat' => 'beer'],
            ['supplier' => $supplierDrinks, 'slug' => 'demo-ursus', 'name' => 'Ursus', 'cat' => 'beer'],
        ];
        foreach ($brandsData as $bd) {
            $brands[$bd['slug']] = SupplierBrand::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $bd['slug']],
                ['merchandise_supplier_id' => $bd['supplier']->id, 'name' => $bd['name'], 'category' => $bd['cat'], 'is_active' => true]
            );
        }

        // ── Supplier Products ──
        $supplierProducts = [];
        $spData = [
            ['supplier' => $supplierDrinks, 'brand' => 'demo-coca-cola', 'sku' => 'DEMO-CC330', 'name' => 'Coca-Cola 330ml', 'type' => 'drink', 'price' => 250, 'vat' => 19, 'barcode' => '5449000000001'],
            ['supplier' => $supplierDrinks, 'brand' => 'demo-pepsi', 'sku' => 'DEMO-PP330', 'name' => 'Pepsi 330ml', 'type' => 'drink', 'price' => 240, 'vat' => 19, 'barcode' => '5449000000002'],
            ['supplier' => $supplierDrinks, 'brand' => 'demo-heineken', 'sku' => 'DEMO-HK500', 'name' => 'Heineken 500ml', 'type' => 'alcohol', 'price' => 450, 'vat' => 19, 'barcode' => '8714800000001', 'age' => true],
            ['supplier' => $supplierDrinks, 'brand' => 'demo-ursus', 'sku' => 'DEMO-UR500', 'name' => 'Ursus 500ml', 'type' => 'alcohol', 'price' => 350, 'vat' => 19, 'barcode' => '5942000000001', 'age' => true],
            ['supplier' => $supplierDrinks, 'brand' => null, 'sku' => 'DEMO-BORSEC500', 'name' => 'Apa Borsec 500ml', 'type' => 'drink', 'price' => 150, 'vat' => 9, 'barcode' => '5944000000001'],
            ['supplier' => $supplierDrinks, 'brand' => null, 'sku' => 'DEMO-BUCOV500', 'name' => 'Apa Bucovina 500ml', 'type' => 'drink', 'price' => 140, 'vat' => 9, 'barcode' => '5944000000002'],
            ['supplier' => $supplierFood, 'brand' => null, 'sku' => 'DEMO-KETCHUP', 'name' => 'Ketchup portie', 'type' => 'food', 'price' => 50, 'vat' => 19, 'barcode' => null],
            ['supplier' => $supplierFood, 'brand' => null, 'sku' => 'DEMO-MUSTAR', 'name' => 'Mustar portie', 'type' => 'food', 'price' => 50, 'vat' => 19, 'barcode' => null],
            ['supplier' => $supplierFood, 'brand' => null, 'sku' => 'DEMO-CHIFLA', 'name' => 'Chifla burger', 'type' => 'food', 'price' => 120, 'vat' => 9, 'barcode' => null],
            ['supplier' => $supplierFood, 'brand' => null, 'sku' => 'DEMO-CARNE', 'name' => 'Carne burger 200g', 'type' => 'food', 'price' => 800, 'vat' => 9, 'barcode' => null],
        ];

        foreach ($spData as $sp) {
            $brand = $sp['brand'] ? ($brands[$sp['brand']] ?? null) : null;
            $vatPrice = (int) round($sp['price'] * (1 + $sp['vat'] / 100));

            $supplierProducts[$sp['sku']] = SupplierProduct::updateOrCreate(
                ['tenant_id' => $tenantId, 'sku' => $sp['sku']],
                [
                    'merchandise_supplier_id' => $sp['supplier']->id,
                    'supplier_brand_id' => $brand?->id,
                    'festival_edition_id' => $edition->id,
                    'name' => $sp['name'],
                    'type' => $sp['type'],
                    'unit_measure' => 'buc',
                    'base_price_cents' => $sp['price'],
                    'vat_rate' => $sp['vat'],
                    'price_with_vat_cents' => $vatPrice,
                    'barcode' => $sp['barcode'],
                    'is_age_restricted' => $sp['age'] ?? false,
                    'min_age' => ($sp['age'] ?? false) ? 18 : 0,
                    'is_active' => true,
                ]
            );
        }

        // ── Inventory Stocks (festival-level) ──
        foreach ($supplierProducts as $sku => $sp) {
            $stock = InventoryStock::firstOrCreate(
                ['festival_edition_id' => $edition->id, 'supplier_product_id' => $sp->id, 'vendor_id' => null],
                [
                    'tenant_id' => $tenantId,
                    'quantity_total' => mt_rand(200, 1000),
                    'quantity_allocated' => mt_rand(50, 200),
                    'quantity_sold' => mt_rand(20, 100),
                    'quantity_returned' => 0,
                    'quantity_wasted' => mt_rand(0, 10),
                    'unit_measure' => 'buc',
                    'last_movement_at' => Carbon::parse('2026-07-15 08:00:00'),
                ]
            );

            // Delivery movement
            InventoryMovement::firstOrCreate(
                ['tenant_id' => $tenantId, 'inventory_stock_id' => $stock->id, 'reference' => "DEMO-DEL-{$sku}"],
                [
                    'festival_edition_id' => $edition->id,
                    'supplier_product_id' => $sp->id,
                    'movement_type' => StockMovementType::Delivery,
                    'quantity' => $stock->quantity_total,
                    'unit_measure' => 'buc',
                    'notes' => 'Livrare initiala festival',
                    'performed_by' => 'demo-admin',
                    'performed_at' => Carbon::parse('2026-07-14 10:00:00'),
                ]
            );
        }

        // ── Merchandise Items (consumables) ──
        $merchSupplier = MerchandiseSupplier::firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'Demo Ambalaje Eco SRL'],
            [
                'cui' => 'DEMO44556677', 'contact_person' => 'Vasile Popa',
                'phone' => '+40 733 111 222', 'email' => 'demo-comenzi@ambalaje-eco.ro',
                'status' => 'active',
            ]
        );

        $merchData = [
            ['name' => 'Demo Pahar personalizat 500ml', 'type' => 'consumable', 'unit' => 'buc', 'qty' => 5000, 'price' => 350, 'inv' => 'DEMO-FE-001'],
            ['name' => 'Demo Pahar shot 50ml', 'type' => 'consumable', 'unit' => 'buc', 'qty' => 3000, 'price' => 150, 'inv' => 'DEMO-FE-001'],
            ['name' => 'Demo Farfurie carton', 'type' => 'packaging', 'unit' => 'buc', 'qty' => 10000, 'price' => 80, 'inv' => 'DEMO-FE-002'],
            ['name' => 'Demo Tacamuri biodegradabile', 'type' => 'consumable', 'unit' => 'set', 'qty' => 8000, 'price' => 120, 'inv' => 'DEMO-FE-002'],
            ['name' => 'Demo Servetele pachet 100buc', 'type' => 'consumable', 'unit' => 'buc', 'qty' => 500, 'price' => 450, 'inv' => 'DEMO-FE-003'],
        ];

        $merchItems = [];
        foreach ($merchData as $md) {
            $merchItems[] = MerchandiseItem::firstOrCreate(
                ['tenant_id' => $tenantId, 'festival_edition_id' => $edition->id, 'name' => $md['name']],
                [
                    'merchandise_supplier_id' => $merchSupplier->id,
                    'type' => $md['type'], 'unit' => $md['unit'],
                    'quantity' => $md['qty'], 'acquisition_price_cents' => $md['price'],
                    'currency' => 'RON', 'vat_rate' => 19, 'invoice_number' => $md['inv'],
                    'invoice_date' => '2026-07-01',
                ]
            );
        }

        // ── Merchandise Allocations ──
        if (count($vendors) >= 3 && count($merchItems) >= 5) {
            // Farfurii → Burger Brothers
            MerchandiseAllocation::firstOrCreate(
                ['merchandise_item_id' => $merchItems[2]->id, 'vendor_id' => $vendors[0]->id],
                ['tenant_id' => $tenantId, 'festival_edition_id' => $edition->id, 'quantity_allocated' => 4000, 'quantity_returned' => 0, 'allocated_at' => '2026-07-14 10:00:00', 'status' => 'allocated']
            );
            // Pahare → Cocktail Paradise
            MerchandiseAllocation::firstOrCreate(
                ['merchandise_item_id' => $merchItems[0]->id, 'vendor_id' => $vendors[1]->id],
                ['tenant_id' => $tenantId, 'festival_edition_id' => $edition->id, 'quantity_allocated' => 2500, 'quantity_returned' => 0, 'allocated_at' => '2026-07-14 10:00:00', 'status' => 'allocated']
            );
            // Pahare shot → Cocktail Paradise
            MerchandiseAllocation::firstOrCreate(
                ['merchandise_item_id' => $merchItems[1]->id, 'vendor_id' => $vendors[1]->id],
                ['tenant_id' => $tenantId, 'festival_edition_id' => $edition->id, 'quantity_allocated' => 1500, 'quantity_returned' => 0, 'allocated_at' => '2026-07-14 10:00:00', 'status' => 'allocated']
            );
            // Servetele → Merch
            MerchandiseAllocation::firstOrCreate(
                ['merchandise_item_id' => $merchItems[4]->id, 'vendor_id' => $vendors[2]->id],
                ['tenant_id' => $tenantId, 'festival_edition_id' => $edition->id, 'quantity_allocated' => 200, 'quantity_returned' => 0, 'allocated_at' => '2026-07-14 10:00:00', 'status' => 'allocated']
            );
        }
    }

    public function cleanup(): void
    {
        $tenantId = $this->parent->tenantId;
        $edition = \App\Models\FestivalEdition::where('tenant_id', $tenantId)->where('slug', 'demo-alpha-fest-2026')->first();

        if ($edition) {
            $stockIds = InventoryStock::where('festival_edition_id', $edition->id)->pluck('id');
            InventoryMovement::where('tenant_id', $tenantId)->where('reference', 'like', 'DEMO-%')->delete();
            InventoryStock::whereIn('id', $stockIds)->delete();
            SupplierProduct::where('tenant_id', $tenantId)->where('sku', 'like', 'DEMO-%')->delete();
        }

        SupplierBrand::where('tenant_id', $tenantId)->where('slug', 'like', 'demo-%')->delete();

        $merchItems = MerchandiseItem::where('tenant_id', $tenantId)->where('name', 'like', 'Demo %')->pluck('id');
        MerchandiseAllocation::whereIn('merchandise_item_id', $merchItems)->delete();
        MerchandiseItem::whereIn('id', $merchItems)->delete();
        MerchandiseSupplier::where('tenant_id', $tenantId)->where('name', 'like', 'Demo %')->delete();
    }
}
