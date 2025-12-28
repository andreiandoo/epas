<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_seats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_seating_id')->constrained('event_seating_layouts')->onDelete('cascade');
            $table->string('seat_uid', 32);
            $table->string('section_name')->nullable();
            $table->string('row_label', 10)->nullable();
            $table->string('seat_label', 10)->nullable();
            $table->foreignId('price_tier_id')->nullable()->constrained('price_tiers')->onDelete('set null');
            $table->integer('price_cents_override')->nullable();
            $table->enum('status', ['available', 'held', 'sold', 'blocked', 'disabled'])->default('available');
            $table->integer('version')->default(1);
            $table->timestamp('last_change_at')->useCurrent();
            $table->timestamps();

            // Critical indexes for performance
            $table->index(['event_seating_id', 'status']);
            $table->unique(['event_seating_id', 'seat_uid']);
            $table->index('last_change_at');
            $table->index(['status', 'last_change_at']); // For expired hold cleanup
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_seats');
    }
};
