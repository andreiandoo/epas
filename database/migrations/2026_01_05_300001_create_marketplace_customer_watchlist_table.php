<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_customer_watchlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_customer_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_event_id')->constrained()->onDelete('cascade');

            $table->boolean('notify_on_sale')->default(true);
            $table->boolean('notify_on_price_drop')->default(false);

            $table->timestamps();

            // Each customer can only have an event in watchlist once
            $table->unique(['marketplace_customer_id', 'marketplace_event_id'], 'mcw_customer_event_unique');

            // Index for listing
            $table->index(['marketplace_customer_id', 'created_at'], 'mcw_customer_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_customer_watchlist');
    }
};
