<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seat_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_seating_id')->constrained('event_seating_layouts')->onDelete('cascade');
            $table->string('seat_uid', 32);
            $table->string('session_uid', 64);
            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            // Indexes for efficient queries
            $table->index(['event_seating_id', 'seat_uid']);
            $table->index(['session_uid']);
            $table->index('expires_at'); // Critical for cleanup job
            $table->index(['expires_at', 'event_seating_id']); // Compound for bulk cleanup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seat_holds');
    }
};
