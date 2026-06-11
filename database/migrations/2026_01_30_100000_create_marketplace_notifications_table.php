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
        Schema::create('marketplace_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();

            // Notification type for filtering and grouping
            $table->string('type', 50); // ticket_sale, refund_request, organizer_registration, etc.

            // Display content
            $table->string('title');
            $table->text('message')->nullable();

            // Visual styling
            $table->string('icon', 50)->default('heroicon-o-bell');
            $table->string('color', 20)->default('primary'); // primary, success, warning, danger, info

            // Additional data for the notification (JSON)
            $table->json('data')->nullable();

            // Polymorphic relation to the related model (Event, Order, Organizer, etc.)
            $table->nullableMorphs('actionable');

            // Action URL for clicking the notification
            $table->string('action_url')->nullable();

            // Read status
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            // Indexes for performance
            $table->index(['marketplace_client_id', 'read_at']);
            $table->index(['marketplace_client_id', 'type']);
            $table->index(['marketplace_client_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_notifications');
    }
};
