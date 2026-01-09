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
        if (Schema::hasTable('tenant_notifications')) {
            return;
        }

        Schema::create('tenant_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            // Notification type and category
            $table->enum('type', [
                'microservice_expiring',
                'microservice_expired',
                'microservice_suspended',
                'efactura_rejected',
                'efactura_failed',
                'whatsapp_credits_low',
                'invitation_batch_completed',
                'invoice_failed',
                'system_alert',
                'other'
            ])->index();

            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium')->index();

            // Notification content
            $table->string('title');
            $table->text('message');
            $table->json('data')->nullable()->comment('Additional context data');

            // Action (optional)
            $table->string('action_url')->nullable();
            $table->string('action_text')->nullable();

            // Status
            $table->enum('status', ['unread', 'read', 'archived'])->default('unread')->index();
            $table->timestamp('read_at')->nullable();

            // Delivery channels
            $table->json('channels')->nullable()->comment('email, database, whatsapp');
            $table->boolean('sent_email')->default(false);
            $table->boolean('sent_whatsapp')->default(false);
            $table->timestamp('sent_at')->nullable();

            // Related entity
            $table->string('related_type')->nullable()->comment('App\Models\TenantMicroservice, etc.');
            $table->unsignedBigInteger('related_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tenant_id', 'status', 'priority'], 'idx_tenant_status_priority');
            $table->index(['tenant_id', 'type'], 'idx_tenant_type');
            $table->index(['created_at'], 'idx_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_notifications');
    }
};
