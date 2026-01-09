<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Invitations Microservice - Audit Logs
     * Comprehensive audit trail for all invitation actions
     */
    public function up(): void
    {
        if (Schema::hasTable('inv_logs')) {
            return;
        }

        Schema::create('inv_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invite_id')->constrained('inv_invites')->onDelete('cascade');

            // Log type
            $table->enum('type', [
                'generate',
                'render',
                'email',
                'download',
                'open',
                'void',
                'resend',
                'check_in',
                'error',
                'status_change',
                'recipient_update'
            ])->index();

            // Payload data (JSON)
            $table->json('payload')->nullable()->comment('Event-specific data');
            // Example for 'email': {
            //   "to": "john@example.com",
            //   "subject": "Your invitation to Summer Festival",
            //   "status": "sent",
            //   "message_id": "msg-123"
            // }
            // Example for 'download': {
            //   "ip": "192.168.1.1",
            //   "user_agent": "Mozilla/5.0...",
            //   "file": "invite-456.pdf"
            // }
            // Example for 'error': {
            //   "code": "EMAIL_BOUNCE",
            //   "message": "Mailbox does not exist",
            //   "context": {...}
            // }

            // Actor (if applicable)
            $table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('actor_type')->nullable()->comment('admin|system|guest');

            // Request context
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->timestamp('created_at');

            // Indexes
            $table->index(['invite_id', 'type']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_logs');
    }
};
