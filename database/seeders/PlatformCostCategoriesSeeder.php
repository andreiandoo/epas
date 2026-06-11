<?php

namespace Database\Seeders;

use App\Models\PlatformCost;
use Illuminate\Database\Seeder;

/**
 * Seeds placeholder PlatformCost rows for the four company-side
 * categories added on 2026-05-06: Company General, Salaries,
 * Accounting, Company Taxes.
 *
 * Each row is created idempotently (firstOrCreate on name+category)
 * with a 0 EUR monthly amount and is_active=false, so reruns are safe
 * and the rows don't pollute monthly totals until an admin fills in
 * a real amount.
 *
 * Run with:
 *   php artisan db:seed --class=PlatformCostCategoriesSeeder
 */
class PlatformCostCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => 'Company General — placeholder',
                'category' => 'company_general',
                'description' => 'Spațiu birou, utilități, abonamente generale ale companiei.',
            ],
            [
                'name' => 'Salaries — placeholder',
                'category' => 'salaries',
                'description' => 'Salarii angajați și colaboratori.',
            ],
            [
                'name' => 'Accounting — placeholder',
                'category' => 'accounting',
                'description' => 'Servicii contabilitate și consultanță fiscală.',
            ],
            [
                'name' => 'Company Taxes — placeholder',
                'category' => 'company_taxes',
                'description' => 'Impozite și taxe ale companiei (profit, TVA, contribuții).',
            ],
        ];

        foreach ($rows as $row) {
            PlatformCost::firstOrCreate(
                [
                    'name' => $row['name'],
                    'category' => $row['category'],
                ],
                [
                    'description' => $row['description'],
                    'amount' => 0,
                    'currency' => 'EUR',
                    'billing_cycle' => 'monthly',
                    'start_date' => now()->startOfMonth(),
                    'end_date' => null,
                    'is_active' => false,
                    'metadata' => ['seeded' => true],
                ]
            );
        }

        $this->command?->info('Seeded ' . count($rows) . ' platform-cost category placeholders.');
    }
}
