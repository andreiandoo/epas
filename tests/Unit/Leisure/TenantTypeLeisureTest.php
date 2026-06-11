<?php

namespace Tests\Unit\Leisure;

use App\Enums\TenantType;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit tests for TenantType::Leisure — no DB, no framework boot.
 * Safe to run on any host without Postgres/MySQL configured.
 */
class TenantTypeLeisureTest extends TestCase
{
    public function test_leisure_enum_case_exists(): void
    {
        $this->assertSame('leisure', TenantType::Leisure->value);
        $this->assertSame('Leisure Venue', TenantType::Leisure->label());
    }

    public function test_leisure_default_microservice_slugs(): void
    {
        $slugs = TenantType::Leisure->defaultMicroserviceSlugs();

        $expected = [
            'analytics', 'crm', 'door-sales', 'efactura', 'accounting',
            'leisure-core', 'leisure-pos', 'leisure-rentals',
            'leisure-multi-society', 'leisure-embed',
        ];

        foreach ($expected as $slug) {
            $this->assertContains($slug, $slugs, "Default microservices should include {$slug}");
        }
    }

    public function test_leisure_default_features_structure(): void
    {
        $features = TenantType::Leisure->defaultFeatures();

        $this->assertArrayHasKey('leisure', $features);
        $leisure = $features['leisure'];

        $this->assertTrue($leisure['enabled']);
        $this->assertTrue($leisure['rentals']['enabled']);
        $this->assertTrue($leisure['pos']['enabled']);
        $this->assertTrue($leisure['physical_inventory']['enabled']);
        $this->assertTrue($leisure['crm']['enabled']);

        $this->assertFalse($leisure['time_slots']['enabled']);
        $this->assertFalse($leisure['multi_society']['enabled']);
        $this->assertFalse($leisure['channel_pricing']['enabled']);
        $this->assertFalse($leisure['embed']['enabled']);
    }

    public function test_non_leisure_types_have_empty_default_features(): void
    {
        $this->assertSame([], TenantType::Festival->defaultFeatures());
        $this->assertSame([], TenantType::Theater->defaultFeatures());
        $this->assertSame([], TenantType::Artist->defaultFeatures());
        $this->assertSame([], TenantType::Museum->defaultFeatures());
    }

    public function test_all_enum_cases_have_a_label(): void
    {
        foreach (TenantType::cases() as $case) {
            $this->assertNotEmpty($case->label(), "Enum case {$case->value} must have a label");
        }
    }

    public function test_all_enum_cases_have_microservice_defaults(): void
    {
        foreach (TenantType::cases() as $case) {
            $slugs = $case->defaultMicroserviceSlugs();
            $this->assertIsArray($slugs);
            $this->assertNotEmpty($slugs, "Enum case {$case->value} must have at least one default microservice");
        }
    }
}
