<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');

            // Milestone type
            $table->enum('type', [
                'campaign_fb',      // Facebook Ads
                'campaign_google',  // Google Ads
                'campaign_tiktok',  // TikTok Ads
                'campaign_instagram', // Instagram Ads
                'campaign_other',   // Other Ad Platforms
                'email',            // Email Campaign
                'price',            // Price Change
                'announcement',     // Public Announcement
                'press',            // Press Release
                'lineup',           // Lineup Announcement
                'custom',           // Custom Milestone
            ]);

            // Basic info
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();

            // Campaign specific (for ad campaigns)
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('currency', 3)->default('RON');
            $table->string('targeting')->nullable();
            $table->string('platform_campaign_id')->nullable(); // External campaign ID

            // Attribution settings
            $table->integer('attribution_window_days')->default(7); // Days after campaign end to count conversions

            // UTM parameters for tracking
            $table->string('utm_source')->nullable();
            $table->string('utm_medium')->nullable();
            $table->string('utm_campaign')->nullable();
            $table->string('utm_content')->nullable();
            $table->string('utm_term')->nullable();

            // Calculated metrics (updated by job/service)
            $table->integer('impressions')->default(0);
            $table->integer('clicks')->default(0);
            $table->integer('conversions')->default(0);
            $table->decimal('attributed_revenue', 12, 2)->default(0);
            $table->decimal('cac', 10, 2)->nullable(); // Customer Acquisition Cost
            $table->decimal('roi', 10, 2)->nullable(); // Return on Investment percentage
            $table->decimal('roas', 10, 2)->nullable(); // Return on Ad Spend

            // For non-ad milestones (impact tracking)
            $table->string('impact_metric')->nullable(); // e.g., '+45% traffic', '+234 sales'
            $table->decimal('baseline_value', 12, 2)->nullable(); // Value before milestone
            $table->decimal('post_value', 12, 2)->nullable(); // Value after milestone

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('metrics_updated_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['event_id', 'type']);
            $table->index(['event_id', 'start_date']);
            $table->index(['tenant_id', 'type']);
            $table->index(['utm_campaign']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_milestones');
    }
};
