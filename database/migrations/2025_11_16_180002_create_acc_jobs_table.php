<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Accounting Connectors - Job Queue
     * Async job processing for accounting operations
     */
    public function up(): void
    {
        Schema::create('acc_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');

            // Job type
            $table->enum('type', [
                'create_invoice',
                'get_pdf',
                'create_credit_note',
                'sync_customer',
                'sync_products',
                'test_connection'
            ])->index();

            // Job payload (JSON)
            $table->json('payload')->comment('Job-specific data');
            // Example for create_invoice: {
            //   "order_ref": "order-123",
            //   "customer": {...},
            //   "lines": [...],
            //   "totals": {...}
            // }

            // Status
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed',
                'manual_required'
            ])->default('pending')->index();

            // Retry logic
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(3);
            $table->timestamp('next_retry_at')->nullable();

            // Results
            $table->json('result')->nullable()->comment('Job result data');
            // Example: {
            //   "external_ref": "INV-2025-001",
            //   "invoice_number": "FACT0001",
            //   "pdf_url": "https://...",
            //   "efactura_id": "123456"
            // }

            // Error tracking
            $table->text('last_error')->nullable();
            $table->json('error_details')->nullable();

            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'type']);
            $table->index('next_retry_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acc_jobs');
    }
};
