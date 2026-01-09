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
        if (Schema::hasTable('wa_messages')) {
            return;
        }

        Schema::create('wa_messages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();

            // Message type/category
            $table->enum('type', ['order_confirm', 'reminder', 'promo', 'otp', 'other'])
                ->default('other')
                ->index();

            // Recipient phone in E.164 format
            $table->string('to_phone')->index();

            // Template used
            $table->string('template_name')->index();

            // Variable values for this message
            $table->json('variables')->nullable();

            // Message status tracking
            $table->enum('status', ['queued', 'sent', 'delivered', 'read', 'failed'])
                ->default('queued')
                ->index();

            // Error tracking
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            // Correlation reference (order_ref, event_id, campaign_id, etc.)
            $table->string('correlation_ref')->nullable()->index();

            // BSP message ID for tracking
            $table->string('bsp_message_id')->nullable()->index();

            // Delivery timestamps
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            // Cost tracking (in credits or currency)
            $table->decimal('cost', 8, 4)->nullable()->comment('Cost in credits/EUR');

            $table->timestamps();

            // Indexes for common queries
            $table->index(['tenant_id', 'type', 'status'], 'idx_tenant_type_status');
            $table->index(['tenant_id', 'correlation_ref'], 'idx_tenant_correlation');
            $table->index(['created_at'], 'idx_created_at');

            // Idempotency: prevent duplicate order confirmations
            $table->index(['tenant_id', 'correlation_ref', 'template_name'], 'idx_idempotency');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wa_messages');
    }
};
