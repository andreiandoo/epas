<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Invitations Microservice - Individual Invitations
     * Stores individual invitation records with tracking
     */
    public function up(): void
    {
        Schema::create('inv_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('inv_batches')->onDelete('cascade');
            $table->foreignId('tenant_id')->constrained('tenants')->onDelete('cascade');

            // Unique invitation code (opaque, for public URLs)
            $table->string('invite_code', 32)->unique()->index()->comment('Unique opaque code for URLs');

            // Status tracking
            $table->enum('status', [
                'created',
                'rendered',
                'emailed',
                'downloaded',
                'opened',
                'checked_in',
                'void'
            ])->default('created')->index();

            // Recipient information (JSON)
            $table->json('recipient')->nullable()->comment('Recipient details: name, email, phone, company');
            // Example: {
            //   "name": "John Doe",
            //   "email": "john@example.com",
            //   "phone": "+1234567890",
            //   "company": "Acme Corp",
            //   "title": "CEO",
            //   "notes": "VIP guest"
            // }

            // Seat assignment (optional)
            $table->string('seat_ref')->nullable()->index()->comment('Seat reference if assigned');

            // Ticket integration
            $table->string('ticket_ref')->nullable()->index()->comment('Reference to generated zero-value ticket');
            $table->text('qr_data')->nullable()->comment('QR code data for scanning');

            // Generated file URLs (JSON)
            $table->json('urls')->nullable()->comment('Generated file URLs: pdf, png, signed_download');
            // Example: {
            //   "pdf": "storage/invites/batch-123/invite-456.pdf",
            //   "png": "storage/invites/batch-123/invite-456.png",
            //   "signed_download": "https://...",
            //   "signed_expires_at": "2025-12-01T10:00:00Z"
            // }

            // Tracking timestamps
            $table->timestamp('rendered_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('voided_at')->nullable();

            // Email delivery tracking
            $table->enum('delivery_status', ['pending', 'sent', 'delivered', 'bounced', 'failed', 'complaint'])
                  ->nullable()
                  ->index();
            $table->text('delivery_error')->nullable()->comment('Error message if delivery failed');
            $table->integer('send_attempts')->default(0)->comment('Number of send attempts');
            $table->timestamp('last_send_attempt_at')->nullable();

            // Check-in tracking
            $table->string('gate_ref')->nullable()->comment('Gate where invitation was scanned');
            $table->timestamp('gate_scanned_at')->nullable();

            // Additional metadata (JSON)
            $table->json('meta')->nullable()->comment('Additional metadata: custom fields, tags, etc.');

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status']);
            $table->index(['batch_id', 'status']);
            $table->index('emailed_at');
            $table->index('downloaded_at');
            $table->index('checked_in_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inv_invites');
    }
};
