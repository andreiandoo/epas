<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_todos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();

            // Human-readable identifier shown in UI. Unique per client,
            // generated post-insert from the row id (TODO-YYYY-000123).
            $table->string('todo_number', 32)->nullable();

            // Marketplace admin who opened the TODO (the reporter).
            $table->foreignId('created_by_marketplace_admin_id')
                ->constrained('marketplace_admins')
                ->cascadeOnDelete();

            // The marketplace admin who owns / will respond to the TODO.
            // For Ambilet this defaults to the marketplace_client's
            // default_todo_admin_id (admin #5).
            $table->foreignId('assigned_to_marketplace_admin_id')
                ->nullable()
                ->constrained('marketplace_admins')
                ->nullOnDelete();

            $table->foreignId('marketplace_todo_category_id')
                ->nullable()
                ->constrained('marketplace_todo_categories')
                ->nullOnDelete();

            $table->string('title', 255);
            $table->mediumText('description')->nullable(); // WYSIWYG HTML

            // Array of {path, original_name, mime, size, disk}. Images
            // uploaded via drag&drop on the create form.
            $table->json('attachments')->nullable();

            $table->enum('status', [
                'open',
                'in_progress',
                'awaiting_response',
                'resolved',
                'closed',
            ])->default('open');

            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])
                ->default('normal');

            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('last_activity_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['marketplace_client_id', 'todo_number'], 'mp_todo_number_uq');
            $table->index(['marketplace_client_id', 'status', 'last_activity_at'], 'mp_todo_listing_idx');
            $table->index(['created_by_marketplace_admin_id', 'status'], 'mp_todo_creator_idx');
            $table->index(['assigned_to_marketplace_admin_id', 'status'], 'mp_todo_assignee_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_todos');
    }
};
