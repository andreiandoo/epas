<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Conversion Deduplication
        Schema::table('platform_conversions', function (Blueprint $table) {
            $table->string('deduplication_key', 128)->nullable()->after('conversion_id')->index();
            $table->timestamp('original_event_time')->nullable()->after('conversion_time');
        });

        // 2. Attribution Windows Configuration
        Schema::table('platform_ad_accounts', function (Blueprint $table) {
            $table->integer('attribution_window_days')->default(28)->after('conversion_settings');
            $table->integer('click_attribution_window_days')->default(7)->after('attribution_window_days');
            $table->integer('view_attribution_window_days')->default(1)->after('click_attribution_window_days');
        });

        // 3. Customer Health Score & Cross-device tracking
        Schema::table('core_customers', function (Blueprint $table) {
            $table->integer('health_score')->default(0)->after('churn_risk_score')->comment('0-100 overall health');
            $table->json('health_score_breakdown')->nullable()->after('health_score');
            $table->timestamp('health_score_calculated_at')->nullable()->after('health_score_breakdown');
            $table->string('primary_device_id', 64)->nullable()->after('primary_browser');
            $table->json('linked_device_ids')->nullable()->after('primary_device_id');
            $table->json('linked_customer_ids')->nullable()->after('linked_device_ids')->comment('Merged customer IDs');
            $table->boolean('is_merged')->default(false)->after('linked_customer_ids');
            $table->unsignedBigInteger('merged_into_id')->nullable()->after('is_merged');
            $table->timestamp('merged_at')->nullable()->after('merged_into_id');
            $table->string('cohort_month', 7)->nullable()->after('merged_at')->comment('YYYY-MM first seen');
            $table->string('cohort_week', 10)->nullable()->after('cohort_month')->comment('YYYY-WW first seen');
        });

        // 4. GDPR Compliance
        Schema::create('gdpr_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_type', 50)->comment('export, deletion, rectification');
            $table->foreignId('customer_id')->nullable()->constrained('core_customers')->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('request_source', 50)->comment('customer, admin, automated');
            $table->string('status', 50)->default('pending');
            $table->json('affected_data')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('processed_by')->nullable();
            $table->json('export_data')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index(['customer_id', 'request_type']);
        });

        // 5. Data Retention Configuration
        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('data_type', 50)->unique()->comment('sessions, events, conversions, etc.');
            $table->integer('retention_days')->default(365);
            $table->boolean('is_active')->default(true);
            $table->string('archive_strategy', 50)->default('delete')->comment('delete, archive, anonymize');
            $table->timestamp('last_cleanup_at')->nullable();
            $table->integer('last_cleanup_count')->default(0);
            $table->timestamps();
        });

        // 6. Cohort Analysis - pre-aggregate data
        Schema::create('cohort_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('cohort_period', 10)->comment('YYYY-MM or YYYY-WW');
            $table->string('cohort_type', 10)->default('month')->comment('month, week');
            $table->integer('period_offset')->comment('0 = cohort period, 1 = next period, etc.');
            $table->integer('customers_count')->default(0);
            $table->integer('active_customers')->default(0);
            $table->integer('purchasers_count')->default(0);
            $table->decimal('total_revenue', 12, 2)->default(0);
            $table->decimal('retention_rate', 5, 2)->default(0);
            $table->decimal('avg_revenue_per_customer', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['cohort_period', 'cohort_type', 'period_offset']);
            $table->index(['cohort_type', 'period_offset']);
        });

        // 7. Lookalike Audience Seeds
        Schema::table('platform_audiences', function (Blueprint $table) {
            $table->string('lookalike_source_type', 50)->nullable()->after('audience_type');
            $table->unsignedBigInteger('lookalike_source_audience_id')->nullable()->after('lookalike_source_type');
            $table->integer('lookalike_percentage')->nullable()->after('lookalike_source_audience_id')->comment('1-10%');
            $table->string('lookalike_country')->nullable()->after('lookalike_percentage');
        });

        // Add indexes for common query patterns
        Schema::table('core_customer_events', function (Blueprint $table) {
            $table->index(['event_type', 'created_at']);
            $table->index(['is_converted', 'created_at']);
        });

        Schema::table('core_sessions', function (Blueprint $table) {
            $table->index(['is_converted', 'started_at']);
            $table->index(['country_code', 'started_at']);
        });

        // Add unique constraint for deduplication
        Schema::table('platform_conversions', function (Blueprint $table) {
            $table->unique(['platform_ad_account_id', 'deduplication_key'], 'unique_conversion_per_account');
        });
    }

    public function down(): void
    {
        Schema::table('platform_conversions', function (Blueprint $table) {
            $table->dropUnique('unique_conversion_per_account');
            $table->dropColumn(['deduplication_key', 'original_event_time']);
        });

        Schema::table('platform_ad_accounts', function (Blueprint $table) {
            $table->dropColumn(['attribution_window_days', 'click_attribution_window_days', 'view_attribution_window_days']);
        });

        Schema::table('core_customers', function (Blueprint $table) {
            $table->dropColumn([
                'health_score', 'health_score_breakdown', 'health_score_calculated_at',
                'primary_device_id', 'linked_device_ids', 'linked_customer_ids',
                'is_merged', 'merged_into_id', 'merged_at', 'cohort_month', 'cohort_week'
            ]);
        });

        Schema::dropIfExists('gdpr_requests');
        Schema::dropIfExists('data_retention_policies');
        Schema::dropIfExists('cohort_metrics');

        Schema::table('platform_audiences', function (Blueprint $table) {
            $table->dropColumn(['lookalike_source_type', 'lookalike_source_audience_id', 'lookalike_percentage', 'lookalike_country']);
        });

        Schema::table('core_customer_events', function (Blueprint $table) {
            $table->dropIndex(['event_type', 'created_at']);
            $table->dropIndex(['is_converted', 'created_at']);
        });

        Schema::table('core_sessions', function (Blueprint $table) {
            $table->dropIndex(['is_converted', 'started_at']);
            $table->dropIndex(['country_code', 'started_at']);
        });
    }
};
