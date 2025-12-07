<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===========================================
        // CORE CUSTOMERS - Platform-wide customer database
        // ===========================================
        Schema::create('core_customers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Public identifier for APIs');

            // Identity (encrypted for PII protection)
            $table->text('email')->nullable()->comment('Encrypted email');
            $table->string('email_hash', 64)->nullable()->index()->comment('SHA-256 hash for lookups');
            $table->text('phone')->nullable()->comment('Encrypted phone');
            $table->string('phone_hash', 64)->nullable()->index()->comment('SHA-256 hash for lookups');
            $table->text('first_name')->nullable()->comment('Encrypted');
            $table->text('last_name')->nullable()->comment('Encrypted');

            // Demographics
            $table->string('country_code', 2)->nullable()->index();
            $table->string('region')->nullable()->comment('State/Province');
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('language', 5)->nullable()->default('en');
            $table->string('timezone')->nullable();
            $table->string('gender', 20)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('age_range', 20)->nullable()->comment('18-24, 25-34, etc.');

            // First touch attribution
            $table->string('first_source')->nullable()->comment('direct, organic, paid, social, referral, email');
            $table->string('first_medium')->nullable();
            $table->string('first_campaign')->nullable();
            $table->string('first_referrer')->nullable();
            $table->string('first_landing_page')->nullable();
            $table->string('first_utm_source')->nullable();
            $table->string('first_utm_medium')->nullable();
            $table->string('first_utm_campaign')->nullable();
            $table->string('first_utm_term')->nullable();
            $table->string('first_utm_content')->nullable();

            // Last touch attribution
            $table->string('last_source')->nullable();
            $table->string('last_medium')->nullable();
            $table->string('last_campaign')->nullable();
            $table->string('last_referrer')->nullable();
            $table->string('last_utm_source')->nullable();
            $table->string('last_utm_medium')->nullable();
            $table->string('last_utm_campaign')->nullable();

            // Ad platform click IDs (for attribution)
            $table->string('first_gclid')->nullable()->comment('Google Ads click ID');
            $table->string('first_fbclid')->nullable()->comment('Facebook click ID');
            $table->string('first_ttclid')->nullable()->comment('TikTok click ID');
            $table->string('first_li_fat_id')->nullable()->comment('LinkedIn click ID');
            $table->string('last_gclid')->nullable();
            $table->string('last_fbclid')->nullable();
            $table->string('last_ttclid')->nullable();
            $table->string('last_li_fat_id')->nullable();

            // Engagement metrics
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->integer('total_visits')->default(0);
            $table->integer('total_pageviews')->default(0);
            $table->integer('total_sessions')->default(0);
            $table->integer('total_time_spent_seconds')->default(0);
            $table->integer('avg_session_duration_seconds')->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);

            // Purchase behavior
            $table->timestamp('first_purchase_at')->nullable();
            $table->timestamp('last_purchase_at')->nullable();
            $table->integer('total_orders')->default(0);
            $table->integer('total_tickets')->default(0);
            $table->decimal('total_spent', 12, 2)->default(0);
            $table->decimal('average_order_value', 10, 2)->default(0);
            $table->decimal('lifetime_value', 12, 2)->default(0)->comment('Calculated LTV');
            $table->string('currency', 3)->default('EUR');
            $table->integer('days_since_last_purchase')->nullable();
            $table->integer('purchase_frequency_days')->nullable()->comment('Avg days between purchases');

            // Event engagement
            $table->integer('total_events_viewed')->default(0);
            $table->integer('total_events_attended')->default(0);
            $table->json('favorite_categories')->nullable()->comment('Most viewed/purchased categories');
            $table->json('favorite_event_types')->nullable();
            $table->json('price_sensitivity')->nullable()->comment('Preferred price ranges');

            // Cross-tenant data
            $table->foreignId('first_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('primary_tenant_id')->nullable()->constrained('tenants')->nullOnDelete()->comment('Most orders from');
            $table->json('tenant_ids')->nullable()->comment('All tenants interacted with');
            $table->integer('tenant_count')->default(0);

            // Device & Technology
            $table->json('devices')->nullable()->comment('Device types used: mobile, desktop, tablet');
            $table->json('browsers')->nullable()->comment('Browsers used');
            $table->json('operating_systems')->nullable();
            $table->string('primary_device')->nullable();
            $table->string('primary_browser')->nullable();

            // Email engagement
            $table->integer('emails_sent')->default(0);
            $table->integer('emails_opened')->default(0);
            $table->integer('emails_clicked')->default(0);
            $table->decimal('email_open_rate', 5, 2)->default(0);
            $table->decimal('email_click_rate', 5, 2)->default(0);
            $table->timestamp('last_email_opened_at')->nullable();
            $table->boolean('email_subscribed')->default(true);
            $table->timestamp('email_unsubscribed_at')->nullable();

            // Segmentation & Scoring
            $table->string('customer_segment')->nullable()->comment('VIP, Regular, At-Risk, Churned, etc.');
            $table->integer('engagement_score')->default(0)->comment('0-100');
            $table->integer('purchase_likelihood_score')->default(0)->comment('0-100 probability to purchase');
            $table->integer('churn_risk_score')->default(0)->comment('0-100 risk of churning');
            $table->decimal('predicted_ltv', 12, 2)->nullable()->comment('ML predicted LTV');

            // RFM Scoring (Recency, Frequency, Monetary)
            $table->integer('rfm_recency_score')->nullable()->comment('1-5');
            $table->integer('rfm_frequency_score')->nullable()->comment('1-5');
            $table->integer('rfm_monetary_score')->nullable()->comment('1-5');
            $table->string('rfm_segment', 50)->nullable()->comment('Champions, Loyal, At Risk, etc.');

            // Consent & Privacy
            $table->boolean('marketing_consent')->default(false);
            $table->boolean('analytics_consent')->default(false);
            $table->boolean('personalization_consent')->default(false);
            $table->timestamp('consent_updated_at')->nullable();
            $table->string('consent_source')->nullable()->comment('How consent was given');
            $table->json('consent_history')->nullable();

            // External IDs for integrations
            $table->string('stripe_customer_id')->nullable()->index();
            $table->string('facebook_user_id')->nullable();
            $table->string('google_user_id')->nullable();
            $table->json('external_ids')->nullable()->comment('Other platform IDs');

            // Metadata
            $table->json('custom_attributes')->nullable()->comment('Flexible custom data');
            $table->json('tags')->nullable()->comment('Manual tags for segmentation');
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes for common queries
            $table->index(['customer_segment', 'last_seen_at']);
            $table->index(['total_spent', 'total_orders']);
            $table->index(['first_seen_at']);
            $table->index(['last_purchase_at']);
            $table->index(['engagement_score']);
            $table->index(['rfm_segment']);
        });

        // ===========================================
        // CORE CUSTOMER EVENTS - All interactions
        // ===========================================
        Schema::create('core_customer_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('core_customers')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->uuid('session_id')->index();
            $table->string('visitor_id', 64)->index()->comment('Anonymous visitor ID before identification');

            // Event details
            $table->string('event_type', 50)->index()->comment('pageview, view_item, add_to_cart, purchase, etc.');
            $table->string('event_category', 50)->nullable()->comment('engagement, ecommerce, content, etc.');
            $table->string('event_action', 100)->nullable();
            $table->string('event_label')->nullable();
            $table->decimal('event_value', 12, 2)->nullable();

            // Page/Content context
            $table->string('page_url')->nullable();
            $table->string('page_path')->nullable();
            $table->string('page_title')->nullable();
            $table->string('page_type', 50)->nullable()->comment('home, event, checkout, etc.');
            $table->string('content_id')->nullable()->comment('Event ID, page ID, etc.');
            $table->string('content_type', 50)->nullable();
            $table->string('content_name')->nullable();

            // E-commerce specific
            $table->unsignedBigInteger('event_id')->nullable()->comment('FK to events table');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->string('product_sku')->nullable();
            $table->decimal('product_price', 10, 2)->nullable();
            $table->integer('quantity')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->string('cart_id')->nullable();
            $table->decimal('cart_value', 10, 2)->nullable();

            // Attribution
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_term')->nullable();
            $table->string('utm_content')->nullable();

            // Click IDs
            $table->string('gclid')->nullable();
            $table->string('fbclid')->nullable();
            $table->string('ttclid')->nullable();
            $table->string('li_fat_id')->nullable();
            $table->string('fbc')->nullable()->comment('Facebook cookie');
            $table->string('fbp')->nullable()->comment('Facebook browser ID');
            $table->string('ttp')->nullable()->comment('TikTok cookie');

            // Device & Browser
            $table->string('device_type', 20)->nullable()->comment('mobile, desktop, tablet');
            $table->string('device_brand')->nullable();
            $table->string('device_model')->nullable();
            $table->string('browser')->nullable();
            $table->string('browser_version')->nullable();
            $table->string('os')->nullable();
            $table->string('os_version')->nullable();
            $table->integer('screen_width')->nullable();
            $table->integer('screen_height')->nullable();

            // Location (from IP)
            $table->string('ip_address', 45)->nullable();
            $table->string('country_code', 2)->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            // Timing
            $table->timestamp('occurred_at')->index();
            $table->integer('time_on_page_seconds')->nullable();
            $table->integer('scroll_depth_percent')->nullable();

            // Processing status
            $table->boolean('sent_to_platform')->default(false)->comment('Sent to platform ad accounts');
            $table->boolean('sent_to_tenant')->default(false)->comment('Sent to tenant ad accounts');
            $table->json('processing_log')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'event_type', 'occurred_at']);
            $table->index(['customer_id', 'event_type']);
            $table->index(['occurred_at', 'event_type']);
            $table->index(['visitor_id', 'session_id']);
        });

        // ===========================================
        // CORE SESSIONS - Visitor sessions
        // ===========================================
        Schema::create('core_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_id')->unique();
            $table->foreignId('customer_id')->nullable()->constrained('core_customers')->nullOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('visitor_id', 64)->index();

            // Session timing
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->integer('duration_seconds')->default(0);
            $table->integer('pageviews')->default(0);
            $table->integer('events')->default(0);
            $table->boolean('is_bounce')->default(false);

            // First page
            $table->string('landing_page')->nullable();
            $table->string('landing_page_type', 50)->nullable();

            // Exit page
            $table->string('exit_page')->nullable();
            $table->string('exit_page_type', 50)->nullable();

            // Attribution
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('referrer')->nullable();
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();

            // Click IDs
            $table->string('gclid')->nullable();
            $table->string('fbclid')->nullable();
            $table->string('ttclid')->nullable();

            // Conversion
            $table->boolean('converted')->default(false);
            $table->decimal('conversion_value', 10, 2)->nullable();
            $table->string('conversion_type', 50)->nullable();

            // Device
            $table->string('device_type', 20)->nullable();
            $table->string('browser')->nullable();
            $table->string('os')->nullable();

            // Location
            $table->string('country_code', 2)->nullable();
            $table->string('city')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'started_at']);
            $table->index(['visitor_id', 'started_at']);
        });

        // ===========================================
        // PLATFORM AD ACCOUNTS - Core admin's own accounts
        // ===========================================
        Schema::create('platform_ad_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 50)->comment('google_ads, meta, tiktok, linkedin');
            $table->string('account_id')->comment('Platform account/pixel ID');
            $table->string('account_name')->nullable();
            $table->text('access_token')->nullable()->comment('Encrypted');
            $table->text('refresh_token')->nullable()->comment('Encrypted');
            $table->timestamp('token_expires_at')->nullable();
            $table->json('credentials')->nullable()->comment('Additional encrypted credentials');
            $table->boolean('is_active')->default(true);
            $table->boolean('receive_conversions')->default(true)->comment('Receive all tenant conversions');
            $table->json('conversion_settings')->nullable()->comment('Which events to track');
            $table->timestamp('last_sync_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['platform', 'account_id']);
        });

        // ===========================================
        // PLATFORM CONVERSIONS - Conversions sent to platform accounts
        // ===========================================
        Schema::create('platform_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('core_customers')->nullOnDelete();
            $table->foreignId('customer_event_id')->nullable()->constrained('core_customer_events')->nullOnDelete();
            $table->foreignId('ad_account_id')->constrained('platform_ad_accounts')->cascadeOnDelete();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            $table->string('conversion_id')->unique();
            $table->string('event_type', 50);
            $table->timestamp('conversion_time');
            $table->decimal('value', 12, 2)->nullable();
            $table->string('currency', 3)->default('EUR');

            // Source identifiers
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('click_id')->nullable()->comment('gclid, fbclid, etc.');

            // User data sent (hashed)
            $table->json('user_data')->nullable();

            // Status
            $table->string('status')->default('pending');
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('api_response')->nullable();

            $table->timestamps();

            $table->index(['ad_account_id', 'status']);
            $table->index(['tenant_id', 'conversion_time']);
        });

        // ===========================================
        // CUSTOMER AUDIENCES - For ad platform syncing
        // ===========================================
        Schema::create('platform_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_account_id')->constrained('platform_ad_accounts')->cascadeOnDelete();
            $table->string('audience_id')->comment('Platform audience ID');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('audience_type', 50)->default('custom')->comment('custom, lookalike, remarketing');

            // Criteria
            $table->json('filters')->nullable()->comment('Segment criteria');
            $table->string('based_on_segment')->nullable()->comment('RFM segment, customer segment');

            // Sync
            $table->boolean('auto_sync')->default(false);
            $table->string('sync_frequency')->default('daily');
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('member_count')->default(0);

            $table->timestamps();

            $table->unique(['ad_account_id', 'audience_id']);
        });

        // ===========================================
        // AUDIENCE MEMBERS - Customers in each audience
        // ===========================================
        Schema::create('platform_audience_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audience_id')->constrained('platform_audiences')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('core_customers')->cascadeOnDelete();
            $table->timestamp('added_at');
            $table->timestamp('synced_at')->nullable();
            $table->string('sync_status')->default('pending');

            $table->unique(['audience_id', 'customer_id']);
            $table->index(['audience_id', 'sync_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_audience_members');
        Schema::dropIfExists('platform_audiences');
        Schema::dropIfExists('platform_conversions');
        Schema::dropIfExists('platform_ad_accounts');
        Schema::dropIfExists('core_sessions');
        Schema::dropIfExists('core_customer_events');
        Schema::dropIfExists('core_customers');
    }
};
