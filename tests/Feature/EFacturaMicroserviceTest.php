<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\AnafQueue;
use App\Services\EFactura\EFacturaService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EFacturaMicroserviceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_queues_invoice_for_submission_idempotently()
    {
        $service = app(EFacturaService::class);

        $invoiceData = [
            'invoice_number' => 'FAC-2025-001',
            'issue_date' => '2025-11-16',
            'seller' => [
                'name' => 'SC Test SRL',
                'vat_id' => 'RO12345678',
            ],
            'buyer' => [
                'name' => 'SC Client SRL',
                'vat_id' => 'RO87654321',
            ],
            'lines' => [
                [
                    'description' => 'Test Product',
                    'quantity' => 1,
                    'unit_price' => 100,
                ],
            ],
            'total' => 100,
        ];

        // First queue
        $result1 = $service->queueInvoice('test_tenant', 1, $invoiceData);
        $this->assertTrue($result1['success']);
        $queueId1 = $result1['queue_id'];

        // Second queue (should be idempotent)
        $result2 = $service->queueInvoice('test_tenant', 1, $invoiceData);
        $this->assertTrue($result2['success']);
        $queueId2 = $result2['queue_id'];

        // Should return same queue entry
        $this->assertEquals($queueId1, $queueId2);

        // Should only have one entry in database
        $this->assertCount(1, AnafQueue::all());
    }

    /** @test */
    public function it_validates_invoice_data_before_queuing()
    {
        $service = app(EFacturaService::class);

        $invalidInvoiceData = [
            'invoice_number' => '', // Missing required field
            'seller' => [],
            'buyer' => [],
            'lines' => [],
        ];

        $result = $service->queueInvoice('test_tenant', 2, $invalidInvoiceData);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('errors', $result);
    }

    /** @test */
    public function it_can_process_queue_entries()
    {
        $service = app(EFacturaService::class);

        // Create a queued entry
        AnafQueue::create([
            'tenant_id' => 'test_tenant',
            'invoice_id' => 1,
            'payload_ref' => 'test/path.xml',
            'status' => AnafQueue::STATUS_QUEUED,
            'next_retry_at' => now(),
        ]);

        // Process queue
        $result = $service->processQueue(10);

        $this->assertArrayHasKey('processed', $result);
        $this->assertGreaterThan(0, $result['processed']);
    }

    /** @test */
    public function it_marks_queue_as_error_after_max_retries()
    {
        $queue = AnafQueue::create([
            'tenant_id' => 'test_tenant',
            'invoice_id' => 1,
            'payload_ref' => 'test/path.xml',
            'status' => AnafQueue::STATUS_ERROR,
            'attempts' => 5,
            'max_attempts' => 5,
        ]);

        $this->assertFalse($queue->canRetry());
    }

    /** @test */
    public function it_can_poll_submitted_invoices()
    {
        $service = app(EFacturaService::class);

        // Create a submitted entry
        AnafQueue::create([
            'tenant_id' => 'test_tenant',
            'invoice_id' => 1,
            'payload_ref' => 'test/path.xml',
            'status' => AnafQueue::STATUS_SUBMITTED,
            'anaf_ids' => ['remote_id' => 'ANAF-TEST-123'],
        ]);

        $result = $service->pollPending(10);

        $this->assertArrayHasKey('polled', $result);
    }

    /** @test */
    public function it_calculates_statistics_correctly()
    {
        // Create various queue entries
        AnafQueue::create([
            'tenant_id' => 'test_tenant',
            'invoice_id' => 1,
            'status' => AnafQueue::STATUS_QUEUED,
        ]);

        AnafQueue::create([
            'tenant_id' => 'test_tenant',
            'invoice_id' => 2,
            'status' => AnafQueue::STATUS_ACCEPTED,
        ]);

        AnafQueue::create([
            'tenant_id' => 'test_tenant',
            'invoice_id' => 3,
            'status' => AnafQueue::STATUS_REJECTED,
        ]);

        $service = app(EFacturaService::class);
        $stats = $service->getStats('test_tenant');

        $this->assertEquals(1, $stats['queued']);
        $this->assertEquals(1, $stats['accepted']);
        $this->assertEquals(1, $stats['rejected']);
    }
}
