<?php

namespace Database\Seeders\Demo;

use App\Enums\CashlessMode;
use App\Enums\NfcChipType;
use App\Models\FestivalAddon;
use App\Models\FestivalDay;
use App\Models\FestivalEdition;
use App\Models\FestivalPass;

class DemoFestivalEditionSeeder
{
    public function __construct(protected FestivalDemoSeeder $parent) {}

    public function run(): void
    {
        $tenantId = $this->parent->tenantId;
        $event = $this->parent->refs['event'];

        // ── Festival Edition ──
        $edition = FestivalEdition::firstOrCreate(
            ['tenant_id' => $tenantId, 'slug' => 'demo-alpha-fest-2026'],
            [
                'event_id' => $event->id,
                'name' => 'Alpha Fest 2026',
                'year' => 2026,
                'edition_number' => 3,
                'start_date' => '2026-07-15',
                'end_date' => '2026-07-19',
                'status' => 'active',
                'currency' => 'RON',
                'cashless_mode' => CashlessMode::Hybrid,
                'nfc_chip_type' => NfcChipType::DesfireEv3,
            ]
        );
        $this->parent->refs['edition'] = $edition;

        // ── Festival Days ──
        $daysData = [
            ['name' => 'Miercuri - Ziua 1', 'date' => '2026-07-15', 'open' => '14:00', 'close' => '03:00', 'sort' => 1],
            ['name' => 'Joi - Ziua 2', 'date' => '2026-07-16', 'open' => '12:00', 'close' => '04:00', 'sort' => 2],
            ['name' => 'Vineri - Ziua 3', 'date' => '2026-07-17', 'open' => '12:00', 'close' => '05:00', 'sort' => 3],
            ['name' => 'Sambata - Ziua 4', 'date' => '2026-07-18', 'open' => '12:00', 'close' => '05:00', 'sort' => 4],
            ['name' => 'Duminica - Ziua 5', 'date' => '2026-07-19', 'open' => '12:00', 'close' => '00:00', 'sort' => 5],
        ];

        $days = [];
        foreach ($daysData as $d) {
            $day = FestivalDay::firstOrCreate(
                ['festival_edition_id' => $edition->id, 'date' => $d['date']],
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $event->id,
                    'name' => $d['name'],
                    'gates_open' => $d['date'] . ' ' . $d['open'] . ':00',
                    'gates_close' => $d['date'] . ' ' . $d['close'] . ':00',
                    'status' => 'scheduled',
                    'sort_order' => $d['sort'],
                ]
            );
            $days[] = $day;
        }
        $this->parent->refs['days'] = $days;

        // ── Festival Passes ──
        $dayIds = collect($days)->pluck('id')->toArray();

        $passesData = [
            ['slug' => 'demo-full-festival', 'name' => 'Full Festival Pass', 'type' => 'full', 'price' => 20000, 'quota' => 5000, 'sold' => 1847, 'days' => $dayIds, 'perks' => ['Acces toate zilele', 'Acces toate scenele']],
            ['slug' => 'demo-day-1', 'name' => 'Single Day - Miercuri', 'type' => 'single_day', 'price' => 8000, 'quota' => 500, 'sold' => 105, 'days' => [$dayIds[0] ?? null], 'perks' => ['Acces Miercuri']],
            ['slug' => 'demo-day-2', 'name' => 'Single Day - Joi', 'type' => 'single_day', 'price' => 8000, 'quota' => 500, 'sold' => 120, 'days' => [$dayIds[1] ?? null], 'perks' => ['Acces Joi']],
            ['slug' => 'demo-day-3', 'name' => 'Single Day - Vineri', 'type' => 'single_day', 'price' => 8000, 'quota' => 500, 'sold' => 145, 'days' => [$dayIds[2] ?? null], 'perks' => ['Acces Vineri']],
            ['slug' => 'demo-day-4', 'name' => 'Single Day - Sambata', 'type' => 'single_day', 'price' => 8000, 'quota' => 500, 'sold' => 110, 'days' => [$dayIds[3] ?? null], 'perks' => ['Acces Sambata']],
            ['slug' => 'demo-day-5', 'name' => 'Single Day - Duminica', 'type' => 'single_day', 'price' => 8000, 'quota' => 500, 'sold' => 43, 'days' => [$dayIds[4] ?? null], 'perks' => ['Acces Duminica']],
            ['slug' => 'demo-vip-upgrade', 'name' => 'VIP Upgrade', 'type' => 'vip', 'price' => 30000, 'quota' => 500, 'sold' => 187, 'days' => $dayIds, 'perks' => ['VIP Lounge', 'Zona dedicata', 'Toalete VIP', 'Drink bun venit']],
        ];

        $passes = [];
        foreach ($passesData as $i => $p) {
            $pass = FestivalPass::firstOrCreate(
                ['festival_edition_id' => $edition->id, 'slug' => $p['slug']],
                [
                    'tenant_id' => $tenantId,
                    'event_id' => $event->id,
                    'name' => $p['name'],
                    'pass_type' => $p['type'],
                    'price_cents' => $p['price'],
                    'currency' => 'RON',
                    'included_day_ids' => array_filter($p['days']),
                    'quota_total' => $p['quota'],
                    'quota_sold' => $p['sold'],
                    'sales_start_at' => '2026-03-01 00:00:00',
                    'sales_end_at' => '2026-07-15 12:00:00',
                    'status' => 'active',
                    'is_refundable' => true,
                    'sort_order' => $i + 1,
                    'perks' => $p['perks'],
                ]
            );
            $passes[$p['slug']] = $pass;
        }
        $this->parent->refs['passes'] = $passes;

        // ── Festival Addons ──
        $addonsData = [
            ['slug' => 'demo-camping-standard', 'name' => 'Camping Standard', 'cat' => 'camping', 'price' => 10000, 'quota' => 1000, 'sold' => 412],
            ['slug' => 'demo-camping-premium', 'name' => 'Camping Premium', 'cat' => 'camping', 'price' => 20000, 'quota' => 200, 'sold' => 89],
            ['slug' => 'demo-locker-rental', 'name' => 'Locker Rental', 'cat' => 'locker', 'price' => 5000, 'quota' => 500, 'sold' => 134],
            ['slug' => 'demo-shower-access', 'name' => 'Shower Access 5-Day', 'cat' => 'shower', 'price' => 3000, 'quota' => 300, 'sold' => 98],
        ];

        $addons = [];
        foreach ($addonsData as $i => $a) {
            $addon = FestivalAddon::firstOrCreate(
                ['tenant_id' => $tenantId, 'slug' => $a['slug']],
                [
                    'event_id' => $event->id,
                    'name' => $a['name'],
                    'category' => $a['cat'],
                    'price_cents' => $a['price'],
                    'currency' => 'RON',
                    'quota_total' => $a['quota'],
                    'quota_sold' => $a['sold'],
                    'status' => 'active',
                    'requires_pass' => true,
                    'sort_order' => $i + 1,
                ]
            );
            $addons[$a['slug']] = $addon;
        }
        $this->parent->refs['addons'] = $addons;
    }

    public function cleanup(): void
    {
        $tenantId = $this->parent->tenantId;

        $edition = FestivalEdition::where('tenant_id', $tenantId)->where('slug', 'demo-alpha-fest-2026')->first();
        if ($edition) {
            FestivalDay::where('festival_edition_id', $edition->id)->delete();
            FestivalPass::where('festival_edition_id', $edition->id)->where('slug', 'like', 'demo-%')->delete();
            $edition->delete();
        }

        FestivalAddon::where('tenant_id', $tenantId)->where('slug', 'like', 'demo-%')->delete();
    }
}
