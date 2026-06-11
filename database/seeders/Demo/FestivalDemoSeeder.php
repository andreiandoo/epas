<?php

namespace Database\Seeders\Demo;

use App\Models\Tenant;
use Illuminate\Console\OutputStyle;

class FestivalDemoSeeder
{
    /** The shadow tenant ID where demo data lives */
    public int $tenantId;

    /** The original (parent) tenant ID */
    public int $parentTenantId;

    public ?OutputStyle $output;

    /** Shared references populated by sub-seeders */
    public array $refs = [];

    public function __construct(int $parentTenantId, ?OutputStyle $output = null)
    {
        $this->parentTenantId = $parentTenantId;
        $this->output = $output;
    }

    public function info(string $msg): void
    {
        $this->output?->writeln("  <info>→</info> {$msg}");
    }

    public static function datasetKey(): string
    {
        return 'festival';
    }

    public static function datasetLabel(): string
    {
        return 'Festival (cashless, vendors, inventory, finance)';
    }

    /**
     * Create the shadow tenant and seed all demo data into it.
     */
    public function run(): void
    {
        $parent = Tenant::findOrFail($this->parentTenantId);

        // Create or reuse shadow tenant
        $shadow = $this->createShadowTenant($parent);
        $this->tenantId = $shadow->id;

        // Link parent → shadow
        $parent->update([
            'demo_shadow_id' => $shadow->id,
            'demo_dataset' => static::datasetKey(),
        ]);

        $this->info("Shadow tenant created: #{$shadow->id} ({$shadow->slug})");

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

    /**
     * Delete the shadow tenant and all its data, unlink from parent.
     */
    public function cleanup(): void
    {
        $parent = Tenant::findOrFail($this->parentTenantId);

        if (! $parent->demo_shadow_id) {
            $this->info('No demo data to clean up.');
            return;
        }

        $shadow = Tenant::find($parent->demo_shadow_id);
        if (! $shadow) {
            $parent->update(['demo_shadow_id' => null, 'demo_dataset' => null]);
            $this->info('Shadow tenant not found, cleared reference.');
            return;
        }

        $this->tenantId = $shadow->id;

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

        // Delete the shadow tenant itself
        $shadow->delete();

        // Unlink from parent
        $parent->update(['demo_shadow_id' => null, 'demo_dataset' => null]);
        $this->info('Shadow tenant deleted and parent unlinked.');
    }

    protected function createShadowTenant(Tenant $parent): Tenant
    {
        $slug = 'demo-shadow-' . $parent->id;

        return Tenant::firstOrCreate(
            ['slug' => $slug],
            [
                'name' => 'DEMO - ' . $parent->name,
                'public_name' => 'DEMO - ' . ($parent->public_name ?? $parent->name),
                'domain' => $slug . '.demo.local',
                'status' => 'active',
                'is_demo_shadow' => true,
                'demo_parent_id' => $parent->id,
                'tenant_type' => $parent->tenant_type,
                'plan' => $parent->plan ?? '2percent',
                'commission_mode' => $parent->commission_mode ?? 'included',
                'commission_rate' => $parent->commission_rate ?? 5.00,
                'locale' => $parent->locale ?? 'ro',
                'currency' => $parent->currency ?? 'RON',
                'company_name' => 'DEMO - ' . ($parent->company_name ?? $parent->name),
                'city' => $parent->city,
                'country' => $parent->country ?? 'RO',
            ]
        );
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
