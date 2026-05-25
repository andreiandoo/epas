<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activities module — A1 / step 1: core `activities` table.
 *
 * Distinct from `events`: an Activity runs on a weekly schedule (Mo-Su
 * operating hours) and customers book by picking a date + time slot.
 * No fiscal declaration, no artists, no postpone/cancel, no calendar-
 * fixed date. Reuses MarketplaceOrganizer, Venue, MarketplaceCity, and
 * MarketplaceEventCategory (aliased as MarketplaceCategory).
 *
 * Non-breaking: brand new table. Nothing else references it yet.
 * Activated per marketplace via the `activities-module` microservice
 * toggle; until that toggle is on for a marketplace, no code path here
 * fires.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('activities')) {
            return;
        }

        Schema::create('activities', function (Blueprint $table) {
            $table->id();

            // Multi-tenancy — every row is scoped to one marketplace.
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            // The organizer (locația de pe bilete.online) that owns and operates this activity.
            $table->foreignId('marketplace_organizer_id')
                ->nullable()
                ->constrained('marketplace_organizers')
                ->nullOnDelete();

            // Physical venue (reuses the venues table — same locații table as Events).
            $table->foreignId('venue_id')
                ->nullable()
                ->constrained('venues')
                ->nullOnDelete();

            // Geo + taxonomy (reuses marketplace_cities / marketplace_event_categories).
            $table->foreignId('marketplace_city_id')
                ->nullable()
                ->constrained('marketplace_cities')
                ->nullOnDelete();

            // Category + optional subcategory. Both FK to `marketplace_event_categories`
            // (aliased as MarketplaceCategory in PHP). FKs use bare unsignedBigInteger +
            // explicit foreign() instead of constrained() so the SAME parent table can be
            // referenced twice with different column names without auto-naming collisions.
            $table->unsignedBigInteger('marketplace_category_id')->nullable();
            $table->unsignedBigInteger('marketplace_subcategory_id')->nullable();
            $table->foreign('marketplace_category_id', 'activities_category_fk')
                ->references('id')->on('marketplace_event_categories')->nullOnDelete();
            $table->foreign('marketplace_subcategory_id', 'activities_subcategory_fk')
                ->references('id')->on('marketplace_event_categories')->nullOnDelete();

            // Identity — translatable (Romanian + English keys at minimum).
            $table->jsonb('title');
            $table->string('slug', 191);
            $table->jsonb('subtitle')->nullable();
            $table->jsonb('short_description')->nullable();
            $table->jsonb('description')->nullable();

            // Operating timing — minutes everywhere for math sanity.
            $table->unsignedSmallInteger('duration_minutes')->default(60);          // one session length
            $table->unsignedSmallInteger('slot_interval_minutes')->default(60);     // gap between slot starts
            $table->unsignedSmallInteger('buffer_minutes')->default(0);             // cleanup/reset between sessions
            $table->unsignedSmallInteger('capacity_per_slot')->default(1);          // max people per session
            $table->unsignedSmallInteger('min_participants')->default(1);           // per booking
            $table->unsignedSmallInteger('max_participants')->default(10);          // per booking

            // Booking window constraints.
            $table->unsignedSmallInteger('booking_lead_time_hours')->default(2);    // can't book closer than N hours
            $table->unsignedSmallInteger('booking_max_advance_days')->default(60);  // can't book further than N days
            $table->text('cancellation_policy')->nullable();

            // Media.
            $table->string('cover_image_url')->nullable();
            $table->string('hero_image_url')->nullable();
            $table->jsonb('gallery')->nullable();

            // Content blocks shown on the public page.
            $table->jsonb('included_items')->nullable();      // ["1h escape room", "drink de bun venit"]
            $table->jsonb('not_included')->nullable();
            $table->jsonb('requirements')->nullable();        // ["minim 14 ani", "pantofi sport"]
            $table->text('meeting_point')->nullable();
            $table->jsonb('languages_offered')->nullable();   // ["ro", "en"]

            // Intent flags — drive the SEO landing filters (same shape as events).
            $table->boolean('is_indoor')->default(false);
            $table->boolean('is_outdoor')->default(false);
            $table->boolean('is_kid_friendly')->default(false);
            $table->boolean('is_accessible')->default(false);
            $table->boolean('is_weather_sensitive')->default(false);
            $table->unsignedSmallInteger('age_min')->nullable();
            $table->unsignedSmallInteger('age_max')->nullable();

            // Difficulty: easy | medium | hard | expert. Plain string (no DB-level enum
            // for portability — app layer validates).
            $table->string('difficulty_level', 16)->nullable();

            // Publishing + featuring (mirrors the Event flags so the same listing code
            // can promote either kind of card).
            $table->boolean('is_published')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_homepage_featured')->default(false);
            $table->boolean('is_category_featured')->default(false);
            $table->boolean('is_city_featured')->default(false);

            // SEO body (RichEditor HTML) + admin-managed FAQs — same pattern as
            // MarketplaceEventCategory / MarketplaceCity (seo_body_title, seo_body, faqs).
            $table->jsonb('seo')->nullable();
            $table->jsonb('seo_body_title')->nullable();
            $table->jsonb('seo_body')->nullable();
            $table->jsonb('faqs')->nullable();

            // Cached aggregates — recomputed by activities:refresh-cheapest-price /
            // activities:refresh-intent-aggregates (parallels the Event commands).
            $table->integer('cheapest_price_cents')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('interested_count')->default(0);
            $table->dateTime('next_session_at')->nullable();
            $table->boolean('has_session_today')->default(false);
            $table->boolean('has_session_tomorrow')->default(false);
            $table->boolean('has_session_this_weekend')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Slug must be unique within a marketplace, not globally (two marketplaces
            // can each have an "escape-room-camera-13"). Used by the public page resolver.
            $table->unique(['marketplace_client_id', 'slug'], 'activities_mp_slug_unique');

            // Listing queries: filter by city + category + published.
            $table->index(['marketplace_client_id', 'is_published'], 'activities_mp_pub_idx');
            $table->index(['marketplace_city_id', 'is_published'], 'activities_city_pub_idx');
            $table->index(['marketplace_category_id', 'is_published'], 'activities_cat_pub_idx');
            $table->index(['marketplace_organizer_id'], 'activities_organizer_idx');
            $table->index(['next_session_at'], 'activities_next_session_idx');
            $table->index(['has_session_today'], 'activities_today_idx');
            $table->index(['has_session_this_weekend'], 'activities_weekend_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
