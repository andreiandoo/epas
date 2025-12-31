<?php

namespace Tests\Feature\Platform;

use App\Models\Platform\CoreCustomer;
use App\Services\Platform\DuplicateDetectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DuplicateDetectionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected DuplicateDetectionService $duplicateService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->duplicateService = new DuplicateDetectionService();
    }

    /** @test */
    public function it_finds_exact_email_hash_matches()
    {
        $emailHash = hash('sha256', 'duplicate@test.com');

        $customer1 = $this->createCustomer([
            'email_hash' => $emailHash,
            'first_name' => 'John',
        ]);

        $customer2 = $this->createCustomer([
            'email_hash' => $emailHash,
            'first_name' => 'John D',
        ]);

        $duplicates = $this->duplicateService->findDuplicatesFor($customer1);

        $this->assertCount(1, $duplicates);
        $this->assertEquals($customer2->id, $duplicates->first()['customer']->id);
        $this->assertGreaterThan(0.9, $duplicates->first()['score']);
    }

    /** @test */
    public function it_finds_phone_hash_matches()
    {
        $phoneHash = hash('sha256', '+1234567890');

        $customer1 = $this->createCustomer([
            'phone_hash' => $phoneHash,
        ]);

        $customer2 = $this->createCustomer([
            'phone_hash' => $phoneHash,
        ]);

        $duplicates = $this->duplicateService->findDuplicatesFor($customer1);

        $this->assertCount(1, $duplicates);
        $this->assertEquals($customer2->id, $duplicates->first()['customer']->id);
    }

    /** @test */
    public function it_finds_fuzzy_name_matches()
    {
        $customer1 = $this->createCustomer([
            'first_name' => 'John',
            'last_name' => 'Smith',
        ]);

        $customer2 = $this->createCustomer([
            'first_name' => 'Jon',
            'last_name' => 'Smith',
        ]);

        // Different enough not to be auto-matched
        $customer3 = $this->createCustomer([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
        ]);

        $duplicates = $this->duplicateService->findDuplicatesFor($customer1, 0.3);

        // Should find customer2 (similar name), not customer3
        $matchedIds = $duplicates->pluck('customer.id')->toArray();
        $this->assertContains($customer2->id, $matchedIds);
        $this->assertNotContains($customer3->id, $matchedIds);
    }

    /** @test */
    public function it_finds_all_duplicates_in_system()
    {
        // Create pairs of duplicates
        for ($i = 0; $i < 3; $i++) {
            $emailHash = hash('sha256', "pair{$i}@test.com");

            $this->createCustomer([
                'email_hash' => $emailHash,
                'first_name' => "User {$i}",
            ]);

            $this->createCustomer([
                'email_hash' => $emailHash,
                'first_name' => "User {$i} Copy",
            ]);
        }

        // Create a unique customer (no duplicate)
        $this->createCustomer([
            'email_hash' => hash('sha256', 'unique@test.com'),
        ]);

        $allDuplicates = $this->duplicateService->findAllDuplicates(0.7, 100);

        $this->assertGreaterThanOrEqual(3, $allDuplicates->count());
    }

    /** @test */
    public function it_assigns_match_types()
    {
        $emailHash = hash('sha256', 'exact@test.com');

        $customer1 = $this->createCustomer([
            'email_hash' => $emailHash,
        ]);

        $customer2 = $this->createCustomer([
            'email_hash' => $emailHash,
        ]);

        $duplicates = $this->duplicateService->findDuplicatesFor($customer1);

        $this->assertCount(1, $duplicates);
        $this->assertArrayHasKey('match_type', $duplicates->first());
        $this->assertContains($duplicates->first()['match_type'], ['exact', 'high', 'medium', 'low']);
    }

    /** @test */
    public function it_assigns_confidence_levels()
    {
        $emailHash = hash('sha256', 'confident@test.com');

        $customer1 = $this->createCustomer([
            'email_hash' => $emailHash,
        ]);

        $customer2 = $this->createCustomer([
            'email_hash' => $emailHash,
        ]);

        $duplicates = $this->duplicateService->findDuplicatesFor($customer1);

        $this->assertCount(1, $duplicates);
        $this->assertArrayHasKey('confidence', $duplicates->first());
        $this->assertContains($duplicates->first()['confidence'], ['definite', 'likely', 'possible']);
    }

    /** @test */
    public function it_excludes_merged_customers()
    {
        $emailHash = hash('sha256', 'merged@test.com');

        $customer1 = $this->createCustomer([
            'email_hash' => $emailHash,
        ]);

        $mergedCustomer = $this->createCustomer([
            'email_hash' => $emailHash,
            'is_merged' => true,
        ]);

        $duplicates = $this->duplicateService->findDuplicatesFor($customer1);

        $matchedIds = $duplicates->pluck('customer.id')->toArray();
        $this->assertNotContains($mergedCustomer->id, $matchedIds);
    }

    /** @test */
    public function it_excludes_anonymized_customers()
    {
        $emailHash = hash('sha256', 'anon@test.com');

        $customer1 = $this->createCustomer([
            'email_hash' => $emailHash,
        ]);

        $anonymizedCustomer = $this->createCustomer([
            'email_hash' => $emailHash,
            'is_anonymized' => true,
        ]);

        $duplicates = $this->duplicateService->findDuplicatesFor($customer1);

        $matchedIds = $duplicates->pluck('customer.id')->toArray();
        $this->assertNotContains($anonymizedCustomer->id, $matchedIds);
    }

    /** @test */
    public function it_auto_merges_high_confidence_duplicates()
    {
        // Create definite duplicates
        $emailHash = hash('sha256', 'automerge@test.com');
        $phoneHash = hash('sha256', '+1234567890');

        $customer1 = $this->createCustomer([
            'email_hash' => $emailHash,
            'phone_hash' => $phoneHash,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'total_orders' => 5,
            'total_spent' => 500,
        ]);

        $customer2 = $this->createCustomer([
            'email_hash' => $emailHash,
            'phone_hash' => $phoneHash,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'total_orders' => 3,
            'total_spent' => 300,
        ]);

        $result = $this->duplicateService->autoMergeHighConfidenceDuplicates(0.98);

        $this->assertArrayHasKey('merged', $result);
        $this->assertArrayHasKey('skipped', $result);
    }

    /** @test */
    public function it_returns_statistics()
    {
        // Create some test data
        for ($i = 0; $i < 5; $i++) {
            $this->createCustomer([
                'first_name' => 'User ' . $i,
            ]);
        }

        $stats = $this->duplicateService->getStatistics();

        $this->assertArrayHasKey('total_customers', $stats);
        $this->assertArrayHasKey('potential_duplicates', $stats);
        $this->assertArrayHasKey('high_confidence_matches', $stats);
    }

    /** @test */
    public function it_recommends_primary_customer()
    {
        $emailHash = hash('sha256', 'primary@test.com');

        // Customer with more orders should be recommended as primary
        $moreOrders = $this->createCustomer([
            'email_hash' => $emailHash,
            'total_orders' => 10,
            'total_spent' => 1000,
        ]);

        $lessOrders = $this->createCustomer([
            'email_hash' => $emailHash,
            'total_orders' => 2,
            'total_spent' => 100,
        ]);

        $allDuplicates = $this->duplicateService->findAllDuplicates(0.7, 100);

        $group = $allDuplicates->first();
        $this->assertEquals($moreOrders->id, $group['recommended_primary']->id);
    }

    /** @test */
    public function it_respects_threshold_parameter()
    {
        $customer1 = $this->createCustomer([
            'first_name' => 'Jonathan',
            'last_name' => 'Smith',
        ]);

        $customer2 = $this->createCustomer([
            'first_name' => 'Jon',
            'last_name' => 'Smyth',
        ]);

        // High threshold should find fewer matches
        $highThreshold = $this->duplicateService->findDuplicatesFor($customer1, 0.95);

        // Low threshold should find more matches
        $lowThreshold = $this->duplicateService->findDuplicatesFor($customer1, 0.3);

        $this->assertLessThanOrEqual(
            $lowThreshold->count(),
            $highThreshold->count()
        );
    }

    /** @test */
    public function it_handles_customers_with_no_duplicates()
    {
        $customer = $this->createCustomer([
            'email_hash' => hash('sha256', 'unique@unique.com'),
            'first_name' => 'Unique',
            'last_name' => 'Person',
        ]);

        $duplicates = $this->duplicateService->findDuplicatesFor($customer);

        $this->assertCount(0, $duplicates);
    }

    protected function createCustomer(array $attributes = []): CoreCustomer
    {
        $defaults = [
            'uuid' => 'test-' . uniqid(),
            'email_hash' => hash('sha256', uniqid() . '@test.com'),
            'total_spent' => 0,
            'total_orders' => 0,
            'is_merged' => false,
            'is_anonymized' => false,
            'first_seen_at' => now()->subDays(30),
            'last_seen_at' => now(),
        ];

        return CoreCustomer::create(array_merge($defaults, $attributes));
    }
}
