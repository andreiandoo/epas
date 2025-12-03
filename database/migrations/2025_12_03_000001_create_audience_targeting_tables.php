<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Customer profiles - enriched customer data for targeting
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            // Purchase metrics
            $table->unsignedInteger('purchase_count')->default(0);
            $table->unsignedBigInteger('total_spent_cents')->default(0);
            $table->unsignedBigInteger('avg_order_cents')->default(0);
            $table->timestamp('first_purchase_at')->nullable();
            $table->timestamp('last_purchase_at')->nullable();

            // Preferences (derived from purchase history)
            $table->json('preferred_genres')->nullable(); // [{slug: 'rock', weight: 0.8}, ...]
            $table->json('preferred_event_types')->nullable(); // [{slug: 'concert', weight: 0.9}, ...]
            $table->json('preferred_price_range')->nullable(); // {min: 2000, max: 15000} in cents
            $table->json('preferred_days')->nullable(); // ['friday', 'saturday']
            $table->json('attended_events')->nullable(); // [event_id, ...]

            // Engagement metrics
            $table->unsignedTinyInteger('engagement_score')->default(0); // 0-100
            $table->unsignedTinyInteger('churn_risk')->default(50); // 0-100
            $table->unsignedInteger('page_views_30d')->default(0);
            $table->unsignedInteger('cart_adds_30d')->default(0);
            $table->unsignedInteger('email_opens_30d')->default(0);
            $table->unsignedInteger('email_clicks_30d')->default(0);

            // Location data
            $table->json('location_data')->nullable(); // {city, country, lat, lng}

            // Calculation metadata
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'engagement_score']);
            $table->index(['tenant_id', 'last_purchase_at']);
            $table->index(['tenant_id', 'total_spent_cents']);
        });

        // Audience segments - define groups of customers
        Schema::create('audience_segments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();

            // Segment configuration
            $table->enum('segment_type', ['dynamic', 'static', 'lookalike'])->default('dynamic');
            $table->json('criteria')->nullable(); // Rules for dynamic segments
            $table->foreignId('source_segment_id')->nullable()->constrained('audience_segments')->nullOnDelete(); // For lookalike

            // Cached counts
            $table->unsignedInteger('customer_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();

            // Status
            $table->enum('status', ['active', 'paused', 'archived'])->default('active');
            $table->boolean('auto_refresh')->default(true);
            $table->unsignedSmallInteger('refresh_interval_hours')->default(24);

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'segment_type']);
        });

        // Segment membership pivot table
        Schema::create('audience_segment_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('segment_id')->constrained('audience_segments')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('score')->default(100); // 0-100 affinity score
            $table->enum('source', ['rule', 'manual', 'import', 'ml'])->default('rule');
            $table->timestamp('added_at');

            $table->unique(['segment_id', 'customer_id']);
            $table->index(['segment_id', 'score']);
        });

        // Event recommendations - AI-driven event-customer matching
        Schema::create('event_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('match_score'); // 0-100
            $table->json('match_reasons')->nullable(); // [{reason: 'genre_match', weight: 30}, ...]

            // Notification tracking
            $table->json('notified_via')->nullable(); // ['email', 'push']
            $table->timestamp('notified_at')->nullable();

            // Conversion tracking
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->unique(['tenant_id', 'event_id', 'customer_id']);
            $table->index(['tenant_id', 'event_id', 'match_score']);
            $table->index(['tenant_id', 'customer_id']);
        });

        // Audience campaigns - marketing campaigns
        Schema::create('audience_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('audience_segments')->nullOnDelete();
            $table->foreignId('event_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('campaign_type', ['email', 'meta_ads', 'google_ads', 'tiktok_ads', 'multi_channel']);

            // Status and scheduling
            $table->enum('status', ['draft', 'scheduled', 'active', 'paused', 'completed', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            // Platform-specific settings
            $table->json('settings')->nullable();
            /*
            For email: {subject, template_id, sender_name, sender_email}
            For meta_ads: {ad_account_id, campaign_objective, budget_cents, duration_days}
            For google_ads: {customer_id, campaign_type, budget_cents}
            For tiktok_ads: {advertiser_id, objective, budget_cents}
            */

            // Results (populated after completion)
            $table->json('results')->nullable();
            /*
            {
                sent: 1000, delivered: 980, opens: 450, clicks: 120,
                conversions: 25, revenue_cents: 250000, cost_cents: 5000,
                ctr: 0.12, conversion_rate: 0.025, roas: 50.0
            }
            */

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'campaign_type']);
            $table->index(['tenant_id', 'scheduled_at']);
        });

        // Audience exports - track exports to ad platforms
        Schema::create('audience_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('segment_id')->constrained('audience_segments')->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained('audience_campaigns')->nullOnDelete();

            $table->enum('platform', ['meta', 'google', 'tiktok', 'brevo']);
            $table->enum('export_type', ['custom_audience', 'lookalike', 'email_list', 'contact_sync']);

            // External platform references
            $table->string('external_audience_id')->nullable();
            $table->string('external_audience_name')->nullable();

            // Export metadata
            $table->unsignedInteger('customer_count')->default(0);
            $table->unsignedInteger('matched_count')->nullable(); // Platform's match count
            $table->decimal('match_rate', 5, 2)->nullable(); // Percentage matched

            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();

            $table->timestamp('exported_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'platform']);
            $table->index(['tenant_id', 'segment_id']);
            $table->index(['segment_id', 'platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audience_exports');
        Schema::dropIfExists('audience_campaigns');
        Schema::dropIfExists('event_recommendations');
        Schema::dropIfExists('audience_segment_customers');
        Schema::dropIfExists('audience_segments');
        Schema::dropIfExists('customer_profiles');
    }
};
