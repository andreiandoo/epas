<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repair migration: production marked 2025_12_07_150000 +
 * 2025_12_07_160000 as run, but the core_customers column block from
 * the first one and both new tables (cohort_metrics,
 * data_retention_policies) never made it into the schema. The cascade
 * also disabled block 2 of 160000 (hasTable cohort_metrics → false)
 * and the [is_anonymized, is_merged] index (hasColumn is_merged →
 * false). AnalyticsCacheService::warmUp ran on cron everyTwoHours and
 * filled /admin/system-errors with 94 schema-drift errors over 7 days.
 *
 * Fully idempotent — every change is guarded by hasColumn/hasTable.
 * Safe to run multiple times. down() is a no-op so we can never roll
 * back into the broken state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('core_customers', function (Blueprint $table) {
            if (!Schema::hasColumn('core_customers', 'health_score')) {
                $table->integer('health_score')->default(0)->comment('0-100 overall health');
            }
            if (!Schema::hasColumn('core_customers', 'health_score_breakdown')) {
                $table->json('health_score_breakdown')->nullable();
            }
            if (!Schema::hasColumn('core_customers', 'health_score_calculated_at')) {
                $table->timestamp('health_score_calculated_at')->nullable();
            }
            if (!Schema::hasColumn('core_customers', 'primary_device_id')) {
                $table->string('primary_device_id', 64)->nullable();
            }
            if (!Schema::hasColumn('core_customers', 'linked_device_ids')) {
                $table->json('linked_device_ids')->nullable();
            }
            if (!Schema::hasColumn('core_customers', 'linked_customer_ids')) {
                $table->json('linked_customer_ids')->nullable()->comment('Merged customer IDs');
            }
            if (!Schema::hasColumn('core_customers', 'is_merged')) {
                $table->boolean('is_merged')->default(false);
            }
            if (!Schema::hasColumn('core_customers', 'merged_into_id')) {
                $table->unsignedBigInteger('merged_into_id')->nullable();
            }
            if (!Schema::hasColumn('core_customers', 'merged_at')) {
                $table->timestamp('merged_at')->nullable();
            }
            if (!Schema::hasColumn('core_customers', 'cohort_month')) {
                $table->string('cohort_month', 7)->nullable()->comment('YYYY-MM first seen');
            }
            if (!Schema::hasColumn('core_customers', 'cohort_week')) {
                $table->string('cohort_week', 10)->nullable()->comment('YYYY-WW first seen');
            }
        });

        // [is_anonymized, is_merged] index — was skipped in 160000 because
        // is_merged didn't exist yet. Now both columns are guaranteed.
        try {
            Schema::table('core_customers', function (Blueprint $table) {
                $table->index(['is_anonymized', 'is_merged']);
            });
        } catch (\Throwable $e) {
            // Index already exists, ignore.
        }

        if (!Schema::hasTable('data_retention_policies')) {
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
        }

        // cohort_metrics: merge of original schema (150000 block 6) +
        // post-create columns (160000 block 2). Both blocks were skipped
        // in production, so we recreate the union here.
        if (!Schema::hasTable('cohort_metrics')) {
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
                $table->unsignedBigInteger('tenant_id')->nullable();
                $table->integer('total_orders')->default(0);
                $table->decimal('revenue_per_customer', 10, 2)->default(0);
                $table->decimal('average_order_value', 10, 2)->default(0);
                $table->date('period_start')->nullable();
                $table->date('period_end')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();

                $table->unique(['cohort_period', 'cohort_type', 'period_offset']);
                $table->index(['cohort_type', 'period_offset']);
                $table->index(['tenant_id', 'cohort_type']);
            });
        } else {
            // Table existed but post-create columns may still be missing.
            Schema::table('cohort_metrics', function (Blueprint $table) {
                if (!Schema::hasColumn('cohort_metrics', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->nullable();
                }
                if (!Schema::hasColumn('cohort_metrics', 'total_orders')) {
                    $table->integer('total_orders')->default(0);
                }
                if (!Schema::hasColumn('cohort_metrics', 'revenue_per_customer')) {
                    $table->decimal('revenue_per_customer', 10, 2)->default(0);
                }
                if (!Schema::hasColumn('cohort_metrics', 'average_order_value')) {
                    $table->decimal('average_order_value', 10, 2)->default(0);
                }
                if (!Schema::hasColumn('cohort_metrics', 'period_start')) {
                    $table->date('period_start')->nullable();
                }
                if (!Schema::hasColumn('cohort_metrics', 'period_end')) {
                    $table->date('period_end')->nullable();
                }
                if (!Schema::hasColumn('cohort_metrics', 'calculated_at')) {
                    $table->timestamp('calculated_at')->nullable();
                }
            });
        }

        // Defensive: 160000 also creates attribution_touchpoints and
        // duplicate_customer_matches; if either was skipped in the same
        // partial-run incident, recreate now.
        if (!Schema::hasTable('attribution_touchpoints')) {
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
        }

        if (!Schema::hasTable('duplicate_customer_matches')) {
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
        }
    }

    public function down(): void
    {
        // No-op on purpose. The original up() blocks in
        // 2025_12_07_150000 / 2025_12_07_160000 are already recorded as
        // run; rolling this repair back would only re-create the
        // schema-drift state without giving the recorded migrations a
        // path to re-execute.
    }
};
