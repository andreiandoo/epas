<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-chat microservice — individual messages within a conversation.
 *
 * Author is polymorphic (morph map: staff => MarketplaceAdmin, customer/
 * organizer/artist => their models). A NULL author with type='system' denotes
 * a system line (joined/left/assigned/queued). Internal notes (is_internal)
 * are staff-only and must be stripped from any opener-facing payload.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->foreignId('chat_conversation_id')
                ->constrained('chat_conversations')
                ->cascadeOnDelete();

            // Polymorphic author. NULL for system messages.
            $table->string('author_type', 32)->nullable();
            $table->unsignedBigInteger('author_id')->nullable();
            // Display-name snapshot (guest name, operator name at send time).
            $table->string('author_label', 191)->nullable();

            //   text   => normal chat message
            //   system => generated notice (join/leave/assign/close)
            $table->string('type', 16)->default('text');
            $table->mediumText('body')->nullable();

            // Staff-only note: never exposed to the opener.
            $table->boolean('is_internal')->default(false);
            // Array of {path, original_name, mime, size} for jpg/png/pdf.
            $table->json('attachments')->nullable();

            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['chat_conversation_id', 'created_at'], 'chat_msg_thread_idx');
            $table->index(['author_type', 'author_id'], 'chat_msg_author_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
