<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_customer_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->foreignId('marketplace_customer_id')->constrained()->onDelete('cascade');

            // Polymorphic relationship for favoriteable items (event, artist, venue)
            $table->string('favoriteable_type', 100); // 'event', 'artist', 'venue'
            $table->unsignedBigInteger('favoriteable_id');

            $table->timestamps();

            // Each customer can only favorite an item once
            $table->unique(
                ['marketplace_customer_id', 'favoriteable_type', 'favoriteable_id'],
                'mcf_customer_favoriteable_unique'
            );

            // Index for listing favorites by type
            $table->index(['marketplace_customer_id', 'favoriteable_type', 'created_at'], 'mcf_customer_type_idx');
            $table->index(['marketplace_client_id', 'favoriteable_type', 'favoriteable_id'], 'mcf_client_favoriteable_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_customer_favorites');
    }
};
