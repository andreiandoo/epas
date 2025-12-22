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
        // Marketplace-specific email templates
        Schema::create('marketplace_email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->string('name')->comment('Human-readable template name');
            $table->string('slug')->comment('Unique identifier within tenant');
            $table->string('subject')->comment('Email subject with variable placeholders');
            $table->text('body')->comment('Email body HTML with variable placeholders');
            $table->string('event_trigger')->nullable()->comment('Platform action that triggers this email');
            $table->text('description')->nullable()->comment('Description of when this template is used');
            $table->json('available_variables')->nullable()->comment('List of available variables for this template');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'event_trigger']);
            $table->index(['tenant_id', 'is_active']);
        });

        // Marketplace email logs
        Schema::create('marketplace_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('organizer_id')->nullable()->constrained('marketplace_organizers')->onDelete('set null');
            $table->foreignId('template_id')->nullable()->constrained('marketplace_email_templates')->onDelete('set null');
            $table->string('recipient_email');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_type')->default('customer')->comment('customer, organizer, admin');
            $table->string('subject');
            $table->text('body');
            $table->string('status')->default('pending')->comment('pending, sent, failed');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable()->comment('Additional context data');
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'recipient_email']);
            $table->index('organizer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('marketplace_email_logs');
        Schema::dropIfExists('marketplace_email_templates');
    }
};
