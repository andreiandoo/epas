<?php

namespace Tests\Feature\Marketplace;

use App\Models\MarketplaceClient;
use App\Models\MarketplaceCustomer;
use App\Models\MarketplaceEmailLog;
use App\Models\MarketplaceRefundRequest;
use App\Models\Order;
use App\Models\Ticket;
use App\Services\Marketplace\OrderTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class OrderTransferTest extends TestCase
{
    use RefreshDatabase;

    protected MarketplaceClient $client;
    protected MarketplaceCustomer $sourceCustomer;
    protected MarketplaceCustomer $targetCustomer;
    protected Order $order;
    protected OrderTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(OrderTransferService::class);

        $this->client = MarketplaceClient::create([
            'name' => 'Test Marketplace',
            'slug' => 'test-marketplace',
            'domain' => 'test.example.com',
            'status' => 'active',
        ]);

        $this->sourceCustomer = MarketplaceCustomer::create([
            'marketplace_client_id' => $this->client->id,
            'email' => 'alice@example.com',
            'first_name' => 'Alice',
            'last_name' => 'A',
            'phone' => '+40700000001',
            'password' => bcrypt('test'),
            'status' => 'active',
        ]);

        $this->targetCustomer = MarketplaceCustomer::create([
            'marketplace_client_id' => $this->client->id,
            'email' => 'bob@example.com',
            'first_name' => 'Bob',
            'last_name' => 'B',
            'phone' => '+40700000002',
            'password' => bcrypt('test'),
            'status' => 'active',
        ]);

        $this->order = Order::create([
            'marketplace_client_id' => $this->client->id,
            'marketplace_customer_id' => $this->sourceCustomer->id,
            'order_number' => 'TEST-' . uniqid(),
            'customer_email' => $this->sourceCustomer->email,
            'customer_name' => 'Alice A',
            'customer_phone' => $this->sourceCustomer->phone,
            'subtotal' => 100.00,
            'total' => 100.00,
            'total_cents' => 10000,
            'currency' => 'RON',
            'status' => 'completed',
            'source' => 'web',
        ]);
    }

    public function test_transfer_moves_order_and_recomputes_totals(): void
    {
        // Source has 1 completed order
        $this->sourceCustomer->updateStats();
        $this->assertSame(1, (int) $this->sourceCustomer->fresh()->total_orders);

        $result = $this->service->transfer(
            $this->order,
            $this->targetCustomer,
            'Test transfer',
            performedByAdminId: null,
            rewriteTicketAttendee: false,
        );

        $this->order->refresh();
        $this->assertSame($this->targetCustomer->id, $this->order->marketplace_customer_id);
        $this->assertSame('bob@example.com', $this->order->customer_email);
        $this->assertSame('Bob B', $this->order->customer_name);

        // Stats recomputed
        $this->assertSame(0, (int) $this->sourceCustomer->fresh()->total_orders);
        $this->assertSame(1, (int) $this->targetCustomer->fresh()->total_orders);

        // History entry persisted on metadata
        $transfers = $this->order->metadata['transfers'] ?? [];
        $this->assertCount(1, $transfers);
        $this->assertSame($this->sourceCustomer->id, $transfers[0]['from_customer_id']);
        $this->assertSame($this->targetCustomer->id, $transfers[0]['to_customer_id']);
        $this->assertSame('Test transfer', $transfers[0]['reason']);

        $this->assertSame($this->sourceCustomer->id, $result['from']);
        $this->assertSame($this->targetCustomer->id, $result['to']);
    }

    public function test_transfer_rewrites_ticket_attendee_when_toggle_is_on(): void
    {
        $ticket = Ticket::create([
            'order_id' => $this->order->id,
            'attendee_name' => 'Alice A',
            'attendee_email' => 'alice@example.com',
            'code' => 'TKT-' . uniqid(),
            'status' => 'active',
        ]);

        $this->service->transfer(
            $this->order,
            $this->targetCustomer,
            'Bilete reatribuite noului proprietar',
            null,
            rewriteTicketAttendee: true,
        );

        $ticket->refresh();
        $this->assertSame('Bob B', $ticket->attendee_name);
        $this->assertSame('bob@example.com', $ticket->attendee_email);
    }

    public function test_transfer_keeps_ticket_attendee_by_default(): void
    {
        $ticket = Ticket::create([
            'order_id' => $this->order->id,
            'attendee_name' => 'Charlie C',
            'attendee_email' => 'charlie@example.com',
            'code' => 'TKT-' . uniqid(),
            'status' => 'active',
        ]);

        $this->service->transfer(
            $this->order,
            $this->targetCustomer,
            'Schimbare cumpărător dar invitatul rămâne acelaşi',
        );

        $ticket->refresh();
        $this->assertSame('Charlie C', $ticket->attendee_name);
        $this->assertSame('charlie@example.com', $ticket->attendee_email);
    }

    public function test_transfer_moves_linked_refund_requests_and_email_logs(): void
    {
        $refund = MarketplaceRefundRequest::create([
            'order_id' => $this->order->id,
            'marketplace_client_id' => $this->client->id,
            'marketplace_customer_id' => $this->sourceCustomer->id,
            'amount' => 50.00,
            'reason' => 'test',
            'status' => 'pending',
        ]);

        $emailLog = MarketplaceEmailLog::create([
            'order_id' => $this->order->id,
            'marketplace_client_id' => $this->client->id,
            'marketplace_customer_id' => $this->sourceCustomer->id,
            'to_email' => 'alice@example.com',
            'subject' => 'Test',
            'body' => 'Test body',
            'status' => 'sent',
        ]);

        $this->service->transfer($this->order, $this->targetCustomer, 'Test transfer');

        $this->assertSame($this->targetCustomer->id, $refund->fresh()->marketplace_customer_id);
        $this->assertSame($this->targetCustomer->id, $emailLog->fresh()->marketplace_customer_id);
    }

    public function test_transfer_blocks_when_emails_match(): void
    {
        $duplicate = MarketplaceCustomer::create([
            'marketplace_client_id' => $this->client->id,
            'email' => $this->sourceCustomer->email,
            'first_name' => 'Alice',
            'last_name' => 'Duplicate',
            'password' => bcrypt('test'),
            'status' => 'active',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('same email');

        $this->service->transfer($this->order, $duplicate, 'Test');
    }

    public function test_transfer_blocks_when_destination_belongs_to_other_marketplace(): void
    {
        $otherClient = MarketplaceClient::create([
            'name' => 'Other Marketplace',
            'slug' => 'other-marketplace',
            'domain' => 'other.example.com',
            'status' => 'active',
        ]);

        $otherCustomer = MarketplaceCustomer::create([
            'marketplace_client_id' => $otherClient->id,
            'email' => 'foreign@other.example.com',
            'first_name' => 'Foreign',
            'last_name' => 'Customer',
            'password' => bcrypt('test'),
            'status' => 'active',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('different marketplace');

        $this->service->transfer($this->order, $otherCustomer, 'Test');
    }

    public function test_transfer_history_appends_entries_on_repeat(): void
    {
        // First transfer A → B
        $this->service->transfer($this->order, $this->targetCustomer, 'First');

        // Bring back to source
        $this->service->transfer($this->order->refresh(), $this->sourceCustomer, 'Reverted');

        $this->order->refresh();
        $transfers = $this->order->metadata['transfers'] ?? [];

        $this->assertCount(2, $transfers);
        $this->assertSame('First', $transfers[0]['reason']);
        $this->assertSame('Reverted', $transfers[1]['reason']);
        $this->assertSame($this->sourceCustomer->id, $this->order->marketplace_customer_id);
    }

    public function test_undo_via_history_returns_order_to_previous_owner(): void
    {
        // Forward transfer A → B
        $this->service->transfer($this->order, $this->targetCustomer, 'Initial mistake');
        $this->order->refresh();
        $this->assertSame($this->targetCustomer->id, $this->order->marketplace_customer_id);

        // Read last transfer from metadata and run reverse
        $last = end($this->order->metadata['transfers']);
        $previous = MarketplaceCustomer::find($last['from_customer_id']);
        $this->service->transfer($this->order, $previous, 'Undo of transfer at ' . $last['at']);

        $this->order->refresh();
        $this->assertSame($this->sourceCustomer->id, $this->order->marketplace_customer_id);
        $this->assertSame('alice@example.com', $this->order->customer_email);

        // Stats are back to original state
        $this->assertSame(1, (int) $this->sourceCustomer->fresh()->total_orders);
        $this->assertSame(0, (int) $this->targetCustomer->fresh()->total_orders);

        // Both transfers are kept in history
        $this->assertCount(2, $this->order->metadata['transfers']);
    }
}
