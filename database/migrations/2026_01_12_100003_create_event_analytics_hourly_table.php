<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_analytics_hourly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->tinyInteger('hour'); // 0-23

            // Core metrics
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

            // Bounce & time metrics
            $table->unsignedInteger('bounces')->default(0);
            $table->unsignedInteger('total_time_on_page')->default(0); // in seconds

            // Traffic sources breakdown (JSON for flexibility)
            $table->json('traffic_sources')->nullable(); // {direct: 10, google: 5, facebook: 3, ...}
            $table->json('utm_campaigns')->nullable(); // {campaign_name: {views: 10, purchases: 2}, ...}
            $table->json('devices')->nullable(); // {desktop: 50, mobile: 40, tablet: 10}
            $table->json('locations')->nullable(); // {RO: 100, US: 20, ...}

            $table->timestamps();

            // Indexes for efficient querying
            $table->unique(['event_id', 'date', 'hour']);
            $table->index(['event_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_analytics_hourly');
    }
};
