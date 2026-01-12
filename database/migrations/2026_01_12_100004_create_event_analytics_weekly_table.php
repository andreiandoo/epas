<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_analytics_weekly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('week_start'); // Monday of the week
            $table->tinyInteger('year');
            $table->tinyInteger('week_number'); // 1-53

            // Core metrics (aggregated from daily)
            $table->unsignedInteger('page_views')->default(0);
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('ticket_views')->default(0);
            $table->unsignedInteger('add_to_carts')->default(0);
            $table->unsignedInteger('checkouts_started')->default(0);
            $table->unsignedInteger('purchases')->default(0);
            $table->unsignedInteger('tickets_sold')->default(0);
            $table->unsignedBigInteger('revenue_cents')->default(0);

            // Engagement metrics
            $table->unsignedInteger('lineup_views')->default(0);
            $table->unsignedInteger('pricing_views')->default(0);
            $table->unsignedInteger('faq_views')->default(0);
            $table->unsignedInteger('gallery_views')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('interests')->default(0);

            // Calculated metrics
            $table->decimal('conversion_rate', 5, 2)->default(0); // purchases / unique_visitors * 100
            $table->decimal('cart_abandonment_rate', 5, 2)->default(0);
            $table->decimal('avg_order_value_cents', 12, 2)->default(0);
            $table->decimal('avg_time_on_page', 8, 2)->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);

            // Traffic sources breakdown
            $table->json('traffic_sources')->nullable();
            $table->json('utm_campaigns')->nullable();
            $table->json('devices')->nullable();
            $table->json('top_locations')->nullable();
            $table->json('ticket_breakdown')->nullable(); // {ticket_type_id: {sold: X, revenue: Y}, ...}

            // Comparison with previous week
            $table->decimal('revenue_change_pct', 8, 2)->nullable();
            $table->decimal('visitors_change_pct', 8, 2)->nullable();
            $table->decimal('conversion_change_pct', 8, 2)->nullable();

            $table->timestamps();

            $table->unique(['event_id', 'week_start']);
            $table->index(['event_id', 'year', 'week_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_analytics_weekly');
    }
};
