<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Chatul intern al echipei din panoul marketplace (1:1, grupuri, „Cere ajutor”).
    // Mesajele se păstrează pentru totdeauna; atașamentele se șterg după 30 de zile (vezi team-chat:purge-attachments).
    public function up(): void
    {
        if (! Schema::hasTable('team_threads')) {
            Schema::create('team_threads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('marketplace_client_id')->constrained()->cascadeOnDelete();
                $table->string('type', 16); // dm | group | help
                $table->string('name')->nullable();
                // Cheie unică pentru conversațiile create de sistem: dm:3:7, team, dept:4
                $table->string('system_key', 64)->nullable();
                $table->boolean('is_system')->default(false);
                $table->unsignedBigInteger('support_department_id')->nullable();
                $table->unsignedBigInteger('chat_conversation_id')->nullable();
                $table->unsignedBigInteger('created_by_marketplace_admin_id')->nullable();
                $table->timestamp('last_message_at')->nullable();
                $table->timestamps();

                $table->unique(['marketplace_client_id', 'system_key'], 'team_threads_system_key_unique');
                $table->index(['marketplace_client_id', 'last_message_at']);
            });
        }

        if (! Schema::hasTable('team_thread_members')) {
            Schema::create('team_thread_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_thread_id')->constrained('team_threads')->cascadeOnDelete();
                $table->foreignId('marketplace_admin_id')->constrained('marketplace_admins')->cascadeOnDelete();
                $table->unsignedBigInteger('last_read_message_id')->default(0);
                $table->timestamp('muted_until')->nullable();
                $table->timestamp('joined_at')->nullable();
                $table->unique(['team_thread_id', 'marketplace_admin_id'], 'team_thread_members_unique');
                $table->index('marketplace_admin_id');
            });
        }

        if (! Schema::hasTable('team_messages')) {
            Schema::create('team_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_thread_id')->constrained('team_threads')->cascadeOnDelete();
                $table->unsignedBigInteger('marketplace_admin_id')->nullable(); // null = mesaj de sistem
                $table->text('body')->nullable();
                $table->json('mentions')->nullable();
                $table->timestamp('edited_at')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['team_thread_id', 'id']);
            });
        }

        if (! Schema::hasTable('team_message_attachments')) {
            Schema::create('team_message_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('team_message_id')->constrained('team_messages')->cascadeOnDelete();
                $table->string('path');
                $table->string('name');
                $table->string('mime', 100);
                $table->unsignedInteger('size')->default(0);
                $table->boolean('keep')->default(false);
                $table->timestamp('purged_at')->nullable();
                $table->timestamps();

                $table->index(['purged_at', 'keep', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('team_message_attachments');
        Schema::dropIfExists('team_messages');
        Schema::dropIfExists('team_thread_members');
        Schema::dropIfExists('team_threads');
    }
};
