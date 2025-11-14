<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dynamic_price_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_seating_id')->constrained('event_seating_layouts')->onDelete('cascade');
            $table->string('seat_uid', 32)->nullable();
            $table->string('section_ref')->nullable(); // For section-wide overrides
            $table->string('row_ref')->nullable(); // For row-wide overrides
            $table->integer('price_cents');
            $table->foreignId('source_rule_id')->nullable()->constrained('dynamic_pricing_rules')->onDelete('set null');
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->index(['event_seating_id', 'seat_uid']);
            $table->index(['event_seating_id', 'effective_from', 'effective_to']);
            $table->index('source_rule_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dynamic_price_overrides');
    }
};
