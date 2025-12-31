<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchase window preferences (early bird vs last minute)
        Schema::create('fs_person_purchase_window', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('core_customers')->cascadeOnDelete();
            $table->string('window_type', 20); // immediate, week, month, quarter, early
            $table->integer('purchases_count')->default(0);
            $table->float('avg_days_before_event')->nullable();
            $table->float('preference_score')->default(0); // % of purchases in this window
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'window_type']);
            $table->index(['tenant_id', 'window_type']);
        });

        // Activity patterns (time-of-day, day-of-week preferences)
        Schema::create('fs_person_activity_pattern', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('core_customers')->cascadeOnDelete();
            // Hourly engagement (0-23)
            $table->jsonb('hourly_views')->nullable(); // {0: count, 1: count, ...}
            $table->jsonb('hourly_purchases')->nullable();
            $table->smallInteger('preferred_hour')->nullable(); // 0-23
            // Daily engagement (0-6, Sun-Sat)
            $table->jsonb('daily_views')->nullable(); // {0: count, 1: count, ...}
            $table->jsonb('daily_purchases')->nullable();
            $table->smallInteger('preferred_day')->nullable(); // 0-6
            // Peak activity windows
            $table->jsonb('peak_hours')->nullable(); // [10, 14, 20]
            $table->jsonb('peak_days')->nullable(); // [5, 6] (weekend)
            // Weekend vs weekday
            $table->float('weekend_ratio')->nullable(); // % of activity on weekends
            $table->boolean('is_weekend_buyer')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id']);
        });

        // Email engagement fatigue
        Schema::create('fs_person_email_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('core_customers')->cascadeOnDelete();
            // Recent metrics
            $table->integer('sent_last_7_days')->default(0);
            $table->integer('sent_last_30_days')->default(0);
            $table->integer('sent_last_90_days')->default(0);
            $table->integer('opened_last_30_days')->default(0);
            $table->integer('clicked_last_30_days')->default(0);
            // Trend analysis
            $table->string('engagement_trend', 20)->nullable(); // increasing, stable, declining
            $table->float('open_rate_30d')->nullable();
            $table->float('open_rate_90d')->nullable();
            $table->float('click_rate_30d')->nullable();
            // Fatigue scoring
            $table->float('fatigue_score')->default(0); // 0-100
            $table->float('optimal_frequency_per_week')->nullable();
            // Timing preferences
            $table->jsonb('preferred_send_hours')->nullable(); // [10, 14, 18]
            $table->jsonb('preferred_send_days')->nullable(); // [1, 2, 3]
            $table->timestamp('last_engagement_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id']);
            $table->index(['tenant_id', 'fatigue_score']);
        });

        // Channel affinity (which channels work best per user)
        Schema::create('fs_person_channel_affinity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('core_customers')->cascadeOnDelete();
            $table->string('channel', 50); // email, organic, paid_search, paid_social, direct, referral
            $table->integer('interaction_count')->default(0);
            $table->integer('conversion_count')->default(0);
            $table->float('conversion_rate')->nullable();
            $table->decimal('revenue_attributed', 12, 2)->default(0);
            $table->timestamp('last_interaction_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'person_id', 'channel']);
            $table->index(['tenant_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fs_person_channel_affinity');
        Schema::dropIfExists('fs_person_email_metrics');
        Schema::dropIfExists('fs_person_activity_pattern');
        Schema::dropIfExists('fs_person_purchase_window');
    }
};
