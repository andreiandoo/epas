<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module (bilete.online): the operator's LOCATION, e.g.
 * "Rezervația naturală Sfânta Ana". It owns the access tickets, experiences
 * and packages sold there (activities.location_id).
 *
 * Deliberately NOT the shared `venues` table: a venue row is shared by every
 * marketplace (Ambilet included), so an operator editing it would change it
 * everywhere. venue_id is only an optional pointer.
 *
 * JSON shapes:
 *   seasons            [{name, start "MM-DD", end "MM-DD", last_entry "HH:MM"|null,
 *                        schedule {mon:{open,close}|null, ..., sun}}]  (a season may wrap the new year)
 *   closed_dates       ["Y-m-d", ...]
 *   display_categories [{id, name, sort_order}]  groups products on the location page
 *   facilities         ["parking", "toilets", ...]
 *   lodging            accommodation info + booking links (info only, no inventory)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activity_locations')) {
            return;
        }

        Schema::create('activity_locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            // Owner. Null = created by the marketplace admin.
            $table->foreignId('marketplace_organizer_id')
                ->nullable()
                ->constrained('marketplace_organizers')
                ->nullOnDelete();
            $table->foreignId('venue_id')
                ->nullable()
                ->constrained('venues')
                ->nullOnDelete();
            $table->foreignId('marketplace_city_id')
                ->nullable()
                ->constrained('marketplace_cities')
                ->nullOnDelete();
            $table->unsignedBigInteger('marketplace_category_id')->nullable();
            $table->foreign('marketplace_category_id', 'activity_locations_category_fk')
                ->references('id')->on('marketplace_event_categories')->nullOnDelete();

            $table->string('slug', 191);
            $table->jsonb('name');
            $table->jsonb('subtitle')->nullable();
            $table->jsonb('short_description')->nullable();
            $table->jsonb('description')->nullable();

            $table->string('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('google_maps_url', 500)->nullable();

            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('website_url', 500)->nullable();

            $table->string('cover_image_url')->nullable();
            $table->jsonb('gallery')->nullable();
            $table->jsonb('facilities')->nullable();
            $table->jsonb('rules')->nullable();

            $table->jsonb('seasons')->nullable();
            $table->jsonb('closed_dates')->nullable();
            $table->unsignedSmallInteger('max_advance_days')->default(90);
            $table->jsonb('display_categories')->nullable();
            $table->jsonb('lodging')->nullable();

            $table->jsonb('faqs')->nullable();
            $table->jsonb('seo')->nullable();

            // First publication is approved by the marketplace admin.
            $table->string('review_status', 16)->default('draft'); // draft|pending|approved|rejected
            $table->boolean('is_published')->default(false);
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['marketplace_client_id', 'slug'], 'activity_locations_mp_slug_unique');
            $table->index(['marketplace_client_id', 'is_published'], 'activity_locations_mp_pub_idx');
            $table->index(['marketplace_organizer_id'], 'activity_locations_organizer_idx');
            $table->index(['marketplace_city_id', 'is_published'], 'activity_locations_city_pub_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_locations');
    }
};
