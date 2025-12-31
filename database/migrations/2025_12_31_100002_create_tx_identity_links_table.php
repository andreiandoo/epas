<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates tx_identity_links table for identity stitching (visitor_id -> person_id).
     * This enables linking anonymous browsing behavior to identified customers after
     * order completion and consent.
     */
    public function up(): void
    {
        Schema::create('tx_identity_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->string('visitor_id', 64);
            $table->foreignId('person_id')->constrained('core_customers')->onDelete('cascade');
            $table->decimal('confidence', 3, 2)->default(1.00);
            $table->timestamp('linked_at')->useCurrent();
            $table->string('link_source', 50); // 'order_completed', 'login', 'manual', 'registration'
            $table->foreignId('order_id')->nullable()->constrained('orders')->onDelete('set null');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            // Ensure unique visitor->person links per tenant
            $table->unique(['tenant_id', 'visitor_id', 'person_id'], 'uniq_identity_link');

            // Indexes for fast lookups
            $table->index(['tenant_id', 'visitor_id'], 'idx_identity_visitor');
            $table->index(['tenant_id', 'person_id'], 'idx_identity_person');
            $table->index(['link_source'], 'idx_identity_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tx_identity_links');
    }
};
