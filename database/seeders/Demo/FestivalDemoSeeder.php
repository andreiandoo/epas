<?php

namespace Database\Seeders\Demo;

use Illuminate\Console\OutputStyle;

class FestivalDemoSeeder
{
    public int $tenantId;
    public ?OutputStyle $output;

    /** Shared references populated by sub-seeders */
    public array $refs = [];

    public function __construct(int $tenantId, ?OutputStyle $output = null)
    {
        $this->tenantId = $tenantId;
        $this->output = $output;
    }

    public function info(string $msg): void
    {
        $this->output?->writeln("  <info>→</info> {$msg}");
    }

    public function run(): void
    {
        $seeders = [
            DemoMicroserviceSeeder::class,
            DemoVenueAndEventSeeder::class,
            DemoFestivalEditionSeeder::class,
            DemoCustomerSeeder::class,
            DemoOrderSeeder::class,
            DemoVendorSeeder::class,
            DemoCashlessSeeder::class,
            DemoInventorySeeder::class,
            DemoFinanceSeeder::class,
            DemoCustomerProfileSeeder::class,
        ];

        foreach ($seeders as $class) {
            $name = class_basename($class);
            $this->info("Running {$name}...");
            (new $class($this))->run();
        }

        $this->verify();
    }

    public function cleanup(): void
    {
        $cleaners = [
            DemoCustomerProfileSeeder::class,
            DemoFinanceSeeder::class,
            DemoInventorySeeder::class,
            DemoCashlessSeeder::class,
            DemoVendorSeeder::class,
            DemoOrderSeeder::class,
            DemoCustomerSeeder::class,
            DemoFestivalEditionSeeder::class,
            DemoVenueAndEventSeeder::class,
            DemoMicroserviceSeeder::class,
        ];

        foreach ($cleaners as $class) {
            $name = class_basename($class);
            $this->info("Cleaning {$name}...");
            (new $class($this))->cleanup();
        }
    }

    protected function verify(): void
    {
        $this->info('Verifying mathematical consistency...');

        $edition = $this->refs['edition'] ?? null;
        if (! $edition) {
            return;
        }

        $accounts = \App\Models\Cashless\CashlessAccount::where('festival_edition_id', $edition->id)->get();

        $totalBalances = $accounts->sum('balance_cents');
        $totalToppedUp = $accounts->sum('total_topped_up_cents');
        $totalSpent = $accounts->sum('total_spent_cents');
        $totalCashedOut = $accounts->sum('total_cashed_out_cents');

        $expectedBalance = $totalToppedUp - $totalSpent - $totalCashedOut;

        if ($totalBalances === $expectedBalance) {
            $this->info("Balance check PASSED: {$totalBalances} cents across " . $accounts->count() . " accounts");
        } else {
            $this->output?->writeln("  <error>Balance MISMATCH: accounts={$totalBalances}, expected={$expectedBalance} (topped={$totalToppedUp}, spent={$totalSpent}, cashed={$totalCashedOut})</error>");
        }
    }
}
