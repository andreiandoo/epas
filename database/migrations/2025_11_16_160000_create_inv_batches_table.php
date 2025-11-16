<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Invitations Microservice - Batch Management
     * Stores invitation batches for events
     */
    public function up(): void
    {
        Schema::create('inv_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('event_ref')->index()->comment('Reference to event (external or internal)');
            $table->string('name')->comment('Batch name for identification');
            $table->integer('qty_planned')->comment('Planned number of invitations');
            $table->foreignUuid('template_id')->nullable()->constrained('ticket_templates')->onDelete('set null');

            // Options stored as JSON
            $table->json('options')->comment('Batch options: watermark, seat_mode, notes, etc.');
            // Example: {
            //   "watermark": "INVITATION",
            //   "seat_mode": "auto|manual|none",
            //   "notes": "VIP guests for opening night",
            //   "send_immediately": false,
            //   "expiry_date": "2025-12-31"
            // }

            // Status tracking
            $table->enum('status', ['draft', 'rendering', 'ready', 'sending', 'completed', 'cancelled'])
                  ->default('draft')
                  ->index();

            // Statistics (denormalized for performance)
            $table->integer('qty_generated')->default(0);
            $table->integer('qty_rendered')->default(0);
            $table->integer('qty_emailed')->default(0);
            $table->integer('qty_downloaded')->default(0);
            $table->integer('qty_opened')->default(0);
            $table->integer('qty_checked_in')->default(0);
            $table->integer('qty_voided')->default(0);

            // Audit
            $table->foreignUuid('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_batches');
    }
};
