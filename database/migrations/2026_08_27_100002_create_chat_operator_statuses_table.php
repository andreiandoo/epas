<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-chat microservice — last-known operator presence & capacity.
 *
 * Live presence is kept in Redis (ephemeral); this table is the durable
 * "last known" mirror so the admin UI can render sensible state after a
 * restart and so we can enforce per-operator capacity without Redis.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_operator_statuses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->foreignId('marketplace_admin_id')
                ->constrained('marketplace_admins')
                ->cascadeOnDelete();

            $table->enum('presence', ['online', 'away', 'offline'])->default('offline');
            $table->unsignedInteger('active_chats_count')->default(0);
            // Per-operator capacity override; NULL falls back to the config default.
            $table->unsignedInteger('max_concurrent_chats')->nullable();
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->unique('marketplace_admin_id', 'chat_op_status_admin_uq');
            $table->index(['marketplace_client_id', 'presence'], 'chat_op_status_presence_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_operator_statuses');
    }
};
