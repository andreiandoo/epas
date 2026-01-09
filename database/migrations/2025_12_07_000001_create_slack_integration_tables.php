<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('slack_connections')) {
            return;
        }

        Schema::create('slack_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('workspace_id')->nullable();
            $table->string('workspace_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes')->nullable();
            $table->json('bot_info')->nullable();
            $table->string('status')->default('pending'); // pending, active, expired, revoked
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'workspace_id']);
        });

        if (Schema::hasTable('slack_channels')) {
            return;
        }

        Schema::create('slack_channels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('channel_id');
            $table->string('name');
            $table->string('type')->default('channel'); // channel, dm, group
            $table->boolean('is_private')->default(false);
            $table->boolean('is_default')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('slack_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'channel_id']);
        });

        if (Schema::hasTable('slack_messages')) {
            return;
        }

        Schema::create('slack_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('channel_id');
            $table->string('message_ts')->nullable();
            $table->string('direction')->default('outbound'); // outbound, inbound
            $table->text('content');
            $table->json('blocks')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status')->default('pending'); // pending, sent, delivered, failed
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('slack_connections')->onDelete('cascade');
            $table->index(['connection_id', 'channel_id']);
        });

        if (Schema::hasTable('slack_webhooks')) {
            return;
        }

        Schema::create('slack_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('event_type');
            $table->string('endpoint_url');
            $table->string('secret')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('slack_connections')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slack_webhooks');
        Schema::dropIfExists('slack_messages');
        Schema::dropIfExists('slack_channels');
        Schema::dropIfExists('slack_connections');
    }
};
