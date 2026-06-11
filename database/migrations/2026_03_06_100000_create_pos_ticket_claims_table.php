<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_ticket_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->enum('status', ['pending', 'claimed', 'expired'])->default('pending');
            $table->timestamp('expires_at');

            // Denormalized event info (avoids Translatable complexity)
            $table->string('event_name');
            $table->string('event_date')->nullable();
            $table->string('venue_name')->nullable();

            // Step 1: required fields (filled by customer)
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();

            // Step 2: optional fields
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();

            $table->timestamp('claimed_at')->nullable();
            $table->timestamps();

            $table->index('token');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_ticket_claims');
    }
};
