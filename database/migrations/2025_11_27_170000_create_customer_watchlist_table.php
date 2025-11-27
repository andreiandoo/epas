<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_watchlist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->timestamps();

            // Prevent duplicate entries
            $table->unique(['customer_id', 'event_id']);
            $table->index('customer_id');
            $table->index('event_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_watchlist');
    }
};
