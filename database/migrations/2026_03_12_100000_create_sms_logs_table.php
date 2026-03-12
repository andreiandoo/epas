<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('marketplace_client_id')->nullable();
            $table->string('phone', 20);
            $table->text('message_text');
            $table->enum('type', ['transactional', 'promotional'])->default('transactional');
            $table->string('provider_id')->nullable()->comment('UUID from SendSMS.ro');
            $table->enum('status', ['queued', 'sent', 'delivered', 'undelivered', 'failed'])->default('queued');
            $table->decimal('cost', 8, 4)->default(0);
            $table->string('currency', 3)->default('EUR');
            $table->unsignedBigInteger('event_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('marketplace_client_id');
            $table->index('type');
            $table->index('status');
            $table->index('created_at');
            $table->index('event_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};
