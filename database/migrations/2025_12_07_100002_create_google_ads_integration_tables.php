<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant connections to Google Ads
        Schema::create('google_ads_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('customer_id')->comment('Google Ads Customer ID (xxx-xxx-xxxx)');
            $table->text('refresh_token')->comment('OAuth refresh token (encrypted)');
            $table->text('access_token')->nullable()->comment('Current access token (encrypted)');
            $table->timestamp('token_expires_at')->nullable();
            $table->string('conversion_action_id')->nullable()->comment('Primary conversion action ID');
            $table->string('status')->default('active');
            $table->json('enabled_conversions')->nullable()->comment('Which conversion types to send');
            $table->timestamp('last_event_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'customer_id']);
            $table->index('status');
        });

        // Conversion actions defined in Google Ads
        Schema::create('google_ads_conversion_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('google_ads_connections')->cascadeOnDelete();
            $table->string('conversion_action_id')->comment('Google Ads conversion action ID');
            $table->string('name');
            $table->string('category')->nullable()->comment('PURCHASE, ADD_TO_CART, LEAD, etc.');
            $table->string('counting_type')->default('ONE_PER_CLICK')->comment('ONE_PER_CLICK, MANY_PER_CLICK');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->string('trigger_on')->nullable()->comment('Event that triggers this: order_completed, etc.');
            $table->json('value_settings')->nullable()->comment('Currency, default value, etc.');
            $table->timestamps();

            $table->unique(['connection_id', 'conversion_action_id'], 'gads_conv_actions_conn_action_unique');
        });

        // Conversion events sent to Google Ads
        Schema::create('google_ads_conversions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('google_ads_connections')->cascadeOnDelete();
            $table->foreignId('conversion_action_id')->nullable()->constrained('google_ads_conversion_actions')->nullOnDelete();
            $table->string('conversion_id')->unique()->comment('Our unique conversion ID');
            $table->string('gclid')->nullable()->comment('Google Click ID from URL');
            $table->string('gbraid')->nullable()->comment('Google GBRAID for iOS');
            $table->string('wbraid')->nullable()->comment('Google WBRAID for web-to-app');
            $table->timestamp('conversion_time');
            $table->decimal('conversion_value', 12, 2)->nullable();
            $table->string('currency_code', 3)->default('EUR');
            $table->string('order_id')->nullable()->comment('Linked order ID');
            $table->json('user_data')->nullable()->comment('Hashed user data for enhanced conversions');
            $table->json('custom_variables')->nullable();
            $table->string('status')->default('pending')->comment('pending, sent, failed');
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('api_response')->nullable();
            $table->string('correlation_type')->nullable()->comment('order, ticket, registration');
            $table->unsignedBigInteger('correlation_id')->nullable();
            $table->timestamps();

            $table->index(['connection_id', 'status']);
            $table->index(['gclid']);
            $table->index(['correlation_type', 'correlation_id']);
        });

        // Audience lists for Customer Match
        Schema::create('google_ads_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('google_ads_connections')->cascadeOnDelete();
            $table->string('resource_name')->comment('Google Ads resource name');
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('membership_status')->default('OPEN');
            $table->integer('size_for_display')->nullable();
            $table->integer('size_for_search')->nullable();
            $table->boolean('is_auto_sync')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'resource_name']);
        });

        // Offline click conversions batch uploads
        Schema::create('google_ads_upload_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('google_ads_connections')->cascadeOnDelete();
            $table->integer('conversion_count');
            $table->string('status')->default('pending');
            $table->string('job_id')->nullable()->comment('Google Ads upload job ID');
            $table->integer('successful_count')->nullable();
            $table->integer('failed_count')->nullable();
            $table->json('errors')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['connection_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_ads_upload_batches');
        Schema::dropIfExists('google_ads_audiences');
        Schema::dropIfExists('google_ads_conversions');
        Schema::dropIfExists('google_ads_conversion_actions');
        Schema::dropIfExists('google_ads_connections');
    }
};
