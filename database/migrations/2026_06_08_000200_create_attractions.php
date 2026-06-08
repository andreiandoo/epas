<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F4 — Attractions (points of interest) + attraction types.
 *
 * An attraction is a place worth visiting (Palatul Parlamentului, Castelul
 * Peleș, Centrul Vechi…). Activities link to attractions many-to-many so a
 * city page can surface "Atracții de neratat" and an activity can show the
 * attractions it covers. Per marketplace client; geo-located for the map.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attraction_types')) {
            Schema::create('attraction_types', function (Blueprint $t) {
                $t->id();
                $t->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
                $t->string('slug', 191);
                $t->json('name');
                $t->json('description')->nullable();
                $t->string('icon_emoji', 16)->nullable();
                $t->string('color', 16)->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_visible')->default(true);
                $t->timestamps();
                $t->unique(['marketplace_client_id', 'slug'], 'attraction_types_mp_slug_unique');
            });
        }

        if (! Schema::hasTable('attractions')) {
            Schema::create('attractions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
                $t->foreignId('attraction_type_id')->nullable()->constrained('attraction_types')->nullOnDelete();
                $t->unsignedBigInteger('marketplace_city_id')->nullable();
                $t->string('slug', 191);
                $t->json('name');
                $t->json('subtitle')->nullable();
                $t->json('description')->nullable();
                $t->string('cover_image_url')->nullable();
                $t->json('gallery')->nullable();
                $t->decimal('latitude', 10, 7)->nullable();
                $t->decimal('longitude', 10, 7)->nullable();
                $t->string('address')->nullable();
                $t->json('seo')->nullable();
                $t->json('faqs')->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_featured')->default(false);
                $t->boolean('is_visible')->default(true);
                $t->timestamps();
                $t->softDeletes();
                $t->unique(['marketplace_client_id', 'slug'], 'attractions_mp_slug_unique');
                $t->index(['marketplace_client_id', 'is_visible'], 'attractions_mp_vis_idx');
                $t->index(['marketplace_city_id', 'is_visible'], 'attractions_city_vis_idx');
                $t->index(['latitude', 'longitude'], 'attractions_geo_idx');
            });
        }

        if (! Schema::hasTable('activity_attraction')) {
            Schema::create('activity_attraction', function (Blueprint $t) {
                $t->id();
                $t->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
                $t->foreignId('attraction_id')->constrained('attractions')->cascadeOnDelete();
                $t->unsignedInteger('sort_order')->default(0);
                $t->unique(['activity_id', 'attraction_id'], 'activity_attraction_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_attraction');
        Schema::dropIfExists('attractions');
        Schema::dropIfExists('attraction_types');
    }
};
