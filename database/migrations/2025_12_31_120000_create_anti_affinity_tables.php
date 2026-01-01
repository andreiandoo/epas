<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anti-affinity for artists (negative signals - bounces, quick exits)
        Schema::create('fs_person_anti_affinity_artist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('core_customers')->cascadeOnDelete();
            $table->unsignedBigInteger('artist_id');
            $table->integer('bounce_count')->default(0); // Quick exits from artist page
            $table->integer('view_count')->default(0); // Total views (to calculate ratio)
            $table->float('anti_affinity_score')->default(0); // bounce_count / view_count
            $table->integer('avg_time_on_page_ms')->nullable(); // Short time = negative signal
            $table->timestamp('last_bounce_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'artist_id']);
            $table->index(['tenant_id', 'artist_id']);
            $table->index(['tenant_id', 'anti_affinity_score']);
        });

        // Anti-affinity for genres
        Schema::create('fs_person_anti_affinity_genre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('core_customers')->cascadeOnDelete();
            $table->string('genre', 100);
            $table->integer('bounce_count')->default(0);
            $table->integer('view_count')->default(0);
            $table->float('anti_affinity_score')->default(0);
            $table->integer('avg_time_on_page_ms')->nullable();
            $table->timestamp('last_bounce_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'genre']);
            $table->index(['tenant_id', 'genre']);
            $table->index(['tenant_id', 'anti_affinity_score']);
        });

        // Anti-affinity for events (viewed but explicitly not interested)
        Schema::create('fs_person_anti_affinity_event', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('core_customers')->cascadeOnDelete();
            $table->unsignedBigInteger('event_entity_id');
            $table->integer('view_count')->default(0);
            $table->integer('bounce_count')->default(0);
            $table->boolean('cart_abandoned')->default(false); // Added to cart but abandoned
            $table->boolean('checkout_abandoned')->default(false); // Started checkout but abandoned
            $table->float('anti_affinity_score')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'event_entity_id']);
            $table->index(['tenant_id', 'event_entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_person_anti_affinity_event');
        Schema::dropIfExists('fs_person_anti_affinity_genre');
        Schema::dropIfExists('fs_person_anti_affinity_artist');
    }
};
