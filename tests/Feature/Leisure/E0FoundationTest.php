<?php

namespace Tests\Feature\Leisure;

use App\Enums\TenantType;
use App\Models\Microservice;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class E0FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_leisure_enum_case_exists(): void
    {
        $this->assertSame('leisure', TenantType::Leisure->value);
        $this->assertSame('Leisure Venue', TenantType::Leisure->label());
    }

    public function test_leisure_has_default_microservices(): void
    {
        $slugs = TenantType::Leisure->defaultMicroserviceSlugs();

        $this->assertContains('leisure-core', $slugs);
        $this->assertContains('leisure-pos', $slugs);
        $this->assertContains('leisure-rentals', $slugs);
        $this->assertContains('leisure-multi-society', $slugs);
        $this->assertContains('leisure-embed', $slugs);
        $this->assertContains('analytics', $slugs);
        $this->assertContains('crm', $slugs);
    }

    public function test_leisure_default_features_structure(): void
    {
        $features = TenantType::Leisure->defaultFeatures();

        $this->assertArrayHasKey('leisure', $features);
        $this->assertTrue($features['leisure']['enabled']);
        $this->assertTrue($features['leisure']['rentals']['enabled']);
        $this->assertTrue($features['leisure']['pos']['enabled']);
        $this->assertTrue($features['leisure']['physical_inventory']['enabled']);
        $this->assertTrue($features['leisure']['crm']['enabled']);
        $this->assertFalse($features['leisure']['time_slots']['enabled']);
        $this->assertFalse($features['leisure']['multi_society']['enabled']);
        $this->assertFalse($features['leisure']['channel_pricing']['enabled']);
        $this->assertFalse($features['leisure']['embed']['enabled']);
    }

    public function test_non_leisure_types_have_empty_default_features(): void
    {
        $this->assertSame([], TenantType::Festival->defaultFeatures());
        $this->assertSame([], TenantType::Theater->defaultFeatures());
        $this->assertSame([], TenantType::Artist->defaultFeatures());
    }

    public function test_tenant_observer_populates_leisure_features_on_create(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test Aquapark SRL',
            'public_name' => 'Aquapark Splash',
            'slug' => 'aquapark-splash-' . uniqid(),
            'tenant_type' => TenantType::Leisure,
            'status' => 'active',
        ]);

        $this->assertIsArray($tenant->features);
        $this->assertArrayHasKey('leisure', $tenant->features);
        $this->assertTrue($tenant->features['leisure']['enabled']);
        $this->assertTrue($tenant->features['leisure']['rentals']['enabled']);
    }

    public function test_tenant_observer_does_not_overwrite_existing_features(): void
    {
        $tenant = Tenant::create([
            'name' => 'Test SRL',
            'public_name' => 'Test',
            'slug' => 'test-no-overwrite-' . uniqid(),
            'tenant_type' => TenantType::Leisure,
            'status' => 'active',
            'features' => [
                'leisure' => [
                    'enabled' => false, // explicitly disabled by admin
                    'pos' => ['enabled' => false],
                ],
            ],
        ]);

        $this->assertFalse($tenant->features['leisure']['enabled']);
        $this->assertFalse($tenant->features['leisure']['pos']['enabled']);
        // But unspecified keys are filled from defaults
        $this->assertTrue($tenant->features['leisure']['rentals']['enabled']);
        $this->assertTrue($tenant->features['leisure']['crm']['enabled']);
    }

    public function test_tenant_observer_backfills_features_when_tenant_type_changes(): void
    {
        $tenant = Tenant::create([
            'name' => 'Migrating SRL',
            'public_name' => 'Migrating',
            'slug' => 'migrating-' . uniqid(),
            'tenant_type' => TenantType::Festival,
            'status' => 'active',
        ]);
        $this->assertEmpty($tenant->features ?? []);

        $tenant->update(['tenant_type' => TenantType::Leisure]);
        $tenant->refresh();

        $this->assertIsArray($tenant->features);
        $this->assertTrue($tenant->features['leisure']['enabled']);
    }

    public function test_non_leisure_tenant_creation_does_not_populate_leisure_features(): void
    {
        $tenant = Tenant::create([
            'name' => 'Theater SRL',
            'public_name' => 'Theater',
            'slug' => 'theater-' . uniqid(),
            'tenant_type' => TenantType::Theater,
            'status' => 'active',
        ]);

        $this->assertArrayNotHasKey('leisure', $tenant->features ?? []);
    }

    public function test_leisure_microservices_seeder_creates_5_microservices(): void
    {
        $this->seed(\Database\Seeders\LeisureMicroservicesSeeder::class);

        $slugs = ['leisure-core', 'leisure-pos', 'leisure-rentals', 'leisure-multi-society', 'leisure-embed'];

        foreach ($slugs as $slug) {
            $ms = Microservice::where('slug', $slug)->first();
            $this->assertNotNull($ms, "Microservice {$slug} should be created by seeder");
            $this->assertSame('leisure', $ms->category);
            $this->assertTrue((bool) $ms->is_active);
        }
    }

    public function test_leisure_microservices_seeder_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\LeisureMicroservicesSeeder::class);
        $countAfterFirst = Microservice::where('category', 'leisure')->count();

        $this->seed(\Database\Seeders\LeisureMicroservicesSeeder::class);
        $countAfterSecond = Microservice::where('category', 'leisure')->count();

        $this->assertSame($countAfterFirst, $countAfterSecond, 'Seeder should be idempotent');
    }
}
