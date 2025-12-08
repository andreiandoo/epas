<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // WhatsApp Business Cloud API connections (direct Meta API - no BSP fees)
        Schema::create('whatsapp_cloud_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('phone_number_id'); // Meta's phone number ID
            $table->string('phone_number'); // The actual WhatsApp number
            $table->string('display_name')->nullable();
            $table->string('business_account_id'); // WhatsApp Business Account ID
            $table->text('access_token'); // Encrypted permanent token
            $table->string('webhook_verify_token')->nullable();
            $table->string('status')->default('pending'); // pending, active, suspended
            $table->timestamp('verified_at')->nullable();
            $table->json('capabilities')->nullable(); // messaging, templates, media
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'phone_number_id']);
        });

        // Message templates (must be pre-approved by Meta)
        Schema::create('whatsapp_cloud_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('template_name');
            $table->string('template_id')->nullable(); // Meta's template ID
            $table->string('language')->default('en');
            $table->string('category'); // utility, marketing, authentication
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->json('components'); // header, body, footer, buttons
            $table->json('example')->nullable(); // Example values for variables
            $table->text('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('whatsapp_cloud_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'template_name', 'language']);
        });

        // Outbound and inbound messages
        Schema::create('whatsapp_cloud_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('wamid')->nullable()->index(); // WhatsApp Message ID
            $table->string('direction'); // inbound, outbound
            $table->string('recipient_phone'); // Customer's phone number
            $table->string('message_type'); // text, template, image, document, audio, video, location, contacts, interactive
            $table->text('content')->nullable(); // Text content or caption
            $table->string('template_name')->nullable();
            $table->json('template_params')->nullable();
            $table->json('media')->nullable(); // media_id, url, mime_type, filename
            $table->string('status')->default('pending'); // pending, sent, delivered, read, failed
            $table->json('status_history')->nullable(); // Track all status changes with timestamps
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('context_message_id')->nullable(); // Reply to message
            $table->string('correlation_type')->nullable(); // order, ticket, campaign
            $table->unsignedBigInteger('correlation_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('whatsapp_cloud_connections')->onDelete('cascade');
            $table->index(['correlation_type', 'correlation_id']);
        });

        // Customer contacts / conversations
        Schema::create('whatsapp_cloud_contacts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('wa_id')->index(); // WhatsApp ID (phone without +)
            $table->string('phone_number');
            $table->string('profile_name')->nullable();
            $table->boolean('is_opted_in')->default(false);
            $table->timestamp('opted_in_at')->nullable();
            $table->timestamp('opted_out_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('conversation_expires_at')->nullable(); // 24h window
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('whatsapp_cloud_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'wa_id']);
        });

        // Webhook events log
        Schema::create('whatsapp_cloud_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id')->nullable();
            $table->string('event_type'); // messages, message_status, message_template_status_update
            $table->json('payload');
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('whatsapp_cloud_connections')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_cloud_webhook_events');
        Schema::dropIfExists('whatsapp_cloud_contacts');
        Schema::dropIfExists('whatsapp_cloud_messages');
        Schema::dropIfExists('whatsapp_cloud_templates');
        Schema::dropIfExists('whatsapp_cloud_connections');
    }
};
