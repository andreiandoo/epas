<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates Feature Store tables for pre-computed aggregations and preferences.
     * These tables power the propensity scoring and audience builder.
     */
    public function up(): void
    {
        // Person daily aggregations
        Schema::create('fs_person_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('person_id')->constrained('core_customers')->onDelete('cascade');
            $table->date('date');
            $table->integer('views_count')->default(0);
            $table->integer('carts_count')->default(0);
            $table->integer('checkouts_count')->default(0);
            $table->integer('purchases_count')->default(0);
            $table->integer('attendance_count')->default(0);
            $table->decimal('gross_amount', 12, 2)->default(0);
            $table->decimal('net_amount', 12, 2)->default(0);
            $table->decimal('avg_order_value', 10, 2)->nullable();
            $table->integer('avg_decision_time_ms')->nullable();
            $table->decimal('discount_usage_rate', 5, 4)->nullable();
            $table->decimal('affiliate_rate', 5, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'date'], 'uniq_person_daily');
            $table->index(['tenant_id', 'date'], 'idx_person_daily_tenant_date');
            $table->index(['person_id', 'date'], 'idx_person_daily_person_date');
        });

        // Person affinity to artists (recency-weighted scores)
        Schema::create('fs_person_affinity_artist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('person_id')->constrained('core_customers')->onDelete('cascade');
            $table->foreignId('artist_id')->constrained('artists')->onDelete('cascade');
            $table->decimal('affinity_score', 8, 4);
            $table->integer('views_count')->default(0);
            $table->integer('purchases_count')->default(0);
            $table->integer('attendance_count')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'artist_id'], 'uniq_person_affinity_artist');
            $table->index(['tenant_id', 'person_id'], 'idx_affinity_artist_tenant_person');
            $table->index(['tenant_id', 'artist_id'], 'idx_affinity_artist_tenant_artist');
            $table->index(['affinity_score'], 'idx_affinity_artist_score');
        });

        // Person affinity to genres
        Schema::create('fs_person_affinity_genre', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('person_id')->constrained('core_customers')->onDelete('cascade');
            $table->string('genre', 100);
            $table->decimal('affinity_score', 8, 4);
            $table->integer('views_count')->default(0);
            $table->integer('purchases_count')->default(0);
            $table->integer('attendance_count')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'genre'], 'uniq_person_affinity_genre');
            $table->index(['tenant_id', 'person_id'], 'idx_affinity_genre_tenant_person');
            $table->index(['tenant_id', 'genre'], 'idx_affinity_genre_tenant_genre');
            $table->index(['affinity_score'], 'idx_affinity_genre_score');
        });

        // Person ticket preferences
        Schema::create('fs_person_ticket_pref', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('person_id')->constrained('core_customers')->onDelete('cascade');
            $table->string('ticket_category', 50); // GA, VIP, EarlyBird, Premium, etc.
            $table->integer('purchases_count')->default(0);
            $table->decimal('avg_price', 10, 2)->nullable();
            $table->decimal('preference_score', 5, 4)->nullable();
            $table->string('price_band', 20)->nullable(); // low, mid, high, premium
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'ticket_category'], 'uniq_person_ticket_pref');
            $table->index(['tenant_id', 'person_id'], 'idx_ticket_pref_tenant_person');
            $table->index(['ticket_category'], 'idx_ticket_pref_category');
        });

        // Event funnel metrics (hourly aggregations)
        Schema::create('fs_event_funnel_hourly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('event_entity_id')->constrained('events')->onDelete('cascade');
            $table->timestamp('hour');
            $table->integer('page_views')->default(0);
            $table->integer('ticket_selections')->default(0);
            $table->integer('add_to_carts')->default(0);
            $table->integer('checkout_starts')->default(0);
            $table->integer('payment_attempts')->default(0);
            $table->integer('orders_completed')->default(0);
            $table->decimal('revenue_gross', 12, 2)->default(0);
            $table->integer('avg_time_to_cart_ms')->nullable();
            $table->integer('avg_time_to_checkout_ms')->nullable();
            $table->integer('avg_checkout_duration_ms')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_entity_id', 'hour'], 'uniq_event_funnel_hourly');
            $table->index(['tenant_id', 'hour'], 'idx_funnel_tenant_hour');
            $table->index(['event_entity_id', 'hour'], 'idx_funnel_event_hour');
        });

        // Person city affinity (for geo-targeting)
        Schema::create('fs_person_affinity_city', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('person_id')->constrained('core_customers')->onDelete('cascade');
            $table->string('city', 100);
            $table->string('country_code', 2)->nullable();
            $table->decimal('affinity_score', 8, 4);
            $table->integer('views_count')->default(0);
            $table->integer('purchases_count')->default(0);
            $table->integer('attendance_count')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'city'], 'uniq_person_affinity_city');
            $table->index(['tenant_id', 'city'], 'idx_affinity_city_tenant_city');
        });

        // Person venue affinity
        Schema::create('fs_person_affinity_venue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');
            $table->foreignId('person_id')->constrained('core_customers')->onDelete('cascade');
            $table->foreignId('venue_id')->constrained('venues')->onDelete('cascade');
            $table->decimal('affinity_score', 8, 4);
            $table->integer('views_count')->default(0);
            $table->integer('purchases_count')->default(0);
            $table->integer('attendance_count')->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'venue_id'], 'uniq_person_affinity_venue');
            $table->index(['tenant_id', 'venue_id'], 'idx_affinity_venue_tenant_venue');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fs_person_affinity_venue');
        Schema::dropIfExists('fs_person_affinity_city');
        Schema::dropIfExists('fs_event_funnel_hourly');
        Schema::dropIfExists('fs_person_ticket_pref');
        Schema::dropIfExists('fs_person_affinity_genre');
        Schema::dropIfExists('fs_person_affinity_artist');
        Schema::dropIfExists('fs_person_daily');
    }
};
