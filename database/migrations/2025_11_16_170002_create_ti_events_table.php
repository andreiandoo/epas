<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Ticket Insurance - Events/Audit Log
     * Tracks all policy-related events
     */
    public function up(): void
    {
        if (Schema::hasTable('ti_events')) {
            return;
        }

        Schema::create('ti_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('policy_id')->constrained('ti_policies')->onDelete('cascade');

            // Event type
            $table->enum('type', [
                'issue',         // Policy issued
                'void',          // Policy voided
                'refund',        // Policy refunded
                'status_sync',   // Status sync with provider
                'error',         // Error occurred
                'notification'   // Notification sent to customer
            ])->index();

            // Event payload (JSON)
            $table->json('payload')->nullable()->comment('Event-specific data');
            // Example for 'issue': {
            //   "policy_number": "POL-123",
            //   "policy_doc_url": "https://...",
            //   "issued_at": "2025-11-16T10:00:00Z"
            // }
            // Example for 'error': {
            //   "code": "PROVIDER_ERROR",
            //   "message": "Failed to contact insurance provider",
            //   "details": {...}
            // }

            $table->timestamp('created_at');

            // Indexes
            $table->index(['policy_id', 'type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ti_events');
    }
};
