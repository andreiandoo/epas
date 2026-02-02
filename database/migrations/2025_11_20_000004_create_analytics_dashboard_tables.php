<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Custom dashboards
        if (Schema::hasTable('analytics_dashboards')) {
            return;
        }

        Schema::create('analytics_dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->json('layout')->nullable(); // Grid layout config
            $table->json('filters')->nullable(); // Default filters
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });

        // Dashboard widgets
        if (Schema::hasTable('analytics_widgets')) {
            return;
        }

        Schema::create('analytics_widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('analytics_dashboards')->onDelete('cascade');
            $table->string('type'); // chart, metric, table, map
            $table->string('title');
            $table->string('data_source'); // sales, attendance, revenue, etc.
            $table->json('config'); // Chart type, colors, aggregation
            $table->json('position'); // Grid position {x, y, w, h}
            $table->string('refresh_interval')->default('5m');
            $table->timestamps();
        });

        // Saved reports
        if (Schema::hasTable('analytics_reports')) {
            return;
        }

        Schema::create('analytics_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('type'); // sales, attendance, financial, custom
            $table->json('config'); // Report parameters
            $table->json('schedule')->nullable(); // Scheduled delivery
            $table->string('format')->default('pdf'); // pdf, xlsx, csv
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });

        // Aggregated metrics cache
        if (Schema::hasTable('analytics_metrics')) {
            return;
        }

        Schema::create('analytics_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('metric_type'); // daily_sales, hourly_attendance, etc.
            $table->date('date');
            $table->integer('hour')->nullable();
            $table->json('data'); // Aggregated values
            $table->timestamps();

            $table->unique(['tenant_id', 'event_id', 'metric_type', 'date', 'hour'], 'analytics_metrics_unique');
            $table->index(['tenant_id', 'metric_type', 'date']);
        });

        // Real-time event tracking
        if (Schema::hasTable('analytics_events')) {
            return;
        }

        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('event_id')->nullable()->constrained()->onDelete('cascade');
            $table->string('event_type'); // page_view, purchase, check_in, etc.
            $table->json('properties')->nullable();
            $table->string('session_id')->nullable();
            $table->string('user_agent')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'event_type', 'occurred_at']);
            $table->index(['event_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
        Schema::dropIfExists('analytics_metrics');
        Schema::dropIfExists('analytics_reports');
        Schema::dropIfExists('analytics_widgets');
        Schema::dropIfExists('analytics_dashboards');
    }
};
