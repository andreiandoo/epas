<?php

namespace Tests\Feature;

use App\Jobs\IssueServiceOrderInvoiceJob;
use App\Models\Invoice;
use App\Models\ServiceOrder;
use App\Services\Accounting\AccountingService;
use App\Services\Invoicing\CompanyInvoiceService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Schemă redusă: istoricul complet de migrații nu rulează pe SQLite. */
class ServiceOrderInvoiceTest extends TestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $app['config']->set('database.connections.invoice_testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('database.default', 'invoice_testing');
        DB::purge('invoice_testing');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('marketplace_clients', function (Blueprint $t) {
            $t->id();
            $t->string('name')->nullable();
            $t->string('company_name')->nullable();
            $t->string('cui')->nullable();
            $t->string('address')->nullable();
            $t->boolean('vat_payer')->default(false);
            $t->text('settings')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('marketplace_organizers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_client_id')->nullable();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('person_type')->nullable();
            $t->string('company_name')->nullable();
            $t->string('company_tax_id')->nullable();
            $t->string('company_registration')->nullable();
            $t->string('company_address')->nullable();
            $t->string('company_city')->nullable();
            $t->string('company_county')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('service_orders', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->nullable();
            $t->string('order_number')->nullable();
            $t->foreignId('marketplace_client_id')->nullable();
            $t->foreignId('marketplace_organizer_id')->nullable();
            $t->foreignId('marketplace_artist_account_id')->nullable();
            $t->foreignId('marketplace_event_id')->nullable();
            $t->string('service_type')->nullable();
            $t->text('config')->nullable();
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->decimal('tax', 10, 2)->default(0);
            $t->decimal('total', 10, 2)->default(0);
            $t->string('currency')->nullable();
            $t->string('payment_method')->nullable();
            $t->string('payment_status')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->string('payment_reference')->nullable();
            $t->string('status')->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('invoices', function (Blueprint $t) {
            $t->id();
            $t->foreignId('marketplace_client_id')->nullable();
            $t->foreignId('marketplace_organizer_id')->nullable();
            $t->string('number')->unique();
            $t->string('type')->nullable();
            $t->string('description')->nullable();
            $t->date('issue_date')->nullable();
            $t->date('due_date')->nullable();
            $t->decimal('subtotal', 10, 2)->default(0);
            $t->decimal('vat_rate', 5, 2)->default(0);
            $t->decimal('vat_amount', 10, 2)->default(0);
            $t->decimal('amount', 10, 2)->default(0);
            $t->string('currency')->nullable();
            $t->string('status')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->text('meta')->nullable();
            $t->timestamps();
        });
    }

    protected function order(bool $enabled = true, array $organizer = [], array $order = []): ServiceOrder
    {
        $clientId = DB::table('marketplace_clients')->insertGetId([
            'name' => 'Ambilet',
            'cui' => '123',
            'settings' => json_encode(['service_order_invoicing' => ['enabled' => $enabled]]),
        ]);
        $organizerId = DB::table('marketplace_organizers')->insertGetId(array_merge([
            'marketplace_client_id' => $clientId,
            'name' => 'Org',
            'email' => 'org@example.com',
            'company_name' => 'Org SRL',
            'company_tax_id' => '12345678',
            'company_registration' => 'J40/1/2020',
            'company_address' => 'Str. X, nr. 5',
            'company_city' => 'Cluj-Napoca',
            'company_county' => 'Cluj',
        ], $organizer));

        return ServiceOrder::create(array_merge([
            'marketplace_client_id' => $clientId,
            'marketplace_organizer_id' => $organizerId,
            'service_type' => ServiceOrder::TYPE_TRACKING,
            'config' => ['vat_rate' => 21],
            'subtotal' => 100,
            'tax' => 21,
            'total' => 121,
            'currency' => 'RON',
            'payment_method' => 'card',
            'status' => ServiceOrder::STATUS_PENDING_PAYMENT,
        ], $order));
    }

    /** @return array{0: \Mockery\MockInterface, 1: object} */
    protected function fakes(): array
    {
        $sent = new \stdClass();
        $sent->payload = null;
        $sent->issued = 0;
        $sent->emails = 0;

        $accounting = $this->mock(AccountingService::class);
        $accounting->shouldReceive('marketplaceConnector')->andReturn(['provider' => 'oblio', 'draft' => false]);
        $accounting->shouldReceive('issueMarketplaceInvoice')->andReturnUsing(function ($id, $number, $payload) use ($sent) {
            $sent->payload = $payload;
            $sent->issued++;

            return ['success' => true, 'external_ref' => 'AMB/7', 'invoice_number' => 'AMB7'];
        });
        $accounting->shouldReceive('submitMarketplaceEFactura')->andReturn(['supported' => true]);
        $accounting->shouldReceive('getMarketplaceInvoicePdf')->andReturn(['pdf_url' => 'https://oblio.test/f.pdf']);

        $company = $this->mock(CompanyInvoiceService::class);
        $company->shouldReceive('sendEmail')->andReturnUsing(function () use ($sent) {
            $sent->emails++;
        });

        Http::fake(['oblio.test/*' => Http::response('%PDF-1.4')]);

        return [$accounting, $sent];
    }

    public function test_paid_order_is_issued_collected_and_sent_once(): void
    {
        [, $sent] = $this->fakes();

        $order = $this->order();
        $order->markAsPaid('TX1');

        $this->assertSame(1, $sent->issued);
        $this->assertSame('Org SRL', $sent->payload['customer']['name']);
        $this->assertSame('Str. X, nr. 5', $sent->payload['customer']['address']['street']);
        $this->assertSame('Cluj-Napoca', $sent->payload['customer']['address']['city']);
        $this->assertSame(['type' => 'Card', 'documentNumber' => 'TX1'], $sent->payload['collect']);
        $this->assertSame(1, $sent->emails);

        $meta = Invoice::first()->meta;
        $this->assertSame('AMB/7', $meta['accounting']['external_ref']);
        $this->assertArrayHasKey('efactura_sent_at', $meta['accounting']);

        // a doua rulare nu emite și nu trimite din nou
        (new IssueServiceOrderInvoiceJob($order->id))->handle(app(\App\Services\Invoicing\ServiceOrderInvoiceService::class));
        $this->assertSame(1, $sent->issued);
        $this->assertSame(1, $sent->emails);
        $this->assertSame(1, Invoice::count());
    }

    public function test_marked_paid_by_transfer_is_collected_as_payment_order(): void
    {
        [, $sent] = $this->fakes();

        $this->order(order: ['payment_method' => 'transfer'])->markAsPaid('OP 12');

        $this->assertSame('Ordin de plata', $sent->payload['collect']['type']);
    }

    public function test_setting_off_keeps_the_previous_behaviour(): void
    {
        Queue::fake();

        $this->order(enabled: false)->markAsPaid('TX1');
        $this->order(enabled: false, order: ['tax' => 0, 'total' => 100])->markAsPaid('TX2');

        Queue::assertNothingPushed();
        $this->assertSame(1, Invoice::count());
    }

    public function test_order_without_vat_is_invoiced_when_enabled(): void
    {
        [, $sent] = $this->fakes();

        $this->order(order: ['tax' => 0, 'total' => 100, 'config' => []])->markAsPaid('TX1');

        $this->assertSame(1, $sent->issued);
        $this->assertSame(0.0, $sent->payload['lines'][0]['tax_rate']);
    }

    public function test_missing_billing_data_blocks_the_send(): void
    {
        [, $sent] = $this->fakes();

        $this->order(organizer: ['company_tax_id' => null])->markAsPaid('TX1');

        $this->assertSame(0, $sent->issued);
        $this->assertArrayHasKey('accounting_blocked_at', Invoice::first()->meta);
    }

    public function test_number_taken_by_another_marketplace_is_skipped(): void
    {
        Queue::fake();

        DB::table('invoices')->insert(['marketplace_client_id' => 999, 'number' => 'F-000001']);

        $this->order(enabled: false)->markAsPaid('TX1');

        $this->assertSame('F-000002', Invoice::where('marketplace_client_id', '!=', 999)->first()->number);
    }
}
