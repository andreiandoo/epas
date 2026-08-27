<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-chat microservice (slug: live-chat) — conversation threads.
 *
 * One row per chat conversation opened from a marketplace frontend. The opener
 * is polymorphic (morph map: customer/organizer/artist => their models, or NULL
 * for an anonymous guest, in which case guest_name/guest_email carry the
 * contact). Fully marketplace-scoped so the module is inert on any marketplace
 * that has not activated the microservice.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            // Human-readable identifier (CHAT-YYYY-000123), generated post-insert.
            $table->string('reference', 32)->nullable();

            // Optional routing department (reuses the support taxonomy).
            $table->foreignId('support_department_id')
                ->nullable()
                ->constrained('support_departments')
                ->nullOnDelete();

            // Context event (not FK-constrained: events is large and we never
            // want a chat row to block an event delete).
            $table->unsignedBigInteger('event_id')->nullable();

            // Who opened it. visitor_type is always set; opener_type/opener_id
            // are NULL for guests (guest_name/guest_email used instead).
            //   visitor_type: customer | organizer | artist | guest
            //   opener morph map: customer/organizer/artist
            $table->string('visitor_type', 16);
            $table->string('opener_type', 32)->nullable();
            $table->unsignedBigInteger('opener_id')->nullable();
            $table->string('guest_name', 191)->nullable();
            $table->string('guest_email', 191)->nullable();

            // Operator (MarketplaceAdmin) currently owning the conversation.
            $table->foreignId('assigned_to_marketplace_admin_id')
                ->nullable()
                ->constrained('marketplace_admins')
                ->nullOnDelete();

            $table->string('subject', 255)->nullable();
            $table->enum('status', [
                'queued',           // Waiting for an available operator
                'active',           // An operator is handling it live
                'offline_message',  // Left outside working hours / no operator
                'resolved',         // Operator marked resolved
                'closed',           // Final state
            ])->default('queued');

            // Captured page/cart/order/browser context at open time.
            $table->json('context')->nullable();

            // Post-chat feedback.
            $table->unsignedTinyInteger('rating')->nullable();
            $table->text('rating_comment')->nullable();

            // If converted into a persistent support ticket.
            $table->foreignId('support_ticket_id')
                ->nullable()
                ->constrained('support_tickets')
                ->nullOnDelete();

            $table->timestamp('queued_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['marketplace_client_id', 'reference'], 'chat_conv_reference_uq');
            $table->index(['marketplace_client_id', 'status', 'last_activity_at'], 'chat_conv_listing_idx');
            $table->index(['assigned_to_marketplace_admin_id', 'status'], 'chat_conv_assigned_idx');
            $table->index(['opener_type', 'opener_id'], 'chat_conv_opener_idx');
            $table->index(['marketplace_client_id', 'event_id'], 'chat_conv_event_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_conversations');
    }
};
