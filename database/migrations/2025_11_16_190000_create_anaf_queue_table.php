<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('anaf_queue')) {
            return;
        }

        Schema::create('anaf_queue', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('invoice_id')->index();

            // Payload reference (path to XML file or JSON data)
            $table->string('payload_ref')->nullable()->comment('Path to stored XML or JSON payload');

            // Status workflow: queued → submitted → accepted/rejected/error
            $table->enum('status', ['queued', 'submitted', 'accepted', 'rejected', 'error'])
                ->default('queued')
                ->index();

            // Error tracking
            $table->text('error_message')->nullable();

            // ANAF remote identifiers and responses
            $table->json('anaf_ids')->nullable()->comment('Remote IDs, download index, etc.');

            // Retry logic
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(5);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            // ANAF response data
            $table->json('response_data')->nullable()->comment('Full ANAF response for audit');

            // Submission metadata
            $table->string('xml_hash')->nullable()->comment('SHA256 hash of submitted XML');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'invoice_id'], 'idx_tenant_invoice');
            $table->index(['status', 'next_retry_at'], 'idx_retry_queue');

            // Ensure one queue entry per invoice (idempotency)
            $table->unique(['tenant_id', 'invoice_id'], 'unique_invoice_submission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anaf_queue');
    }
};
