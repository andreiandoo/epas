<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Link merch products to specific events (tour merch, event-exclusive items)
        Schema::create('merch_product_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merch_product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->integer('price_override_cents')->nullable()->comment('Event-specific price override');
            $table->integer('stock_override')->nullable()->comment('Event-specific stock limit');
            $table->boolean('is_bundle_only')->default(false)->comment('Only available as part of ticket+merch bundle');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['merch_product_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merch_product_event');
    }
};
