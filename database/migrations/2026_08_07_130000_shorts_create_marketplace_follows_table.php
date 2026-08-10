<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Follow graph for the "Following" feed segment (B2).
 *
 * Polymorphic on purpose: `favoriteArtists` already exists and could act as a
 * proxy, but it only covers artists — a viewer who follows an organiser or a
 * venue has nowhere to put that today, and the feed segment would be blind to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketplace_follows')) {
            return;
        }

        Schema::create('marketplace_follows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_customer_id')
                ->constrained('marketplace_customers')
                ->cascadeOnDelete();
            $table->nullableMorphs('followable'); // Artist|Tenant|Venue
            $table->timestamps();

            $table->unique(
                ['marketplace_customer_id', 'followable_type', 'followable_id'],
                'mp_follow_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_follows');
    }
};
