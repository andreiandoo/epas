<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained('marketplace_clients')->cascadeOnDelete();
            $table->foreignId('marketplace_customer_id')->nullable()->constrained('marketplace_customers')->nullOnDelete();
            $table->string('session_id', 64);
            $table->string('status', 20)->default('open'); // open, resolved, escalated
            $table->string('page_url', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('escalated_at')->nullable();
            $table->timestamps();

            $table->index(['marketplace_client_id', 'status']);
            $table->index(['marketplace_customer_id']);
            $table->index(['session_id']);
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_conversation_id')->constrained('chat_conversations')->cascadeOnDelete();
            $table->string('role', 20); // user, assistant, system
            $table->text('content');
            $table->json('tool_calls')->nullable();
            $table->json('tool_results')->nullable();
            $table->unsignedInteger('tokens_used')->nullable();
            $table->tinyInteger('rating')->nullable(); // 1 = thumbs up, -1 = thumbs down
            $table->timestamp('created_at')->useCurrent();

            $table->index(['chat_conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_conversations');
    }
};
