<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tracking alerts table
        Schema::create('tracking_alerts', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('type', 50)->index();
            $table->string('category', 30)->index();
            $table->string('priority', 20)->index();
            $table->unsignedBigInteger('person_id')->nullable()->index();
            $table->string('entity_type', 30)->default('person');
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->jsonb('data')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->timestamp('handled_at')->nullable();
            $table->string('action_taken')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'priority']);
            $table->index(['tenant_id', 'type', 'created_at']);
        });

        // Customer journey transitions table
        Schema::create('customer_journey_transitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('person_id')->index();
            $table->string('from_stage', 30);
            $table->string('to_stage', 30)->index();
            $table->string('trigger', 50)->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['tenant_id', 'person_id', 'created_at']);
            $table->index(['tenant_id', 'from_stage', 'to_stage']);
        });

        // Win-back conversions tracking
        Schema::create('winback_conversions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('person_id')->index();
            $table->string('campaign_id', 50)->index();
            $table->decimal('order_value', 10, 2);
            $table->timestamp('converted_at');

            $table->index(['tenant_id', 'campaign_id']);
        });

        // Add journey stage columns to core_customers if not exists
        if (Schema::hasTable('core_customers')) {
            Schema::table('core_customers', function (Blueprint $table) {
                if (!Schema::hasColumn('core_customers', 'journey_stage')) {
                    $table->string('journey_stage', 30)->nullable()->after('rfm_segment');
                }
                if (!Schema::hasColumn('core_customers', 'journey_stage_updated_at')) {
                    $table->timestamp('journey_stage_updated_at')->nullable()->after('journey_stage');
                }
                if (!Schema::hasColumn('core_customers', 'last_winback_at')) {
                    $table->timestamp('last_winback_at')->nullable();
                }
                if (!Schema::hasColumn('core_customers', 'last_winback_tier')) {
                    $table->string('last_winback_tier', 30)->nullable();
                }
                if (!Schema::hasColumn('core_customers', 'last_winback_campaign_id')) {
                    $table->string('last_winback_campaign_id', 50)->nullable();
                }
                if (!Schema::hasColumn('core_customers', 'last_winback_converted_at')) {
                    $table->timestamp('last_winback_converted_at')->nullable();
                }
            });
        }

        // Lookalike audience cache table
        Schema::create('lookalike_audiences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('name');
            $table->string('source_type', 30); // segment, high_value, event_purchasers
            $table->unsignedBigInteger('source_id')->nullable();
            $table->jsonb('seed_person_ids');
            $table->integer('seed_count');
            $table->integer('lookalike_count');
            $table->float('min_similarity');
            $table->jsonb('seed_profile')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'source_type']);
        });

        // Lookalike audience members
        Schema::create('lookalike_audience_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('audience_id')->index();
            $table->unsignedBigInteger('person_id')->index();
            $table->float('similarity_score');
            $table->jsonb('match_breakdown')->nullable();
            $table->timestamps();

            $table->foreign('audience_id')->references('id')->on('lookalike_audiences')->onDelete('cascade');
            $table->unique(['audience_id', 'person_id']);
        });

        // Demand forecasts cache
        Schema::create('demand_forecasts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('event_id')->index();
            $table->integer('projected_sales');
            $table->float('projected_fill_rate');
            $table->decimal('projected_revenue', 12, 2);
            $table->float('sellout_probability');
            $table->string('sellout_risk', 20);
            $table->string('confidence', 20);
            $table->jsonb('velocity_data')->nullable();
            $table->jsonb('interest_signals')->nullable();
            $table->jsonb('recommendations')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_forecasts');
        Schema::dropIfExists('lookalike_audience_members');
        Schema::dropIfExists('lookalike_audiences');
        Schema::dropIfExists('winback_conversions');
        Schema::dropIfExists('customer_journey_transitions');
        Schema::dropIfExists('tracking_alerts');

        if (Schema::hasTable('core_customers')) {
            Schema::table('core_customers', function (Blueprint $table) {
                $table->dropColumn([
                    'journey_stage',
                    'journey_stage_updated_at',
                    'last_winback_at',
                    'last_winback_tier',
                    'last_winback_campaign_id',
                    'last_winback_converted_at',
                ]);
            });
        }
    }
};
