<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ==========================================
        // ADS SERVICE REQUESTS (from organizers)
        // ==========================================
        if (!Schema::hasTable('ads_service_requests')) {
            Schema::create('ads_service_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('set null');
                $table->foreignId('marketplace_client_id')->nullable()->constrained('marketplace_clients')->onDelete('set null');
                $table->foreignId('marketplace_organizer_id')->nullable()->constrained('marketplace_organizers')->onDelete('set null');

                // Request details
                $table->string('name');
                $table->text('brief')->nullable();
                $table->json('target_platforms'); // ['facebook','instagram','google']
                $table->json('creative_types')->nullable(); // ['image','video','carousel']

                // Budget
                $table->decimal('budget', 12, 2);
                $table->decimal('service_fee', 12, 2)->default(0);
                $table->string('currency', 3)->default('EUR');

                // Materials provided by organizer
                $table->json('materials')->nullable(); // [{type, path, filename, mime}]
                $table->json('brand_guidelines')->nullable(); // colors, fonts, tone

                // Target audience hints from organizer
                $table->json('audience_hints')->nullable(); // {age_range, interests, locations}

                // Status
                $table->enum('status', [
                    'pending',        // Just submitted
                    'under_review',   // Admin reviewing
                    'approved',       // Ready to create campaign
                    'rejected',       // Not suitable
                    'in_progress',    // Campaign being built
                    'completed',      // Campaign launched
                    'cancelled',      // Organizer cancelled
                ])->default('pending');
                $table->text('review_notes')->nullable();

                // Payment
                $table->enum('payment_status', ['pending', 'paid', 'refunded', 'failed'])->default('pending');
                $table->string('payment_reference')->nullable();
                $table->timestamp('paid_at')->nullable();

                // Tracking
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'status']);
                $table->index(['event_id']);
                $table->index(['payment_status']);
            });
        }

        // ==========================================
        // ADS CAMPAIGNS (main orchestration)
        // ==========================================
        if (!Schema::hasTable('ads_campaigns')) {
            Schema::create('ads_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('event_id')->nullable()->constrained('events')->onDelete('set null');
                $table->foreignId('service_request_id')->nullable()->constrained('ads_service_requests')->onDelete('set null');
                $table->foreignId('marketplace_client_id')->nullable()->constrained('marketplace_clients')->onDelete('set null');

                // Campaign info
                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('objective', [
                    'conversions',    // Ticket sales
                    'traffic',        // Website visits
                    'awareness',      // Brand awareness / reach
                    'engagement',     // Post engagement
                    'leads',          // Lead generation
                ])->default('conversions');

                // Budget
                $table->decimal('total_budget', 12, 2);
                $table->decimal('daily_budget', 12, 2)->nullable();
                $table->decimal('spent_budget', 12, 2)->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->enum('budget_allocation', ['equal', 'performance', 'manual'])->default('performance');

                // Schedule
                $table->timestamp('start_date')->nullable();
                $table->timestamp('end_date')->nullable();

                // Platforms
                $table->json('target_platforms'); // ['facebook','instagram','google']

                // A/B Testing
                $table->boolean('ab_testing_enabled')->default(false);
                $table->enum('ab_test_variable', ['creative', 'audience', 'placement', 'copy'])->nullable();
                $table->integer('ab_test_split_percentage')->default(50); // % for variant A
                $table->timestamp('ab_test_winner_date')->nullable();
                $table->string('ab_test_winner')->nullable(); // 'A' or 'B'
                $table->enum('ab_test_metric', ['ctr', 'conversions', 'cpc', 'roas'])->nullable();

                // Auto-optimization
                $table->boolean('auto_optimize')->default(true);
                $table->json('optimization_rules')->nullable(); // {pause_if_cpc_above: 5, boost_if_roas_above: 3}
                $table->enum('optimization_goal', ['conversions', 'clicks', 'impressions', 'roas'])->default('conversions');
                $table->timestamp('last_optimized_at')->nullable();

                // Retargeting
                $table->boolean('retargeting_enabled')->default(true);
                $table->json('retargeting_config')->nullable(); // {website_visitors: true, cart_abandoners: true, past_attendees: true, lookalike_percentage: 2}

                // Conversion tracking link
                $table->string('tracking_url')->nullable();
                $table->string('utm_source')->nullable();
                $table->string('utm_medium')->nullable();
                $table->string('utm_campaign')->nullable();
                $table->string('utm_content')->nullable();

                // Status
                $table->enum('status', [
                    'draft',
                    'pending_review',
                    'approved',
                    'launching',
                    'active',
                    'paused',
                    'optimizing',
                    'completed',
                    'failed',
                    'archived',
                ])->default('draft');
                $table->text('status_notes')->nullable();

                // Aggregate performance (cached, updated by sync job)
                $table->bigInteger('total_impressions')->default(0);
                $table->bigInteger('total_clicks')->default(0);
                $table->bigInteger('total_conversions')->default(0);
                $table->decimal('total_spend', 12, 2)->default(0);
                $table->decimal('total_revenue', 12, 2)->default(0);
                $table->decimal('avg_ctr', 8, 4)->default(0);
                $table->decimal('avg_cpc', 8, 4)->default(0);
                $table->decimal('avg_cpm', 8, 4)->default(0);
                $table->decimal('roas', 8, 4)->default(0);
                $table->decimal('cac', 8, 4)->default(0);

                $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'status']);
                $table->index(['event_id', 'status']);
                $table->index(['start_date', 'end_date']);
            });
        }

        // ==========================================
        // ADS CAMPAIGN CREATIVES
        // ==========================================
        if (!Schema::hasTable('ads_campaign_creatives')) {
            Schema::create('ads_campaign_creatives', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('ads_campaigns')->onDelete('cascade');

                // Creative content
                $table->enum('type', ['image', 'video', 'carousel', 'stories', 'reels']);
                $table->string('headline', 255)->nullable();
                $table->text('primary_text')->nullable();
                $table->text('description')->nullable();
                $table->string('cta_type')->nullable(); // LEARN_MORE, SHOP_NOW, SIGN_UP, GET_TICKETS, BOOK_NOW
                $table->string('cta_url')->nullable();
                $table->string('display_url')->nullable();

                // Media
                $table->string('media_path')->nullable();
                $table->string('media_url')->nullable();
                $table->string('media_type')->nullable(); // image/jpeg, video/mp4
                $table->string('thumbnail_path')->nullable();
                $table->integer('media_width')->nullable();
                $table->integer('media_height')->nullable();
                $table->integer('media_duration')->nullable(); // seconds for video
                $table->bigInteger('media_size')->nullable(); // bytes

                // Carousel items (JSON array)
                $table->json('carousel_items')->nullable(); // [{image_path, headline, description, url}]

                // A/B Testing
                $table->string('variant_label')->nullable(); // 'A', 'B', 'C'
                $table->boolean('is_winner')->default(false);

                // Platform-specific overrides
                $table->json('facebook_overrides')->nullable();
                $table->json('instagram_overrides')->nullable();
                $table->json('google_overrides')->nullable();

                // Performance (cached)
                $table->bigInteger('impressions')->default(0);
                $table->bigInteger('clicks')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->decimal('spend', 12, 2)->default(0);
                $table->integer('conversions')->default(0);

                // Status
                $table->enum('status', ['draft', 'pending_review', 'approved', 'active', 'paused', 'rejected'])->default('draft');
                $table->text('rejection_reason')->nullable();

                $table->integer('sort_order')->default(0);
                $table->timestamps();

                $table->index(['campaign_id', 'variant_label']);
                $table->index(['campaign_id', 'status']);
            });
        }

        // ==========================================
        // ADS CAMPAIGN TARGETING
        // ==========================================
        if (!Schema::hasTable('ads_campaign_targeting')) {
            Schema::create('ads_campaign_targeting', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('ads_campaigns')->onDelete('cascade');

                // Demographics
                $table->integer('age_min')->default(18);
                $table->integer('age_max')->default(65);
                $table->json('genders')->nullable(); // ['all'], ['male'], ['female'], ['male','female']
                $table->json('languages')->nullable(); // ['en','ro','hu']

                // Location targeting
                $table->json('locations')->nullable(); // [{type: 'country/city/region', id, name, radius_km}]
                $table->json('excluded_locations')->nullable();
                $table->enum('location_type', ['everyone', 'living_in', 'recently_in', 'traveling_in'])->default('everyone');

                // Interest targeting
                $table->json('interests')->nullable(); // [{id, name, platform}] - festivals, concerts, nightlife
                $table->json('behaviors')->nullable(); // [{id, name}] - frequent travelers, event goers
                $table->json('demographics_detailed')->nullable(); // education, work, relationship

                // Custom audiences
                $table->json('custom_audience_ids')->nullable(); // IDs from Facebook/Google custom audiences
                $table->json('lookalike_config')->nullable(); // {source_audience_id, percentage: 1-10, country}
                $table->json('excluded_audience_ids')->nullable();

                // Placement
                $table->json('placements')->nullable(); // {facebook: [feed,stories,reels], instagram: [feed,stories,explore], google: [search,display,youtube]}
                $table->boolean('automatic_placements')->default(true);

                // Scheduling
                $table->json('ad_schedule')->nullable(); // [{day: 'monday', start: '09:00', end: '23:00'}]
                $table->string('timezone')->nullable();

                // Device targeting
                $table->json('devices')->nullable(); // ['mobile', 'desktop', 'tablet']
                $table->json('operating_systems')->nullable(); // ['ios', 'android']

                // A/B Testing variant
                $table->string('variant_label')->nullable(); // 'A', 'B'

                $table->timestamps();

                $table->index('campaign_id');
            });
        }

        // ==========================================
        // ADS PLATFORM CAMPAIGNS (per-platform tracking)
        // ==========================================
        if (!Schema::hasTable('ads_platform_campaigns')) {
            Schema::create('ads_platform_campaigns', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('ads_campaigns')->onDelete('cascade');

                // Platform identification
                $table->enum('platform', ['facebook', 'instagram', 'google', 'tiktok']);

                // Platform IDs (returned by APIs after creation)
                $table->string('platform_campaign_id')->nullable();
                $table->string('platform_adset_id')->nullable();
                $table->string('platform_ad_id')->nullable();
                $table->string('platform_creative_id')->nullable();

                // Platform-specific config
                $table->string('platform_objective')->nullable(); // Platform's objective mapping
                $table->string('bid_strategy')->nullable(); // LOWEST_COST, TARGET_CPA, MANUAL
                $table->decimal('bid_amount', 10, 2)->nullable();
                $table->decimal('budget_allocated', 12, 2)->default(0);
                $table->decimal('daily_budget', 12, 2)->nullable();

                // A/B variant
                $table->string('variant_label')->nullable();

                // Status
                $table->enum('status', [
                    'draft',
                    'pending_creation',
                    'creating',
                    'active',
                    'paused',
                    'ended',
                    'failed',
                    'deleted',
                ])->default('draft');
                $table->text('error_message')->nullable();
                $table->json('api_response')->nullable();

                // Performance (synced from platform)
                $table->bigInteger('impressions')->default(0);
                $table->bigInteger('reach')->default(0);
                $table->bigInteger('clicks')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->decimal('cpc', 8, 4)->default(0);
                $table->decimal('cpm', 8, 4)->default(0);
                $table->decimal('spend', 12, 2)->default(0);
                $table->integer('conversions')->default(0);
                $table->decimal('conversion_rate', 8, 4)->default(0);
                $table->decimal('cost_per_conversion', 8, 4)->default(0);
                $table->decimal('revenue', 12, 2)->default(0);
                $table->decimal('roas', 8, 4)->default(0);
                $table->bigInteger('frequency')->default(0);
                $table->bigInteger('video_views')->default(0);
                $table->decimal('video_view_rate', 8, 4)->default(0);

                // Sync tracking
                $table->timestamp('last_synced_at')->nullable();
                $table->timestamp('launched_at')->nullable();
                $table->timestamps();

                $table->unique(['campaign_id', 'platform', 'variant_label'], 'ads_plat_camp_unique');
                $table->index(['platform', 'status']);
                $table->index('platform_campaign_id');
            });
        }

        // ==========================================
        // ADS CAMPAIGN METRICS (daily snapshots)
        // ==========================================
        if (!Schema::hasTable('ads_campaign_metrics')) {
            Schema::create('ads_campaign_metrics', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('ads_campaigns')->onDelete('cascade');
                $table->foreignId('platform_campaign_id')->nullable()->constrained('ads_platform_campaigns')->onDelete('cascade');

                $table->date('date');
                $table->enum('platform', ['facebook', 'instagram', 'google', 'tiktok', 'aggregated']);

                // Delivery metrics
                $table->bigInteger('impressions')->default(0);
                $table->bigInteger('reach')->default(0);
                $table->bigInteger('clicks')->default(0);
                $table->decimal('ctr', 8, 4)->default(0);
                $table->bigInteger('frequency')->default(0);

                // Cost metrics
                $table->decimal('spend', 12, 2)->default(0);
                $table->decimal('cpc', 8, 4)->default(0);
                $table->decimal('cpm', 8, 4)->default(0);

                // Conversion metrics
                $table->integer('conversions')->default(0);
                $table->decimal('conversion_rate', 8, 4)->default(0);
                $table->decimal('cost_per_conversion', 8, 4)->default(0);

                // Revenue metrics (from Tixello orders)
                $table->decimal('revenue', 12, 2)->default(0);
                $table->decimal('roas', 8, 4)->default(0); // Revenue / Spend
                $table->decimal('cac', 8, 4)->default(0);  // Spend / Customers acquired
                $table->integer('tickets_sold')->default(0);
                $table->integer('new_customers')->default(0);

                // Engagement metrics
                $table->bigInteger('likes')->default(0);
                $table->bigInteger('shares')->default(0);
                $table->bigInteger('comments')->default(0);
                $table->bigInteger('saves')->default(0);

                // Video metrics
                $table->bigInteger('video_views')->default(0);
                $table->decimal('video_view_rate', 8, 4)->default(0);
                $table->bigInteger('video_views_25')->default(0);
                $table->bigInteger('video_views_50')->default(0);
                $table->bigInteger('video_views_75')->default(0);
                $table->bigInteger('video_views_100')->default(0);

                // Audience quality
                $table->decimal('quality_score', 5, 2)->nullable();
                $table->decimal('relevance_score', 5, 2)->nullable();

                // A/B variant
                $table->string('variant_label')->nullable();

                $table->timestamps();

                $table->unique(['campaign_id', 'platform_campaign_id', 'date', 'platform', 'variant_label'], 'ads_metrics_unique');
                $table->index(['campaign_id', 'date']);
                $table->index(['date', 'platform']);
            });
        }

        // ==========================================
        // ADS CAMPAIGN REPORTS
        // ==========================================
        if (!Schema::hasTable('ads_campaign_reports')) {
            Schema::create('ads_campaign_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('ads_campaigns')->onDelete('cascade');

                $table->enum('report_type', ['daily', 'weekly', 'monthly', 'final', 'ab_test', 'custom']);
                $table->string('title');
                $table->date('period_start');
                $table->date('period_end');

                // Report data
                $table->json('summary')->nullable();        // {impressions, clicks, spend, conversions, revenue, roas, cac}
                $table->json('platform_breakdown')->nullable(); // {facebook: {...}, google: {...}}
                $table->json('daily_data')->nullable();      // [{date, metrics...}]
                $table->json('creative_performance')->nullable(); // [{creative_id, metrics...}]
                $table->json('audience_insights')->nullable(); // {top_age_group, top_location, top_device}
                $table->json('recommendations')->nullable();  // [{type, message, impact}]
                $table->json('ab_test_results')->nullable();  // {winner, confidence, metrics_comparison}

                // Delivery
                $table->boolean('sent_to_organizer')->default(false);
                $table->timestamp('sent_at')->nullable();
                $table->string('pdf_path')->nullable();

                $table->foreignId('generated_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->index(['campaign_id', 'report_type']);
                $table->index(['period_start', 'period_end']);
            });
        }

        // ==========================================
        // ADS CAMPAIGN OPTIMIZATION LOG
        // ==========================================
        if (!Schema::hasTable('ads_optimization_logs')) {
            Schema::create('ads_optimization_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('campaign_id')->constrained('ads_campaigns')->onDelete('cascade');
                $table->foreignId('platform_campaign_id')->nullable()->constrained('ads_platform_campaigns')->onDelete('set null');

                $table->enum('action_type', [
                    'budget_increase',
                    'budget_decrease',
                    'budget_reallocation',
                    'bid_adjustment',
                    'audience_expansion',
                    'audience_narrowing',
                    'creative_pause',
                    'creative_activate',
                    'ab_test_winner',
                    'platform_pause',
                    'platform_resume',
                    'retargeting_update',
                    'schedule_adjustment',
                    'campaign_pause',
                    'campaign_resume',
                    'manual_override',
                ]);

                $table->text('description');
                $table->json('before_state')->nullable(); // {budget: 100, cpc: 0.5}
                $table->json('after_state')->nullable();  // {budget: 150, cpc: 0.45}
                $table->json('trigger_metrics')->nullable(); // What triggered this optimization
                $table->decimal('expected_improvement', 8, 4)->nullable(); // % expected improvement
                $table->decimal('actual_improvement', 8, 4)->nullable();   // % actual (filled later)

                $table->enum('source', ['auto', 'manual', 'ai_suggested'])->default('auto');
                $table->foreignId('performed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();

                $table->index(['campaign_id', 'action_type']);
                $table->index('created_at');
            });
        }

        // ==========================================
        // ADS AUDIENCE SEGMENTS (reusable)
        // ==========================================
        if (!Schema::hasTable('ads_audience_segments')) {
            Schema::create('ads_audience_segments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tenant_id')->nullable()->constrained()->onDelete('cascade');
                $table->foreignId('marketplace_client_id')->nullable()->constrained('marketplace_clients')->onDelete('set null');

                $table->string('name');
                $table->text('description')->nullable();
                $table->enum('type', [
                    'custom',           // Uploaded customer list
                    'website_visitors', // Pixel-based
                    'past_attendees',   // People who attended events
                    'cart_abandoners',  // Started checkout but didn't finish
                    'lookalike',        // Similar to source audience
                    'engaged_users',    // Interacted with content
                    'email_subscribers',// Newsletter subscribers
                    'high_value',       // Top spenders
                ]);

                // Source configuration
                $table->json('source_config')->nullable(); // {event_ids: [], days_back: 30, min_spend: 50}

                // Platform audience IDs (synced)
                $table->string('facebook_audience_id')->nullable();
                $table->string('google_audience_id')->nullable();
                $table->string('tiktok_audience_id')->nullable();

                // Sync status
                $table->integer('estimated_size')->default(0);
                $table->timestamp('last_synced_at')->nullable();
                $table->boolean('auto_sync')->default(true);
                $table->json('sync_status')->nullable(); // {facebook: 'synced', google: 'pending'}

                $table->timestamps();

                $table->index(['tenant_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ads_audience_segments');
        Schema::dropIfExists('ads_optimization_logs');
        Schema::dropIfExists('ads_campaign_reports');
        Schema::dropIfExists('ads_campaign_metrics');
        Schema::dropIfExists('ads_platform_campaigns');
        Schema::dropIfExists('ads_campaign_targeting');
        Schema::dropIfExists('ads_campaign_creatives');
        Schema::dropIfExists('ads_campaigns');
        Schema::dropIfExists('ads_service_requests');
    }
};
