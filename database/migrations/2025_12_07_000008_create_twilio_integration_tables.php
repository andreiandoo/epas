<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('twilio_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->text('account_sid')->nullable();
            $table->text('auth_token')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('messaging_service_sid')->nullable();
            $table->json('enabled_channels')->nullable(); // sms, whatsapp, voice
            $table->string('status')->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id']);
        });

        Schema::create('twilio_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('message_sid')->nullable();
            $table->string('channel')->default('sms'); // sms, whatsapp
            $table->string('direction')->default('outbound');
            $table->string('from_number');
            $table->string('to_number');
            $table->text('body');
            $table->json('media_urls')->nullable();
            $table->string('status')->default('pending');
            $table->decimal('price', 10, 4)->nullable();
            $table->string('price_unit')->nullable();
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('twilio_connections')->onDelete('cascade');
            $table->index(['connection_id', 'to_number']);
        });

        Schema::create('twilio_calls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('call_sid')->nullable();
            $table->string('direction')->default('outbound');
            $table->string('from_number');
            $table->string('to_number');
            $table->string('status')->default('pending');
            $table->integer('duration')->nullable();
            $table->decimal('price', 10, 4)->nullable();
            $table->string('price_unit')->nullable();
            $table->text('twiml')->nullable();
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('twilio_connections')->onDelete('cascade');
        });

        Schema::create('twilio_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('event_type');
            $table->string('endpoint_url');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('twilio_connections')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('twilio_webhooks');
        Schema::dropIfExists('twilio_calls');
        Schema::dropIfExists('twilio_messages');
        Schema::dropIfExists('twilio_connections');
    }
};
