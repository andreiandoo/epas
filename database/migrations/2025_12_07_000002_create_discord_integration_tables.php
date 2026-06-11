<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('discord_connections')) {
            return;
        }

        Schema::create('discord_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('guild_id')->nullable();
            $table->string('guild_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->text('bot_token')->nullable();
            $table->json('scopes')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'guild_id']);
        });

        if (Schema::hasTable('discord_webhooks')) {
            return;
        }

        Schema::create('discord_webhooks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('webhook_id');
            $table->string('webhook_token');
            $table->string('name');
            $table->string('channel_id');
            $table->string('channel_name')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('discord_connections')->onDelete('cascade');
        });

        if (Schema::hasTable('discord_messages')) {
            return;
        }

        Schema::create('discord_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('channel_id');
            $table->string('message_id')->nullable();
            $table->string('delivery_method')->default('webhook'); // webhook, bot
            $table->text('content');
            $table->json('embeds')->nullable();
            $table->string('status')->default('pending');
            $table->string('correlation_ref')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->json('error_details')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('discord_connections')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discord_messages');
        Schema::dropIfExists('discord_webhooks');
        Schema::dropIfExists('discord_connections');
    }
};
