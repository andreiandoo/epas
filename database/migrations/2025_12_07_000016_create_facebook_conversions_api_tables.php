<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Facebook Conversions API connections
        if (Schema::hasTable('facebook_capi_connections')) {
            return;
        }

        Schema::create('facebook_capi_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('pixel_id');
            $table->text('access_token'); // Encrypted system user token
            $table->string('business_id')->nullable();
            $table->string('ad_account_id')->nullable();
            $table->boolean('test_mode')->default(false);
            $table->string('test_event_code')->nullable(); // For testing in Events Manager
            $table->string('status')->default('active');
            $table->json('enabled_events')->nullable(); // Which events to send
            $table->timestamp('last_event_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'pixel_id']);
        });

        // Event configurations (what data to send for each event type)
        if (Schema::hasTable('facebook_capi_event_configs')) {
            return;
        }

        Schema::create('facebook_capi_event_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('event_name'); // Purchase, AddToCart, Lead, CompleteRegistration, ViewContent, etc.
            $table->boolean('is_enabled')->default(true);
            $table->string('trigger_on'); // order_completed, ticket_purchased, registration, etc.
            $table->json('custom_data_mapping')->nullable(); // Map local fields to custom_data
            $table->json('user_data_mapping')->nullable(); // Map local fields to user_data (for matching)
            $table->boolean('send_test_events')->default(false);
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('facebook_capi_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'event_name']);
        });

        // Sent events log
        if (Schema::hasTable('facebook_capi_events')) {
            return;
        }

        Schema::create('facebook_capi_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('event_id')->index(); // Unique ID for deduplication
            $table->string('event_name'); // Purchase, AddToCart, Lead, etc.
            $table->timestamp('event_time');
            $table->string('event_source_url')->nullable();
            $table->string('action_source'); // website, app, email, phone_call, chat, physical_store, other
            $table->json('user_data'); // Hashed email, phone, fbp, fbc, etc.
            $table->json('custom_data')->nullable(); // value, currency, content_ids, contents, etc.
            $table->string('correlation_type')->nullable(); // orders, tickets, registrations
            $table->unsignedBigInteger('correlation_id')->nullable();
            $table->string('status')->default('pending'); // pending, sent, failed
            $table->string('fbtrace_id')->nullable(); // Facebook's trace ID from response
            $table->integer('events_received')->nullable(); // From Facebook response
            $table->json('messages')->nullable(); // Warnings/errors from Facebook
            $table->text('error_message')->nullable();
            $table->boolean('is_test_event')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('facebook_capi_connections')->onDelete('cascade');
            $table->index(['correlation_type', 'correlation_id']);
        });

        // Event batches (for batch sending)
        if (Schema::hasTable('facebook_capi_batches')) {
            return;
        }

        Schema::create('facebook_capi_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->integer('event_count');
            $table->string('status')->default('pending'); // pending, sent, partial, failed
            $table->integer('events_received')->nullable();
            $table->string('fbtrace_id')->nullable();
            $table->json('messages')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('facebook_capi_connections')->onDelete('cascade');
        });

        // Custom audiences (for syncing customer lists)
        if (Schema::hasTable('facebook_capi_custom_audiences')) {
            return;
        }

        Schema::create('facebook_capi_custom_audiences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('audience_id'); // Facebook custom audience ID
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('subtype'); // CUSTOM (user list), WEBSITE, ENGAGEMENT
            $table->string('data_source'); // customers, attendees, purchasers
            $table->json('filters')->nullable(); // Which customers to include
            $table->boolean('is_auto_sync')->default(false);
            $table->integer('approximate_count')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('facebook_capi_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'audience_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_capi_custom_audiences');
        Schema::dropIfExists('facebook_capi_batches');
        Schema::dropIfExists('facebook_capi_events');
        Schema::dropIfExists('facebook_capi_event_configs');
        Schema::dropIfExists('facebook_capi_connections');
    }
};
