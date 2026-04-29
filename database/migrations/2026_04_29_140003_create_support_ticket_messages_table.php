<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')
                ->constrained('marketplace_clients')
                ->cascadeOnDelete();
            $table->foreignId('support_ticket_id')
                ->constrained('support_tickets')
                ->cascadeOnDelete();

            // Polymorphic author. Morph map:
            //   'organizer' => MarketplaceOrganizer,
            //   'customer'  => Customer,
            //   'staff'     => User
            $table->string('author_type', 32);
            $table->unsignedBigInteger('author_id');

            $table->mediumText('body');
            // Internal notes are visible only to staff — must be filtered out
            // of any opener-facing API response.
            $table->boolean('is_internal_note')->default(false);
            // Array of {path, original_name, mime, size} for jpg/png/pdf up to 3MB.
            $table->json('attachments')->nullable();

            $table->timestamps();

            $table->index(['support_ticket_id', 'created_at'], 'support_msg_thread_idx');
            $table->index(['author_type', 'author_id'], 'support_msg_author_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_messages');
    }
};
