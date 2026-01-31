<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tenant connections to TikTok Ads
        if (Schema::hasTable('tiktok_ads_connections')) {
            return;
        }

        Schema::create('tiktok_ads_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('pixel_id')->comment('TikTok Pixel ID');
            $table->text('access_token')->comment('TikTok Access Token (encrypted)');
            $table->string('advertiser_id')->nullable()->comment('TikTok Advertiser ID');
            $table->boolean('test_mode')->default(false);
            $table->string('test_event_code')->nullable();
            $table->string('status')->default('active');
            $table->json('enabled_events')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'pixel_id']);
            $table->index('status');
        });

        // Event configurations
        if (Schema::hasTable('tiktok_ads_event_configs')) {
            return;
        }

        Schema::create('tiktok_ads_event_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('tiktok_ads_connections')->cascadeOnDelete();
            $table->string('event_name')->comment('TikTok event name: CompletePayment, etc.');
            $table->boolean('is_enabled')->default(true);
            $table->string('trigger_on')->nullable()->comment('Internal event that triggers this');
            $table->json('content_mapping')->nullable();
            $table->json('user_data_mapping')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'event_name']);
        });

        // Events sent to TikTok
        if (Schema::hasTable('tiktok_ads_events')) {
            return;
        }

        Schema::create('tiktok_ads_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('tiktok_ads_connections')->cascadeOnDelete();
            $table->string('event_id')->unique();
            $table->string('event_name');
            $table->timestamp('event_time');
            $table->string('event_source_url')->nullable();
            $table->json('user_data')->nullable()->comment('Hashed user data');
            $table->json('properties')->nullable()->comment('Event properties: value, currency, etc.');
            $table->json('contents')->nullable()->comment('Product/content items');
            $table->string('ttclid')->nullable()->comment('TikTok Click ID');
            $table->string('ttp')->nullable()->comment('TikTok cookie _ttp');
            $table->string('status')->default('pending');
            $table->string('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('api_response')->nullable();
            $table->string('correlation_type')->nullable();
            $table->unsignedBigInteger('correlation_id')->nullable();
            $table->boolean('is_test_event')->default(false);
            $table->timestamps();

            $table->index(['connection_id', 'status']);
            $table->index(['ttclid']);
            $table->index(['correlation_type', 'correlation_id']);
        });

        // Batch uploads
        if (Schema::hasTable('tiktok_ads_batches')) {
            return;
        }

        Schema::create('tiktok_ads_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('tiktok_ads_connections')->cascadeOnDelete();
            $table->integer('event_count');
            $table->string('status')->default('pending');
            $table->integer('events_received')->nullable();
            $table->json('messages')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Custom audiences for TikTok
        if (Schema::hasTable('tiktok_ads_audiences')) {
            return;
        }

        Schema::create('tiktok_ads_audiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connection_id')->constrained('tiktok_ads_connections')->cascadeOnDelete();
            $table->string('audience_id')->comment('TikTok custom audience ID');
            $table->string('name');
            $table->string('audience_type')->default('CUSTOMER_FILE');
            $table->integer('size')->nullable();
            $table->boolean('is_auto_sync')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['connection_id', 'audience_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_ads_audiences');
        Schema::dropIfExists('tiktok_ads_batches');
        Schema::dropIfExists('tiktok_ads_events');
        Schema::dropIfExists('tiktok_ads_event_configs');
        Schema::dropIfExists('tiktok_ads_connections');
    }
};
