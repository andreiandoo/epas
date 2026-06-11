<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_password_reset_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_client_id')->constrained()->onDelete('cascade');
            $table->string('type', 20); // 'customer' or 'organizer'
            $table->string('template_slug', 100);
            $table->string('status', 20)->default('draft'); // draft, sending, paused, completed, failed
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('last_processed_id')->default(0); // cursor for resumable batching
            $table->unsignedInteger('batch_size')->default(200);
            $table->unsignedInteger('delay_seconds')->default(10);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_password_reset_campaigns');
    }
};
