<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_customer_notifications')) {
            return;
        }

        Schema::create('marketplace_customer_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketplace_client_id');
            $table->unsignedBigInteger('marketplace_customer_id');

            // Use short FK names to stay under MySQL's 64 char limit
            $table->foreign('marketplace_client_id', 'mcn_client_fk')
                ->references('id')->on('marketplace_clients')->onDelete('cascade');
            $table->foreign('marketplace_customer_id', 'mcn_customer_fk')
                ->references('id')->on('marketplace_customers')->onDelete('cascade');

            $table->string('type', 50); // order_confirmed, ticket_ready, event_reminder, reward_earned, etc.
            $table->string('title');
            $table->text('message');
            $table->string('icon', 50)->nullable(); // Icon name or emoji
            $table->string('action_url')->nullable(); // Link to navigate to
            $table->string('action_text', 50)->nullable(); // Button text

            $table->json('data')->nullable(); // Additional data (order_id, event_id, etc.)

            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            // Indexes for efficient queries
            $table->index(['marketplace_customer_id', 'read_at', 'created_at'], 'mcn_customer_unread_idx');
            $table->index(['marketplace_client_id', 'type'], 'mcn_client_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_customer_notifications');
    }
};
