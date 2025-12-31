<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant connections to LinkedIn Ads
        Schema::create('linkedin_ads_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('ad_account_id')->comment('LinkedIn Ad Account ID');
            $table->text('access_token')->comment('OAuth access token (encrypted)');
            $table->text('refresh_token')->nullable()->comment('OAuth refresh token (encrypted)');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('insight_tag_id')->nullable()->comment('LinkedIn Insight Tag ID');
            $table->string('status')->default('active');
            $table->json('enabled_conversions')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'ad_account_id']);
            $table->index('status');
        });

        // Conversion rules defined in LinkedIn
        Schema::create('linkedin_ads_conversion_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('linkedin_ads_connections')->cascadeOnDelete();
            $table->string('conversion_rule_id')->comment('LinkedIn conversion rule ID');
            $table->string('name');
            $table->string('conversion_type')->comment('PURCHASE, LEAD, etc.');
            $table->string('attribution_type')->default('LAST_TOUCH_BY_CAMPAIGN');
            $table->boolean('is_enabled')->default(true);
            $table->string('trigger_on')->nullable();
            $table->json('value_settings')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'conversion_rule_id'], 'li_ads_conv_rules_conn_rule_unique');
        });

        // Conversions sent to LinkedIn
        Schema::create('linkedin_ads_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('linkedin_ads_connections')->cascadeOnDelete();
            $table->foreignId('conversion_rule_id')->nullable()->constrained('linkedin_ads_conversion_rules')->nullOnDelete();
            $table->string('conversion_id')->unique();
            $table->timestamp('conversion_time');
            $table->decimal('conversion_value', 12, 2)->nullable();
            $table->string('currency_code', 3)->default('EUR');
            $table->json('user_data')->nullable()->comment('Hashed user identifiers');
            $table->string('li_fat_id')->nullable()->comment('LinkedIn first-party cookie');
            $table->string('click_id')->nullable()->comment('LinkedIn click ID from URL');
            $table->string('status')->default('pending');
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('api_response')->nullable();
            $table->string('correlation_type')->nullable();
            $table->unsignedBigInteger('correlation_id')->nullable();
            $table->timestamps();

            $table->index(['connection_id', 'status']);
            $table->index(['correlation_type', 'correlation_id']);
        });

        // Matched audiences
        Schema::create('linkedin_ads_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('linkedin_ads_connections')->cascadeOnDelete();
            $table->string('dmp_segment_id')->comment('LinkedIn DMP segment ID');
            $table->string('name');
            $table->string('audience_type')->default('COMPANY_CONTACTS');
            $table->integer('matched_count')->nullable();
            $table->boolean('is_auto_sync')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'dmp_segment_id']);
        });

        // Batch uploads
        Schema::create('linkedin_ads_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('linkedin_ads_connections')->cascadeOnDelete();
            $table->integer('conversion_count');
            $table->string('status')->default('pending');
            $table->integer('successful_count')->nullable();
            $table->integer('failed_count')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_ads_batches');
        Schema::dropIfExists('linkedin_ads_audiences');
        Schema::dropIfExists('linkedin_ads_conversions');
        Schema::dropIfExists('linkedin_ads_conversion_rules');
        Schema::dropIfExists('linkedin_ads_connections');
    }
};
