<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->date('date');

            // Traffic metrics
            $table->integer('page_views')->default(0);
            $table->integer('unique_visitors')->default(0);
            $table->integer('sessions')->default(0);
            $table->decimal('avg_session_duration', 8, 2)->default(0); // seconds
            $table->decimal('bounce_rate', 5, 2)->default(0); // percentage

            // Conversion funnel
            $table->integer('add_to_cart_count')->default(0);
            $table->integer('checkout_started_count')->default(0);
            $table->integer('purchases_count')->default(0);
            $table->decimal('conversion_rate', 5, 2)->default(0); // percentage

            // Revenue metrics
            $table->decimal('revenue', 12, 2)->default(0);
            $table->integer('tickets_sold')->default(0);
            $table->decimal('avg_order_value', 10, 2)->default(0);

            // Traffic sources breakdown (JSON)
            $table->json('traffic_sources')->nullable();
            // Structure: [{source: 'facebook', visitors: 100, revenue: 5000, conversions: 10}, ...]

            // Top locations (JSON)
            $table->json('top_locations')->nullable();
            // Structure: [{city: 'București', country: 'Romania', visitors: 50, tickets: 20, revenue: 3000}, ...]

            // Ticket breakdown (JSON)
            $table->json('ticket_breakdown')->nullable();
            // Structure: [{ticket_type_id: 1, name: 'VIP', sold: 10, revenue: 5000}, ...]

            // Device breakdown
            $table->json('device_breakdown')->nullable();
            // Structure: {desktop: 60, mobile: 35, tablet: 5}

            // Hourly distribution (for heatmap)
            $table->json('hourly_visits')->nullable();
            // Structure: {0: 10, 1: 5, ..., 23: 15}

            $table->json('hourly_sales')->nullable();
            // Structure: {0: 2, 1: 1, ..., 23: 5}

            $table->timestamps();

            // Unique constraint
            $table->unique(['event_id', 'date'], 'event_analytics_daily_unique');

            // Indexes
            $table->index(['tenant_id', 'date']);
            $table->index(['event_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_analytics_daily');
    }
};
