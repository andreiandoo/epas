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
        if (Schema::hasTable('webhook_delivery_logs')) {
            return;
        }

        Schema::create('webhook_delivery_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('webhook_id')->index();
            $table->string('event')->index();
            $table->boolean('success')->default(false)->index();
            $table->smallInteger('status_code')->nullable();
            $table->decimal('duration_ms', 10, 2);
            $table->tinyInteger('attempt')->default(1);
            $table->text('error_message')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('created_at')->index();

            // Foreign key
            $table->foreign('webhook_id')
                ->references('id')
                ->on('tenant_webhooks')
                ->onDelete('cascade');

            // Composite indexes
            $table->index(['webhook_id', 'created_at']);
            $table->index(['webhook_id', 'success', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_delivery_logs');
    }
};
