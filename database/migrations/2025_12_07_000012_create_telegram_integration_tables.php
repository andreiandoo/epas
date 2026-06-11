<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Telegram Bot connections
        if (Schema::hasTable('telegram_bot_connections')) {
            return;
        }

        Schema::create('telegram_bot_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('bot_token'); // Encrypted
            $table->string('bot_username');
            $table->string('bot_id');
            $table->string('bot_name')->nullable();
            $table->string('webhook_url')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('status')->default('pending'); // pending, active, suspended
            $table->json('commands')->nullable(); // Registered bot commands
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'bot_id']);
        });

        // Telegram channels/groups the bot is added to
        if (Schema::hasTable('telegram_chats')) {
            return;
        }

        Schema::create('telegram_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->bigInteger('chat_id'); // Can be negative for groups
            $table->string('chat_type'); // private, group, supergroup, channel
            $table->string('title')->nullable();
            $table->string('username')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('permissions')->nullable(); // Bot permissions in this chat
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('telegram_bot_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'chat_id']);
        });

        // Messages sent/received
        if (Schema::hasTable('telegram_messages')) {
            return;
        }

        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->bigInteger('chat_id');
            $table->bigInteger('message_id')->nullable(); // Telegram's message ID
            $table->string('direction'); // inbound, outbound
            $table->string('message_type'); // text, photo, document, video, audio, voice, sticker, location, contact, poll
            $table->text('content')->nullable();
            $table->json('entities')->nullable(); // Text entities (bold, links, etc.)
            $table->json('media')->nullable(); // file_id, file_size, etc.
            $table->json('keyboard')->nullable(); // Inline or reply keyboard
            $table->bigInteger('reply_to_message_id')->nullable();
            $table->string('status')->default('pending'); // pending, sent, delivered, failed
            $table->text('error_message')->nullable();
            $table->string('correlation_type')->nullable();
            $table->unsignedBigInteger('correlation_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('telegram_bot_connections')->onDelete('cascade');
            $table->index(['correlation_type', 'correlation_id']);
        });

        // Subscribers (users who started the bot)
        if (Schema::hasTable('telegram_subscribers')) {
            return;
        }

        Schema::create('telegram_subscribers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->bigInteger('user_id'); // Telegram user ID
            $table->string('username')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('language_code')->nullable();
            $table->boolean('is_bot')->default(false);
            $table->boolean('is_blocked')->default(false);
            $table->timestamp('subscribed_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('last_interaction_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('telegram_bot_connections')->onDelete('cascade');
            $table->unique(['connection_id', 'user_id']);
        });

        // Webhook updates log
        if (Schema::hasTable('telegram_updates')) {
            return;
        }

        Schema::create('telegram_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id')->nullable();
            $table->bigInteger('update_id');
            $table->string('update_type'); // message, callback_query, inline_query, etc.
            $table->json('payload');
            $table->string('status')->default('pending');
            $table->text('processing_error')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('connection_id')->references('id')->on('telegram_bot_connections')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_updates');
        Schema::dropIfExists('telegram_subscribers');
        Schema::dropIfExists('telegram_messages');
        Schema::dropIfExists('telegram_chats');
        Schema::dropIfExists('telegram_bot_connections');
    }
};
