<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Live-chat microservice — abuse blocklist (anti-bot / anti-spam).
 *
 * An IP or email present here (and not expired) is denied the ability to open
 * new conversations. Populated manually by operators or automatically by the
 * anti-abuse throttling layer.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_blocklist', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            $table->enum('type', ['ip', 'email']);
            $table->string('value', 191);
            $table->string('reason', 255)->nullable();
            $table->foreignId('created_by_marketplace_admin_id')
                ->nullable()
                ->constrained('marketplace_admins')
                ->nullOnDelete();
            // NULL = permanent block.
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['marketplace_client_id', 'type', 'value'], 'chat_blocklist_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_blocklist');
    }
};
