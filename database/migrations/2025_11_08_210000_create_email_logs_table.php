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
        Schema::create('email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->nullable()->constrained()->onDelete('set null');
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable()->comment('Variables used in email');
            $table->string('tenant_id')->nullable()->comment('Associated tenant if applicable');
            $table->timestamps();

            // Indexes
            $table->index('email_template_id');
            $table->index('recipient_email');
            $table->index('status');
            $table->index('sent_at');
            $table->index('tenant_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_logs');
    }
};
