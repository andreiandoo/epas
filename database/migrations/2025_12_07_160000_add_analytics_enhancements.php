<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add GDPR anonymization fields to core_customers
        Schema::table('core_customers', function (Blueprint $table) {
            $table->boolean('is_anonymized')->default(false)->after('is_merged');
            $table->timestamp('anonymized_at')->nullable()->after('is_anonymized');
            $table->integer('rfm_score')->nullable()->after('rfm_segment')->comment('Combined RFM score 3-15');
        });

        // Add additional fields to cohort_metrics for the CalculateCohortMetricsJob
        Schema::table('cohort_metrics', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            $table->integer('total_orders')->default(0)->after('total_revenue');
            $table->decimal('revenue_per_customer', 10, 2)->default(0)->after('total_orders');
            $table->decimal('average_order_value', 10, 2)->default(0)->after('revenue_per_customer');
            $table->date('period_start')->nullable()->after('average_order_value');
            $table->date('period_end')->nullable()->after('period_start');
            $table->timestamp('calculated_at')->nullable()->after('period_end');

            // Update the unique constraint to include tenant_id
            $table->dropUnique(['cohort_period', 'cohort_type', 'period_offset']);
        });

        Schema::table('cohort_metrics', function (Blueprint $table) {
            $table->unique(['cohort_period', 'cohort_type', 'period_offset', 'tenant_id'], 'cohort_metrics_unique');
            $table->index(['tenant_id', 'cohort_type']);
        });

        // Create table for storing customer merge history
        if (Schema::hasTable('customer_merge_logs')) {
            return;
        }

        Schema::create('customer_merge_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_customer_id');
            $table->unsignedBigInteger('target_customer_id');
            $table->json('merged_data')->nullable()->comment('Snapshot of source customer data');
            $table->string('merged_by')->nullable()->comment('User or system that performed merge');
            $table->timestamps();

            $table->index(['source_customer_id']);
            $table->index(['target_customer_id']);
        });

        // Create table for churn predictions history
        if (Schema::hasTable('churn_predictions')) {
            return;
        }

        Schema::create('churn_predictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('core_customer_id')->constrained('core_customers')->cascadeOnDelete();
            $table->decimal('risk_score', 5, 2)->comment('0-100 churn probability');
            $table->string('risk_level', 20)->comment('minimal, low, medium, high, critical');
            $table->json('factors')->nullable()->comment('Contributing factors to churn risk');
            $table->json('recommendations')->nullable();
            $table->timestamp('predicted_at');
            $table->timestamps();

            $table->index(['core_customer_id', 'predicted_at']);
            $table->index(['risk_level', 'predicted_at']);
        });

        // Create table for attribution data
        if (Schema::hasTable('attribution_touchpoints')) {
            return;
        }

        Schema::create('attribution_touchpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('core_customer_id')->constrained('core_customers')->cascadeOnDelete();
            $table->foreignId('conversion_id')->nullable()->constrained('platform_conversions')->nullOnDelete();
            $table->string('channel', 100);
            $table->string('source', 100)->nullable();
            $table->string('medium', 100)->nullable();
            $table->string('campaign', 255)->nullable();
            $table->string('touchpoint_type', 50)->comment('first_touch, assist, last_touch');
            $table->decimal('attribution_value', 12, 2)->default(0);
            $table->json('attribution_weights')->nullable()->comment('Weights per model type');
            $table->timestamp('touchpoint_at');
            $table->timestamps();

            $table->index(['core_customer_id', 'touchpoint_at']);
            $table->index(['channel', 'touchpoint_at']);
            $table->index(['conversion_id']);
        });

        // Create table for duplicate detection results
        if (Schema::hasTable('duplicate_customer_matches')) {
            return;
        }

        Schema::create('duplicate_customer_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_a_id');
            $table->unsignedBigInteger('customer_b_id');
            $table->decimal('match_score', 5, 3)->comment('0-1 similarity score');
            $table->string('match_type', 50)->comment('exact, high, medium, low');
            $table->string('confidence', 20)->comment('definite, likely, possible');
            $table->json('matched_fields')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->string('resolution', 50)->nullable()->comment('merged, dismissed, pending');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolved_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_a_id')->references('id')->on('core_customers')->cascadeOnDelete();
            $table->foreign('customer_b_id')->references('id')->on('core_customers')->cascadeOnDelete();
            $table->unique(['customer_a_id', 'customer_b_id']);
            $table->index(['match_score', 'is_resolved']);
            $table->index(['match_type', 'is_resolved']);
        });

        // Add index for anonymized customer filtering
        Schema::table('core_customers', function (Blueprint $table) {
            $table->index(['is_anonymized', 'is_merged']);
        });
    }

    public function down(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            $table->dropIndex(['is_anonymized', 'is_merged']);
            $table->dropColumn(['is_anonymized', 'anonymized_at', 'rfm_score']);
        });

        Schema::table('cohort_metrics', function (Blueprint $table) {
            $table->dropUnique('cohort_metrics_unique');
            $table->dropIndex(['tenant_id', 'cohort_type']);
            $table->dropColumn([
                'tenant_id', 'total_orders', 'revenue_per_customer',
                'average_order_value', 'period_start', 'period_end', 'calculated_at'
            ]);
        });

        Schema::table('cohort_metrics', function (Blueprint $table) {
            $table->unique(['cohort_period', 'cohort_type', 'period_offset']);
        });

        Schema::dropIfExists('duplicate_customer_matches');
        Schema::dropIfExists('attribution_touchpoints');
        Schema::dropIfExists('churn_predictions');
        Schema::dropIfExists('customer_merge_logs');
    }
};
