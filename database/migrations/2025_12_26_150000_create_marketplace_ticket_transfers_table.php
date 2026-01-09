<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_ticket_transfers')) {
            return;
        }

        Schema::create('marketplace_ticket_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();

            // From
            $table->foreignId('from_customer_id')->nullable()->constrained('marketplace_customers')->nullOnDelete();
            $table->string('from_email');
            $table->string('from_name');

            // To
            $table->string('to_email');
            $table->string('to_name');
            $table->foreignId('to_customer_id')->nullable()->constrained('marketplace_customers')->nullOnDelete();

            // Transfer details
            $table->string('token')->unique();
            $table->string('status')->default('pending'); // pending, accepted, rejected, expired, cancelled
            $table->text('message')->nullable();

            // Timestamps
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['to_email', 'status']);
            $table->index(['token', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_ticket_transfers');
    }
};
